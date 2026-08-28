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

    return [
      'status' => $isPremium ? 'active' : ($status === 'active' ? 'expired' : $status),
      'plan' => $row['subscription_plan'] !== null ? (string) $row['subscription_plan'] : null,
      'startedAt' => $row['subscription_started_at'] !== null ? (string) $row['subscription_started_at'] : null,
      'expiresAt' => $expiresAt,
      'isPremium' => $isPremium,
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
    if ($status !== 'active' || $expiresAt === null || strtotime($expiresAt) > time()) {
      return;
    }

    $update = $this->db->prepare('UPDATE users SET subscription_status = :status WHERE id = :id');
    $update->execute(['status' => 'expired', 'id' => $userId]);
  }

  /** @return array<string, mixed> */
  private function freeState(): array
  {
    return [
      'status' => 'free',
      'plan' => null,
      'startedAt' => null,
      'expiresAt' => null,
      'isPremium' => false,
    ];
  }
}
