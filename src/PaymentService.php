<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class PaymentService
{
  private const PROVIDER = 'yookassa';

  public function __construct(
    private readonly PDO $db,
    private readonly SubscriptionService $subscriptions,
  ) {}

  public function isMockMode(): bool
  {
    $shopId = trim(Env::get('YOOKASSA_SHOP_ID', ''));
    $secret = trim(Env::get('YOOKASSA_SECRET_KEY', ''));

    return $shopId === '' || $secret === '' || Env::get('YOOKASSA_MOCK', '0') === '1';
  }

  /** @return array<string, mixed> */
  public function createCheckout(int $userId, string $planId, string $returnUrl): array
  {
    if ($this->subscriptions->isPremium($userId)) {
      throw new \InvalidArgumentException('У вас уже есть активная подписка');
    }

    $plan = PricingConfig::plan($planId);
    if ($plan === null) {
      throw new \InvalidArgumentException('Неизвестный тариф');
    }

    $idempotenceKey = bin2hex(random_bytes(16));
    $amount = (int) $plan['priceRub'];

    $stmt = $this->db->prepare(
      'INSERT INTO payments (user_id, plan, amount, currency, status, provider, idempotence_key, created_at)
       VALUES (:user_id, :plan, :amount, :currency, :status, :provider, :idempotence_key, datetime(\'now\'))',
    );
    $stmt->execute([
      'user_id' => $userId,
      'plan' => $planId,
      'amount' => $amount,
      'currency' => 'RUB',
      'status' => 'pending',
      'provider' => self::PROVIDER,
      'idempotence_key' => $idempotenceKey,
    ]);

    $paymentId = (int) $this->db->lastInsertId();

    if ($this->isMockMode()) {
      $confirmationUrl = rtrim($returnUrl, '/') . '?payment=mock&paymentId=' . $paymentId;

      return [
        'paymentId' => $paymentId,
        'planId' => $planId,
        'amountRub' => $amount,
        'confirmationUrl' => $confirmationUrl,
        'mock' => true,
      ];
    }

    $confirmationUrl = $this->createYooKassaPayment($paymentId, $userId, $plan, $idempotenceKey, $returnUrl);

    return [
      'paymentId' => $paymentId,
      'planId' => $planId,
      'amountRub' => $amount,
      'confirmationUrl' => $confirmationUrl,
      'mock' => false,
    ];
  }

  public function completeMockPayment(int $paymentId, int $userId): void
  {
    if (!$this->isMockMode()) {
      throw new \RuntimeException('Mock-оплата доступна только в dev-режиме');
    }

    $this->markPaymentSucceeded($paymentId, $userId, 'mock_' . $paymentId);
  }

  /** @param array<string, mixed> $payload */
  public function handleWebhook(array $payload): void
  {
    $event = (string) ($payload['event'] ?? '');
    if ($event !== 'payment.succeeded') {
      return;
    }

    $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
    $providerPaymentId = (string) ($object['id'] ?? '');
    $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
    $paymentId = (int) ($metadata['payment_id'] ?? 0);
    $userId = (int) ($metadata['user_id'] ?? 0);

    if ($paymentId <= 0 || $userId <= 0) {
      return;
    }

    $this->markPaymentSucceeded($paymentId, $userId, $providerPaymentId);
  }

  /** @return array{payment: array<string, mixed>, subscription: array<string, mixed>, activated: bool, providerStatus?: string} */
  public function syncPaymentForUser(int $paymentId, int $userId): array
  {
    $payment = $this->getPayment($paymentId, $userId);
    if ($payment === null) {
      throw new \InvalidArgumentException('Платёж не найден');
    }

    if ((string) $payment['status'] === 'succeeded') {
      return [
        'payment' => $payment,
        'subscription' => $this->subscriptions->getForUser($userId),
        'activated' => false,
      ];
    }

    if ($this->isMockMode()) {
      throw new \RuntimeException('Синхронизация YooKassa недоступна в mock-режиме');
    }

    $providerPaymentId = $payment['providerPaymentId'];
    if ($providerPaymentId === null || $providerPaymentId === '') {
      throw new \RuntimeException('Платёж ещё не создан в YooKassa');
    }

    $providerPayment = $this->fetchYooKassaPayment($providerPaymentId);
    $providerStatus = (string) ($providerPayment['status'] ?? '');

    if ($providerStatus === 'succeeded') {
      $this->markPaymentSucceeded($paymentId, $userId, $providerPaymentId);
      $payment = $this->getPayment($paymentId, $userId);
      if ($payment === null) {
        throw new \RuntimeException('Платёж не найден после активации');
      }

      return [
        'payment' => $payment,
        'subscription' => $this->subscriptions->getForUser($userId),
        'activated' => true,
        'providerStatus' => $providerStatus,
      ];
    }

    return [
      'payment' => $payment,
      'subscription' => $this->subscriptions->getForUser($userId),
      'activated' => false,
      'providerStatus' => $providerStatus,
    ];
  }

  /** @return array{payment: array<string, mixed>, subscription: array<string, mixed>, activated: bool, providerStatus?: string}|null} */
  public function syncRecentPendingPayments(int $userId): ?array
  {
    $stmt = $this->db->prepare(
      'SELECT id FROM payments
       WHERE user_id = :user_id AND status = :status
       ORDER BY id DESC
       LIMIT 5',
    );
    $stmt->execute(['user_id' => $userId, 'status' => 'pending']);
    $rows = $stmt->fetchAll();
    if ($rows === false || $rows === []) {
      return null;
    }

    foreach ($rows as $row) {
      try {
        $result = $this->syncPaymentForUser((int) $row['id'], $userId);
        if ($result['activated'] || ($result['subscription']['isPremium'] ?? false)) {
          return $result;
        }
      } catch (\Throwable) {
        continue;
      }
    }

    $paymentId = (int) $rows[0]['id'];
    $payment = $this->getPayment($paymentId, $userId);
    if ($payment === null) {
      return null;
    }

    return [
      'payment' => $payment,
      'subscription' => $this->subscriptions->getForUser($userId),
      'activated' => false,
    ];
  }

  /** @return array<string, mixed>|null */
  public function getPayment(int $paymentId, int $userId): ?array
  {
    $stmt = $this->db->prepare(
      'SELECT id, user_id, plan, amount, currency, status, provider, provider_payment_id, created_at, paid_at
       FROM payments WHERE id = :id AND user_id = :user_id',
    );
    $stmt->execute(['id' => $paymentId, 'user_id' => $userId]);
    $row = $stmt->fetch();
    if ($row === false) {
      return null;
    }

    return [
      'id' => (int) $row['id'],
      'planId' => (string) $row['plan'],
      'amountRub' => (int) $row['amount'],
      'currency' => (string) $row['currency'],
      'status' => (string) $row['status'],
      'provider' => (string) $row['provider'],
      'providerPaymentId' => $row['provider_payment_id'] !== null ? (string) $row['provider_payment_id'] : null,
      'createdAt' => (string) $row['created_at'],
      'paidAt' => $row['paid_at'] !== null ? (string) $row['paid_at'] : null,
    ];
  }

  /** @param array<string, mixed> $plan */
  private function createYooKassaPayment(
    int $paymentId,
    int $userId,
    array $plan,
    string $idempotenceKey,
    string $returnUrl,
  ): string {
    $shopId = trim(Env::get('YOOKASSA_SHOP_ID', ''));
    $secret = trim(Env::get('YOOKASSA_SECRET_KEY', ''));
    if ($shopId === '' || $secret === '') {
      throw new \RuntimeException('YooKassa не настроена');
    }

    $payload = [
      'amount' => [
        'value' => number_format((int) $plan['priceRub'], 2, '.', ''),
        'currency' => 'RUB',
      ],
      'confirmation' => [
        'type' => 'redirect',
        'return_url' => $returnUrl,
      ],
      'capture' => true,
      'description' => 'Подписка PianoBro: ' . (string) $plan['name'],
      'metadata' => [
        'payment_id' => (string) $paymentId,
        'user_id' => (string) $userId,
        'plan_id' => (string) $plan['id'],
      ],
    ];

    $ch = curl_init('https://api.yookassa.ru/v3/payments');
    if ($ch === false) {
      throw new \RuntimeException('Не удалось инициализировать HTTP-клиент');
    }

    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Idempotence-Key: ' . $idempotenceKey,
      ],
      CURLOPT_USERPWD => $shopId . ':' . $secret,
      CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if (!is_string($response) || $status < 200 || $status >= 300) {
      $detail = is_string($response) ? trim($response) : '';
      if ($detail !== '') {
        $decoded = json_decode($detail, true);
        if (is_array($decoded)) {
          $description = (string) ($decoded['description'] ?? '');
          $code = (string) ($decoded['code'] ?? '');
          $detail = trim($code . ($description !== '' ? ': ' . $description : ''));
        }
      }

      throw new \RuntimeException(
        $detail !== '' ? 'YooKassa: ' . $detail : 'YooKassa вернула ошибку (HTTP ' . $status . ')',
      );
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
      throw new \RuntimeException('Некорректный ответ YooKassa');
    }

    $providerPaymentId = (string) ($data['id'] ?? '');
    $confirmationUrl = (string) ($data['confirmation']['confirmation_url'] ?? '');
    if ($providerPaymentId === '' || $confirmationUrl === '') {
      throw new \RuntimeException('YooKassa не вернула ссылку на оплату');
    }

    $stmt = $this->db->prepare(
      'UPDATE payments SET provider_payment_id = :provider_payment_id WHERE id = :id',
    );
    $stmt->execute(['provider_payment_id' => $providerPaymentId, 'id' => $paymentId]);

    return $confirmationUrl;
  }

  /** @return array<string, mixed> */
  private function fetchYooKassaPayment(string $providerPaymentId): array
  {
    $shopId = trim(Env::get('YOOKASSA_SHOP_ID', ''));
    $secret = trim(Env::get('YOOKASSA_SECRET_KEY', ''));
    if ($shopId === '' || $secret === '') {
      throw new \RuntimeException('YooKassa не настроена');
    }

    $ch = curl_init('https://api.yookassa.ru/v3/payments/' . rawurlencode($providerPaymentId));
    if ($ch === false) {
      throw new \RuntimeException('Не удалось инициализировать HTTP-клиент');
    }

    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_USERPWD => $shopId . ':' . $secret,
      CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if (!is_string($response) || $status < 200 || $status >= 300) {
      throw new \RuntimeException('YooKassa вернула ошибку (HTTP ' . $status . ')');
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
      throw new \RuntimeException('Некорректный ответ YooKassa');
    }

    return $data;
  }

  private function markPaymentSucceeded(int $paymentId, int $userId, string $providerPaymentId): void
  {
    $stmt = $this->db->prepare(
      'SELECT id, user_id, plan, status FROM payments WHERE id = :id',
    );
    $stmt->execute(['id' => $paymentId]);
    $payment = $stmt->fetch();
    if ($payment === false) {
      throw new \InvalidArgumentException('Платёж не найден');
    }

    if ((int) $payment['user_id'] !== $userId) {
      throw new \InvalidArgumentException('Платёж принадлежит другому пользователю');
    }

    if ((string) $payment['status'] === 'succeeded') {
      return;
    }

    $update = $this->db->prepare(
      'UPDATE payments SET status = :status, provider_payment_id = :provider_payment_id, paid_at = datetime(\'now\')
       WHERE id = :id',
    );
    $update->execute([
      'status' => 'succeeded',
      'provider_payment_id' => $providerPaymentId,
      'id' => $paymentId,
    ]);

    $this->subscriptions->activate($userId, (string) $payment['plan'], self::PROVIDER, $providerPaymentId);
  }

  /** @return array{totalBuyers:int,today:int,yesterday:int} */
  public function getPurchaseStats(): array
  {
    $totalBuyers = (int) $this->db->query(
      "SELECT COUNT(DISTINCT user_id) FROM payments WHERE status = 'succeeded'",
    )->fetchColumn();

    $tz = new \DateTimeZone(trim(Env::get('APP_TIMEZONE', 'Europe/Moscow')) ?: 'Europe/Moscow');
    $todayStart = new \DateTimeImmutable('today', $tz);
    $todayEnd = $todayStart->modify('+1 day');
    $yesterdayStart = $todayStart->modify('-1 day');

    return [
      'totalBuyers' => $totalBuyers,
      'today' => $this->countSucceededPaymentsBetween(
        $todayStart->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        $todayEnd->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
      ),
      'yesterday' => $this->countSucceededPaymentsBetween(
        $yesterdayStart->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        $todayStart->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
      ),
    ];
  }

  private function countSucceededPaymentsBetween(string $start, string $end): int
  {
    $stmt = $this->db->prepare(
      "SELECT COUNT(*) FROM payments
       WHERE status = 'succeeded'
         AND COALESCE(paid_at, created_at) >= :start
         AND COALESCE(paid_at, created_at) < :end",
    );
    $stmt->execute(['start' => $start, 'end' => $end]);

    return (int) $stmt->fetchColumn();
  }
}
