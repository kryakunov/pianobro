<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class Database
{
  private static ?PDO $pdo = null;

  public static function connection(): PDO
  {
    if (self::$pdo !== null) {
      return self::$pdo;
    }

    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
      mkdir($dir, 0775, true);
    }

    $path = $dir . '/app.sqlite';
    self::$pdo = new PDO('sqlite:' . $path, null, null, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    self::$pdo->exec('PRAGMA foreign_keys = ON');
    self::migrate(self::$pdo);

    return self::$pdo;
  }

  private static function migrate(PDO $pdo): void
  {
    $pdo->exec(<<<'SQL'
      CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
      );

      CREATE TABLE IF NOT EXISTS note_stats (
        user_id INTEGER NOT NULL,
        midi INTEGER NOT NULL,
        correct_count INTEGER NOT NULL DEFAULT 0,
        wrong_count INTEGER NOT NULL DEFAULT 0,
        last_practiced_at TEXT,
        PRIMARY KEY (user_id, midi),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE TABLE IF NOT EXISTS training_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        mode TEXT NOT NULL,
        correct INTEGER NOT NULL,
        wrong INTEGER NOT NULL,
        accuracy INTEGER NOT NULL,
        total INTEGER NOT NULL,
        settings_json TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE TABLE IF NOT EXISTS note_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_id INTEGER,
        midi INTEGER NOT NULL,
        correct INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE CASCADE
      );

      CREATE INDEX IF NOT EXISTS idx_note_attempts_user_date
        ON note_attempts(user_id, created_at);
      SQL);

    self::migrateOAuthAccounts($pdo);
    self::migrateNullablePasswordHash($pdo);
  }

  private static function migrateOAuthAccounts(PDO $pdo): void
  {
    $pdo->exec(<<<'SQL'
      CREATE TABLE IF NOT EXISTS oauth_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        provider TEXT NOT NULL,
        provider_user_id TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(provider, provider_user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      );
      SQL);
  }

  private static function migrateNullablePasswordHash(PDO $pdo): void
  {
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    $passwordColumn = null;
    foreach ($columns as $column) {
      if (($column['name'] ?? '') === 'password_hash') {
        $passwordColumn = $column;
        break;
      }
    }

    if ($passwordColumn === null || (int) ($passwordColumn['notnull'] ?? 1) === 0) {
      return;
    }

    $pdo->exec(<<<'SQL'
      CREATE TABLE users_oauth_migration (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        password_hash TEXT,
        name TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
      );

      INSERT INTO users_oauth_migration (id, email, password_hash, name, created_at)
      SELECT id, email, password_hash, name, created_at FROM users;

      DROP TABLE users;
      ALTER TABLE users_oauth_migration RENAME TO users;
      SQL);
  }
}
