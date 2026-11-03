/** Client-side mirror of server pricing config (also injected via window.__PRICING__). */

export const PricingConfig = {
  freeDailySessions: 3,
  guestDailySessions: 1,
  freeDailyNotes: 80,
  guestDailyNotes: 25,
  freeMaxSessionNotes: 10,
  diagnosticNoteCount: 15,
  plans: [
    {
      id: 'monthly',
      name: 'Подписка PianoBro — 1 месяц',
      shortName: '1 месяц',
      priceRub: 99,
      durationDays: 30,
      description: 'Доступ к персональным тренировкам нот, расширенной статистике и безлимитным занятиям на 30 дней.',
      badge: null,
      monthlyEquivalentRub: 99,
      buttonLabel: 'Оплатить 99 ₽',
    },
    {
      id: 'quarterly',
      name: 'Подписка PianoBro — 3 месяца',
      shortName: '3 месяца',
      priceRub: 249,
      durationDays: 90,
      description: 'Доступ к персональным тренировкам нот, расширенной статистике и безлимитным занятиям на 90 дней.',
      badge: 'Популярный',
      monthlyEquivalentRub: 83,
      buttonLabel: 'Оплатить 249 ₽',
      featured: true,
    },
    {
      id: 'yearly',
      name: 'Подписка PianoBro — 1 год',
      shortName: '1 год',
      priceRub: 790,
      durationDays: 365,
      description: 'Доступ к персональным тренировкам нот, расширенной статистике и безлимитным занятиям на 365 дней.',
      badge: 'Выгодно',
      monthlyEquivalentRub: 66,
      buttonLabel: 'Оплатить 790 ₽',
    },
  ],
  features: [
    'Персональная программа тренировок нот',
    'Тренировка слабых нот по вашей статистике',
    'Сохранение прогресса в аккаунте',
    'Расширенная статистика по каждой ноте',
    'Безлимитные тренировки на период подписки',
    'Доступ ко всем упражнениям тренажёра',
  ],
};

export function getPlans(pricing = window.__PRICING__) {
  return pricing?.plans ?? PricingConfig.plans;
}

export function getPlan(planId, pricing = window.__PRICING__) {
  return getPlans(pricing).find((plan) => plan.id === planId) ?? null;
}
