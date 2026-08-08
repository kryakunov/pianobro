<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class AdminService
{
  public function __construct(
    private readonly PDO $db,
    private readonly RoadmapService $roadmap,
  ) {}

  public function isAdmin(?array $user): bool
  {
    if ($user === null) {
      return false;
    }

    $emails = self::adminEmails();

    return $emails !== [] && in_array(strtolower((string) $user['email']), $emails, true);
  }

  /** @return list<string> */
  public static function adminEmails(): array
  {
    $raw = Env::get('ADMIN_EMAILS');
    if ($raw === '') {
      return [];
    }

    $emails = [];
    foreach (explode(',', $raw) as $part) {
      $email = strtolower(trim($part));
      if ($email !== '') {
        $emails[] = $email;
      }
    }

    return array_values(array_unique($emails));
  }

  /** @return list<array<string, mixed>> */
  public function listUsersWithRoadmap(): array
  {
    $stmt = $this->db->query(<<<'SQL'
      SELECT
        u.id,
        u.email,
        u.name,
        u.created_at,
        u.last_login_at,
        (
          SELECT MAX(ts.created_at)
          FROM training_sessions ts
          WHERE ts.user_id = u.id
        ) AS last_session_at,
        (
          SELECT MAX(na.created_at)
          FROM note_attempts na
          WHERE na.user_id = u.id
        ) AS last_attempt_at,
        (
          SELECT COUNT(*)
          FROM training_sessions ts
          WHERE ts.user_id = u.id
        ) AS sessions_count,
        (
          SELECT COUNT(*)
          FROM note_stats ns
          WHERE ns.user_id = u.id
        ) AS practiced_notes_count
      FROM users u
      ORDER BY datetime(COALESCE(u.last_login_at, u.created_at)) DESC, u.id DESC
      SQL);

    $rows = $stmt->fetchAll();
    $users = [];

    foreach ($rows as $row) {
      $userId = (int) $row['id'];
      $roadmap = $this->roadmap->getRoadmap($userId);
      $progress = $roadmap['progress'];

      $users[] = [
        'id' => $userId,
        'email' => (string) $row['email'],
        'name' => (string) $row['name'],
        'createdAt' => (string) $row['created_at'],
        'lastLoginAt' => $this->resolveLastActivity(
          $row['last_login_at'] ?? null,
          $row['last_session_at'] ?? null,
          $row['last_attempt_at'] ?? null,
        ),
        'sessionsCount' => (int) $row['sessions_count'],
        'practicedNotesCount' => (int) $row['practiced_notes_count'],
        'roadmap' => [
          'totalXp' => (int) ($progress['totalXp'] ?? 0),
          'rank' => $progress['rank'] ?? ['title' => '—', 'emoji' => ''],
          'completedCount' => (int) ($progress['completedCount'] ?? 0),
          'totalStages' => (int) ($progress['totalStages'] ?? 0),
          'currentStageId' => $progress['currentStageId'] ?? null,
          'stages' => $this->summarizeStages($roadmap),
        ],
      ];
    }

    return $users;
  }

  /**
   * @param array<string, mixed> $roadmap
   * @return list<array<string, mixed>>
   */
  private function summarizeStages(array $roadmap): array
  {
    $configStages = [];
    foreach ($roadmap['stages'] as $stage) {
      $configStages[(string) $stage['id']] = $stage;
    }

    $items = [];
    foreach ($roadmap['progress']['stages'] as $stageProgress) {
      $id = (string) $stageProgress['id'];
      $config = $configStages[$id] ?? [];

      $items[] = [
        'id' => $id,
        'title' => (string) ($config['title'] ?? $id),
        'badge' => (string) ($config['badge'] ?? ''),
        'progress' => (int) ($stageProgress['progress'] ?? 0),
        'completed' => (bool) ($stageProgress['completed'] ?? false),
        'notesComplete' => (bool) ($stageProgress['notesComplete'] ?? false),
        'capstoneReady' => (bool) ($stageProgress['capstoneReady'] ?? false),
        'hasCapstone' => (bool) ($stageProgress['hasCapstone'] ?? false),
        'unlocked' => (bool) ($stageProgress['unlocked'] ?? false),
        'masteredNotes' => (int) ($stageProgress['masteredNotes'] ?? 0),
        'poolSize' => (int) ($stageProgress['poolSize'] ?? 0),
      ];
    }

    return $items;
  }

  private function resolveLastActivity(?string $lastLogin, ?string $lastSession, ?string $lastAttempt): ?string
  {
    $candidates = array_filter([$lastLogin, $lastSession, $lastAttempt], static fn(?string $value): bool => $value !== null && $value !== '');

    if ($candidates === []) {
      return null;
    }

    usort($candidates, static fn(string $a, string $b): int => strcmp($b, $a));

    return $candidates[0];
  }
}
