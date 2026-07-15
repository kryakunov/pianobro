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
  }
}
