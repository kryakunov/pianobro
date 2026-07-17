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

    $stageProgress = [];
    $totalXp = 0;
    $previousCompleted = true;

    foreach ($config['stages'] as $stage) {
      $progress = $this->buildStageProgress($stage, $histories);
      $unlocked = $previousCompleted;
      $previousCompleted = $progress['completed'];

      if ($progress['completed']) {
        $totalXp += (int) ($stage['xp'] ?? 0);
      }

      $stageProgress[] = [
        'id' => $stage['id'],
        'progress' => $progress['progress'],
        'completed' => $progress['completed'],
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
   * @return array{
   *   progress:int,
   *   completed:bool,
   *   masteredNotes:int,
   *   poolSize:int,
   *   inProgressNotes:int
   * }
   */
  private function buildStageProgress(array $stage, array $histories): array
  {
    $settings = $stage['settings'] ?? [];
    $poolMode = isset($stage['poolMode']) ? (string) $stage['poolMode'] : null;
    $pool = NotePool::fromSettings(is_array($settings) ? $settings : [], $poolMode);
    $poolSize = count($pool);

    if ($poolSize === 0) {
      return [
        'progress' => 0,
        'completed' => false,
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

    $progress = (int) round($sum / $poolSize);

    return [
      'progress' => $progress,
      'completed' => $poolSize > 0 && $masteredNotes === $poolSize,
      'masteredNotes' => $masteredNotes,
      'poolSize' => $poolSize,
      'inProgressNotes' => $inProgressNotes,
    ];
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
