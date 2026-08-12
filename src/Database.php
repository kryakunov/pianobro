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
    self::migrateLastLoginAt($pdo);
    self::migrateUserRole($pdo);
    self::migrateTeacherMode($pdo);
    self::migrateTeacherStudents($pdo);
    self::migrateUserRoles($pdo);
    self::migrateAnalytics($pdo);
    self::migrateAnalyticsSearchReferral($pdo);
    self::migrateTeacherStudentExclusive($pdo);
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

  private static function migrateLastLoginAt(PDO $pdo): void
  {
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    foreach ($columns as $column) {
      if (($column['name'] ?? '') === 'last_login_at') {
        return;
      }
    }

    $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at TEXT');

    $pdo->exec(<<<'SQL'
      UPDATE users
      SET last_login_at = (
        SELECT MAX(dt) FROM (
          SELECT MAX(created_at) AS dt FROM training_sessions WHERE user_id = users.id
          UNION ALL
          SELECT MAX(created_at) AS dt FROM note_attempts WHERE user_id = users.id
        )
        WHERE dt IS NOT NULL
      )
      WHERE last_login_at IS NULL
      SQL);
  }

  private static function migrateUserRole(PDO $pdo): void
  {
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll();
    foreach ($columns as $column) {
      if (($column['name'] ?? '') === 'role') {
        return;
      }
    }

    $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'student'");
  }

  private static function migrateTeacherMode(PDO $pdo): void
  {
    $pdo->exec(<<<'SQL'
      CREATE TABLE IF NOT EXISTS teacher_classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        invite_code TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE TABLE IF NOT EXISTS class_members (
        class_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        joined_at TEXT NOT NULL DEFAULT (datetime('now')),
        PRIMARY KEY (class_id, student_id),
        FOREIGN KEY (class_id) REFERENCES teacher_classes(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE INDEX IF NOT EXISTS idx_class_members_student ON class_members(student_id);

      CREATE TABLE IF NOT EXISTS assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_id INTEGER NOT NULL,
        teacher_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        type TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        due_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (class_id) REFERENCES teacher_classes(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE TABLE IF NOT EXISTS assignment_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        assignment_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        session_id INTEGER,
        result_json TEXT,
        errors_json TEXT,
        teacher_comment TEXT,
        commented_at TEXT,
        completed_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(assignment_id, student_id),
        FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (session_id) REFERENCES training_sessions(id) ON DELETE SET NULL
      );

      CREATE INDEX IF NOT EXISTS idx_assignment_submissions_student
        ON assignment_submissions(student_id, status);
      SQL);
  }

  private static function migrateTeacherStudents(PDO $pdo): void
  {
    $pdo->exec(<<<'SQL'
      CREATE TABLE IF NOT EXISTS teacher_students (
        teacher_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        joined_at TEXT NOT NULL DEFAULT (datetime('now')),
        PRIMARY KEY (teacher_id, student_id),
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE INDEX IF NOT EXISTS idx_teacher_students_student ON teacher_students(student_id);

      CREATE TABLE IF NOT EXISTS teacher_invitations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        email TEXT NOT NULL COLLATE NOCASE,
        token TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        accepted_at TEXT,
        student_id INTEGER,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL
      );

      CREATE INDEX IF NOT EXISTS idx_teacher_invitations_email
        ON teacher_invitations(email, status);

      CREATE TABLE IF NOT EXISTS student_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        type TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        due_at TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        result_json TEXT,
        errors_json TEXT,
        teacher_comment TEXT,
        commented_at TEXT,
        completed_at TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE INDEX IF NOT EXISTS idx_student_assignments_student
        ON student_assignments(student_id, status);
      SQL);
  }

  private static function migrateUserRoles(PDO $pdo): void
  {
    $pdo->exec(<<<'SQL'
      CREATE TABLE IF NOT EXISTS user_roles (
        user_id INTEGER NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('teacher', 'student')),
        granted_at TEXT NOT NULL DEFAULT (datetime('now')),
        PRIMARY KEY (user_id, role),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      );

      CREATE INDEX IF NOT EXISTS idx_user_roles_role ON user_roles(role);
      SQL);

    $pdo->exec(<<<'SQL'
      INSERT OR IGNORE INTO user_roles (user_id, role)
      SELECT id, 'teacher' FROM users WHERE role = 'teacher';

      INSERT OR IGNORE INTO user_roles (user_id, role)
      SELECT DISTINCT student_id, 'student' FROM teacher_students;
      SQL);
  }

  private static function migrateAnalytics(PDO $pdo): void
  {
    $pdo->exec(<<<'SQL'
      CREATE TABLE IF NOT EXISTS analytics_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        session_id TEXT NOT NULL,
        event_type TEXT NOT NULL CHECK(event_type IN ('click', 'page_view', 'page_time', 'search_referral')),
        page_path TEXT NOT NULL,
        target TEXT,
        duration_ms INTEGER,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
      );

      CREATE INDEX IF NOT EXISTS idx_analytics_events_created
        ON analytics_events(created_at);

      CREATE INDEX IF NOT EXISTS idx_analytics_events_type_created
        ON analytics_events(event_type, created_at);

      CREATE INDEX IF NOT EXISTS idx_analytics_events_page_created
        ON analytics_events(page_path, created_at);

      CREATE INDEX IF NOT EXISTS idx_analytics_events_target_created
        ON analytics_events(target, created_at);
      SQL);
  }

  private static function migrateAnalyticsSearchReferral(PDO $pdo): void
  {
    $sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'analytics_events'")->fetchColumn();
    if ($sql === false || str_contains((string) $sql, 'search_referral')) {
      return;
    }

    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();

    try {
      $pdo->exec(<<<'SQL'
        CREATE TABLE analytics_events_new (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER,
          session_id TEXT NOT NULL,
          event_type TEXT NOT NULL CHECK(event_type IN ('click', 'page_view', 'page_time', 'search_referral')),
          page_path TEXT NOT NULL,
          target TEXT,
          duration_ms INTEGER,
          created_at TEXT NOT NULL DEFAULT (datetime('now')),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        );

        INSERT INTO analytics_events_new
          (id, user_id, session_id, event_type, page_path, target, duration_ms, created_at)
        SELECT id, user_id, session_id, event_type, page_path, target, duration_ms, created_at
        FROM analytics_events;

        DROP TABLE analytics_events;
        ALTER TABLE analytics_events_new RENAME TO analytics_events;

        CREATE INDEX IF NOT EXISTS idx_analytics_events_created
          ON analytics_events(created_at);

        CREATE INDEX IF NOT EXISTS idx_analytics_events_type_created
          ON analytics_events(event_type, created_at);

        CREATE INDEX IF NOT EXISTS idx_analytics_events_page_created
          ON analytics_events(page_path, created_at);

        CREATE INDEX IF NOT EXISTS idx_analytics_events_target_created
          ON analytics_events(target, created_at);
        SQL);
      $pdo->commit();
    } catch (\Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
  }

  private static function migrateTeacherStudentExclusive(PDO $pdo): void
  {
    $index = $pdo->query(
      "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = 'idx_teacher_students_student_unique'",
    )->fetchColumn();
    if ($index !== false) {
      return;
    }

    $pdo->exec(<<<'SQL'
      DELETE FROM teacher_students
      WHERE rowid NOT IN (
        SELECT MIN(rowid) FROM teacher_students GROUP BY student_id
      );

      CREATE UNIQUE INDEX idx_teacher_students_student_unique
        ON teacher_students(student_id);
      SQL);
  }
}
