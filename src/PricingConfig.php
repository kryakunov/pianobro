<?php

declare(strict_types=1);

namespace PianoTrainer;

final class PricingConfig
{
  public const FREE_DAILY_SESSIONS = 3;

  /** @return array{inn:string,name:string,email:string,site:string} */
  public static function seller(): array
  {
    return [
      'inn' => trim(Env::get('SELLER_INN', '662844166191')),
      'name' => trim(Env::get('SELLER_NAME', 'Самозанятый Крякунов Андрей Сергеевич')),
      'email' => trim(Env::get('SELLER_EMAIL', 'support@pianobro.ru')),
      'site' => trim(Env::get('APP_URL', 'https://pianobro.ru')),
    ];
  }

  /** @return list<array<string, mixed>> */
  public static function plans(): array
  {
    return [
      [
        'id' => 'monthly',
        'name' => 'Подписка PianoBro — 1 месяц',
        'shortName' => '1 месяц',
        'priceRub' => 99,
        'durationDays' => 30,
        'description' => 'Доступ к персональным тренировкам нот, расширенной статистике и безлимитным занятиям на 30 дней.',
        'badge' => null,
        'monthlyEquivalentRub' => 99,
        'buttonLabel' => 'Оплатить 99 ₽',
      ],
      [
        'id' => 'quarterly',
        'name' => 'Подписка PianoBro — 3 месяца',
        'shortName' => '3 месяца',
        'priceRub' => 249,
        'durationDays' => 90,
        'description' => 'Доступ к персональным тренировкам нот, расширенной статистике и безлимитным занятиям на 90 дней.',
        'badge' => 'Популярный',
        'monthlyEquivalentRub' => 83,
        'buttonLabel' => 'Оплатить 249 ₽',
        'featured' => true,
      ],
      [
        'id' => 'yearly',
        'name' => 'Подписка PianoBro — 1 год',
        'shortName' => '1 год',
        'priceRub' => 790,
        'durationDays' => 365,
        'description' => 'Доступ к персональным тренировкам нот, расширенной статистике и безлимитным занятиям на 365 дней.',
        'badge' => 'Выгодно',
        'monthlyEquivalentRub' => 66,
        'buttonLabel' => 'Оплатить 790 ₽',
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
      'Персональная программа тренировок нот',
      'Тренировка слабых нот по вашей статистике',
      'Сохранение прогресса в аккаунте',
      'Расширенная статистика по каждой ноте',
      'Безлимитные тренировки на период подписки',
      'Доступ ко всем упражнениям тренажёра',
    ];
  }

  /** @return array<string, mixed> */
  public static function toPublicArray(): array
  {
    return [
      'plans' => self::plans(),
      'features' => self::subscriptionFeatures(),
      'seller' => self::seller(),
      'freeDailySessions' => self::FREE_DAILY_SESSIONS,
    ];
  }
}
