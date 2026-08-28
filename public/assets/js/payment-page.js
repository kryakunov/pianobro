import { isLoggedIn } from './auth.js';

let checkoutLoading = false;

async function startCheckout(planId) {
  if (checkoutLoading) return;

  if (!isLoggedIn()) {
    window.pianoOpenAuth?.('register');
    sessionStorage.setItem('piano-pending-checkout-plan', planId);
    return;
  }

  checkoutLoading = true;
  const statusEl = document.getElementById('payment-status');

  try {
    const returnUrl = `${window.location.origin}/payment?payment=return&plan=${encodeURIComponent(planId)}`;
    const res = await fetch('/api/billing/checkout', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ planId, returnUrl }),
    });
    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'checkout failed');
    }

    if (data.mock && data.confirmationUrl) {
      const url = new URL(data.confirmationUrl, window.location.origin);
      const paymentId = url.searchParams.get('paymentId');
      const completeRes = await fetch('/api/billing/mock-complete', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ paymentId: Number(paymentId) }),
      });
      if (!completeRes.ok) throw new Error('mock complete failed');
      if (statusEl) {
        statusEl.hidden = false;
        statusEl.textContent = 'Подписка активирована. Можно начать тренировку.';
        statusEl.className = 'payment-legal__notice payment-legal__notice--success';
      }
      return;
    }

    if (data.confirmationUrl) {
      window.location.href = data.confirmationUrl;
      return;
    }

    throw new Error('no confirmation url');
  } catch (error) {
    if (statusEl) {
      statusEl.hidden = false;
      statusEl.textContent = 'Не удалось начать оплату. Попробуйте позже или напишите в поддержку.';
      statusEl.className = 'payment-legal__notice payment-legal__notice--error';
    }
    console.error(error);
  } finally {
    checkoutLoading = false;
  }
}

export function initPaymentPage() {
  document.querySelectorAll('.payment-legal__buy[data-plan-id]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const planId = btn.getAttribute('data-plan-id');
      if (planId) void startCheckout(planId);
    });
  });

  const params = new URLSearchParams(window.location.search);
  const statusEl = document.getElementById('payment-status');
  if (params.get('payment') === 'return' && statusEl) {
    statusEl.hidden = false;
    statusEl.textContent = 'Если оплата прошла успешно, подписка активируется в течение нескольких минут. Обновите страницу или войдите в аккаунт.';
    statusEl.className = 'payment-legal__notice payment-legal__notice--success';
  }
}

export async function resumePendingCheckout() {
  const planId = sessionStorage.getItem('piano-pending-checkout-plan');
  if (!planId || !isLoggedIn()) return;
  sessionStorage.removeItem('piano-pending-checkout-plan');
  await startCheckout(planId);
}
