<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class SubscriptionService
{
  public function __construct(private readonly PDO $db) {}

  /** @return array<string, mixed> */
  public function getForUser(int $userId): array
  {
    $this->expireIfNeeded($userId);

    $stmt = $this->db->prepare(
      'SELECT subscription_status, subscription_plan, subscription_started_at, subscription_expires_at,
              payment_provider, last_payment_id
       FROM users WHERE id = :id',
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if ($row === false) {
      return $this->freeState();
    }

    $status = (string) ($row['subscription_status'] ?? 'free');
    $expiresAt = $row['subscription_expires_at'] !== null ? (string) $row['subscription_expires_at'] : null;
    $isPremium = $status === 'active' && $expiresAt !== null && strtotime($expiresAt) > time();
    $notesUsed = $isPremium ? 0 : $this->getDailyNoteUsage($userId);

    return [
      'status' => $isPremium ? 'active' : ($status === 'active' ? 'expired' : $status),
      'plan' => $row['subscription_plan'] !== null ? (string) $row['subscription_plan'] : null,
      'startedAt' => $row['subscription_started_at'] !== null ? (string) $row['subscription_started_at'] : null,
      'expiresAt' => $expiresAt,
      'paymentProvider' => $row['payment_provider'] !== null ? (string) $row['payment_provider'] : null,
      'lastPaymentId' => $row['last_payment_id'] !== null ? (string) $row['last_payment_id'] : null,
      'isPremium' => $isPremium,
      'dailyLimit' => $isPremium ? null : PricingConfig::FREE_DAILY_SESSIONS,
      'dailyUsed' => $isPremium ? 0 : $this->getDailyUsage($userId),
      'dailyRemaining' => $isPremium ? null : max(0, PricingConfig::FREE_DAILY_SESSIONS - $this->getDailyUsage($userId)),
      'dailyNotesLimit' => $isPremium ? null : PricingConfig::FREE_DAILY_NOTES,
      'dailyNotesUsed' => $notesUsed,
      'dailyNotesRemaining' => $isPremium ? null : max(0, PricingConfig::FREE_DAILY_NOTES - $notesUsed),
    ];
  }

  public function isPremium(int $userId): bool
  {
    return (bool) ($this->getForUser($userId)['isPremium'] ?? false);
  }

  public function activate(int $userId, string $planId, string $provider, string $paymentId): void
  {
    $plan = PricingConfig::plan($planId);
    if ($plan === null) {
      throw new \InvalidArgumentException('Неизвестный тариф');
    }

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
      'provider' => $provider,
      'payment_id' => $paymentId,
      'id' => $userId,
    ]);
  }

  /** @return array{allowed:bool, reason?:string, remaining?:int|null, isPremium?:bool} */
  public function canStartTraining(int $userId, string $type = 'training'): array
  {
    if ($type === 'diagnostic') {
      return ['allowed' => true, 'isPremium' => $this->isPremium($userId)];
    }

    if ($this->isPremium($userId)) {
      return ['allowed' => true, 'isPremium' => true, 'remaining' => null];
    }

    $used = $this->getDailyUsage($userId);
    $limit = PricingConfig::FREE_DAILY_SESSIONS;
    if ($used >= $limit) {
      return [
        'allowed' => false,
        'reason' => 'daily_limit',
        'remaining' => 0,
        'isPremium' => false,
      ];
    }

    return [
      'allowed' => true,
      'remaining' => $limit - $used,
      'isPremium' => false,
    ];
  }

  public function recordTrainingSession(int $userId, string $type = 'training'): void
  {
    if ($type === 'diagnostic' || $this->isPremium($userId)) {
      return;
    }

    $date = gmdate('Y-m-d');
    $stmt = $this->db->prepare(
      'INSERT INTO daily_training_usage (user_id, usage_date, session_count)
       VALUES (:user_id, :usage_date, 1)
       ON CONFLICT(user_id, usage_date)
       DO UPDATE SET session_count = session_count + 1',
    );
    $stmt->execute(['user_id' => $userId, 'usage_date' => $date]);
  }

  public function getDailyUsage(int $userId): int
  {
    $row = $this->getDailyUsageRow($userId);

    return $row !== null ? (int) $row['session_count'] : 0;
  }

  public function getDailyNoteUsage(int $userId): int
  {
    $row = $this->getDailyUsageRow($userId);

    return $row !== null ? (int) ($row['note_count'] ?? 0) : 0;
  }

  /** @return array{allowed:bool, reason?:string, remaining?:int|null, limit?:int|null, used?:int, isPremium?:bool} */
  public function canPlayNotes(int $userId, int $count = 1): array
  {
    if ($this->isPremium($userId)) {
      return ['allowed' => true, 'isPremium' => true, 'remaining' => null, 'limit' => null];
    }

    $used = $this->getDailyNoteUsage($userId);
    $limit = PricingConfig::FREE_DAILY_NOTES;
    $remaining = max(0, $limit - $used);

    if ($used + $count > $limit) {
      return [
        'allowed' => false,
        'reason' => 'daily_notes_limit',
        'remaining' => $remaining,
        'limit' => $limit,
        'used' => $used,
        'isPremium' => false,
      ];
    }

    return [
      'allowed' => true,
      'remaining' => $remaining - $count,
      'limit' => $limit,
      'used' => $used,
      'isPremium' => false,
    ];
  }

  public function recordNoteAttempts(int $userId, int $count = 1): void
  {
    if ($count <= 0 || $this->isPremium($userId)) {
      return;
    }

    $date = gmdate('Y-m-d');
    $stmt = $this->db->prepare(
      'INSERT INTO daily_training_usage (user_id, usage_date, session_count, note_count)
       VALUES (:user_id, :usage_date, 0, :insert_note_count)
       ON CONFLICT(user_id, usage_date)
       DO UPDATE SET note_count = note_count + :update_note_count',
    );
    $stmt->execute([
      'user_id' => $userId,
      'usage_date' => $date,
      'insert_note_count' => $count,
      'update_note_count' => $count,
    ]);
  }

  /** @return array<string, mixed>|null */
  private function getDailyUsageRow(int $userId): ?array
  {
    $date = gmdate('Y-m-d');
    $stmt = $this->db->prepare(
      'SELECT session_count, note_count FROM daily_training_usage WHERE user_id = :user_id AND usage_date = :usage_date',
    );
    $stmt->execute(['user_id' => $userId, 'usage_date' => $date]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
  }

  /** @param array<string, mixed> $result */
  public function saveDiagnostic(int $userId, array $result): int
  {
    $stmt = $this->db->prepare(
      'INSERT INTO diagnostic_results
         (user_id, correct, total, accuracy, avg_response_ms, weak_notes_json, clef_errors_json, created_at)
       VALUES
         (:user_id, :correct, :total, :accuracy, :avg_response_ms, :weak_notes_json, :clef_errors_json, datetime(\'now\'))',
    );
    $stmt->execute([
      'user_id' => $userId,
      'correct' => (int) ($result['correct'] ?? 0),
      'total' => (int) ($result['total'] ?? 0),
      'accuracy' => (int) ($result['accuracy'] ?? 0),
      'avg_response_ms' => (int) ($result['avgResponseMs'] ?? 0),
      'weak_notes_json' => json_encode($result['weakNotes'] ?? [], JSON_UNESCAPED_UNICODE),
      'clef_errors_json' => json_encode($result['clefErrors'] ?? [], JSON_UNESCAPED_UNICODE),
    ]);

    return (int) $this->db->lastInsertId();
  }

  /** @return array<string, mixed>|null */
  public function latestDiagnostic(int $userId): ?array
  {
    $stmt = $this->db->prepare(
      'SELECT * FROM diagnostic_results WHERE user_id = :user_id ORDER BY id DESC LIMIT 1',
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
      return null;
    }

    return $this->hydrateDiagnosticRow($row);
  }

  /** @param array<string, mixed> $row @return array<string, mixed> */
  private function hydrateDiagnosticRow(array $row): array
  {
    return [
      'id' => (int) $row['id'],
      'correct' => (int) $row['correct'],
      'total' => (int) $row['total'],
      'accuracy' => (int) $row['accuracy'],
      'avgResponseMs' => (int) ($row['avg_response_ms'] ?? 0),
      'weakNotes' => json_decode((string) ($row['weak_notes_json'] ?? '[]'), true) ?: [],
      'clefErrors' => json_decode((string) ($row['clef_errors_json'] ?? '[]'), true) ?: [],
      'createdAt' => (string) $row['created_at'],
    ];
  }

  private function expireIfNeeded(int $userId): void
  {
    $stmt = $this->db->prepare(
      'SELECT subscription_status, subscription_expires_at FROM users WHERE id = :id',
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
      return;
    }

    $status = (string) ($row['subscription_status'] ?? 'free');
    $expiresAt = $row['subscription_expires_at'] !== null ? (string) $row['subscription_expires_at'] : null;
    if ($status !== 'active' || $expiresAt === null) {
      return;
    }

    if (strtotime($expiresAt) <= time()) {
      $update = $this->db->prepare(
        'UPDATE users SET subscription_status = :status WHERE id = :id',
      );
      $update->execute(['status' => 'expired', 'id' => $userId]);
    }
  }

  /** @return array<string, mixed> */
  private function freeState(): array
  {
    return [
      'status' => 'free',
      'plan' => null,
      'startedAt' => null,
      'expiresAt' => null,
      'paymentProvider' => null,
      'lastPaymentId' => null,
      'isPremium' => false,
      'dailyLimit' => PricingConfig::FREE_DAILY_SESSIONS,
      'dailyUsed' => 0,
      'dailyRemaining' => PricingConfig::FREE_DAILY_SESSIONS,
      'dailyNotesLimit' => PricingConfig::FREE_DAILY_NOTES,
      'dailyNotesUsed' => 0,
      'dailyNotesRemaining' => PricingConfig::FREE_DAILY_NOTES,
    ];
  }
}
