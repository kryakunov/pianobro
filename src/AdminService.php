<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class AdminService
{
  public function __construct(
    private readonly PDO $db,
    private readonly RoadmapService $roadmap,
    private readonly RoleService $roles,
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

  /** @return array{today:int,yesterday:int} */
  public function getDailyOnlineCounts(): array
  {
    $todayStart = new \DateTimeImmutable('today');
    $todayEnd = $todayStart->modify('+1 day');
    $yesterdayStart = $todayStart->modify('-1 day');

    return [
      'today' => $this->countActiveUsersBetween(
        $todayStart->format('Y-m-d H:i:s'),
        $todayEnd->format('Y-m-d H:i:s'),
      ),
      'yesterday' => $this->countActiveUsersBetween(
        $yesterdayStart->format('Y-m-d H:i:s'),
        $todayStart->format('Y-m-d H:i:s'),
      ),
    ];
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
        u.subscription_status,
        u.subscription_plan,
        u.subscription_expires_at,
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
        ) AS practiced_notes_count,
        (
          SELECT COUNT(*)
          FROM payments p
          WHERE p.user_id = u.id AND p.status = 'succeeded'
        ) AS payments_count,
        (
          SELECT MAX(COALESCE(p.paid_at, p.created_at))
          FROM payments p
          WHERE p.user_id = u.id AND p.status = 'succeeded'
        ) AS last_paid_at
      FROM users u
      ORDER BY datetime(COALESCE(u.last_login_at, u.created_at)) DESC, u.id DESC
      SQL);

    $rows = $stmt->fetchAll();
    $rolesByUser = $this->roles->getRolesByUser();
    $users = [];

    foreach ($rows as $row) {
      $userId = (int) $row['id'];
      $roadmap = $this->roadmap->getRoadmap($userId);
      $progress = $roadmap['progress'];
      $roles = $rolesByUser[$userId] ?? [];

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
        'roles' => $roles,
        'isTeacher' => in_array(RoleService::ROLE_TEACHER, $roles, true),
        'subscription' => $this->summarizeSubscription($row),
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

  /** @return array{id:int,email:string,name:string,isAdmin:bool} */
  public function resolveManagedUser(int $userId): array
  {
    if ($userId <= 0) {
      throw new \InvalidArgumentException('Некорректный ID пользователя');
    }

    $stmt = $this->db->prepare('SELECT id, email, name FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
      throw new \InvalidArgumentException('Пользователь не найден');
    }

    $email = strtolower((string) $row['email']);

    return [
      'id' => $userId,
      'email' => (string) $row['email'],
      'name' => (string) $row['name'],
      'isAdmin' => in_array($email, self::adminEmails(), true),
    ];
  }

  /** @return array{id:int,email:string,name:string,isAdmin:bool} */
  public function assertCanModifyUser(int $userId): array
  {
    return $this->resolveManagedUser($userId);
  }

  public function assertCanDeleteUser(int $userId, int $actingAdminId): void
  {
    $target = $this->resolveManagedUser($userId);

    if ($userId === $actingAdminId) {
      throw new \InvalidArgumentException('Нельзя удалить свой аккаунт');
    }

    if ($target['isAdmin']) {
      throw new \InvalidArgumentException('Нельзя удалить аккаунт администратора');
    }
  }

  /** @return array{id:int,roles:list<string>,isTeacher:bool} */
  public function setUserTeacherRole(int $userId, bool $enabled): array
  {
    $this->assertCanModifyUser($userId);

    if ($enabled) {
      $this->roles->grantRole($userId, RoleService::ROLE_TEACHER);
    } else {
      $this->roles->revokeRole($userId, RoleService::ROLE_TEACHER);
    }

    $roles = $this->roles->getRoles($userId);

    return [
      'id' => $userId,
      'roles' => $roles,
      'isTeacher' => in_array(RoleService::ROLE_TEACHER, $roles, true),
    ];
  }

  /** @return array{isPremium:bool,hasPurchased:bool,planId:?string,planName:string,status:string,expiresAt:?string,paymentsCount:int,lastPaidAt:?string} */
  public function grantSubscription(int $userId, string $planId): array
  {
    $plan = PricingConfig::plan($planId);
    if ($plan === null) {
      throw new \InvalidArgumentException('Неизвестный тариф');
    }

    $this->assertCanModifyUser($userId);

    $now = gmdate('Y-m-d H:i:s');
    $expires = gmdate('Y-m-d H:i:s', time() + ((int) $plan['durationDays'] * 86400));

    $stmt = $this->db->prepare(
      'UPDATE users SET
         subscription_status = :status,
         subscription_plan = :plan,
         subscription_started_at = :started_at,
         subscription_expires_at = :expires_at,
         payment_provider = :provider,
         last_payment_id = :payment_id
       WHERE id = :id',
    );
    $stmt->execute([
      'status' => 'active',
      'plan' => $planId,
      'started_at' => $now,
      'expires_at' => $expires,
      'provider' => 'admin',
      'payment_id' => 'admin-grant-' . $now,
      'id' => $userId,
    ]);

    return $this->getSubscriptionForUser($userId);
  }

  /** @return array{isPremium:bool,hasPurchased:bool,planId:?string,planName:string,status:string,expiresAt:?string,paymentsCount:int,lastPaidAt:?string} */
  public function revokeSubscription(int $userId): array
  {
    $this->assertCanModifyUser($userId);

    $stmt = $this->db->prepare(
      'UPDATE users SET
         subscription_status = :status,
         subscription_plan = NULL,
         subscription_started_at = NULL,
         subscription_expires_at = NULL,
         payment_provider = NULL,
         last_payment_id = NULL
       WHERE id = :id',
    );
    $stmt->execute([
      'status' => 'free',
      'id' => $userId,
    ]);

    return $this->getSubscriptionForUser($userId);
  }

  public function deleteUser(int $userId, int $actingAdminId): void
  {
    $this->assertCanDeleteUser($userId, $actingAdminId);

    $delete = $this->db->prepare('DELETE FROM users WHERE id = :id');
    $delete->execute(['id' => $userId]);
    if ($delete->rowCount() === 0) {
      throw new \InvalidArgumentException('Пользователь не найден');
    }
  }

  /** @return array{isPremium:bool,hasPurchased:bool,planId:?string,planName:string,status:string,expiresAt:?string,paymentsCount:int,lastPaidAt:?string} */
  public function getSubscriptionForUser(int $userId): array
  {
    $stmt = $this->db->prepare(<<<'SQL'
      SELECT
        subscription_status,
        subscription_plan,
        subscription_expires_at,
        (
          SELECT COUNT(*)
          FROM payments p
          WHERE p.user_id = users.id AND p.status = 'succeeded'
        ) AS payments_count,
        (
          SELECT MAX(COALESCE(p.paid_at, p.created_at))
          FROM payments p
          WHERE p.user_id = users.id AND p.status = 'succeeded'
        ) AS last_paid_at
      FROM users
      WHERE id = :id
      SQL);
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
      throw new \InvalidArgumentException('Пользователь не найден');
    }

    return $this->summarizeSubscription($row);
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

  /** @param array<string, mixed> $row
   * @return array{isPremium:bool,hasPurchased:bool,planId:?string,planName:string,status:string,expiresAt:?string,paymentsCount:int,lastPaidAt:?string}
   */
  private function summarizeSubscription(array $row): array
  {
    $planId = $row['subscription_plan'] !== null && $row['subscription_plan'] !== ''
      ? (string) $row['subscription_plan']
      : null;
    $status = (string) ($row['subscription_status'] ?? 'free');
    $expiresAt = $row['subscription_expires_at'] !== null && $row['subscription_expires_at'] !== ''
      ? (string) $row['subscription_expires_at']
      : null;
    $isPremium = $status === 'active' && $expiresAt !== null && strtotime($expiresAt) > time();
    $paymentsCount = (int) ($row['payments_count'] ?? 0);
    $hasPurchased = $paymentsCount > 0;
    $lastPaidAt = $row['last_paid_at'] !== null && $row['last_paid_at'] !== ''
      ? (string) $row['last_paid_at']
      : null;

    $planConfig = $planId !== null ? PricingConfig::plan($planId) : null;
    $planName = $planConfig !== null
      ? (string) ($planConfig['shortName'] ?? $planConfig['name'])
      : ($planId ?? '—');

    if ($isPremium || $hasPurchased) {
      $displayStatus = $isPremium ? 'active' : 'expired';
    } else {
      $displayStatus = 'free';
      $planName = 'Бесплатный';
    }

    return [
      'isPremium' => $isPremium,
      'hasPurchased' => $hasPurchased,
      'planId' => $planId,
      'planName' => $planName,
      'status' => $displayStatus,
      'expiresAt' => $expiresAt,
      'paymentsCount' => $paymentsCount,
      'lastPaidAt' => $lastPaidAt,
    ];
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

  private function countActiveUsersBetween(string $start, string $end): int
  {
    $stmt = $this->db->prepare(<<<'SQL'
      SELECT COUNT(DISTINCT user_id) FROM (
        SELECT id AS user_id
        FROM users
        WHERE last_login_at >= :start AND last_login_at < :end
        UNION
        SELECT user_id
        FROM training_sessions
        WHERE created_at >= :start AND created_at < :end
        UNION
        SELECT user_id
        FROM note_attempts
        WHERE created_at >= :start AND created_at < :end
      )
      SQL);
    $stmt->execute(['start' => $start, 'end' => $end]);

    return (int) $stmt->fetchColumn();
  }
}
