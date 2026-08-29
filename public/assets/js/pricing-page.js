import { getPlan } from './pricing-config.js';
import { trackConversion, refreshBillingState, isPremiumUser, setPendingAuthAction } from './subscription.js';
import { navigateTo } from './routes.js';
import { isLoggedIn } from './auth.js';

let checkoutLoading = false;
let paymentClickBound = false;

async function readJsonResponse(res) {
  const text = await res.text();
  if (!text.trim()) {
    throw new Error('Пустой ответ сервера');
  }
  try {
    return JSON.parse(text);
  } catch {
    throw new Error('Сервер вернул некорректный ответ. Обновите страницу и попробуйте снова.');
  }
}

function showPaymentStatus(message, variant = 'info') {
  const statusEl = document.getElementById('payment-status');
  if (!statusEl) {
    alert(message);
    return;
  }
  statusEl.hidden = false;
  statusEl.textContent = message;
  statusEl.className = `payment-legal__notice payment-legal__notice--${variant}`;
  statusEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function setBuyButtonLoading(btn, loading) {
  if (!btn) return;
  btn.disabled = loading;
  btn.setAttribute('aria-busy', loading ? 'true' : 'false');
  if (loading) {
    btn.dataset.originalLabel = btn.textContent ?? '';
    btn.textContent = 'Подключаем оплату…';
  } else if (btn.dataset.originalLabel) {
    btn.textContent = btn.dataset.originalLabel;
    delete btn.dataset.originalLabel;
  }
}

async function startCheckout(planId, triggerBtn = null) {
  if (checkoutLoading) return;
  if (isPremiumUser()) {
    navigateTo('/payment/success');
    return;
  }

  if (!isLoggedIn()) {
    trackConversion('registration_started', { source: 'payment', tariff: planId });
    setPendingAuthAction({ type: 'checkout', planId });
    sessionStorage.setItem('piano-pending-checkout-plan', planId);
    showPaymentStatus('Для оплаты нужно войти или зарегистрироваться.', 'info');
    window.pianoOpenAuth?.('register');
    return;
  }

  checkoutLoading = true;
  setBuyButtonLoading(triggerBtn, true);
  trackConversion('payment_started', { tariff: planId });

  try {
    const res = await fetch('/api/billing/checkout', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        planId,
        returnUrl: `${window.location.origin}/payment?payment=return&plan=${encodeURIComponent(planId)}`,
      }),
    });

    const data = await readJsonResponse(res);
    if (!res.ok) {
      if (res.status === 401) {
        throw new Error('Требуется вход в аккаунт');
      }
      throw new Error(data.detail || data.error || 'checkout failed');
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
      showPaymentStatus('Переход на страницу оплаты ЮKassa…', 'info');
      window.location.href = data.confirmationUrl;
      return;
    }

    throw new Error('no confirmation url');
  } catch (error) {
    trackConversion('payment_failed', { tariff: planId, message: String(error?.message ?? error) });
    const message = String(error?.message ?? error);
    showPaymentStatus(
      message.includes('Требуется вход')
        ? 'Для оплаты войдите или зарегистрируйтесь, затем нажмите кнопку снова.'
        : `Не удалось начать оплату: ${message}. Напишите на support@pianobro.ru, если ошибка повторится.`,
      'error',
    );
  } finally {
    checkoutLoading = false;
    setBuyButtonLoading(triggerBtn, false);
  }
}

function bindPaymentClickDelegation() {
  if (paymentClickBound) return;
  paymentClickBound = true;
  document.addEventListener('click', (event) => {
    const btn = event.target.closest('.payment-legal__buy[data-plan-id]');
    if (!btn || btn.disabled) return;
    const planId = btn.getAttribute('data-plan-id');
    if (planId) void startCheckout(planId, btn);
  });
}

bindPaymentClickDelegation();

export function initPaymentPage() {
  trackConversion('pricing_view');

  const params = new URLSearchParams(window.location.search);
  if (params.get('payment') === 'return') {
    trackConversion('payment_success', { tariff: params.get('plan') ?? null, source: 'return_url' });
    showPaymentStatus(
      'Если оплата прошла успешно, подписка активируется в течение нескольких минут. Обновите страницу или войдите в аккаунт снова.',
      'success',
    );
  }
}

export async function resumePendingCheckout() {
  const planId = sessionStorage.getItem('piano-pending-checkout-plan');
  if (!planId || !isLoggedIn()) return;
  sessionStorage.removeItem('piano-pending-checkout-plan');
  await startCheckout(planId);
}

export { startCheckout, getPlan, initPaymentPage as initPricingPage };
