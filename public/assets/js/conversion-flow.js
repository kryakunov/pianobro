import {
  refreshBillingState,
  checkTrainingSession,
  consumeTrainingSession,
  showPaywall,
  bindPaywallUi,
  trackConversion,
  persistDiagnosticResult,
  loadDiagnosticResult,
  setPendingAuthAction,
  consumePendingAuthAction,
  isPremiumUser,
} from './subscription.js';
import {
  DIAGNOSTIC_SETTINGS,
  analyzeDiagnosticAttempts,
  formatWeakNotesList,
  getDiagnosticSessionLimit,
} from './diagnostic.js';
import { initPricingPage, startCheckout } from './pricing-page.js';
import { navigateTo, ROUTES } from './routes.js';
import { isLoggedIn } from './auth.js';

let deps = {};
let isDiagnosticSession = false;

export function conversionIsDiagnostic() {
  return isDiagnosticSession;
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
    void startDiagnostic();
  });

  document.getElementById('btn-show-weak-notes')?.addEventListener('click', () => {
    const saved = loadDiagnosticResult();
    if (saved) {
      showDiagnosticResult(saved);
      return;
    }
    if (isLoggedIn()) {
      void openPersonalPlan();
      return;
    }
    void startDiagnostic();
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

  document.getElementById('btn-back-pricing')?.addEventListener('click', (e) => {
    e.preventDefault();
    navigateTo('/');
  });

  document.getElementById('btn-back-personal-plan')?.addEventListener('click', (e) => {
    e.preventDefault();
    navigateTo('/');
  });
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

export async function startDiagnostic() {
  trackConversion('diagnostic_started');
  if (!(await ensureTrainingAllowed('diagnostic'))) return;

  isDiagnosticSession = true;
  const { noteTrainer, enterPractice } = deps;

  noteTrainer.setConfig(structuredClone(DIAGNOSTIC_SETTINGS));
  noteTrainer.sessionLimit = getDiagnosticSessionLimit();
  noteTrainer.setOptions({ soundEnabled: true });

  enterPractice('notes', 'Бесплатная диагностика', { returnPath: ROUTES.home });
}

export function hideDiagnosticModal() {
  const modal = document.getElementById('diagnostic-modal');
  if (modal) modal.hidden = true;
}

export function showDiagnosticResult(result) {
  const modal = document.getElementById('diagnostic-modal');
  const summary = document.getElementById('diagnostic-summary');
  const weak = document.getElementById('diagnostic-weak');
  const offer = document.getElementById('diagnostic-offer');

  if (!modal || !summary || !weak || !offer) return;

  summary.textContent = `Вы ответили правильно на ${result.correct} из ${result.total} заданий (${result.accuracy}% точности).`;
  weak.textContent = `PianoBro заметил, что сложнее всего вам даются: ${formatWeakNotesList(result.weakNotes)}.`;
  offer.textContent = 'Сервис может составить для вас персональную программу тренировок и чаще повторять именно те ноты, которые пока путаются.';

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
    showPaywall('diagnostic_complete');
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
    showPaywall('personal_plan');
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
      void startDiagnostic();
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
    if (!(await ensureTrainingAllowed('training'))) return;
    await recordTrainingStart('training');
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
  if (screen === 'pricing') {
    deps.showScreen?.('pricing');
    initPricingPage();
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
  if (!pending) return;

  if (pending.type === 'checkout' && pending.planId) {
    await startCheckout(pending.planId);
    return;
  }
  if (pending.type === 'personal_plan') {
    await openPersonalPlan();
  }
}

export async function bootPracticeGate(homework, isDiagnostic = false) {
  if (homework || isDiagnostic) return true;
  if (!(await ensureTrainingAllowed('training'))) {
    navigateTo(ROUTES.notes);
    return false;
  }
  await recordTrainingStart('training');
  return true;
}

export async function gateNotesTrainingStart(onAllowed) {
  if (!(await ensureTrainingAllowed('training'))) return false;
  onAllowed?.();
  return true;
}
