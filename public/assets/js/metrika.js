import { ROUTES, routeForScreen } from './routes.js';

const SCREEN_TITLES = {
  home: 'Главная',
  roadmap: 'Путь новичка',
  'notes-pick': 'Тренажёр нот',
  'rhythm-pick': 'Ритм-игра',
  'melody-pick': 'Каталог мелодий',
  stats: 'Статистика',
  homework: 'Домашка',
  teacher: 'Кабинет преподавателя',
  practice: 'Тренировка',
};

const PRACTICE_DEFAULT_TITLES = {
  notes: 'Тренировка нот',
  rhythm: 'Ритм-игра',
  melody: 'Тренировка мелодии',
};

let lastVirtualHitKey = '';

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

function practicePath(mode, lessonId = null) {
  if (mode === 'melody' && lessonId) {
    return ROUTES.practiceMelody(lessonId);
  }
  if (mode === 'rhythm') {
    return ROUTES.practiceRhythm;
  }
  return ROUTES.practiceNotes;
}

/**
 * Virtual pageview for SPA screen changes (ym hit).
 * @param {string} screen — internal screen id (home, notes-pick, practice, …)
 * @param {{ mode?: string, title?: string, lessonId?: string|null, path?: string }} [options]
 */
export function trackVirtualScreen(screen, options = {}) {
  const { mode = '', title: titleOverride, lessonId = null, path: pathOverride } = options;

  let path = pathOverride;
  if (!path) {
    if (screen === 'practice' && mode) {
      path = practicePath(mode, lessonId);
    } else {
      path = routeForScreen(screen);
    }
  }

  let title = titleOverride;
  if (!title) {
    if (screen === 'practice' && mode) {
      title = PRACTICE_DEFAULT_TITLES[mode] ?? SCREEN_TITLES.practice;
    } else {
      title = SCREEN_TITLES[screen] ?? document.title;
    }
  }

  const hitKey = `${path}|${title}`;
  if (hitKey === lastVirtualHitKey) return;
  lastVirtualHitKey = hitKey;

  trackPageView(path, title, {
    params: {
      screen,
      virtual: true,
      ...(mode ? { mode } : {}),
    },
  });
}

export function trackPracticePageView(mode, title, lessonId = null) {
  trackVirtualScreen('practice', { mode, title, lessonId });
}

export function initMetrikaPageview() {
  const id = getMetrikaId();
  if (!id) return;

  const boot = window.__BOOT__ ?? {};
  const send = () => {
    const screen = boot.screen ?? 'home';
    const mode = boot.mode ?? '';

    if (screen === 'practice' && mode) {
      trackVirtualScreen('practice', {
        mode,
        title: document.title,
        lessonId: boot.lessonId ?? null,
      });
      return;
    }

    trackVirtualScreen(screen, { title: document.title });
  };

  document.addEventListener(`yacounter${id}inited`, send, { once: true });
}

export function hasMetrika() {
  return canUseMetrika();
}

if (typeof window !== 'undefined') {
  window.pianoTrackScreen = trackVirtualScreen;
  window.pianoTrackGoal = trackGoal;
}
