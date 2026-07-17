export const REQUIRED_STREAK = 2;

/** @param {boolean[]} history */
export function endStreak(history) {
  let streak = 0;
  for (let i = history.length - 1; i >= 0; i -= 1) {
    if (!history[i]) break;
    streak += 1;
  }
  return streak;
}

/** @param {boolean[]} history */
export function isMastered(history) {
  return endStreak(history) >= REQUIRED_STREAK;
}

/** @param {boolean[]} history */
export function summarizeHistory(history) {
  const attempts = history.length;
  let correct = 0;
  for (const hit of history) {
    if (hit) correct += 1;
  }
  const wrong = attempts - correct;
  const accuracy = attempts > 0 ? Math.round((correct / attempts) * 100) : 0;
  return {
    correct,
    wrong,
    attempts,
    accuracy,
    streak: endStreak(history),
  };
}

/** @param {boolean[]} history */
export function masteryLevel(history) {
  if (!history.length) return 'learning';
  if (isMastered(history)) return 'mastered';
  return 'learning';
}

/** @param {boolean[]} history */
export function noteProgressPercent(history) {
  if (!history.length) return 0;
  if (isMastered(history)) return 100;

  const streak = endStreak(history);
  if (streak === 1) return 50;

  const { correct, accuracy } = summarizeHistory(history);
  const correctPart = Math.min(correct / REQUIRED_STREAK, 1) * 50;
  const accuracyPart = Math.min(accuracy / 85, 1) * 50;
  return Math.round(correctPart + accuracyPart);
}

/** @param {unknown} value @returns {boolean[]} */
export function normalizeHistory(value) {
  if (Array.isArray(value)) {
    return value.map(Boolean);
  }
  if (value && typeof value === 'object' && Array.isArray(value.history)) {
    return value.history.map(Boolean);
  }
  if (value && typeof value === 'object' && ('correct' in value || 'wrong' in value)) {
    const correct = Number(value.correct ?? 0);
    const wrong = Number(value.wrong ?? 0);
    const history = [];
    for (let i = 0; i < correct; i += 1) history.push(true);
    for (let i = 0; i < wrong; i += 1) history.push(false);
    return history;
  }
  return [];
}

/** @param {{ history?: boolean[], midi?: number, name?: string }} row */
export function enrichNoteFromHistory(row) {
  const history = row.history ?? [];
  const summary = summarizeHistory(history);
  return {
    ...row,
    ...summary,
    history,
    level: masteryLevel(history),
  };
}
