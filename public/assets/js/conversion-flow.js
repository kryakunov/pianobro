import {
  refreshBillingState,
  checkTrainingSession,
  consumeTrainingSession,
  checkNotesAllowed,
  consumeNoteAttempt,
  showPaywall,
  bindPaywallUi,
  trackConversion,
  persistDiagnosticResult,
  loadDiagnosticResult,
  setPendingAuthAction,
  consumePendingAuthAction,
  isPremiumUser,
  getNotesQuota,
} from './subscription.js';
import {
  analyzeDiagnosticAttempts,
  getDiagnosticSessionLimit,
} from './diagnostic.js';
import { renderTrainingResultWeakNotes } from './training-result-ui.js';
import { initPaymentPage, startCheckout, resumePendingCheckout } from './pricing-page.js';
import { navigateTo, ROUTES } from './routes.js';
import { isLoggedIn } from './auth.js';

let deps = {};
let isDiagnosticSession = false;
let diagnosticPickMode = false;

export function conversionIsDiagnostic() {
  return isDiagnosticSession;
}

export function isDiagnosticPickMode() {
  return diagnosticPickMode;
}

export function clearDiagnosticPickMode() {
  diagnosticPickMode = false;
}

export function openDiagnosticPick() {
  diagnosticPickMode = true;
  navigateTo(ROUTES.notes);
}

export function initConversionFlow(appDeps) {
  deps = appDeps;
  window.pianoNavigate = navigateTo;
  window.pianoOpenAuth = (tab) => deps.openAuthModal?.(tab ?? 'login');

  bindPaywallUi();
  bindUi();
  void refreshBillingState();
}

function bindUi() {
  document.getElementById('btn-start-diagnostic')?.addEventListener('click', () => {
    openDiagnosticPick();
  });

  document.getElementById('diagnostic-close')?.addEventListener('click', hideDiagnosticModal);
  document.getElementById('diagnostic-modal')?.querySelector('[data-close-diagnostic]')
    ?.addEventListener('click', hideDiagnosticModal);

  document.getElementById('diagnostic-open-plan')?.addEventListener('click', () => {
    hideDiagnosticModal();
    void openPersonalPlan();
  });

  document.getElementById('btn-payment-success-start')?.addEventListener('click', () => {
    navigateTo(isPremiumUser() ? '/noty' : '/');
  });

  document.getElementById('btn-back-payment')?.addEventListener('click', (e) => {
    e.preventDefault();
    navigateTo('/');
  });

  document.getElementById('btn-back-offer')?.addEventListener('click', (e) => {
    e.preventDefault();
    navigateTo('/payment');
  });

  document.getElementById('btn-back-personal-plan')?.addEventListener('click', (e) => {
    e.preventDefault();
    navigateTo('/');
  });
}

export async function ensureNotesQuota(minCount = 1) {
  if (isPremiumUser()) return true;

  const check = await checkNotesAllowed(minCount, isLoggedIn());
  if (check?.allowed) return true;

  const quota = getNotesQuota(isLoggedIn());
  showPaywall(check?.reason ?? 'daily_notes_limit', {
    weakNotes: loadDiagnosticResult()?.weakNotes ?? [],
    limit: quota.limit,
    used: quota.used,
    remaining: quota.remaining,
  });
  return false;
}

export async function handleNoteAttemptConsumption() {
  if (isPremiumUser() || conversionIsDiagnostic()) return true;

  const result = await consumeNoteAttempt(1, isLoggedIn());
  if (result.ok) {
    window.pianoUpdateNotesQuota?.();
    if ((result.quota?.remaining ?? 99) <= 5) {
      window.pianoUpdateNotesQuota?.();
    }
    return true;
  }

  const quota = getNotesQuota(isLoggedIn());
  showPaywall(result.check?.reason ?? 'mid_session', {
    weakNotes: loadDiagnosticResult()?.weakNotes ?? [],
    limit: quota.limit,
    used: quota.used,
    remaining: quota.remaining,
  });
  return false;
}

export async function ensureTrainingAllowed(type = 'training') {
  if (type === 'diagnostic') return true;

  await refreshBillingState();
  if (isPremiumUser()) return true;

  const check = await checkTrainingSession(type, isLoggedIn());
  if (check?.allowed) return true;

  showPaywall('daily_limit');
  return false;
}

export async function recordTrainingStart(type = 'training') {
  await consumeTrainingSession(type, isLoggedIn());
  await refreshBillingState();
}

export async function startDiagnostic(settings) {
  trackConversion('diagnostic_started');
  if (!(await ensureTrainingAllowed('diagnostic'))) return;

  isDiagnosticSession = true;
  clearDiagnosticPickMode();
  const { noteTrainer, enterPractice } = deps;

  noteTrainer.setConfig(structuredClone(settings));
  noteTrainer.sessionLimit = getDiagnosticSessionLimit();
  noteTrainer.setOptions({ soundEnabled: true });

  enterPractice('notes', 'Бесплатная диагностика', { returnPath: ROUTES.home });
}

export function hideDiagnosticModal() {
  const modal = document.getElementById('diagnostic-modal');
  if (modal) modal.hidden = true;
}

function renderDiagnosticWeakNotes(weakNotes = []) {
  renderTrainingResultWeakNotes({
    title: document.getElementById('diagnostic-weak-title'),
    hint: document.getElementById('diagnostic-weak-hint'),
    tags: document.getElementById('diagnostic-weak-tags'),
  }, weakNotes);
}

export function showDiagnosticResult(result) {
  const modal = document.getElementById('diagnostic-modal');
  const correctEl = document.getElementById('diagnostic-correct');
  const wrongEl = document.getElementById('diagnostic-wrong');
  const accuracyEl = document.getElementById('diagnostic-accuracy');

  if (!modal || !correctEl || !wrongEl || !accuracyEl) return;

  const wrong = Math.max(0, result.total - result.correct);
  correctEl.textContent = String(result.correct);
  wrongEl.textContent = String(wrong);
  accuracyEl.textContent = `${result.accuracy}%`;

  renderDiagnosticWeakNotes(result.weakNotes ?? []);

  modal.hidden = false;
  trackConversion('diagnostic_completed', {
    diagnosticScore: result.accuracy,
    correct: result.correct,
    total: result.total,
  });
}

export async function handleDiagnosticComplete() {
  isDiagnosticSession = false;
  const attempts = deps.noteTrainer?.sessionAttempts ?? [];
  const result = analyzeDiagnosticAttempts(attempts);
  await persistDiagnosticResult(result, isLoggedIn());
  deps.hideSessionModal?.();
  showDiagnosticResult(result);
  if (!isPremiumUser()) {
    showPaywall('diagnostic_complete', { weakNotes: result.weakNotes ?? [] });
  }
}

export async function openPersonalPlan() {
  if (!isLoggedIn()) {
    setPendingAuthAction({ type: 'personal_plan' });
    trackConversion('registration_started', { source: 'personal_plan' });
    deps.openAuthModal?.('register');
    return;
  }

  if (!isPremiumUser()) {
    showPaywall('personal_plan', { weakNotes: loadDiagnosticResult()?.weakNotes ?? [] });
    return;
  }

  deps.showScreen?.('personal-plan');
  await renderPersonalPlan();
}

async function renderPersonalPlan() {
  const panel = document.getElementById('personal-plan-panel');
  if (!panel) return;

  panel.innerHTML = '<p class="personal-plan__loading">Загрузка…</p>';

  const diagnostic = loadDiagnosticResult();
  let weakNotes = diagnostic?.weakNotes ?? [];

  if (isLoggedIn()) {
    try {
      const stats = await deps.loadNoteStats?.();
      const fromStats = extractWeakNotesFromStats(stats);
      if (fromStats.length) weakNotes = fromStats;
    } catch {
      /* use diagnostic */
    }
  }

  if (!weakNotes.length) {
    panel.innerHTML = `
      <p class="personal-plan__empty">Пока недостаточно данных. Пройдите диагностику или несколько тренировок.</p>
      <button type="button" class="btn btn--primary" id="personal-plan-start-diagnostic">Пройти диагностику</button>
    `;
    document.getElementById('personal-plan-start-diagnostic')?.addEventListener('click', () => {
      openDiagnosticPick();
    });
    return;
  }

  const tags = weakNotes.map((n) => `<span class="weak-notes-offer__tag">${n.name}</span>`).join('');
  panel.innerHTML = `
    <div class="weak-notes-offer">
      <div class="weak-notes-offer__content">
        <div class="weak-notes-offer__text">
          <strong>Слабые ноты</strong>
          <p>PianoBro будет чаще показывать эти ноты в тренировках:</p>
        </div>
        <div class="weak-notes-offer__tags">${tags}</div>
      </div>
    </div>
    <button type="button" class="btn btn--primary" id="personal-plan-train">Тренировать слабые ноты</button>
  `;

  document.getElementById('personal-plan-train')?.addEventListener('click', async () => {
    const midis = weakNotes.map((n) => n.midi);
    deps.noteTrainer?.setCustomPool(midis);
    deps.noteTrainer.sessionLimit = Math.min(20, midis.length * 3);
    if (!(await ensureNotesQuota(1))) return;
    sessionStorage.setItem('piano-pending-notes-practice', JSON.stringify({
      settings: deps.noteTrainer.settings,
      options: { soundEnabled: true },
      sessionLimit: deps.noteTrainer.sessionLimit,
      returnPath: '/personal-plan',
    }));
    navigateTo(ROUTES.practiceNotes);
  });
}

function extractWeakNotesFromStats(stats) {
  if (!stats?.notes?.length) return [];
  return stats.notes
    .filter((n) => (n.wrong ?? 0) > (n.correct ?? 0) || (n.accuracy ?? 100) < 70)
    .sort((a, b) => (a.accuracy ?? 0) - (b.accuracy ?? 0))
    .slice(0, 5)
    .map((n) => ({ midi: n.midi, name: n.name, count: n.wrong ?? 0 }));
}

export function bootConversionScreen(screen) {
  if (screen === 'payment') {
    deps.showScreen?.('payment');
    initPaymentPage();
    return true;
  }
  if (screen === 'offer') {
    deps.showScreen?.('offer');
    return true;
  }
  if (screen === 'payment-success') {
    deps.showScreen?.('payment-success');
    return true;
  }
  if (screen === 'personal-plan') {
    void openPersonalPlan();
    return true;
  }
  return false;
}

export async function afterAuthConversionHooks() {
  await refreshBillingState();
  trackConversion('registration_completed');

  const pending = consumePendingAuthAction();
  if (pending?.type === 'checkout' && pending.planId) {
    await startCheckout(pending.planId);
    return;
  }
  if (pending?.type === 'personal_plan') {
    await openPersonalPlan();
    return;
  }

  await resumePendingCheckout();
}

export async function bootPracticeGate(homework, isDiagnostic = false) {
  if (homework || isDiagnostic) return true;
  if (!(await ensureNotesQuota(1))) {
    navigateTo(ROUTES.notes);
    return false;
  }
  return true;
}

export async function getWeakNotesForPersonalization(loadNoteStats) {
  const diagnostic = loadDiagnosticResult();
  let weakNotes = diagnostic?.weakNotes ?? [];

  if (isLoggedIn() && loadNoteStats) {
    try {
      const stats = await loadNoteStats();
      const fromStats = extractWeakNotesFromStats(stats);
      if (fromStats.length) weakNotes = fromStats;
    } catch {
      /* use diagnostic */
    }
  }

  return weakNotes;
}

export async function gateNotesTrainingStart(onAllowed) {
  if (!(await ensureNotesQuota(1))) return false;
  onAllowed?.();
  return true;
}
