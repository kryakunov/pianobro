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
      return [
        'paymentId' => $paymentId,
        'planId' => $planId,
        'amountRub' => $amount,
        'confirmationUrl' => rtrim($returnUrl, '/') . '?payment=mock&paymentId=' . $paymentId,
        'mock' => true,
      ];
    }

    return [
      'paymentId' => $paymentId,
      'planId' => $planId,
      'amountRub' => $amount,
      'confirmationUrl' => $this->createYooKassaPayment($paymentId, $userId, $plan, $idempotenceKey, $returnUrl),
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
    if ((string) ($payload['event'] ?? '') !== 'payment.succeeded') {
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
      'description' => (string) $plan['name'],
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
    curl_close($ch);

    if (!is_string($response) || $status < 200 || $status >= 300) {
      throw new \RuntimeException('YooKassa вернула ошибку');
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

  private function markPaymentSucceeded(int $paymentId, int $userId, string $providerPaymentId): void
  {
    $stmt = $this->db->prepare('SELECT id, user_id, plan, status FROM payments WHERE id = :id');
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
}
