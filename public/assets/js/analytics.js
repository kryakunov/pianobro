const SESSION_KEY = 'piano-analytics-session';
const SEARCH_REFERRAL_KEY = 'piano-analytics-search-recorded';
const FLUSH_INTERVAL_MS = 5000;
const MIN_PAGE_TIME_MS = 1000;

/** @type {Array<{pattern: RegExp, source: string, param: string}>} */
const SEARCH_ENGINES = [
  { pattern: /(^|\.)google\./i, source: 'google', param: 'q' },
  { pattern: /(^|\.)yandex\./i, source: 'yandex', param: 'text' },
  { pattern: /(^|\.)bing\./i, source: 'bing', param: 'q' },
  { pattern: /(^|\.)duckduckgo\./i, source: 'duckduckgo', param: 'q' },
  { pattern: /(^|\.)mail\.ru$/i, source: 'mail', param: 'q' },
  { pattern: /go\.mail\.ru/i, source: 'mail', param: 'q' },
  { pattern: /(^|\.)yahoo\./i, source: 'yahoo', param: 'p' },
  { pattern: /(^|\.)rambler\./i, source: 'rambler', param: 'query' },
];

/** @type {Array<Record<string, unknown>>} */
const queue = [];

let pageEnteredAt = Date.now();
let currentPath = window.location.pathname;
let flushTimer = null;
let initialized = false;
let pageTimeSent = false;

function getSessionId() {
  try {
    let id = sessionStorage.getItem(SESSION_KEY);
    if (!id) {
      id = (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`);
      sessionStorage.setItem(SESSION_KEY, id);
    }
    return id;
  } catch {
    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  }
}

function enqueue(event) {
  queue.push({
    ...event,
    path: event.path ?? currentPath,
    at: new Date().toISOString(),
  });

  if (queue.length >= 10) {
    void flush(false);
  }
}

async function flush(useBeacon = false) {
  if (!queue.length) {
    return;
  }

  const events = queue.splice(0, queue.length);
  const payload = JSON.stringify({
    sessionId: getSessionId(),
    events,
  });

  if (useBeacon && navigator.sendBeacon) {
    navigator.sendBeacon(
      '/api/analytics/events',
      new Blob([payload], { type: 'application/json' }),
    );
    return;
  }

  try {
    await fetch('/api/analytics/events', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: payload,
      keepalive: true,
    });
  } catch {
    queue.unshift(...events);
  }
}

function decodeSearchQuery(value) {
  try {
    return decodeURIComponent(String(value).replace(/\+/g, ' ')).trim();
  } catch {
    return String(value).trim();
  }
}

function detectSearchReferral() {
  try {
    if (sessionStorage.getItem(SEARCH_REFERRAL_KEY) === '1') {
      return null;
    }
  } catch {
    // continue
  }

  const landingParams = new URLSearchParams(window.location.search);
  const utmTerm = landingParams.get('utm_term');
  if (utmTerm) {
    const query = decodeSearchQuery(utmTerm);
    if (query) {
      return { source: 'utm', query };
    }
  }

  const referrer = document.referrer;
  if (!referrer) {
    return null;
  }

  let refUrl;
  try {
    refUrl = new URL(referrer);
  } catch {
    return null;
  }

  if (refUrl.hostname === window.location.hostname) {
    return null;
  }

  for (const engine of SEARCH_ENGINES) {
    if (!engine.pattern.test(refUrl.hostname)) {
      continue;
    }

    const raw = refUrl.searchParams.get(engine.param);
    if (!raw) {
      continue;
    }

    const query = decodeSearchQuery(raw);
    if (query) {
      return { source: engine.source, query };
    }
  }

  return null;
}

function recordSearchReferral() {
  const referral = detectSearchReferral();
  if (!referral) {
    return;
  }

  enqueue({
    type: 'search_referral',
    target: `${referral.source}:${referral.query}`,
  });

  try {
    sessionStorage.setItem(SEARCH_REFERRAL_KEY, '1');
  } catch {
    // ignore
  }
}

function recordPageTime(force = false) {
  if (pageTimeSent && !force) {
    return;
  }

  const durationMs = Date.now() - pageEnteredAt;
  if (durationMs >= MIN_PAGE_TIME_MS) {
    enqueue({ type: 'page_time', durationMs });
    pageTimeSent = true;
  }
}

function onPageHide() {
  if (!pageTimeSent) {
    recordPageTime(true);
  }
  flush(true);
}

function onVisibilityChange() {
  if (document.visibilityState === 'hidden') {
    recordPageTime();
    flush(true);
    pageEnteredAt = Date.now();
    return;
  }

  if (document.visibilityState === 'visible') {
    pageTimeSent = false;
    pageEnteredAt = Date.now();
  }
}

export function initAnalytics() {
  if (initialized) {
    return;
  }
  initialized = true;

  pageEnteredAt = Date.now();
  currentPath = window.location.pathname;

  recordSearchReferral();

  window.addEventListener('pagehide', onPageHide);
  document.addEventListener('visibilitychange', onVisibilityChange);

  flushTimer = window.setInterval(() => {
    void flush(false);
  }, FLUSH_INTERVAL_MS);
}

export function trackPageView(path = window.location.pathname) {
  if (!pageTimeSent) {
    recordPageTime(true);
  }
  void flush(false);

  currentPath = path;
  pageEnteredAt = Date.now();
  pageTimeSent = false;
}

export function destroyAnalytics() {
  if (!initialized) {
    return;
  }

  window.removeEventListener('pagehide', onPageHide);
  document.removeEventListener('visibilitychange', onVisibilityChange);

  if (flushTimer !== null) {
    window.clearInterval(flushTimer);
    flushTimer = null;
  }

  initialized = false;
}
