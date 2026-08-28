import { trackGoal } from './metrika.js';
import { recordAnalyticsEvent } from './analytics.js';

const GUEST_USAGE_KEY = 'piano-guest-daily-usage';
const DIAGNOSTIC_KEY = 'piano-diagnostic-result';
const PENDING_AUTH_ACTION_KEY = 'piano-pending-auth-action';

/** @type {{ pricing: object, subscription: object, mockMode: boolean, userId: number|null } | null} */
let billingState = null;

export function getBillingState() {
  return billingState;
}

export function getSubscription() {
  return billingState?.subscription ?? { status: 'free', isPremium: false };
}

export function isPremiumUser() {
  return Boolean(getSubscription().isPremium);
}

export function getPricing() {
  return billingState?.pricing ?? window.__PRICING__ ?? {};
}

function todayKey() {
  return new Date().toISOString().slice(0, 10);
}

function readGuestUsage() {
  try {
    const raw = localStorage.getItem(GUEST_USAGE_KEY);
    if (!raw) return { date: todayKey(), count: 0 };
    const data = JSON.parse(raw);
    if (data.date !== todayKey()) return { date: todayKey(), count: 0 };
    return { date: data.date, count: Number(data.count) || 0 };
  } catch {
    return { date: todayKey(), count: 0 };
  }
}

function writeGuestUsage(count) {
  localStorage.setItem(GUEST_USAGE_KEY, JSON.stringify({ date: todayKey(), count }));
}

export async function refreshBillingState() {
  try {
    const res = await fetch('/api/billing/status', { credentials: 'same-origin' });
    if (!res.ok) throw new Error('status failed');
    billingState = await res.json();
    return billingState;
  } catch {
    billingState = {
      pricing: window.__PRICING__ ?? {},
      subscription: { status: 'free', isPremium: false },
      mockMode: true,
      userId: null,
    };
    return billingState;
  }
}

/** @param {boolean} isLoggedIn */
export function canGuestStartTraining(isLoggedIn, type = 'training') {
  if (type === 'diagnostic') return { allowed: true, remaining: null };
  if (isLoggedIn) return null;

  const limit = getPricing().guestDailySessions ?? 1;
  const used = readGuestUsage().count;
  if (used >= limit) {
    return { allowed: false, reason: 'guest_daily_limit', remaining: 0 };
  }
  return { allowed: true, remaining: limit - used };
}

export function recordGuestTrainingSession() {
  const used = readGuestUsage().count;
  writeGuestUsage(used + 1);
}

export async function checkTrainingSession(type = 'training', isLoggedIn = false) {
  if (!isLoggedIn) {
    return canGuestStartTraining(false, type) ?? { allowed: true };
  }

  try {
    const res = await fetch('/api/billing/check-session', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type }),
    });
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      return data;
    }
    return await res.json();
  } catch {
    return { allowed: true };
  }
}

export async function consumeTrainingSession(type = 'training', isLoggedIn = false) {
  if (type === 'diagnostic') return true;

  if (!isLoggedIn) {
    recordGuestTrainingSession();
    return true;
  }

  if (isPremiumUser()) return true;

  try {
    const res = await fetch('/api/billing/use-session', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type }),
    });
    if (!res.ok) return false;
    const data = await res.json();
    if (data.subscription) {
      billingState = { ...(billingState ?? {}), subscription: data.subscription };
    }
    return true;
  } catch {
    return false;
  }
}

export function trackConversion(eventName, params = {}) {
  const payload = {
    ...params,
    timestamp: new Date().toISOString(),
    userId: billingState?.userId ?? null,
    sourcePage: window.location.pathname,
  };

  trackGoal(eventName, payload);
  recordAnalyticsEvent(eventName, payload);
}

export function showPaywall(source = 'unknown') {
  if (isPremiumUser()) return false;

  const modal = document.getElementById('paywall-modal');
  if (!modal) return false;

  modal.hidden = false;
  trackConversion('paywall_shown', { source });
  return true;
}

export function hidePaywall() {
  const modal = document.getElementById('paywall-modal');
  if (modal) modal.hidden = true;
}

export function saveDiagnosticResult(result) {
  localStorage.setItem(DIAGNOSTIC_KEY, JSON.stringify({ ...result, savedAt: new Date().toISOString() }));
}

export function loadDiagnosticResult() {
  try {
    const raw = localStorage.getItem(DIAGNOSTIC_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export async function persistDiagnosticResult(result, isLoggedIn) {
  saveDiagnosticResult(result);
  if (!isLoggedIn) return;
  try {
    await fetch('/api/billing/diagnostic', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(result),
    });
  } catch {
    /* ignore */
  }
}

export function setPendingAuthAction(action) {
  sessionStorage.setItem(PENDING_AUTH_ACTION_KEY, JSON.stringify(action));
}

export function consumePendingAuthAction() {
  try {
    const raw = sessionStorage.getItem(PENDING_AUTH_ACTION_KEY);
    if (!raw) return null;
    sessionStorage.removeItem(PENDING_AUTH_ACTION_KEY);
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function bindPaywallUi() {
  document.getElementById('paywall-close')?.addEventListener('click', hidePaywall);
  document.getElementById('paywall-choose-plan')?.addEventListener('click', () => {
    hidePaywall();
    trackConversion('tariff_selected', { source: 'paywall' });
    window.pianoNavigate?.('/pricing');
  });
}
