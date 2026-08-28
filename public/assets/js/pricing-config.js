/** Client-side mirror of server pricing config (also injected via window.__PRICING__). */

export const PricingConfig = {
  freeDailySessions: 3,
  guestDailySessions: 1,
  diagnosticNoteCount: 15,
  plans: [
    {
      id: 'monthly',
      name: '1 месяц',
      priceRub: 99,
      durationDays: 30,
      description: 'Попробовать персональные тренировки',
      badge: null,
      monthlyEquivalentRub: 99,
      buttonLabel: 'Оформить за 99 ₽',
    },
    {
      id: 'quarterly',
      name: '3 месяца',
      priceRub: 249,
      durationDays: 90,
      description: 'Оптимально, чтобы закрепить чтение нот',
      badge: 'Популярный',
      monthlyEquivalentRub: 83,
      buttonLabel: 'Оформить за 249 ₽',
      featured: true,
    },
    {
      id: 'yearly',
      name: '1 год',
      priceRub: 790,
      durationDays: 365,
      description: 'Для регулярной практики в течение года',
      badge: 'Выгодно',
      monthlyEquivalentRub: 66,
      buttonLabel: 'Оформить за 790 ₽',
    },
  ],
  features: [
    'Персональная программа тренировок',
    'Тренировка слабых нот',
    'Сохранение прогресса',
    'Расширенная статистика',
    'Безлимитные тренировки',
    'Доступ ко всем упражнениям',
  ],
};

export function getPlans(pricing = window.__PRICING__) {
  return pricing?.plans ?? PricingConfig.plans;
}

export function getPlan(planId, pricing = window.__PRICING__) {
  return getPlans(pricing).find((plan) => plan.id === planId) ?? null;
}
