<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class NoteMastery
{
  public const REQUIRED_STREAK = 2;

  /** @return array<int, list<bool>> */
  public static function loadHistories(PDO $db, int $userId): array
  {
    $stmt = $db->prepare(
      'SELECT midi, correct
       FROM note_attempts
       WHERE user_id = :user_id
       ORDER BY created_at ASC, id ASC',
    );
    $stmt->execute(['user_id' => $userId]);

    $histories = [];
    foreach ($stmt->fetchAll() as $row) {
      $midi = (int) $row['midi'];
      $histories[$midi][] = (int) $row['correct'] === 1;
    }

    return $histories;
  }

  /**
   * @param list<bool> $history
   * @return array{correct:int, wrong:int, attempts:int, accuracy:int, streak:int}
   */
  public static function summarize(array $history): array
  {
    $correct = 0;
    foreach ($history as $hit) {
      if ($hit) {
        $correct++;
      }
    }
    $attempts = count($history);
    $wrong = $attempts - $correct;
    $accuracy = $attempts > 0 ? (int) round(($correct / $attempts) * 100) : 0;
    $streak = self::endStreak($history);

    return [
      'correct' => $correct,
      'wrong' => $wrong,
      'attempts' => $attempts,
      'accuracy' => $accuracy,
      'streak' => $streak,
    ];
  }

  /** @param list<bool> $history */
  public static function endStreak(array $history): int
  {
    $streak = 0;
    for ($i = count($history) - 1; $i >= 0; $i--) {
      if (!$history[$i]) {
        break;
      }
      $streak++;
    }

    return $streak;
  }

  /** @param list<bool> $history */
  public static function isMastered(array $history): bool
  {
    return self::endStreak($history) >= self::REQUIRED_STREAK;
  }

  /** @param list<bool> $history */
  public static function masteryLevel(array $history): string
  {
    if ($history === []) {
      return 'learning';
    }
    if (self::isMastered($history)) {
      return 'mastered';
    }

    return 'learning';
  }

  /** @param list<bool> $history */
  public static function progressPercent(array $history): int
  {
    if ($history === []) {
      return 0;
    }
    if (self::isMastered($history)) {
      return 100;
    }

    $streak = self::endStreak($history);
    if ($streak === 1) {
      return 50;
    }

    $summary = self::summarize($history);
    $correctPart = min($summary['correct'] / self::REQUIRED_STREAK, 1) * 50;
    $accuracyPart = min($summary['accuracy'] / 85, 1) * 50;

    return (int) round($correctPart + $accuracyPart);
  }

  /**
   * @param list<int|bool> $values
   * @return list<bool>
   */
  public static function normalizeHistory(array $values): array
  {
    $history = [];
    foreach ($values as $value) {
      $history[] = (bool) $value;
    }

    return $history;
  }
}
