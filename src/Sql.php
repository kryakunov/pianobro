<?php

declare(strict_types=1);

namespace PianoTrainer;

final class Sql
{
  public static function isSqlite(): bool
  {
    return Database::driver() === 'sqlite';
  }

  public static function nowUtc(): string
  {
    return gmdate('Y-m-d H:i:s');
  }

  public static function dateExpr(string $column): string
  {
    return self::isSqlite() ? "date({$column})" : "DATE({$column})";
  }

  public static function upsertNoteStats(): string
  {
    if (self::isSqlite()) {
      return <<<'SQL'
        INSERT INTO note_stats (user_id, midi, correct_count, wrong_count, last_practiced_at)
        VALUES (:user_id, :midi, :correct, :wrong, :last_practiced_at)
        ON CONFLICT(user_id, midi) DO UPDATE SET
          correct_count = correct_count + excluded.correct_count,
          wrong_count = wrong_count + excluded.wrong_count,
          last_practiced_at = excluded.last_practiced_at
        SQL;
    }

    return <<<'SQL'
      INSERT INTO note_stats (user_id, midi, correct_count, wrong_count, last_practiced_at)
      VALUES (:user_id, :midi, :correct, :wrong, :last_practiced_at)
      ON DUPLICATE KEY UPDATE
        correct_count = correct_count + VALUES(correct_count),
        wrong_count = wrong_count + VALUES(wrong_count),
        last_practiced_at = VALUES(last_practiced_at)
      SQL;
  }

  public static function bumpDailySessionCount(): string
  {
    if (self::isSqlite()) {
      return <<<'SQL'
        INSERT INTO daily_training_usage (user_id, usage_date, session_count)
        VALUES (:user_id, :usage_date, 1)
        ON CONFLICT(user_id, usage_date)
        DO UPDATE SET session_count = session_count + 1
        SQL;
    }

    return <<<'SQL'
      INSERT INTO daily_training_usage (user_id, usage_date, session_count)
      VALUES (:user_id, :usage_date, 1)
      ON DUPLICATE KEY UPDATE session_count = session_count + 1
      SQL;
  }

  public static function bumpDailyNoteCount(): string
  {
    if (self::isSqlite()) {
      return <<<'SQL'
        INSERT INTO daily_training_usage (user_id, usage_date, session_count, note_count)
        VALUES (:user_id, :usage_date, 0, :insert_note_count)
        ON CONFLICT(user_id, usage_date)
        DO UPDATE SET note_count = note_count + :update_note_count
        SQL;
    }

    return <<<'SQL'
      INSERT INTO daily_training_usage (user_id, usage_date, session_count, note_count)
      VALUES (:user_id, :usage_date, 0, :insert_note_count)
      ON DUPLICATE KEY UPDATE note_count = note_count + :update_note_count
      SQL;
  }

  public static function insertIgnoreUserRole(): string
  {
    return self::isSqlite()
      ? 'INSERT OR IGNORE INTO user_roles (user_id, role) VALUES (:user_id, :role)'
      : 'INSERT IGNORE INTO user_roles (user_id, role) VALUES (:user_id, :role)';
  }

  public static function insertRoadmapCapstoneIgnore(): string
  {
    if (self::isSqlite()) {
      return <<<'SQL'
        INSERT INTO roadmap_capstone_completions (user_id, stage_id)
        VALUES (:user_id, :stage_id)
        ON CONFLICT(user_id, stage_id) DO NOTHING
        SQL;
    }

    return <<<'SQL'
      INSERT IGNORE INTO roadmap_capstone_completions (user_id, stage_id)
      VALUES (:user_id, :stage_id)
      SQL;
  }
}
