<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class RoadmapService
{
  /** @var array<string, mixed>|null */
  private ?array $config = null;

  public function __construct(private readonly PDO $db) {}

  /** @return array<string, mixed> */
  public function getRoadmap(?int $userId = null): array
  {
    $config = $this->loadConfig();
    $histories = $userId !== null ? NoteMastery::loadHistories($this->db, $userId) : [];
    $capstoneCompletions = $userId !== null ? $this->loadCapstoneCompletions($userId) : [];

    $stageProgress = [];
    $totalXp = 0;
    $previousCompleted = true;

    foreach ($config['stages'] as $stage) {
      $progress = $this->buildStageProgress($stage, $histories, $capstoneCompletions);
      $unlocked = $previousCompleted;
      $previousCompleted = $progress['completed'];

      if ($progress['completed']) {
        $totalXp += (int) ($stage['xp'] ?? 0);
      }

      $stageProgress[] = [
        'id' => $stage['id'],
        'progress' => $progress['progress'],
        'completed' => $progress['completed'],
        'notesComplete' => $progress['notesComplete'],
        'capstoneComplete' => $progress['capstoneComplete'],
        'capstoneReady' => $progress['capstoneReady'],
        'hasCapstone' => $progress['hasCapstone'],
        'unlocked' => $unlocked,
        'masteredNotes' => $progress['masteredNotes'],
        'poolSize' => $progress['poolSize'],
        'inProgressNotes' => $progress['inProgressNotes'],
      ];
    }

    $currentStageId = null;
    foreach ($stageProgress as $item) {
      if ($item['unlocked'] && !$item['completed']) {
        $currentStageId = $item['id'];
        break;
      }
    }

    return [
      'ranks' => $config['ranks'],
      'stages' => $config['stages'],
      'progress' => [
        'totalXp' => $totalXp,
        'rank' => $this->resolveRank($config['ranks'], $totalXp),
        'stages' => $stageProgress,
        'currentStageId' => $currentStageId,
        'completedCount' => count(array_filter($stageProgress, static fn(array $s): bool => $s['completed'])),
        'totalStages' => count($stageProgress),
      ],
    ];
  }

  public function markCapstoneComplete(int $userId, string $stageId): void
  {
    $stageId = trim($stageId);
    if ($stageId === '') {
      throw new \InvalidArgumentException('Укажите уровень');
    }

    $config = $this->loadConfig();
    $known = false;
    foreach ($config['stages'] as $stage) {
      if ((string) ($stage['id'] ?? '') === $stageId) {
        $known = true;
        break;
      }
    }
    if (!$known) {
      throw new \InvalidArgumentException('Уровень не найден');
    }

    $stmt = $this->db->prepare(
      'INSERT INTO roadmap_capstone_completions (user_id, stage_id)
       VALUES (:user_id, :stage_id)
       ON CONFLICT(user_id, stage_id) DO NOTHING',
    );
    $stmt->execute([
      'user_id' => $userId,
      'stage_id' => $stageId,
    ]);
  }

  /** @return array<string, mixed> */
  private function loadConfig(): array
  {
    if ($this->config !== null) {
      return $this->config;
    }

    $path = dirname(__DIR__) . '/data/note-roadmap.json';
    if (!is_file($path)) {
      throw new \RuntimeException('Roadmap config not found');
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
      throw new \RuntimeException('Roadmap config unreadable');
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['stages'], $data['ranks'])) {
      throw new \RuntimeException('Roadmap config invalid');
    }

    usort($data['stages'], static fn(array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    $this->config = $data;

    return $this->config;
  }

  /**
   * @param array<string, mixed> $stage
   * @param array<int, list<bool>> $histories
   * @param array<string, true> $capstoneCompletions
   * @return array{
   *   progress:int,
   *   completed:bool,
   *   notesComplete:bool,
   *   capstoneComplete:bool,
   *   capstoneReady:bool,
   *   hasCapstone:bool,
   *   masteredNotes:int,
   *   poolSize:int,
   *   inProgressNotes:int
   * }
   */
  private function buildStageProgress(array $stage, array $histories, array $capstoneCompletions = []): array
  {
    $settings = $stage['settings'] ?? [];
    $poolMode = isset($stage['poolMode']) ? (string) $stage['poolMode'] : null;
    $pool = NotePool::fromSettings(is_array($settings) ? $settings : [], $poolMode);
    $poolSize = count($pool);
    $hasCapstone = isset($stage['capstone']['lessonId']);
    $stageId = (string) ($stage['id'] ?? '');

    if ($poolSize === 0) {
      return [
        'progress' => 0,
        'completed' => false,
        'notesComplete' => false,
        'capstoneComplete' => false,
        'capstoneReady' => false,
        'hasCapstone' => $hasCapstone,
        'masteredNotes' => 0,
        'poolSize' => 0,
        'inProgressNotes' => 0,
      ];
    }

    $sum = 0;
    $masteredNotes = 0;
    $inProgressNotes = 0;

    foreach ($pool as $midi) {
      $history = $histories[$midi] ?? [];
      $noteProgress = NoteMastery::progressPercent($history);
      $sum += $noteProgress;

      if ($noteProgress >= 100) {
        $masteredNotes++;
      } elseif ($noteProgress > 0) {
        $inProgressNotes++;
      }
    }

    $notesProgress = (int) round($sum / $poolSize);
    $notesComplete = $masteredNotes === $poolSize;
    $capstoneComplete = !$hasCapstone || isset($capstoneCompletions[$stageId]);
    $capstoneReady = $notesComplete && $hasCapstone && !$capstoneComplete;
    $completed = $notesComplete && (!$hasCapstone || $capstoneComplete);

    if ($completed) {
      $progress = 100;
    } elseif ($notesComplete && $hasCapstone) {
      $progress = 90;
    } elseif ($hasCapstone) {
      $progress = (int) round($notesProgress * 0.9);
    } else {
      $progress = $notesProgress;
    }

    return [
      'progress' => $progress,
      'completed' => $completed,
      'notesComplete' => $notesComplete,
      'capstoneComplete' => $capstoneComplete,
      'capstoneReady' => $capstoneReady,
      'hasCapstone' => $hasCapstone,
      'masteredNotes' => $masteredNotes,
      'poolSize' => $poolSize,
      'inProgressNotes' => $inProgressNotes,
    ];
  }

  /** @return array<string, true> */
  private function loadCapstoneCompletions(int $userId): array
  {
    $completed = [];

    $stmt = $this->db->prepare(
      'SELECT stage_id FROM roadmap_capstone_completions WHERE user_id = :user_id',
    );
    $stmt->execute(['user_id' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
      $stageId = (string) ($row['stage_id'] ?? '');
      if ($stageId !== '') {
        $completed[$stageId] = true;
      }
    }

    $melodyAccuracies = $this->loadMelodyBestAccuracies($userId);
    foreach ($this->loadConfig()['stages'] as $stage) {
      if (!isset($stage['capstone']['lessonId'])) {
        continue;
      }

      $stageId = (string) ($stage['id'] ?? '');
      $lessonId = (string) $stage['capstone']['lessonId'];
      $minAccuracy = (int) ($stage['capstone']['minAccuracy'] ?? 75);
      if ($stageId !== '' && ($melodyAccuracies[$lessonId] ?? 0) >= $minAccuracy) {
        $completed[$stageId] = true;
      }
    }

    return $completed;
  }

  /** @return array<string, int> */
  private function loadMelodyBestAccuracies(int $userId): array
  {
    $stmt = $this->db->prepare(
      'SELECT accuracy, settings_json
       FROM training_sessions
       WHERE user_id = :user_id AND mode = \'melody\'',
    );
    $stmt->execute(['user_id' => $userId]);

    $bestByLesson = [];
    foreach ($stmt->fetchAll() as $row) {
      $settings = json_decode((string) ($row['settings_json'] ?? ''), true);
      if (!is_array($settings)) {
        continue;
      }

      $lessonId = isset($settings['lessonId']) ? (string) $settings['lessonId'] : '';
      if ($lessonId === '') {
        continue;
      }

      $accuracy = (int) ($row['accuracy'] ?? 0);
      $bestByLesson[$lessonId] = max($bestByLesson[$lessonId] ?? 0, $accuracy);
    }

    return $bestByLesson;
  }

  /** @param list<array<string, mixed>> $ranks */
  private function resolveRank(array $ranks, int $totalXp): array
  {
    $current = $ranks[0] ?? ['minXp' => 0, 'title' => 'Новичок', 'emoji' => '🌱'];
    foreach ($ranks as $rank) {
      if ($totalXp >= (int) ($rank['minXp'] ?? 0)) {
        $current = $rank;
      }
    }

    return $current;
  }
}
