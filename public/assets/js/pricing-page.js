import { getPlan } from './pricing-config.js';
import { trackConversion, refreshBillingState, isPremiumUser, setPendingAuthAction, getSubscription, formatSubscriptionExpiryDate } from './subscription.js';
import { trackGoal } from './metrika.js';
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

function alreadySubscribedMessage() {
  const expires = formatSubscriptionExpiryDate(getSubscription().expiresAt);
  return expires
    ? `У вас уже есть активная подписка (до ${expires}).`
    : 'У вас уже есть активная подписка.';
}

function notifyAlreadySubscribed() {
  showPaymentStatus(alreadySubscribedMessage(), 'info');
}

function setPaymentBuyButtonsDisabled(disabled) {
  document.querySelectorAll('.payment-legal__buy[data-plan-id]').forEach((btn) => {
    btn.disabled = disabled;
  });
}

function guardActiveSubscription() {
  if (!isPremiumUser()) return false;
  notifyAlreadySubscribed();
  setPaymentBuyButtonsDisabled(true);
  return true;
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

const PENDING_PAYMENT_KEY = 'piano-pending-payment-id';

async function syncPaymentAfterReturn(paymentId = null) {
  const res = await fetch('/api/billing/sync-payment', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(paymentId ? { paymentId } : {}),
  });
  return readJsonResponse(res);
}

async function handlePaymentReturn(planId) {
  showPaymentStatus('Проверяем статус оплаты…', 'info');

  const storedId = sessionStorage.getItem(PENDING_PAYMENT_KEY);
  sessionStorage.removeItem(PENDING_PAYMENT_KEY);
  const paymentId = storedId ? Number(storedId) : null;

  for (let attempt = 0; attempt < 8; attempt += 1) {
    try {
      const data = await syncPaymentAfterReturn(paymentId && Number.isFinite(paymentId) ? paymentId : null);
      if (data.subscription?.isPremium) {
        await refreshBillingState();
        window.pianoUpdateSubscription?.();
        trackConversion('payment_success', { tariff: planId, source: 'return_sync' });
        trackConversion('subscription_activated', { tariff: planId });
        navigateTo('/payment/success');
        return;
      }
    } catch {
      // retry below
    }

    if (attempt < 7) {
      await new Promise((resolve) => window.setTimeout(resolve, 1500));
    }
  }

  await refreshBillingState();
  window.pianoUpdateSubscription?.();
  showPaymentStatus(
    'Оплата обрабатывается. Если тариф не обновится в течение нескольких минут, обновите страницу или напишите на support@pianobro.ru.',
    'info',
  );
}

async function startCheckout(planId, triggerBtn = null) {
  if (checkoutLoading) return;
  if (isPremiumUser()) {
    notifyAlreadySubscribed();
    setPaymentBuyButtonsDisabled(true);
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
        window.pianoUpdateSubscription?.();
        trackConversion('payment_success', { tariff: planId, mock: true });
        trackConversion('subscription_activated', { tariff: planId, mock: true });
        navigateTo('/payment/success');
        return;
      }
    }

    if (data.confirmationUrl) {
      if (data.paymentId) {
        sessionStorage.setItem(PENDING_PAYMENT_KEY, String(data.paymentId));
      }
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
    if (planId) {
      trackGoal('payment_click', { tariff: planId });
      void startCheckout(planId, btn);
    }
  });
}

bindPaymentClickDelegation();

export function initPaymentPage() {
  trackConversion('pricing_view');

  if (guardActiveSubscription()) {
    return;
  }

  setPaymentBuyButtonsDisabled(false);

  const params = new URLSearchParams(window.location.search);
  if (params.get('payment') === 'return') {
    void handlePaymentReturn(params.get('plan'));
  }
}

export async function resumePendingCheckout() {
  const planId = sessionStorage.getItem('piano-pending-checkout-plan');
  if (!planId || !isLoggedIn()) return;
  sessionStorage.removeItem('piano-pending-checkout-plan');
  if (isPremiumUser()) {
    notifyAlreadySubscribed();
    setPaymentBuyButtonsDisabled(true);
    return;
  }
  await startCheckout(planId);
}

export { startCheckout, getPlan, initPaymentPage as initPricingPage };
