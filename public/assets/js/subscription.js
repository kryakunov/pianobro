import { trackGoal } from './metrika.js';
import { recordAnalyticsEvent } from './analytics.js';
import { getPlan } from './pricing-config.js';

const GUEST_USAGE_KEY = 'piano-guest-daily-usage';
const GUEST_NOTES_KEY = 'piano-guest-daily-notes';
const DIAGNOSTIC_KEY = 'piano-diagnostic-result';
const PENDING_AUTH_ACTION_KEY = 'piano-pending-auth-action';

const PAYWALL_COPY = {
  daily_limit: {
    title: 'Лимит тренировок на сегодня',
    message: 'Бесплатно доступно несколько тренировок в день. С подпиской PianoBro занимайтесь без ограничений и получайте персональные упражнения.',
  },
  daily_notes_limit: {
    title: 'Вы активно тренируетесь!',
    message: 'Бесплатный лимит нот на сегодня исчерпан. С подпиской PianoBro — безлимитные занятия и тренировки по вашим слабым нотам.',
  },
  diagnostic_complete: {
    title: 'PianoBro знает ваши слабые ноты',
    message: 'Подписка откроет персональные тренировки: сервис будет чаще повторять ноты, которые вы путаете.',
  },
  personal_plan: {
    title: 'Персональный план — для подписчиков',
    message: 'PianoBro составит программу под ваши ошибки и будет чаще показывать сложные ноты.',
  },
  stats_learning: {
    title: 'Тренировка слабых нот',
    message: 'Персональные тренировки по вашим ошибкам доступны в подписке PianoBro.',
  },
  session_complete: {
    title: 'Отличный темп!',
    message: 'Продолжайте прогресс с персональными тренировками — PianoBro запомнит ошибки и подстроит занятия под вас.',
  },
  mid_session: {
    title: 'Лимит бесплатных нот',
    message: 'Вы уже хорошо потренировались сегодня. Подписка снимает дневной лимит и включает персональный алгоритм.',
  },
  default: {
    title: 'Откройте персональные тренировки',
    message: 'PianoBro будет запоминать ваши ошибки, чаще повторять сложные ноты и показывать прогресс, чтобы вы быстрее читали ноты на пианино.',
  },
};

/** @type {{ pricing: object, subscription: object, mockMode: boolean, userId: number|null } | null} */
let billingState = null;

function seedBillingStateFromBoot() {
  const user = window.__USER__;
  if (!user?.subscription) return;
  billingState = {
    pricing: window.__PRICING__ ?? {},
    subscription: user.subscription,
    mockMode: Boolean(window.__BILLING_BOOT__?.mockMode ?? true),
    userId: user.id ?? null,
  };
}

seedBillingStateFromBoot();

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

function readGuestBucket(key, countField) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return { date: todayKey(), count: 0 };
    const data = JSON.parse(raw);
    if (data.date !== todayKey()) return { date: todayKey(), count: 0 };
    return { date: data.date, count: Number(data[countField] ?? data.count) || 0 };
  } catch {
    return { date: todayKey(), count: 0 };
  }
}

function writeGuestBucket(key, countField, count) {
  localStorage.setItem(key, JSON.stringify({ date: todayKey(), [countField]: count }));
}

function readGuestUsage() {
  return readGuestBucket(GUEST_USAGE_KEY, 'count');
}

function writeGuestUsage(count) {
  writeGuestBucket(GUEST_USAGE_KEY, 'count', count);
}

function readGuestNotesUsage() {
  return readGuestBucket(GUEST_NOTES_KEY, 'notes');
}

function writeGuestNotesUsage(count) {
  writeGuestBucket(GUEST_NOTES_KEY, 'notes', count);
}

export function getNotesQuota(isLoggedIn = false) {
  if (isPremiumUser()) {
    return { limit: null, used: 0, remaining: null, isPremium: true };
  }

  const pricing = getPricing();
  if (isLoggedIn) {
    const sub = getSubscription();
    return {
      limit: sub.dailyNotesLimit ?? pricing.freeDailyNotes ?? 80,
      used: sub.dailyNotesUsed ?? 0,
      remaining: sub.dailyNotesRemaining ?? null,
      isPremium: false,
    };
  }

  const limit = pricing.guestDailyNotes ?? 25;
  const used = readGuestNotesUsage().count;
  return {
    limit,
    used,
    remaining: Math.max(0, limit - used),
    isPremium: false,
  };
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
      mockMode: Boolean(window.__BILLING_BOOT__?.mockMode ?? true),
      userId: window.__USER__?.id ?? null,
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

export function checkGuestNotesAllowed(count = 1) {
  const pricing = getPricing();
  const limit = pricing.guestDailyNotes ?? 25;
  const used = readGuestNotesUsage().count;
  const remaining = Math.max(0, limit - used);
  if (used + count > limit) {
    return {
      allowed: false,
      reason: 'daily_notes_limit',
      remaining,
      limit,
      used,
      isPremium: false,
    };
  }
  return {
    allowed: true,
    remaining: remaining - count,
    limit,
    used,
    isPremium: false,
  };
}

export function recordGuestNoteAttempt(count = 1) {
  const used = readGuestNotesUsage().count;
  writeGuestNotesUsage(used + count);
  const quota = getNotesQuota(false);
  return { ...quota, used: used + count, remaining: Math.max(0, (quota.limit ?? 0) - used - count) };
}

export async function checkNotesAllowed(count = 1, isLoggedIn = false) {
  if (isPremiumUser()) {
    return { allowed: true, isPremium: true, remaining: null };
  }

  if (!isLoggedIn) {
    return checkGuestNotesAllowed(count);
  }

  try {
    const res = await fetch('/api/billing/check-notes', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ count }),
    });
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      return data.check ?? data;
    }
    return await res.json();
  } catch {
    return { allowed: true };
  }
}

export async function consumeNoteAttempt(count = 1, isLoggedIn = false) {
  if (isPremiumUser()) return { ok: true };

  if (!isLoggedIn) {
    const check = checkGuestNotesAllowed(count);
    if (!check.allowed) return { ok: false, check };
    recordGuestNoteAttempt(count);
    return { ok: true, quota: getNotesQuota(false) };
  }

  try {
    const res = await fetch('/api/billing/use-notes', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ count }),
    });
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      return { ok: false, check: data.check ?? data };
    }
    const data = await res.json();
    if (data.subscription) {
      billingState = { ...(billingState ?? {}), subscription: data.subscription };
    }
    return { ok: true, quota: getNotesQuota(true) };
  } catch {
    return { ok: true };
  }
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

function renderPaywallWeakNotes(weakNotes = []) {
  const block = document.getElementById('paywall-weak');
  const tags = document.getElementById('paywall-weak-tags');
  if (!block || !tags) return;

  if (!weakNotes?.length) {
    block.hidden = true;
    tags.innerHTML = '';
    return;
  }

  block.hidden = false;
  tags.innerHTML = weakNotes
    .slice(0, 6)
    .map((note) => `<span class="paywall-weak__tag">${note.name ?? note}</span>`)
    .join('');
}

function renderPaywallQuota(context = {}) {
  const el = document.getElementById('paywall-quota');
  if (!el) return;

  const { limit, used, remaining } = context;
  if (limit == null || used == null) {
    el.hidden = true;
    el.textContent = '';
    return;
  }

  el.hidden = false;
  el.textContent = remaining != null && remaining <= 5
    ? `Сегодня использовано ${used} из ${limit} бесплатных нот.`
    : `Бесплатно сегодня: ${used} из ${limit} нот.`;
}

export function showPaywall(source = 'unknown', context = {}) {
  if (isPremiumUser()) return false;

  const modal = document.getElementById('paywall-modal');
  if (!modal) return false;

  const copy = PAYWALL_COPY[source] ?? PAYWALL_COPY.default;
  const titleEl = document.getElementById('paywall-title');
  const messageEl = document.getElementById('paywall-message');
  if (titleEl) titleEl.textContent = context.title ?? copy.title;
  if (messageEl) messageEl.textContent = context.message ?? copy.message;

  renderPaywallWeakNotes(context.weakNotes ?? []);
  renderPaywallQuota(context);

  modal.hidden = false;
  trackConversion('paywall_shown', { source, ...context });
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
  document.getElementById('paywall-modal')?.querySelector('[data-close-paywall]')
    ?.addEventListener('click', hidePaywall);
  document.getElementById('paywall-choose-plan')?.addEventListener('click', () => {
    hidePaywall();
    trackConversion('tariff_selected', { source: 'paywall' });
    window.pianoNavigate?.('/payment');
  });
}

export function formatNotesQuotaLabel(quota) {
  if (quota.isPremium || quota.limit == null) return '';
  const remaining = quota.remaining ?? Math.max(0, quota.limit - (quota.used ?? 0));
  if (remaining <= 5) {
    return `Осталось ${remaining} бесплатных нот сегодня`;
  }
  return `Бесплатно сегодня: ${quota.used ?? 0} / ${quota.limit} нот`;
}

export function formatSubscriptionExpiryDate(value) {
  if (!value) return null;
  try {
    const normalized = String(value).includes('T') ? String(value) : `${String(value).replace(' ', 'T')}Z`;
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return null;
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
  } catch {
    return null;
  }
}

/** @returns {{ tier: 'premium'|'free'|'expired', title: string, badgeText: string, badgeClass: string, meta: string, detail: string, showUpgradeCta: boolean } | null} */
export function getSubscriptionDisplay(isLoggedIn = false) {
  if (!isLoggedIn) return null;

  const sub = getSubscription();
  const pricing = getPricing();

  if (sub.isPremium) {
    const plan = getPlan(sub.plan, pricing);
    const planLabel = plan?.shortName ?? 'Premium';
    const expiresLabel = formatSubscriptionExpiryDate(sub.expiresAt);
    return {
      tier: 'premium',
      title: plan?.name ?? 'Подписка PianoBro',
      badgeText: planLabel,
      badgeClass: 'subscription-badge--premium',
      meta: expiresLabel ? `до ${expiresLabel}` : 'Активна',
      detail: expiresLabel
        ? `Подписка активна до ${expiresLabel}. Безлимитные тренировки и персональные задания по вашим ошибкам.`
        : 'Подписка активна. Безлимитные тренировки и персональные задания по вашим ошибкам.',
      showUpgradeCta: false,
    };
  }

  if (sub.status === 'expired') {
    return {
      tier: 'expired',
      title: 'Подписка истекла',
      badgeText: 'Истекла',
      badgeClass: 'subscription-badge--expired',
      meta: 'Продлите доступ',
      detail: 'Срок подписки закончился. Оформите тариф снова, чтобы вернуть безлимит и персональные тренировки.',
      showUpgradeCta: true,
    };
  }

  const quotaLabel = formatNotesQuotaLabel(getNotesQuota(true));
  const dailyLimit = sub.dailyNotesLimit ?? pricing.freeDailyNotes ?? 80;

  return {
    tier: 'free',
    title: 'Бесплатный тариф',
    badgeText: 'Бесплатный',
    badgeClass: 'subscription-badge--free',
    meta: quotaLabel || `${dailyLimit} нот в день`,
    detail: quotaLabel
      ? `${quotaLabel}. Подписка снимает лимит и добавляет персональные тренировки по вашим ошибкам.`
      : `Доступно ${dailyLimit} нот в день. Подписка снимает лимит и добавляет персональные тренировки.`,
    showUpgradeCta: true,
  };
}
