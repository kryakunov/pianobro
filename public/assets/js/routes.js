export const ROUTES = {
  home: '/',
  roadmap: '/put-novichka',
  notes: '/noty',
  melodies: '/melodii',
  stats: '/statistika',
  homework: '/domashka',
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
};

export function routeForScreen(screen) {
  return SCREEN_ROUTES[screen] ?? ROUTES.home;
}

export function navigateToScreen(screen) {
  window.location.assign(routeForScreen(screen));
}

export function navigateTo(path) {
  window.location.assign(path);
}
