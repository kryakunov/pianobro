/** @type {list<{minXp:number,title:string,emoji:string}>} */
export const DEFAULT_RANKS = [
  { minXp: 0, title: 'Новичок', emoji: '🌱' },
  { minXp: 100, title: 'Ученик', emoji: '📖' },
  { minXp: 350, title: 'Пианист', emoji: '🎹' },
  { minXp: 650, title: 'Виртуоз', emoji: '⭐' },
  { minXp: 900, title: 'Мастер нот', emoji: '🏆' },
];

export const AVATAR_FALLBACK_EMOJIS = ['🎹', '🎵', '🎼', '🎶', '🎻', '🎺', '🪗', '🥁'];

export function hashUserId(userId) {
  const s = String(userId ?? 0);
  let h = 2166136261;
  for (let i = 0; i < s.length; i += 1) {
    h ^= s.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

/** @param {list<{minXp?:number}>|null|undefined} ranks */
export function resolveRankIndex(totalXp, ranks = DEFAULT_RANKS) {
  let index = 0;
  (ranks ?? DEFAULT_RANKS).forEach((rank, i) => {
    if (totalXp >= (rank.minXp ?? 0)) {
      index = i;
    }
  });
  return index;
}

/** @param {list<{minXp?:number,title?:string,emoji?:string}>|null|undefined} ranks */
export function resolveRank(totalXp, ranks = DEFAULT_RANKS) {
  const list = ranks ?? DEFAULT_RANKS;
  let current = list[0] ?? DEFAULT_RANKS[0];
  list.forEach((rank) => {
    if (totalXp >= (rank.minXp ?? 0)) {
      current = rank;
    }
  });
  return current;
}

/**
 * @param {{ userId?: number|null, totalXp?: number, rank?: {title?:string,emoji?:string}|null, ranks?: list<{minXp?:number,title?:string,emoji?:string}>|null, isPremium?: boolean }} input
 */
export function buildHeaderAvatarState({
  userId = 0,
  totalXp = 0,
  rank = null,
  ranks = null,
  isPremium = false,
} = {}) {
  const rankList = ranks ?? DEFAULT_RANKS;
  const rankIndex = resolveRankIndex(totalXp, rankList);
  const resolvedRank = rank ?? resolveRank(totalXp, rankList);
  const emoji = resolvedRank?.emoji
    || AVATAR_FALLBACK_EMOJIS[hashUserId(userId) % AVATAR_FALLBACK_EMOJIS.length];
  const rankTitle = resolvedRank?.title ?? 'Новичок';
  const hue = hashUserId(userId) % 360;

  const classNames = ['auth-user__avatar', `auth-user__avatar--rank-${rankIndex}`];
  if (isPremium) {
    classNames.push('auth-user__avatar--premium');
  }

  return {
    emoji,
    rankTitle,
    className: classNames.join(' '),
    style: `--avatar-hue: ${hue}`,
    title: isPremium ? `${rankTitle} · Premium` : rankTitle,
  };
}

/** @param {HTMLElement|null|undefined} element */
export function applyHeaderAvatar(element, state) {
  if (!element || !state) {
    return;
  }
  element.className = state.className;
  element.style.cssText = state.style ?? '';
  element.textContent = state.emoji;
  element.title = state.title;
}
