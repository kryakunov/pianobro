export const ROUTES = {
  home: '/',
  roadmap: '/put-novichka',
  notes: '/noty',
  rhythm: '/ritm',
  melodies: '/melodii',
  blog: '/blog',
  payment: '/payment',
  offer: '/oferta',
  paymentSuccess: '/payment/success',
  personalPlan: '/personal-plan',
  stats: '/statistika',
  homework: '/domashka',
  teacher: '/teacher',
  practiceNotes: '/trenirovka/noty',
  practiceRhythm: '/trenirovka/ritm',
  melody: (id) => `/melodii/${encodeURIComponent(id)}`,
  practiceMelody: (id) => `/trenirovka/melodiya/${encodeURIComponent(id)}`,
  blogArticle: (slug) => `/blog/${encodeURIComponent(slug)}`,
};

export const SCREEN_ROUTES = {
  home: ROUTES.home,
  roadmap: ROUTES.roadmap,
  'notes-pick': ROUTES.notes,
  'rhythm-pick': ROUTES.rhythm,
  'melody-pick': ROUTES.melodies,
  blog: ROUTES.blog,
  'blog-article': ROUTES.blog,
  payment: ROUTES.payment,
  offer: ROUTES.offer,
  'payment-success': ROUTES.paymentSuccess,
  'personal-plan': ROUTES.personalPlan,
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
