export const ROUTES = {
  home: '/',
  roadmap: '/put-novichka',
  notes: '/noty',
  melodies: '/melodii',
  stats: '/statistika',
  homework: '/domashka',
  teacher: '/teacher',
  practiceNotes: '/trenirovka/noty',
  melody: (id) => `/melodii/${encodeURIComponent(id)}`,
  practiceMelody: (id) => `/trenirovka/melodiya/${encodeURIComponent(id)}`,
};

export const SCREEN_ROUTES = {
  home: ROUTES.home,
  roadmap: ROUTES.roadmap,
  'notes-pick': ROUTES.notes,
  'melody-pick': ROUTES.melodies,
  stats: ROUTES.stats,
  homework: ROUTES.homework,
  teacher: ROUTES.teacher,
};

export function routeForScreen(screen) {
  return SCREEN_ROUTES[screen] ?? ROUTES.home;
}

/** @type {((path: string, options?: { replace?: boolean }) => void) | null} */
let navigateImpl = null;

export function setNavigateImpl(fn) {
  navigateImpl = fn;
}

export function navigateTo(path, options = {}) {
  if (navigateImpl) {
    navigateImpl(path, options);
    return;
  }
  window.location.assign(path);
}

export function navigateToScreen(screen) {
  navigateTo(routeForScreen(screen));
}
