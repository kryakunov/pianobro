const ICON_IDS = [
  'piano', 'melody', 'treble', 'bass', 'notes', 'stats', 'sharp', 'flat',
  'session', 'sessions', 'mastered', 'practice', 'learning', 'warning',
  'keyboard', 'chart', 'target', 'search', 'upload', 'user', 'midi', 'mic', 'play',
  'check', 'arrow',
];

/**
 * @param {typeof ICON_IDS[number]} name
 * @param {string} [className]
 */
export function icon(name, className = 'icon') {
  if (!ICON_IDS.includes(name)) return '';
  return `<svg class="${className}" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-${name}"/></svg>`;
}

/**
 * @param {typeof ICON_IDS[number]} name
 * @param {string} [badgeClass]
 */
export function iconBadge(name, badgeClass = 'icon-badge') {
  return `<span class="${badgeClass}">${icon(name, 'icon icon--badge')}</span>`;
}

export function iconBadgeColored(name, tone = 'primary') {
  return `<span class="icon-badge icon-badge--${tone}">${icon(name, 'icon icon--badge')}</span>`;
}
