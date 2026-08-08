function getMetrikaId() {
  return Number(window.__METRIKA_ID__) || 0;
}

function canUseMetrika() {
  return getMetrikaId() > 0 && typeof window.ym === 'function';
}

function absoluteUrl(path) {
  if (/^https?:\/\//i.test(path)) return path;
  return new URL(path, window.location.origin).href;
}

export function trackPageView(url = window.location.href, title = document.title, options = {}) {
  const id = getMetrikaId();
  if (!id || typeof window.ym !== 'function') return;

  window.ym(id, 'hit', absoluteUrl(url), {
    title,
    ...options,
  });
}

export function trackGoal(name, params = {}) {
  const id = getMetrikaId();
  if (!id || typeof window.ym !== 'function' || !name) return;
  window.ym(id, 'reachGoal', name, params);
}

export function initMetrikaPageview() {
  const id = getMetrikaId();
  if (!id) return;

  const boot = window.__BOOT__ ?? {};
  const send = () => {
    trackPageView(window.location.href, document.title, {
      params: {
        screen: boot.screen ?? '',
        mode: boot.mode ?? '',
      },
    });
  };

  document.addEventListener(`yacounter${id}inited`, send, { once: true });
}

export function trackPracticePageView(mode, title, lessonId = null) {
  const path = mode === 'melody' && lessonId
    ? `/trenirovka/melodiya/${encodeURIComponent(lessonId)}`
    : '/trenirovka/noty';

  if (window.location.pathname === path) return;

  trackPageView(path, title, {
    params: {
      screen: 'practice',
      mode,
      virtual: true,
    },
  });
}

export function hasMetrika() {
  return canUseMetrika();
}
