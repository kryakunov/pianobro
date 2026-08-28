import { getPlans, getPlan } from './pricing-config.js';
import { trackConversion, refreshBillingState, isPremiumUser, setPendingAuthAction } from './subscription.js';
import { navigateTo } from './routes.js';
import { isLoggedIn } from './auth.js';

let checkoutLoading = false;

function renderFeatureList(container, features) {
  if (!container) return;
  container.innerHTML = features.map((item) => `<li>${item}</li>`).join('');
}

function renderPlans(container, pricing, { onSelect } = {}) {
  if (!container) return;

  const plans = getPlans(pricing);
  container.innerHTML = plans.map((plan) => {
    const featured = plan.featured ? ' pricing-card--featured' : '';
    const badge = plan.badge
      ? `<span class="pricing-card__badge">${plan.badge}</span>`
      : '';
    const monthly = plan.id === 'yearly'
      ? `<p class="pricing-card__note">≈${plan.monthlyEquivalentRub} ₽ в месяц</p>`
      : '';

    return `
      <article class="pricing-card${featured}" data-plan-id="${plan.id}">
        ${badge}
        <h3 class="pricing-card__title">${plan.name}</h3>
        <p class="pricing-card__price">${plan.priceRub}&nbsp;₽</p>
        <p class="pricing-card__desc">${plan.description}</p>
        ${monthly}
        <button type="button" class="btn ${plan.featured ? 'btn--primary' : 'btn--secondary'} pricing-card__btn" data-plan-select="${plan.id}">
          ${plan.buttonLabel}
        </button>
      </article>
    `;
  }).join('');

  container.querySelectorAll('[data-plan-select]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const planId = btn.getAttribute('data-plan-select');
      if (planId) onSelect?.(planId);
    });
  });
}

async function startCheckout(planId) {
  if (checkoutLoading) return;
  if (isPremiumUser()) {
    navigateTo('/payment/success');
    return;
  }

  if (!isLoggedIn()) {
    trackConversion('registration_started', { source: 'pricing', tariff: planId });
    setPendingAuthAction({ type: 'checkout', planId });
    window.pianoOpenAuth?.('register');
    return;
  }

  checkoutLoading = true;
  trackConversion('payment_started', { tariff: planId });

  try {
    const res = await fetch('/api/billing/checkout', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        planId,
        returnUrl: `${window.location.origin}/pricing?payment=return&plan=${encodeURIComponent(planId)}`,
      }),
    });

    const data = await res.json();
    if (!res.ok) {
      throw new Error(data.error || 'checkout failed');
    }

    trackConversion('tariff_selected', { tariff: planId });

    if (data.mock && data.confirmationUrl) {
      const url = new URL(data.confirmationUrl, window.location.origin);
      const paymentId = url.searchParams.get('paymentId');
      if (paymentId) {
        const completeRes = await fetch('/api/billing/mock-complete', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ paymentId: Number(paymentId) }),
        });
        if (!completeRes.ok) {
          throw new Error('mock complete failed');
        }
        await refreshBillingState();
        trackConversion('payment_success', { tariff: planId, mock: true });
        trackConversion('subscription_activated', { tariff: planId, mock: true });
        navigateTo('/payment/success');
        return;
      }
    }

    if (data.confirmationUrl) {
      window.location.href = data.confirmationUrl;
      return;
    }

    throw new Error('no confirmation url');
  } catch (error) {
    trackConversion('payment_failed', { tariff: planId, message: String(error?.message ?? error) });
    alert('Не удалось начать оплату. Попробуйте ещё раз.');
  } finally {
    checkoutLoading = false;
  }
}

export function initPricingPage() {
  const plansEl = document.getElementById('pricing-plans');
  const featuresEl = document.getElementById('pricing-features');
  const pricing = window.__PRICING__ ?? {};

  trackConversion('pricing_view');
  renderFeatureList(featuresEl, pricing.features ?? []);
  renderPlans(plansEl, pricing, { onSelect: startCheckout });

  const params = new URLSearchParams(window.location.search);
  if (params.get('payment') === 'return') {
    trackConversion('payment_success', { tariff: params.get('plan') ?? null, source: 'return_url' });
  }
}

export { startCheckout, getPlan };
