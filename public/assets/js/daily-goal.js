export const DAILY_GOAL_TARGETS = [10, 20, 30, 50];
export const DEFAULT_DAILY_GOAL_TARGET = 20;

const PROGRESS_KEY = 'piano-daily-goal';
const TARGET_KEY = 'piano-daily-goal-target';

function todayKey() {
  return new Date().toISOString().slice(0, 10);
}

export function getDailyGoalTarget() {
  const value = parseInt(localStorage.getItem(TARGET_KEY) ?? String(DEFAULT_DAILY_GOAL_TARGET), 10);
  return DAILY_GOAL_TARGETS.includes(value) ? value : DEFAULT_DAILY_GOAL_TARGET;
}

export function setDailyGoalTarget(target) {
  const value = DAILY_GOAL_TARGETS.includes(target) ? target : DEFAULT_DAILY_GOAL_TARGET;
  localStorage.setItem(TARGET_KEY, String(value));
  return value;
}

export function getLocalDailyGoalProgress() {
  try {
    const raw = localStorage.getItem(PROGRESS_KEY);
    const data = raw ? JSON.parse(raw) : null;
    if (!data || data.date !== todayKey()) {
      return { date: todayKey(), completed: 0 };
    }
    return { date: data.date, completed: Number(data.completed) || 0 };
  } catch {
    return { date: todayKey(), completed: 0 };
  }
}

export function addLocalDailyGoalProgress(correctCount) {
  const increment = Math.max(0, Number(correctCount) || 0);
  if (!increment) return getLocalDailyGoalProgress();

  const current = getLocalDailyGoalProgress();
  const next = {
    date: todayKey(),
    completed: current.completed + increment,
  };
  localStorage.setItem(PROGRESS_KEY, JSON.stringify(next));
  return next;
}

export function resolveDailyGoal({ serverDailyGoal = null, localFallback = null } = {}) {
  const target = getDailyGoalTarget();
  const serverDate = serverDailyGoal?.date ?? null;
  const serverCompleted = Number(serverDailyGoal?.completed ?? NaN);

  if (serverDate === todayKey() && Number.isFinite(serverCompleted)) {
    return {
      date: serverDate,
      target,
      completed: Math.max(0, serverCompleted),
    };
  }

  const local = localFallback ?? getLocalDailyGoalProgress();
  return {
    date: local.date,
    target,
    completed: Math.max(0, local.completed),
  };
}
