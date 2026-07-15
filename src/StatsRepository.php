<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class StatsRepository
{
  public function __construct(private readonly PDO $db) {}

  /**
   * @param list<array{expectedMidi:int,playedMidi:int,correct:bool}> $attempts
   */
  public function saveNoteSession(
    int $userId,
    int $correct,
    int $wrong,
    int $accuracy,
    int $total,
    ?array $settings,
    array $attempts,
  ): void {
    $this->db->beginTransaction();
    try {
      $stmt = $this->db->prepare(
        'INSERT INTO training_sessions (user_id, mode, correct, wrong, accuracy, total, settings_json)
         VALUES (:user_id, :mode, :correct, :wrong, :accuracy, :total, :settings)',
      );
      $stmt->execute([
        'user_id' => $userId,
        'mode' => 'notes',
        'correct' => $correct,
        'wrong' => $wrong,
        'accuracy' => $accuracy,
        'total' => $total,
        'settings' => $settings !== null ? json_encode($settings, JSON_UNESCAPED_UNICODE) : null,
      ]);
      $sessionId = (int) $this->db->lastInsertId();

      $attemptInsert = $this->db->prepare(
        'INSERT INTO note_attempts (user_id, session_id, midi, correct, created_at)
         VALUES (:user_id, :session_id, :midi, :correct, datetime(\'now\'))',
      );

      $upsert = $this->db->prepare(
        'INSERT INTO note_stats (user_id, midi, correct_count, wrong_count, last_practiced_at)
         VALUES (:user_id, :midi, :correct, :wrong, datetime(\'now\'))
         ON CONFLICT(user_id, midi) DO UPDATE SET
           correct_count = correct_count + excluded.correct_count,
           wrong_count = wrong_count + excluded.wrong_count,
           last_practiced_at = datetime(\'now\')',
      );

      $aggregated = [];
      foreach ($attempts as $attempt) {
        $midi = (int) $attempt['expectedMidi'];
        if (!isset($aggregated[$midi])) {
          $aggregated[$midi] = ['correct' => 0, 'wrong' => 0];
        }
        if ($attempt['correct']) {
          $aggregated[$midi]['correct']++;
        } else {
          $aggregated[$midi]['wrong']++;
        }
      }

      foreach ($aggregated as $midi => $counts) {
        if ($counts['correct'] === 0 && $counts['wrong'] === 0) {
          continue;
        }
        $upsert->execute([
          'user_id' => $userId,
          'midi' => $midi,
          'correct' => $counts['correct'],
          'wrong' => $counts['wrong'],
        ]);
      }

      foreach ($attempts as $attempt) {
        $attemptInsert->execute([
          'user_id' => $userId,
          'session_id' => $sessionId,
          'midi' => (int) $attempt['expectedMidi'],
          'correct' => !empty($attempt['correct']) ? 1 : 0,
        ]);
      }

      $this->db->commit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }
  }

  public function saveMelodySession(
    int $userId,
    int $correct,
    int $wrong,
    int $accuracy,
    int $total,
    ?string $lessonId,
  ): void {
    $stmt = $this->db->prepare(
      'INSERT INTO training_sessions (user_id, mode, correct, wrong, accuracy, total, settings_json)
       VALUES (:user_id, :mode, :correct, :wrong, :accuracy, :total, :settings)',
    );
    $stmt->execute([
      'user_id' => $userId,
      'mode' => 'melody',
      'correct' => $correct,
      'wrong' => $wrong,
      'accuracy' => $accuracy,
      'total' => $total,
      'settings' => $lessonId !== null ? json_encode(['lessonId' => $lessonId], JSON_UNESCAPED_UNICODE) : null,
    ]);
  }

  /** @return array{summary:array,notes:array} */
  public function getNoteStats(int $userId): array
  {
    $stmt = $this->db->prepare(
      'SELECT midi, correct_count, wrong_count, last_practiced_at
       FROM note_stats
       WHERE user_id = :user_id
       ORDER BY midi ASC',
    );
    $stmt->execute(['user_id' => $userId]);
    $rows = $stmt->fetchAll();

    $notes = [];
    $mastered = 0;
    $learning = 0;
    $needsPractice = 0;
    $totalAttempts = 0;

    foreach ($rows as $row) {
      $correct = (int) $row['correct_count'];
      $wrong = (int) $row['wrong_count'];
      $attempts = $correct + $wrong;
      $accuracy = $attempts > 0 ? (int) round(($correct / $attempts) * 100) : 0;
      $level = self::masteryLevel($correct, $wrong, $accuracy);

      if ($level === 'mastered') {
        $mastered++;
      } elseif ($level === 'needs_practice') {
        $needsPractice++;
      } else {
        $learning++;
      }

      $totalAttempts += $attempts;
      $notes[] = [
        'midi' => (int) $row['midi'],
        'name' => NoteNames::fromMidi((int) $row['midi']),
        'correct' => $correct,
        'wrong' => $wrong,
        'attempts' => $attempts,
        'accuracy' => $accuracy,
        'level' => $level,
        'lastPracticedAt' => $row['last_practiced_at'],
      ];
    }

    $sessionStmt = $this->db->prepare(
      'SELECT COUNT(*) AS cnt FROM training_sessions WHERE user_id = :user_id AND mode = \'notes\'',
    );
    $sessionStmt->execute(['user_id' => $userId]);
    $sessions = (int) ($sessionStmt->fetch()['cnt'] ?? 0);

    return [
      'summary' => [
        'sessions' => $sessions,
        'notesTracked' => count($notes),
        'totalAttempts' => $totalAttempts,
        'mastered' => $mastered,
        'learning' => $learning,
        'needsPractice' => $needsPractice,
      ],
      'notes' => $notes,
      'dailyProgress' => $this->getDailyProgress($userId),
      'dailyGoal' => [
        'date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        'completed' => $this->getDailyGoalToday($userId),
      ],
    ];
  }

  public function getDailyGoalToday(int $userId): int
  {
    $stmt = $this->db->prepare(
      'SELECT COUNT(*) AS cnt
       FROM note_attempts
       WHERE user_id = :user_id AND correct = 1 AND date(created_at) = date(\'now\')',
    );
    $stmt->execute(['user_id' => $userId]);

    return (int) ($stmt->fetch()['cnt'] ?? 0);
  }

  /** @return list<array{date:string,learned:int,repeated:int}> */
  public function getDailyProgress(int $userId, int $days = 14): array
  {
    $days = max(7, min(30, $days));
    $startDate = (new \DateTimeImmutable('today'))
      ->modify('-' . ($days - 1) . ' days')
      ->format('Y-m-d');

    $byDay = [];
    for ($i = $days - 1; $i >= 0; $i--) {
      $date = (new \DateTimeImmutable('today'))
        ->modify('-' . $i . ' days')
        ->format('Y-m-d');
      $byDay[$date] = ['date' => $date, 'learned' => 0, 'repeated' => 0];
    }

    $preload = $this->db->prepare(
      'SELECT DISTINCT midi
       FROM note_attempts
       WHERE user_id = :user_id AND correct = 1 AND date(created_at) < :start_date',
    );
    $preload->execute([
      'user_id' => $userId,
      'start_date' => $startDate,
    ]);
    $everHadCorrect = [];
    foreach ($preload->fetchAll() as $row) {
      $everHadCorrect[(int) $row['midi']] = true;
    }

    $stmt = $this->db->prepare(
      'SELECT date(created_at) AS day, midi, correct
       FROM note_attempts
       WHERE user_id = :user_id AND date(created_at) >= :start_date
       ORDER BY created_at ASC, id ASC',
    );
    $stmt->execute([
      'user_id' => $userId,
      'start_date' => $startDate,
    ]);

    $learnedByDay = [];
    $repeatedByDay = [];

    foreach ($stmt->fetchAll() as $row) {
      $day = (string) $row['day'];
      $midi = (int) $row['midi'];
      $correct = (int) $row['correct'] === 1;

      if (!isset($byDay[$day])) {
        continue;
      }

      if ($correct && !isset($everHadCorrect[$midi])) {
        $learnedByDay[$day][$midi] = true;
        $everHadCorrect[$midi] = true;
        continue;
      }

      if (isset($everHadCorrect[$midi])) {
        $repeatedByDay[$day][$midi] = true;
      }
    }

    foreach ($byDay as $date => &$entry) {
      $entry['learned'] = isset($learnedByDay[$date]) ? count($learnedByDay[$date]) : 0;
      $entry['repeated'] = isset($repeatedByDay[$date]) ? count($repeatedByDay[$date]) : 0;
    }
    unset($entry);

    return array_values($byDay);
  }

  public static function masteryLevel(int $correct, int $wrong, int $accuracy): string
  {
    $attempts = $correct + $wrong;
    if ($attempts < 2) {
      return 'learning';
    }
    if ($accuracy >= 85 && $correct >= 3) {
      return 'mastered';
    }
    if ($accuracy < 60 || $wrong > $correct) {
      return 'needs_practice';
    }

    return 'learning';
  }
}
