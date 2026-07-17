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
        if (!empty($attempt['correct'])) {
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

  /**
   * @param list<array{midi:int,history?:list<int|bool>}> $entries
   */
  public function mergeGuestNoteStats(int $userId, array $entries): int
  {
    $attemptInsert = $this->db->prepare(
      'INSERT INTO note_attempts (user_id, session_id, midi, correct, created_at)
       VALUES (:user_id, NULL, :midi, :correct, datetime(\'now\'))',
    );

    $upsert = $this->db->prepare(
      'INSERT INTO note_stats (user_id, midi, correct_count, wrong_count, last_practiced_at)
       VALUES (:user_id, :midi, :correct, :wrong, datetime(\'now\'))
       ON CONFLICT(user_id, midi) DO UPDATE SET
         correct_count = correct_count + excluded.correct_count,
         wrong_count = wrong_count + excluded.wrong_count,
         last_practiced_at = datetime(\'now\')',
    );

    $merged = 0;
    foreach ($entries as $entry) {
      $history = NoteMastery::normalizeHistory($entry['history'] ?? []);
      if ($history === []) {
        continue;
      }

      $correct = 0;
      $wrong = 0;
      foreach ($history as $hit) {
        if ($hit) {
          $correct++;
        } else {
          $wrong++;
        }
        $attemptInsert->execute([
          'user_id' => $userId,
          'midi' => (int) ($entry['midi'] ?? 0),
          'correct' => $hit ? 1 : 0,
        ]);
      }

      $upsert->execute([
        'user_id' => $userId,
        'midi' => (int) ($entry['midi'] ?? 0),
        'correct' => $correct,
        'wrong' => $wrong,
      ]);
      $merged++;
    }

    return $merged;
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
    $histories = NoteMastery::loadHistories($this->db, $userId);

    $notes = [];
    $mastered = 0;
    $learning = 0;
    $totalAttempts = 0;

    ksort($histories);
    foreach ($histories as $midi => $history) {
      $summary = NoteMastery::summarize($history);
      $level = NoteMastery::masteryLevel($history);

      if ($level === 'mastered') {
        $mastered++;
      } else {
        $learning++;
      }

      $totalAttempts += $summary['attempts'];
      $notes[] = [
        'midi' => $midi,
        'name' => NoteNames::fromMidi($midi),
        'correct' => $summary['correct'],
        'wrong' => $summary['wrong'],
        'attempts' => $summary['attempts'],
        'accuracy' => $summary['accuracy'],
        'streak' => $summary['streak'],
        'history' => array_map(static fn(bool $hit): int => $hit ? 1 : 0, $history),
        'level' => $level,
        'lastPracticedAt' => $this->lastPracticedAt($userId, $midi),
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
      ],
      'notes' => $notes,
      'dailyProgress' => $this->getDailyProgress($userId),
      'dailyGoal' => [
        'date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        'completed' => $this->getDailyGoalToday($userId),
      ],
    ];
  }

  private function lastPracticedAt(int $userId, int $midi): ?string
  {
    $stmt = $this->db->prepare(
      'SELECT last_practiced_at FROM note_stats WHERE user_id = :user_id AND midi = :midi',
    );
    $stmt->execute(['user_id' => $userId, 'midi' => $midi]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (string) $value : null;
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

    $stmt = $this->db->prepare(
      'SELECT date(created_at) AS day, midi, correct
       FROM note_attempts
       WHERE user_id = :user_id
       ORDER BY created_at ASC, id ASC',
    );
    $stmt->execute(['user_id' => $userId]);

    $histories = [];
    $masteredEver = [];
    $learnedByDay = [];
    $repeatedByDay = [];

    foreach ($stmt->fetchAll() as $row) {
      $day = (string) $row['day'];
      $midi = (int) $row['midi'];
      $hit = (int) $row['correct'] === 1;
      $histories[$midi][] = $hit;

      if (!isset($byDay[$day])) {
        continue;
      }

      if (NoteMastery::isMastered($histories[$midi]) && !isset($masteredEver[$midi])) {
        $masteredEver[$midi] = true;
        $learnedByDay[$day][$midi] = true;
        continue;
      }

      if (isset($masteredEver[$midi])) {
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
}
