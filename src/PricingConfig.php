<?php

declare(strict_types=1);

namespace PianoTrainer;

final class PricingConfig
{
  public const FREE_DAILY_SESSIONS = 3;
  public const GUEST_DAILY_SESSIONS = 1;
  public const DIAGNOSTIC_NOTE_COUNT = 15;

  /** @return list<array<string, mixed>> */
  public static function plans(): array
  {
    return [
      [
        'id' => 'monthly',
        'name' => '1 месяц',
        'priceRub' => 99,
        'durationDays' => 30,
        'description' => 'Попробовать персональные тренировки',
        'badge' => null,
        'monthlyEquivalentRub' => 99,
        'buttonLabel' => 'Оформить за 99 ₽',
      ],
      [
        'id' => 'quarterly',
        'name' => '3 месяца',
        'priceRub' => 249,
        'durationDays' => 90,
        'description' => 'Оптимально, чтобы закрепить чтение нот',
        'badge' => 'Популярный',
        'monthlyEquivalentRub' => 83,
        'buttonLabel' => 'Оформить за 249 ₽',
        'featured' => true,
      ],
      [
        'id' => 'yearly',
        'name' => '1 год',
        'priceRub' => 790,
        'durationDays' => 365,
        'description' => 'Для регулярной практики в течение года',
        'badge' => 'Выгодно',
        'monthlyEquivalentRub' => 66,
        'buttonLabel' => 'Оформить за 790 ₽',
      ],
    ];
  }

  /** @return array<string, mixed>|null */
  public static function plan(string $planId): ?array
  {
    foreach (self::plans() as $plan) {
      if (($plan['id'] ?? '') === $planId) {
        return $plan;
      }
    }

    return null;
  }

  /** @return list<string> */
  public static function subscriptionFeatures(): array
  {
    return [
      'Персональная программа тренировок',
      'Тренировка слабых нот',
      'Сохранение прогресса',
      'Расширенная статистика',
      'Безлимитные тренировки',
      'Доступ ко всем упражнениям',
    ];
  }

  /** @return array<string, mixed> */
  public static function toPublicArray(): array
  {
    return [
      'plans' => self::plans(),
      'features' => self::subscriptionFeatures(),
      'freeDailySessions' => self::FREE_DAILY_SESSIONS,
      'guestDailySessions' => self::GUEST_DAILY_SESSIONS,
      'diagnosticNoteCount' => self::DIAGNOSTIC_NOTE_COUNT,
    ];
  }
}
