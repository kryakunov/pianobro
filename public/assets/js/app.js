import { PianoKeyboard } from './piano.js';
import { MidiInput } from './midi.js';
import { MicPitchInput } from './mic-pitch.js';
import { MelodyTrainer } from './trainer.js';
import { NoteTrainer, DEFAULT_NOTE_SETTINGS, DEFAULT_NOTE_SESSION_LIMIT, DEFAULT_TRAINER_OPTIONS, NOTE_SESSION_LIMITS, buildPoolFromSettings, describeNoteSettings, usesBothClefs } from './note-trainer.js';
import { StaffView } from './staff.js';
import { normalizeLesson } from './lesson-utils.js';
import { midiToLesson } from './midi-import.js';
import { KEYBOARD_MAP, midiToName, PIANO_START, PIANO_END } from './notes.js';
import { initAuth, getUser, isLoggedIn, login, register, logout, saveSessionStats, loadNoteStats } from './auth.js';
import { icon, iconBadgeColored } from './icons.js';
import {
  addLocalDailyGoalProgress,
  getDailyGoalTarget,
  resolveDailyGoal,
  setDailyGoalTarget,
  DAILY_GOAL_TARGETS,
  DEFAULT_DAILY_GOAL_TARGET,
} from './daily-goal.js';
import { playTrainerNote, warmupTrainerSound } from './trainer-sounds.js';

const SESSION_LIMIT = 10;
const TRAINER_PREFS_KEY = 'piano-trainer-prefs';

const $ = (sel) => document.querySelector(sel);

const els = {
  app: $('#app'),
  mainHeader: $('#main-header'),
  screenHome: $('#screen-home'),
  screenMelodyPick: $('#screen-melody-pick'),
  screenNotesPick: $('#screen-notes-pick'),
  screenStats: $('#screen-stats'),
  screenPractice: $('#screen-practice'),
  btnGoMelodies: $('#btn-go-melodies'),
  btnGoNotes: $('#btn-go-notes'),
  btnGoStatsHome: $('#btn-go-stats-home'),
  btnGoStats: $('#btn-go-stats'),
  btnBackStats: $('#btn-back-stats'),
  statsPanel: $('#stats-panel'),
  authPanel: $('#auth-panel'),
  btnOpenAuth: $('#btn-open-auth'),
  authUser: $('#auth-user'),
  authUserName: $('#auth-user-name'),
  btnLogout: $('#btn-logout'),
  authModal: $('#auth-modal'),
  authTabs: document.querySelectorAll('[data-auth-tab]'),
  authFormLogin: $('#auth-form-login'),
  authFormRegister: $('#auth-form-register'),
  authErrorLogin: $('#auth-error-login'),
  authErrorRegister: $('#auth-error-register'),
  btnBackMelody: $('#btn-back-melody'),
  btnBackNotes: $('#btn-back-notes'),
  btnBackPractice: $('#btn-back-practice'),
  lessonList: $('#lesson-list'),
  melodySearch: $('#melody-search'),
  midiUpload: $('#midi-upload'),
  btnMidiUpload: $('#btn-midi-upload'),
  difficultyTabs: document.querySelectorAll('.difficulty-tab'),
  notesSettingsForm: $('#notes-settings-form'),
  weakNotesOffer: $('#weak-notes-offer'),
  notesSettingsError: $('#notes-settings-error'),
  dailyGoalPanel: $('#daily-goal-panel'),
  dailyGoalText: $('#daily-goal-text'),
  dailyGoalPercent: $('#daily-goal-percent'),
  dailyGoalRing: $('#daily-goal-ring'),
  practiceTitle: $('#practice-title'),
  practiceModeBadge: $('#practice-mode-badge'),
  practiceDailyGoal: $('#practice-daily-goal'),
  practiceDailyGoalText: $('#practice-daily-goal-text'),
  practiceProgress: $('#practice-progress'),
  practiceSessionProgress: $('#practice-session-progress'),
  practiceSessionProgressFill: $('#practice-session-progress-fill'),
  practiceInputStatus: $('#practice-input-status'),
  practiceInputDot: $('#practice-input-dot'),
  practiceInputStatusText: $('#practice-input-status-text'),
  btnPracticeConnectMidi: $('#btn-practice-connect-midi'),
  practiceFeedback: $('#practice-feedback'),
  practiceControls: $('#practice-controls'),
  staffViewport: $('#staff-viewport'),
  practiceLayout: document.querySelector('.practice-layout'),
  practiceKeyboardArea: $('#practice-keyboard-area'),
  keyboardHintsPanel: $('#keyboard-hints-panel'),
  keyboardVisibilityPanel: $('#keyboard-visibility-panel'),
  soundModePanel: $('#sound-mode-panel'),
  soundToggleTabs: document.querySelectorAll('#sound-mode-panel [data-sound]'),
  keyboardToggleTabs: document.querySelectorAll('#keyboard-visibility-panel [data-keyboard]'),
  pianoWrap: $('#piano-wrap'),
  keyboardHintTabs: document.querySelectorAll('#keyboard-hints-panel [data-hints]'),
  piano: $('#piano'),
  btnConnectMidi: $('#btn-connect-midi'),
  btnConnectMic: $('#btn-connect-mic'),
  midiStatusText: $('#midi-status-text'),
  midiLiveNote: $('#midi-live-note'),
  midiDeviceSelect: $('#midi-device-select'),
  midiTranspose: $('#midi-transpose'),
  midiTransposeWrap: $('#midi-transpose-wrap'),
  midiDot: document.querySelector('.midi-dot'),
  sessionModal: $('#session-modal'),
  modalCorrect: $('#modal-correct'),
  modalWrong: $('#modal-wrong'),
  modalAccuracy: $('#modal-accuracy'),
  modalSubtitle: $('#modal-subtitle'),
  btnModalRetry: $('#btn-modal-retry'),
  btnModalPick: $('#btn-modal-pick'),
  btnModalHome: $('#btn-modal-home'),
};

let currentScreen = 'home';
let appMode = 'melody';
let selectedLessonId = null;
let selectedImportedId = null;
let noteSettings = structuredClone(DEFAULT_NOTE_SETTINGS);
let currentPracticeTitle = '';
let lessons = [];
let remoteSearchResults = [];
let remoteSearchDone = false;
let searchQuery = '';
let searchRequestId = 0;
let selectedDifficultyFilter = 'all';
let lastSessionStats = null;
let cachedNoteStats = null;
let dailyGoalState = {
  date: new Date().toISOString().slice(0, 10),
  target: DEFAULT_DAILY_GOAL_TARGET,
  completed: 0,
};

const piano = new PianoKeyboard(els.piano, PIANO_START, PIANO_END);
const midi = new MidiInput();
const micPitch = new MicPitchInput();
const melodyTrainer = new MelodyTrainer(piano);
const noteTrainer = new NoteTrainer(piano);
const staffView = new StaffView(els.staffViewport);

function showScreen(name) {
  currentScreen = name;
  const screens = {
    home: els.screenHome,
    'melody-pick': els.screenMelodyPick,
    'notes-pick': els.screenNotesPick,
    stats: els.screenStats,
    practice: els.screenPractice,
  };

  for (const [key, el] of Object.entries(screens)) {
    if (!el) continue;
    const active = key === name;
    el.hidden = !active;
    el.classList.toggle('screen--active', active);
  }

  const isPractice = name === 'practice';
  els.app.classList.toggle('app--practice', isPractice);
  els.mainHeader.hidden = isPractice;
  document.body.classList.toggle('body--practice', isPractice);

  const midiPanel = $('#midi-panel');
  if (midiPanel) midiPanel.hidden = name === 'home';

  if (name === 'notes-pick') {
    refreshWeakNotesOffer();
  }
}

function setMidiStatus(state, text) {
  els.midiDot.className = 'midi-dot midi-dot--' + state;
  els.midiStatusText.textContent = text;
}

function setMicStatus(active, text) {
  if (active) {
    els.btnConnectMic.textContent = 'Микрофон вкл';
    els.btnConnectMic.classList.add('btn--primary');
    els.btnConnectMic.classList.remove('btn--secondary');
  } else {
    els.btnConnectMic.textContent = 'Микрофон';
    els.btnConnectMic.classList.remove('btn--primary');
    els.btnConnectMic.classList.add('btn--secondary');
  }
  if (text) els.midiStatusText.textContent = text;
}

function showFeedback(text, type) {
  els.practiceFeedback.textContent = text;
  els.practiceFeedback.className = 'practice-feedback';
  els.practiceFeedback.classList.toggle('practice-feedback--empty', !text);
  if (type === 'correct') els.practiceFeedback.classList.add('practice-feedback--correct');
  else if (type === 'wrong') els.practiceFeedback.classList.add('practice-feedback--wrong');
  else if (type === 'complete') els.practiceFeedback.classList.add('practice-feedback--complete');
  else if (type === 'info') els.practiceFeedback.classList.add('practice-feedback--info');
}

function updatePracticeInputStatus() {
  if (!els.practiceInputStatus) return;

  if (midi.isConnected || micPitch.isActive) {
    els.practiceInputStatus.hidden = true;
    return;
  }

  els.practiceInputStatus.hidden = false;
  els.practiceInputStatus.className = 'practice-input-status practice-input-status--off';
  els.practiceInputStatusText.textContent = prefersTouchInput()
    ? 'Пианино не подключено — нажимайте клавиши на экране'
    : 'Пианино не подключено — нажимайте клавиши на экране, клавиатуре ПК (A–L) или подключите MIDI';
  if (els.btnPracticeConnectMidi) {
    els.btnPracticeConnectMidi.hidden = !midi.isSupported;
  }
}

async function connectMidiDevice() {
  try {
    const name = await midi.connect();
    setMidiStatus('on', `Подключено: ${name}`);
    els.btnConnectMidi.textContent = 'Переподключить';
    els.midiTransposeWrap.hidden = false;
    renderMidiDevices(midi.listInputs());
    updatePracticeInputStatus();
    return name;
  } catch (e) {
    setMidiStatus('error', e.message);
    updatePracticeInputStatus();
    throw e;
  }
}

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    credentials: 'same-origin',
    ...options,
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

function updatePracticeProgress(state) {
  let current;
  let total;

  if (appMode === 'melody') {
    current = Math.min(state.index, state.total);
    total = state.total;
    els.practiceProgress.textContent = `${current} / ${total}`;
  } else {
    current = noteTrainer.examMode
      ? (state.answeredCount ?? state.correct + state.wrong)
      : state.correct;
    total = state.sessionLimit;
    els.practiceProgress.textContent = noteTrainer.examMode
      ? `Вопрос ${Math.min(current, total)} / ${total}`
      : `${current} / ${total}`;
  }

  const pct = total > 0 ? Math.min(100, (current / total) * 100) : 0;
  if (els.practiceSessionProgressFill) {
    els.practiceSessionProgressFill.style.width = `${pct}%`;
  }
  if (els.practiceSessionProgress) {
    els.practiceSessionProgress.setAttribute('aria-valuenow', String(current));
    els.practiceSessionProgress.setAttribute('aria-valuemax', String(total));
  }
}

function resetPracticeProgress() {
  const limit = appMode === 'notes' ? noteTrainer.sessionLimit : SESSION_LIMIT;
  updatePracticeProgress({ index: 0, total: limit, correct: 0, sessionLimit: limit });
}

function updateMelodyUI(state) {
  updatePracticeProgress(state);
  staffView.update(state);
}

function updateNoteUI(state) {
  updatePracticeProgress(state);
}

function showSessionModal(stats) {
  lastSessionStats = stats;
  els.modalCorrect.textContent = String(stats.correct);
  els.modalWrong.textContent = String(stats.wrong);
  els.modalAccuracy.textContent = `${stats.accuracy}%`;
  if (els.modalSubtitle) {
    const total = stats.total ?? stats.correct;
    els.modalSubtitle.textContent = stats.examMode
      ? `Экзамен: ${stats.correct} верных из ${total}`
      : `Вы прошли ${total} ${pluralNotes(total)}`;
  }
  els.sessionModal.hidden = false;
}

function pluralNotes(count) {
  const mod10 = count % 10;
  const mod100 = count % 100;
  if (mod10 === 1 && mod100 !== 11) return 'ноту';
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'ноты';
  return 'нот';
}

function readSessionLimitFromForm() {
  const select = els.notesSettingsForm?.querySelector('[name="session-limit"]');
  const value = parseInt(select?.value ?? String(DEFAULT_NOTE_SESSION_LIMIT), 10);
  return NOTE_SESSION_LIMITS.includes(value) ? value : DEFAULT_NOTE_SESSION_LIMIT;
}

function readTrainerOptionsFromForm() {
  const form = els.notesSettingsForm;
  if (!form) return { ...DEFAULT_TRAINER_OPTIONS, dailyTarget: DEFAULT_DAILY_GOAL_TARGET };

  const checked = (name) => form.querySelector(`[name="${name}"]`)?.checked ?? false;
  const dailyTarget = parseInt(form.querySelector('[name="daily-goal"]')?.value ?? String(DEFAULT_DAILY_GOAL_TARGET), 10);

  return {
    soundEnabled: checked('sound-enabled'),
    examMode: checked('exam-mode'),
    dailyTarget: DAILY_GOAL_TARGETS.includes(dailyTarget) ? dailyTarget : DEFAULT_DAILY_GOAL_TARGET,
  };
}

function loadTrainerPrefs() {
  try {
    const raw = localStorage.getItem(TRAINER_PREFS_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw);
    return {
      soundEnabled: data.soundEnabled ?? DEFAULT_TRAINER_OPTIONS.soundEnabled,
      examMode: Boolean(data.examMode),
      dailyTarget: DAILY_GOAL_TARGETS.includes(Number(data.dailyTarget))
        ? Number(data.dailyTarget)
        : getDailyGoalTarget(),
    };
  } catch {
    return null;
  }
}

function saveTrainerPrefs(options) {
  localStorage.setItem(TRAINER_PREFS_KEY, JSON.stringify({
    soundEnabled: options.soundEnabled,
    examMode: options.examMode,
    dailyTarget: options.dailyTarget,
  }));
  setDailyGoalTarget(options.dailyTarget);
}

function applyTrainerPrefsToForm() {
  const prefs = loadTrainerPrefs();
  const form = els.notesSettingsForm;
  if (!form) return;

  const soundInput = form.querySelector('[name="sound-enabled"]');
  const examInput = form.querySelector('[name="exam-mode"]');
  const dailySelect = form.querySelector('[name="daily-goal"]');

  if (prefs) {
    if (soundInput) soundInput.checked = prefs.soundEnabled;
    if (examInput) examInput.checked = prefs.examMode;
    if (dailySelect) dailySelect.value = String(prefs.dailyTarget);
  } else if (dailySelect) {
    dailySelect.value = String(getDailyGoalTarget());
  }
}

function renderDailyGoalPanel() {
  if (!els.dailyGoalPanel) return;

  const { completed, target } = dailyGoalState;
  const pct = target > 0 ? Math.min(100, Math.round((completed / target) * 100)) : 0;

  els.dailyGoalPanel.hidden = false;
  if (els.dailyGoalText) {
    els.dailyGoalText.textContent = completed >= target
      ? `Цель на сегодня выполнена: ${completed} / ${target}`
      : `Сегодня: ${completed} / ${target} верных нот`;
  }
  if (els.dailyGoalPercent) els.dailyGoalPercent.textContent = `${pct}%`;

  if (els.dailyGoalRing) {
    const circumference = 2 * Math.PI * 16;
    els.dailyGoalRing.style.strokeDasharray = `${circumference}`;
    els.dailyGoalRing.style.strokeDashoffset = `${circumference * (1 - pct / 100)}`;
  }

  els.dailyGoalPanel.classList.toggle('daily-goal--done', completed >= target && target > 0);
}

function updatePracticeDailyGoalDisplay() {
  if (!els.practiceDailyGoalText) return;
  const { completed, target } = dailyGoalState;
  els.practiceDailyGoalText.textContent = `${completed} / ${target}`;
  els.practiceDailyGoal?.classList.toggle('practice-daily-goal--done', completed >= target && target > 0);
}

async function refreshDailyGoalPanel() {
  dailyGoalState.target = getDailyGoalTarget();

  if (isLoggedIn()) {
    try {
      if (!cachedNoteStats) cachedNoteStats = await loadNoteStats();
      dailyGoalState = resolveDailyGoal({ serverDailyGoal: cachedNoteStats?.dailyGoal });
    } catch {
      dailyGoalState = resolveDailyGoal({});
    }
  } else {
    dailyGoalState = resolveDailyGoal({});
  }

  renderDailyGoalPanel();
}

function syncSoundToggleUI() {
  els.soundToggleTabs?.forEach((tab) => {
    const enabled = noteTrainer.soundEnabled;
    tab.classList.toggle('keyboard-mode__tab--active', tab.dataset.sound === (enabled ? 'on' : 'off'));
  });
}

function syncKeyboardToggleUI() {
  const visible = !els.practiceKeyboardArea?.hidden;
  els.keyboardToggleTabs?.forEach((tab) => {
    tab.classList.toggle('keyboard-mode__tab--active', tab.dataset.keyboard === (visible ? 'on' : 'off'));
  });
}

function syncPracticeSoundPanel() {
  if (!els.soundModePanel) return;
  syncSoundToggleUI();
}

function syncPracticeControls() {
  if (!els.practiceControls) return;

  const inPractice = currentScreen === 'practice';
  els.practiceControls.hidden = !inPractice;
  if (!inPractice) return;

  syncPracticeSoundPanel();
  syncKeyboardToggleUI();

  if (!els.keyboardHintsPanel) return;

  const hideHints = appMode === 'notes' && noteTrainer.examMode;
  els.keyboardHintsPanel.hidden = hideHints;
}

function setTrainerSoundEnabled(enabled) {
  noteTrainer.soundEnabled = Boolean(enabled);
  syncSoundToggleUI();

  const prefs = loadTrainerPrefs() ?? {
    ...DEFAULT_TRAINER_OPTIONS,
    dailyTarget: getDailyGoalTarget(),
  };
  saveTrainerPrefs({
    ...prefs,
    soundEnabled: noteTrainer.soundEnabled,
    examMode: noteTrainer.examMode,
  });

  const soundInput = els.notesSettingsForm?.querySelector('[name="sound-enabled"]');
  if (soundInput) soundInput.checked = noteTrainer.soundEnabled;
}

function hideSessionModal() {
  els.sessionModal.hidden = true;
}

let keyboardHints = true;

function prefersTouchInput() {
  return (
    window.matchMedia('(hover: none) and (pointer: coarse)').matches
    || window.matchMedia('(max-width: 768px)').matches
  );
}

function setPianoVisible(visible) {
  els.practiceKeyboardArea.hidden = !visible;
  els.practiceKeyboardArea.classList.toggle('practice-keyboard-area--hidden', !visible);
  els.practiceLayout?.classList.toggle('practice-layout--keyboard-hidden', !visible);
  syncPracticeControls();
  syncKeyboardToggleUI();
  if (visible && currentScreen === 'practice') {
    requestAnimationFrame(() => {
      piano.relayout({ scrollToDefault: true });
      refreshKeyboardHints();
      if (appMode === 'melody' && melodyTrainer.lesson) {
        staffView.loadLesson(melodyTrainer.lesson);
        staffView.update(melodyTrainer.state);
      } else if (appMode === 'notes' && noteTrainer.currentMidi !== null) {
        showNoteDrillStaff(noteTrainer.currentMidi, {
          spelling: noteTrainer.spelling,
          clef: noteTrainer.currentClef,
        });
      }
      setTimeout(() => piano.relayout({ scrollToDefault: true }), 120);
      setTimeout(() => piano.relayout({ scrollToDefault: true }), 320);
    });
  }
}

function setKeyboardHints(enabled) {
  if (appMode === 'notes' && noteTrainer.examMode) {
    enabled = false;
  }
  keyboardHints = enabled;
  melodyTrainer.showKeyboardHints = enabled;
  noteTrainer.showKeyboardHints = enabled;
  els.keyboardHintTabs.forEach((tab) => {
    tab.classList.toggle('keyboard-mode__tab--active', tab.dataset.hints === (enabled ? 'on' : 'off'));
  });
  refreshKeyboardHints();
}

function refreshKeyboardHints() {
  if (appMode === 'melody' && melodyTrainer.running) {
    melodyTrainer.refreshKeyboardHighlight();
  } else if (appMode === 'notes' && noteTrainer.running) {
    noteTrainer.refreshKeyboardHint();
  }
}

function showNoteDrillStaff(midi, { spelling, clef } = {}) {
  const dualClef = usesBothClefs(noteTrainer.settings);
  els.staffViewport.classList.toggle('staff-viewport--grand', dualClef);
  staffView.showDrillNote(midi, { spelling, clef, dualClef });
}

function enterPractice(mode, title) {
  appMode = mode;
  currentPracticeTitle = title;
  els.practiceTitle.textContent = title;
  if (mode === 'notes') {
    els.practiceModeBadge.hidden = !noteTrainer.examMode;
    els.practiceDailyGoal.hidden = false;
    updatePracticeDailyGoalDisplay();
    setKeyboardHints(!noteTrainer.examMode);
    syncPracticeControls();
    if (noteTrainer.soundEnabled) warmupTrainerSound();
  } else {
    els.practiceModeBadge.hidden = true;
    els.practiceDailyGoal.hidden = true;
    setKeyboardHints(true);
    syncPracticeControls();
    if (noteTrainer.soundEnabled) warmupTrainerSound();
  }
  resetPracticeProgress();
  showFeedback('', 'info');
  updatePracticeInputStatus();
  showScreen('practice');
  setPianoVisible(true);

  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      piano.relayout({ scrollToDefault: true });
      if (mode === 'melody' && melodyTrainer.lesson) {
        staffView.loadLesson(melodyTrainer.lesson);
        els.staffViewport.classList.toggle('staff-viewport--grand', melodyTrainer.lesson.twoHands);
        melodyTrainer.start();
      } else if (mode === 'notes') {
        els.staffViewport.classList.toggle('staff-viewport--grand', usesBothClefs(noteTrainer.settings));
        noteTrainer.start();
      }
      setTimeout(() => piano.relayout({ scrollToDefault: true }), 150);
    });
  });
}

function exitPractice() {
  melodyTrainer.reset();
  noteTrainer.stop();
  staffView.clear();
  els.staffViewport.classList.remove('staff-viewport--grand');
  piano.clearStates();
  hideSessionModal();
  resetPracticeProgress();
  showFeedback('', 'info');
  els.practiceModeBadge.hidden = true;
  els.practiceDailyGoal.hidden = true;
  syncPracticeControls();
}

function onSessionComplete(stats) {
  showSessionModal(stats);

  if (stats.mode === 'notes' && stats.correct > 0) {
    if (isLoggedIn()) {
      dailyGoalState.completed += stats.correct;
    } else {
      const local = addLocalDailyGoalProgress(stats.correct);
      dailyGoalState = resolveDailyGoal({ localFallback: local });
    }
    renderDailyGoalPanel();
    updatePracticeDailyGoalDisplay();
  }

  if (!isLoggedIn()) return;

  const payload = {
    mode: stats.mode ?? appMode,
    correct: stats.correct,
    wrong: stats.wrong,
    accuracy: stats.accuracy,
    total: stats.total,
  };

  if (payload.mode === 'notes') {
    payload.settings = stats.settings ?? noteTrainer.settings;
    payload.attempts = stats.attempts ?? [];
  } else if (payload.mode === 'melody') {
    payload.lessonId = selectedLessonId ?? selectedImportedId ?? null;
  }

  saveSessionStats(payload).then(async (ok) => {
    if (!ok) return;
    cachedNoteStats = null;
    if (payload.mode === 'notes') {
      try {
        const data = await loadNoteStats();
        cachedNoteStats = data;
        dailyGoalState = resolveDailyGoal({ serverDailyGoal: data?.dailyGoal });
        renderDailyGoalPanel();
        updatePracticeDailyGoalDisplay();
      } catch {
        /* ignore */
      }
    }
  });
}

function updateAuthUI() {
  const user = getUser();
  const loggedIn = Boolean(user);

  if (els.btnOpenAuth) els.btnOpenAuth.hidden = loggedIn;
  if (els.authUser) els.authUser.hidden = !loggedIn;
  if (els.btnLogout) els.btnLogout.hidden = !loggedIn;
  if (els.authUserName) els.authUserName.textContent = user?.name ?? '';
}

function openAuthModal(tab = 'login') {
  els.authModal.hidden = false;
  setAuthTab(tab);
  els.authErrorLogin.hidden = true;
  els.authErrorRegister.hidden = true;
}

function closeAuthModal() {
  els.authModal.hidden = true;
}

function setAuthTab(tab) {
  els.authTabs.forEach((btn) => {
    const active = btn.dataset.authTab === tab;
    btn.classList.toggle('auth-tab--active', active);
    btn.setAttribute('aria-selected', String(active));
  });
  els.authFormLogin.hidden = tab !== 'login';
  els.authFormRegister.hidden = tab !== 'register';
  const title = tab === 'login' ? 'Вход' : 'Регистрация';
  const titleEl = $('#auth-modal-title');
  if (titleEl) titleEl.textContent = title;
}

const LEVEL_LABELS = {
  mastered: 'Выучено хорошо',
  learning: 'В процессе',
  needs_practice: 'Нужно потренировать',
};

const LEVEL_ICONS = {
  mastered: 'mastered',
  learning: 'learning',
  needs_practice: 'warning',
};

const LEVEL_ORDER = ['needs_practice', 'learning', 'mastered'];

function getWeakNotesFromStats(data) {
  if (!data?.notes?.length) return [];
  return data.notes.filter((note) => note.level === 'needs_practice');
}

function describeWeakNotesTraining(weakNotes) {
  if (!weakNotes.length) return 'Слабые ноты';
  const names = weakNotes.map((note) => note.name);
  if (names.length <= 4) return `Слабые ноты: ${names.join(', ')}`;
  return `Слабые ноты: ${names.slice(0, 3).join(', ')} и ещё ${names.length - 3}`;
}

function startWeakNotesTraining(weakNotes) {
  if (!weakNotes.length) return;

  const midis = weakNotes.map((note) => note.midi);
  noteTrainer.setCustomPool(midis);
  noteTrainer.sessionLimit = Math.min(Math.max(10, midis.length), 30);
  noteSettings = structuredClone(noteTrainer.settings);
  enterPractice('notes', describeWeakNotesTraining(weakNotes));
}

function bindWeakNotesPracticeButton(button) {
  button?.addEventListener('click', () => {
    const weakNotes = getWeakNotesFromStats(cachedNoteStats);
    startWeakNotesTraining(weakNotes);
  });
}

function renderWeakNotesOffer(weakNotes) {
  if (!els.weakNotesOffer) return;

  if (!weakNotes.length) {
    els.weakNotesOffer.hidden = true;
    els.weakNotesOffer.innerHTML = '';
    return;
  }

  const preview = weakNotes
    .slice(0, 6)
    .map((note) => `<span class="weak-notes-offer__tag">${escapeHtml(note.name)}</span>`)
    .join('');
  const more = weakNotes.length > 6
    ? `<span class="weak-notes-offer__more">+${weakNotes.length - 6}</span>`
    : '';

  els.weakNotesOffer.hidden = false;
  els.weakNotesOffer.innerHTML = `
    <div class="weak-notes-offer__content">
      ${iconBadgeColored('target', 'warn')}
      <div class="weak-notes-offer__text">
        <strong>Есть ${weakNotes.length} ${pluralNotes(weakNotes.length)} для повторения</strong>
        <p>Запустим тренировку только по нотам, которые пока даются сложнее всего.</p>
        <div class="weak-notes-offer__tags">${preview}${more}</div>
      </div>
      <button type="button" class="btn btn--primary" id="btn-weak-notes-offer">Потренировать слабые ноты</button>
    </div>
  `;
  bindWeakNotesPracticeButton($('#btn-weak-notes-offer'));
}

async function refreshWeakNotesOffer() {
  if (!isLoggedIn()) {
    renderWeakNotesOffer([]);
    return;
  }

  try {
    if (!cachedNoteStats) {
      cachedNoteStats = await loadNoteStats();
    }
    renderWeakNotesOffer(getWeakNotesFromStats(cachedNoteStats));
  } catch {
    renderWeakNotesOffer([]);
  }
}

function renderWeakNotesPracticeCta(weakNotes) {
  if (!weakNotes.length) return '';

  const preview = weakNotes
    .slice(0, 8)
    .map((note) => `<span class="stats-practice-cta__tag">${escapeHtml(note.name)}</span>`)
    .join('');

  return `
    <section class="stats-practice-cta">
      <div class="stats-practice-cta__head">
        ${iconBadgeColored('target', 'warn')}
        <h3 class="stats-practice-cta__title">Рекомендуемая тренировка</h3>
      </div>
      <p class="stats-practice-cta__text">
        Подобрали сессию из ${weakNotes.length} ${pluralNotes(weakNotes.length)}, которые стоит потренировать отдельно.
      </p>
      <div class="stats-practice-cta__tags">${preview}</div>
      <button type="button" class="btn btn--primary" id="btn-practice-weak-notes">
        Потренировать слабые ноты
      </button>
    </section>
  `;
}

function formatChartDay(isoDate) {
  const date = new Date(`${isoDate}T12:00:00`);
  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
}

function renderStatsChart(dailyProgress) {
  if (!Array.isArray(dailyProgress) || dailyProgress.length === 0) {
    return '';
  }

  const maxValue = Math.max(
    1,
    ...dailyProgress.map((day) => Math.max(day.learned, day.repeated)),
  );
  const hasActivity = dailyProgress.some((day) => day.learned > 0 || day.repeated > 0);

  const columns = dailyProgress.map((day, index) => {
    const learnedHeight = Math.round((day.learned / maxValue) * 100);
    const repeatedHeight = Math.round((day.repeated / maxValue) * 100);
    const label = formatChartDay(day.date);
    const title = `${label}: выучено ${day.learned}, повторено ${day.repeated}`;
    const showLabel = dailyProgress.length <= 10 || index % 2 === 0;

    return `
      <div class="stats-chart__column" title="${escapeHtml(title)}">
        <div class="stats-chart__bars" aria-hidden="true">
          <div class="stats-chart__bar stats-chart__bar--learned" style="height: ${learnedHeight}%"></div>
          <div class="stats-chart__bar stats-chart__bar--repeated" style="height: ${repeatedHeight}%"></div>
        </div>
        <span class="stats-chart__label${showLabel ? '' : ' stats-chart__label--short'}">${escapeHtml(showLabel ? label : label.replace(/\s.*/, ''))}</span>
      </div>
    `;
  }).join('');

  return `
    <section class="stats-chart">
      <div class="stats-chart__header">
        <div class="stats-chart__title-wrap">
          ${iconBadgeColored('chart', 'primary')}
          <h3 class="stats-chart__title">Прогресс по дням</h3>
        </div>
        <div class="stats-chart__legend">
          <span class="stats-chart__legend-item stats-chart__legend-item--learned">Выучено</span>
          <span class="stats-chart__legend-item stats-chart__legend-item--repeated">Повторено</span>
        </div>
      </div>
      <p class="stats-chart__hint">
        Выучено — ноты, которые вы впервые угадали верно в этот день. Повторено — ноты, с которыми вы уже занимались раньше.
      </p>
      <div class="stats-chart__plot${hasActivity ? '' : ' stats-chart__plot--empty'}" role="img" aria-label="График прогресса по дням">
        ${columns}
      </div>
      ${hasActivity ? '' : '<p class="stats-chart__empty">Пройдите тренировку нот — график заполнится по дням.</p>'}
    </section>
  `;
}

function renderStatsPanel(data) {
  if (!data) {
    els.statsPanel.innerHTML = `
      <div class="stats-empty">
        ${iconBadgeColored('user', 'primary')}
        <p>Войдите в аккаунт, чтобы отслеживать прогресс по нотам.</p>
        <button type="button" class="btn btn--primary" id="btn-stats-login">Войти или зарегистрироваться</button>
      </div>
    `;
    $('#btn-stats-login')?.addEventListener('click', () => openAuthModal('login'));
    return;
  }

  const { summary, notes, dailyProgress } = data;
  const weakNotes = getWeakNotesFromStats(data);
  const groups = LEVEL_ORDER.map((level) => ({
    level,
    label: LEVEL_LABELS[level],
    items: notes.filter((n) => n.level === level),
  })).filter((g) => g.items.length);

  const summaryHtml = `
    <div class="stats-summary">
      <div class="stats-summary__item">
        ${iconBadgeColored('sessions', 'primary')}
        <span class="stats-summary__value">${summary.sessions}</span>
        <span class="stats-summary__label">сессий</span>
      </div>
      <div class="stats-summary__item stats-summary__item--good">
        ${iconBadgeColored('mastered', 'success')}
        <span class="stats-summary__value">${summary.mastered}</span>
        <span class="stats-summary__label">выучено</span>
      </div>
      <div class="stats-summary__item stats-summary__item--warn">
        ${iconBadgeColored('practice', 'warn')}
        <span class="stats-summary__value">${summary.needsPractice}</span>
        <span class="stats-summary__label">нужно потренировать</span>
      </div>
    </div>
  `;

  const groupsHtml = groups.length
    ? groups.map((group) => `
      <section class="stats-group stats-group--${group.level}">
        <h3 class="stats-group__title">
          ${iconBadgeColored(LEVEL_ICONS[group.level], group.level === 'mastered' ? 'success' : group.level === 'needs_practice' ? 'warn' : 'primary')}
          ${escapeHtml(group.label)}
          <span class="stats-group__count">${group.items.length}</span>
        </h3>
        <div class="stats-note-list">
          ${group.items.map((note) => `
            <div class="stats-note stats-note--${note.level}">
              <span class="stats-note__name">${escapeHtml(note.name)}</span>
              <span class="stats-note__meta">${note.accuracy}% · ${note.attempts} ${pluralAttempts(note.attempts)}</span>
            </div>
          `).join('')}
        </div>
      </section>
    `).join('')
    : '<p class="stats-empty__hint">Пройдите тренировку нот — здесь появится статистика по каждой ноте.</p>';

  els.statsPanel.innerHTML = renderWeakNotesPracticeCta(weakNotes) + summaryHtml + renderStatsChart(dailyProgress) + groupsHtml;
  bindWeakNotesPracticeButton($('#btn-practice-weak-notes'));
}

function pluralAttempts(n) {
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return 'попытка';
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'попытки';
  return 'попыток';
}

async function openStatsScreen() {
  if (!isLoggedIn()) {
    openAuthModal('login');
    return;
  }
  showScreen('stats');
  els.statsPanel.innerHTML = '<p class="loading">Загрузка статистики…</p>';
  try {
    const data = await loadNoteStats();
    cachedNoteStats = data;
    renderStatsPanel(data);
  } catch {
    els.statsPanel.innerHTML = '<p class="loading">Не удалось загрузить статистику</p>';
  }
}

async function loadLessons() {
  try {
    lessons = await fetchJson('/api/lessons');
    renderLessonList();
  } catch {
    els.lessonList.innerHTML = '<p class="loading">Ошибка загрузки уроков</p>';
  }
}

const DIFFICULTY_LABELS = {
  beginner: 'Начальный',
  intermediate: 'Средний',
  advanced: 'Продвинутый',
};

const CATEGORY_LABELS = {
  popular: 'Популярные',
  international: 'Классика',
};

const DIFFICULTY_ORDER = ['beginner', 'intermediate', 'advanced'];
const CATEGORY_ORDER = ['popular', 'international'];

function filterLocalLessons(query) {
  const needle = query.trim().toLowerCase();
  return lessons.filter((lesson) => {
    if (selectedDifficultyFilter !== 'all' && lesson.difficulty !== selectedDifficultyFilter) {
      return false;
    }
    if (!needle) return true;
    return lesson.title.toLowerCase().includes(needle)
      || lesson.composer.toLowerCase().includes(needle);
  });
}

function groupLessonsByDifficultyAndCategory(items) {
  const groups = [];
  for (const difficulty of DIFFICULTY_ORDER) {
    const byDifficulty = items.filter((lesson) => lesson.difficulty === difficulty);
    if (!byDifficulty.length) continue;

    const section = { difficulty, categories: [] };

    for (const category of CATEGORY_ORDER) {
      const byCategory = byDifficulty.filter((lesson) => (lesson.category ?? 'international') === category);
      if (byCategory.length) {
        section.categories.push({ category, lessons: byCategory });
      }
    }

    const other = byDifficulty.filter((lesson) => !CATEGORY_ORDER.includes(lesson.category ?? 'international'));
    if (other.length) {
      section.categories.push({ category: 'other', lessons: other });
    }

    groups.push(section);
  }
  return groups;
}

function lessonCardHtml(lesson, { remote = false } = {}) {
  const hands = lesson.twoHands ? ' · 2 руки' : '';
  const metaParts = [];
  if (lesson.composer) metaParts.push(lesson.composer);
  if (lesson.difficulty) metaParts.push(DIFFICULTY_LABELS[lesson.difficulty]?.toLowerCase() ?? lesson.difficulty);
  if (hands) metaParts.push('2 руки');
  if (lesson.noteCount) metaParts.push(`${lesson.noteCount} нот`);
  if (remote) metaParts.push('из интернета');

  const classes = ['lesson-card'];
  if (remote) classes.push('lesson-card--remote');
  if (lesson.category === 'popular') classes.push('lesson-card--popular');

  return `
    <button type="button" class="${classes.join(' ')}"
            data-id="${escapeHtml(lesson.id)}"
            ${remote ? `data-remote-id="${lesson.remoteId}"` : ''}>
      <span class="lesson-card__icon icon-badge icon-badge--melody" aria-hidden="true">${icon('melody', 'icon icon--badge')}</span>
      <span class="lesson-card__body">
        <span class="lesson-card__title">${escapeHtml(lesson.title)}</span>
        <span class="lesson-card__meta">${escapeHtml(metaParts.join(' · '))}</span>
      </span>
      <span class="lesson-card__play" aria-hidden="true">${icon('play', 'icon icon--sm')}</span>
    </button>
  `;
}

function renderLessonList() {
  const query = searchQuery.trim();
  const localMatches = filterLocalLessons(query);
  const parts = [];

  if (query) {
    parts.push('<div class="lesson-section-label">В каталоге</div>');
    if (localMatches.length) {
      parts.push(localMatches.map((lesson) => lessonCardHtml(lesson)).join(''));
    } else {
      parts.push('<p class="lesson-list__empty">В каталоге ничего не найдено</p>');
    }

    parts.push('<div class="lesson-section-label">Из интернета</div>');
    if (remoteSearchResults.length) {
      parts.push(remoteSearchResults.map((result) => lessonCardHtml({
        id: `remote-${result.id}`,
        remoteId: result.id,
        title: result.title,
        composer: '',
        noteCount: null,
        twoHands: false,
        difficulty: '',
      }, { remote: true })).join(''));
    } else if (query.length >= 2 && !remoteSearchDone) {
      parts.push('<p class="lesson-list__empty">Ищем MIDI-файлы…</p>');
    } else if (query.length >= 2) {
      parts.push('<p class="lesson-list__empty">В интернете ничего не найдено</p>');
    }
  } else {
    const groups = groupLessonsByDifficultyAndCategory(localMatches);
    for (const section of groups) {
      parts.push(`<div class="lesson-section-label">${DIFFICULTY_LABELS[section.difficulty]}</div>`);
      for (const block of section.categories) {
        if (block.category && CATEGORY_LABELS[block.category]) {
          parts.push(`<div class="lesson-section-label lesson-section-label--sub">${CATEGORY_LABELS[block.category]}</div>`);
        }
        parts.push(block.lessons.map((lesson) => lessonCardHtml(lesson)).join(''));
      }
    }
    if (!groups.length) {
      parts.push('<p class="lesson-list__empty">Нет мелодий для выбранного уровня</p>');
    }
  }

  els.lessonList.innerHTML = parts.join('');

  els.lessonList.querySelectorAll('.lesson-card').forEach((card) => {
    card.addEventListener('click', () => {
      if (card.dataset.remoteId) {
        loadRemoteMidi(Number(card.dataset.remoteId), card.querySelector('.lesson-card__title')?.textContent ?? '');
      } else {
        selectLesson(card.dataset.id);
      }
    });
  });
}

async function runMelodySearch(query) {
  const trimmed = query.trim();
  searchQuery = query;
  renderLessonList();

  if (trimmed.length < 2) {
    remoteSearchResults = [];
    remoteSearchDone = false;
    renderLessonList();
    return;
  }

  remoteSearchDone = false;
  renderLessonList();

  const requestId = ++searchRequestId;
  try {
    const data = await fetchJson(`/api/midi/search?q=${encodeURIComponent(trimmed)}`);
    if (requestId !== searchRequestId || searchQuery.trim() !== trimmed) return;
    remoteSearchResults = data.results ?? [];
  } catch {
    if (requestId !== searchRequestId) return;
    remoteSearchResults = [];
  } finally {
    if (requestId === searchRequestId && searchQuery.trim() === trimmed) {
      remoteSearchDone = true;
      renderLessonList();
    }
  }
}

let searchDebounceTimer = null;

function loadMelodyLesson(lesson, { activeId = null } = {}) {
  const normalized = normalizeLesson(lesson);
  melodyTrainer.loadLesson(normalized, { sessionLimit: SESSION_LIMIT });
  noteTrainer.sessionLimit = SESSION_LIMIT;

  if (activeId?.startsWith('remote-')) {
    selectedLessonId = null;
    selectedImportedId = activeId;
  } else if (activeId) {
    selectedLessonId = activeId;
    selectedImportedId = null;
  }

  enterPractice('melody', normalized.title);
}

async function loadRemoteMidi(remoteId, title) {
  try {
    const res = await fetch(`/api/midi/${remoteId}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const lesson = await midiToLesson(await res.arrayBuffer(), {
      id: `remote-${remoteId}`,
      title: title.trim() || 'MIDI мелодия',
      composer: 'FreeMidi',
    });
    loadMelodyLesson(lesson, { activeId: `remote-${remoteId}` });
  } catch {
    alert('Не удалось загрузить MIDI. Попробуйте другой вариант.');
  }
}

async function loadUploadedMidi(file) {
  if (!file) return;

  const name = file.name.toLowerCase();
  if (!name.endsWith('.mid') && !name.endsWith('.midi')) {
    alert('Выберите файл с расширением .mid или .midi');
    els.midiUpload.value = '';
    return;
  }

  try {
    const lesson = await midiToLesson(await file.arrayBuffer(), {
      id: `upload-${Date.now()}`,
      title: file.name.replace(/\.(mid|midi)$/i, ''),
      composer: 'Загружено',
    });
    selectedLessonId = null;
    selectedImportedId = lesson.id;
    loadMelodyLesson(lesson, { activeId: lesson.id });
  } catch (error) {
    alert(error?.message ?? 'Не удалось прочитать MIDI-файл');
  } finally {
    els.midiUpload.value = '';
  }
}

function readNoteSettingsFromForm() {
  const form = els.notesSettingsForm;
  if (!form) return structuredClone(DEFAULT_NOTE_SETTINGS);

  const checked = (name) => form.querySelector(`[name="${name}"]`)?.checked ?? false;
  const trebleFirst = checked('treble-first');
  const trebleSecond = checked('treble-second');
  const bassSmall = checked('bass-small');
  const bassGreat = checked('bass-great');

  return {
    treble: {
      enabled: trebleFirst || trebleSecond,
      first: trebleFirst,
      second: trebleSecond,
    },
    bass: {
      enabled: bassSmall || bassGreat,
      small: bassSmall,
      great: bassGreat,
    },
    alteration: {
      sharp: checked('alt-sharp'),
      flat: checked('alt-flat'),
    },
  };
}

function applyNoteSettingsToForm(settings) {
  const form = els.notesSettingsForm;
  if (!form) return;

  const set = (name, value) => {
    const input = form.querySelector(`[name="${name}"]`);
    if (input) input.checked = value;
  };

  set('treble-first', settings.treble.first);
  set('treble-second', settings.treble.second);
  set('bass-small', settings.bass.small);
  set('bass-great', settings.bass.great);
  set('alt-sharp', settings.alteration.sharp);
  set('alt-flat', settings.alteration.flat);
}

function applySessionLimitToForm(limit = DEFAULT_NOTE_SESSION_LIMIT) {
  const select = els.notesSettingsForm?.querySelector('[name="session-limit"]');
  if (select) select.value = String(limit);
}

function hasSelectedOctaves(settings) {
  return settings.treble.first || settings.treble.second
    || settings.bass.small || settings.bass.great;
}

function validateNoteSettings(settings) {
  if (!hasSelectedOctaves(settings)) {
    return 'Выберите хотя бы одну октаву';
  }
  if (!buildPoolFromSettings(settings).length) {
    return 'Нет нот для выбранных настроек — включите диезы/бемоли или измените октавы';
  }
  return null;
}

function startNotesTraining() {
  const settings = readNoteSettingsFromForm();
  const options = readTrainerOptionsFromForm();
  const error = validateNoteSettings(settings);

  if (error) {
    els.notesSettingsError.textContent = error;
    els.notesSettingsError.hidden = false;
    return;
  }

  els.notesSettingsError.hidden = true;
  saveTrainerPrefs(options);
  noteSettings = settings;
  noteTrainer.setConfig(settings);
  noteTrainer.setOptions(options);
  noteTrainer.sessionLimit = readSessionLimitFromForm();
  dailyGoalState.target = options.dailyTarget;
  enterPractice('notes', describeNoteSettings(settings, options));
}

async function selectLesson(id) {
  try {
    const lesson = normalizeLesson(await fetchJson(`/api/lessons/${id}`));
    loadMelodyLesson(lesson, { activeId: id });
  } catch {
    alert('Не удалось загрузить урок');
  }
}

function onNoteOn(midiNote) {
  piano.pressKey(midiNote);
  const trainerRunning = appMode === 'melody'
    ? melodyTrainer.running
    : noteTrainer.running;
  if (noteTrainer.soundEnabled && trainerRunning) {
    playTrainerNote(midiNote, 0.55);
  }
  if (appMode === 'melody') {
    melodyTrainer.handleNoteOn(midiNote);
  } else {
    noteTrainer.handleNoteOn(midiNote);
  }
}

function onNoteOff(midiNote) {
  if (appMode === 'melody') {
    melodyTrainer.handleNoteOff(midiNote);
  } else {
    noteTrainer.handleNoteOff(midiNote);
  }
}

piano.onNoteOn = onNoteOn;
piano.onNoteOff = onNoteOff;
midi.onNoteOn = onNoteOn;
midi.onNoteOff = onNoteOff;
micPitch.onNoteOn = onNoteOn;
micPitch.onNoteOff = onNoteOff;

function showInputActivity({ note, type, source, rawNote }) {
  if (type !== 'on') return;
  if (currentScreen === 'practice') {
    els.midiLiveNote.textContent = '';
    return;
  }
  const name = midiToName(note);
  if (source === 'mic') {
    els.midiLiveNote.textContent = `🎤 ${name}`;
    return;
  }
  const extra = rawNote !== undefined && rawNote !== note ? ` (MIDI ${rawNote}→${note})` : ` (MIDI ${note})`;
  els.midiLiveNote.textContent = `▸ ${name}${extra}`;
}

midi.onActivity = showInputActivity;
micPitch.onActivity = showInputActivity;

micPitch.onStatusChange = (state) => {
  if (state === 'on') {
    els.midiDot.className = 'midi-dot midi-dot--on';
    setMicStatus(true, 'Микрофон: слушаю ноты');
  } else if (!midi.isConnected) {
    els.midiDot.className = 'midi-dot midi-dot--off';
    setMicStatus(false, 'MIDI не подключён');
  } else {
    setMidiStatus(false);
  }
  if (currentScreen === 'practice') updatePracticeInputStatus();
};

async function startMicListening() {
  try {
    await micPitch.start();
    if (currentScreen === 'practice') updatePracticeInputStatus();
  } catch (error) {
    setMicStatus(false);
    setMidiStatus('error', error?.message ?? 'Не удалось включить микрофон');
    if (currentScreen === 'practice') updatePracticeInputStatus();
  }
}

function stopMicListening() {
  micPitch.stop();
  setMicStatus(false);
  if (midi.isConnected) {
    const input = midi.listInputs().find((i) => i.selected);
    setMidiStatus('on', `Подключено: ${input?.name ?? midi.deviceName ?? 'MIDI'}`);
  } else {
    setMidiStatus('off', 'MIDI не подключён');
  }
  if (currentScreen === 'practice') updatePracticeInputStatus();
}

function renderMidiDevices(inputs) {
  if (!inputs.length) {
    els.midiDeviceSelect.hidden = true;
    return;
  }
  els.midiDeviceSelect.hidden = false;
  els.midiDeviceSelect.replaceChildren();
  for (const i of inputs) {
    const opt = document.createElement('option');
    opt.value = i.id;
    opt.textContent = i.name;
    opt.selected = i.selected;
    els.midiDeviceSelect.appendChild(opt);
  }
}

midi.onInputsChanged = (inputs) => {
  renderMidiDevices(inputs);
  if (currentScreen === 'practice') updatePracticeInputStatus();
};

melodyTrainer.onUpdate = (state) => {
  if (appMode === 'melody' && currentScreen === 'practice') updateMelodyUI(state);
};
melodyTrainer.onFeedback = showFeedback;
melodyTrainer.onComplete = onSessionComplete;

noteTrainer.onUpdate = (state) => {
  if (appMode === 'notes' && currentScreen === 'practice') updateNoteUI(state);
};
noteTrainer.onFeedback = showFeedback;
noteTrainer.onComplete = onSessionComplete;
noteTrainer.onNoteChange = (midiNote, { spelling, clef } = {}) => {
  showNoteDrillStaff(midiNote, { spelling, clef });
};

els.notesSettingsForm?.addEventListener('submit', (e) => {
  e.preventDefault();
  startNotesTraining();
});

els.notesSettingsForm?.addEventListener('change', () => {
  if (!els.notesSettingsError.hidden) els.notesSettingsError.hidden = true;
  const options = readTrainerOptionsFromForm();
  if (options.dailyTarget) {
    setDailyGoalTarget(options.dailyTarget);
    dailyGoalState.target = options.dailyTarget;
    renderDailyGoalPanel();
    updatePracticeDailyGoalDisplay();
  }
});

els.keyboardToggleTabs?.forEach((tab) => {
  tab.addEventListener('click', () => {
    setPianoVisible(tab.dataset.keyboard === 'on');
  });
});

els.keyboardHintTabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    if (appMode === 'notes' && noteTrainer.examMode) return;
    setKeyboardHints(tab.dataset.hints === 'on');
  });
});

els.soundToggleTabs?.forEach((tab) => {
  tab.addEventListener('click', () => {
    setTrainerSoundEnabled(tab.dataset.sound === 'on');
  });
});

els.btnGoMelodies.addEventListener('click', () => showScreen('melody-pick'));

function openNotesPickScreen() {
  applyTrainerPrefsToForm();
  refreshDailyGoalPanel();
  showScreen('notes-pick');
}

els.btnGoNotes.addEventListener('click', openNotesPickScreen);

document.querySelectorAll('[data-landing-go="notes"]').forEach((btn) => {
  btn.addEventListener('click', openNotesPickScreen);
});

document.querySelectorAll('[data-landing-go="melodies"]').forEach((btn) => {
  btn.addEventListener('click', () => showScreen('melody-pick'));
});
els.btnGoStatsHome?.addEventListener('click', openStatsScreen);
els.btnGoStats?.addEventListener('click', openStatsScreen);
els.btnBackStats?.addEventListener('click', () => showScreen('home'));

els.btnOpenAuth?.addEventListener('click', () => openAuthModal('login'));
els.btnLogout?.addEventListener('click', async () => {
  await logout();
  cachedNoteStats = null;
  updateAuthUI();
  renderWeakNotesOffer([]);
});

els.authTabs.forEach((tab) => {
  tab.addEventListener('click', () => setAuthTab(tab.dataset.authTab));
});

els.authModal?.querySelectorAll('[data-close-auth]').forEach((el) => {
  el.addEventListener('click', closeAuthModal);
});

els.authFormLogin?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = new FormData(e.target);
  els.authErrorLogin.hidden = true;
  try {
    await login(form.get('email'), form.get('password'));
    updateAuthUI();
    closeAuthModal();
  } catch (err) {
    els.authErrorLogin.textContent = err.message;
    els.authErrorLogin.hidden = false;
  }
});

els.authFormRegister?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = new FormData(e.target);
  els.authErrorRegister.hidden = true;

  if (form.get('website')) {
    return;
  }

  const password = String(form.get('password') ?? '');
  const passwordConfirm = String(form.get('password_confirm') ?? '');
  if (password !== passwordConfirm) {
    els.authErrorRegister.textContent = 'Пароли не совпадают';
    els.authErrorRegister.hidden = false;
    return;
  }

  try {
    await register(
      form.get('name'),
      form.get('email'),
      password,
      passwordConfirm,
      form.get('website'),
    );
    updateAuthUI();
    closeAuthModal();
  } catch (err) {
    els.authErrorRegister.textContent = err.message;
    els.authErrorRegister.hidden = false;
  }
});

els.btnBackMelody.addEventListener('click', () => showScreen('home'));
els.btnBackNotes.addEventListener('click', () => showScreen('home'));

els.btnBackPractice.addEventListener('click', () => {
  exitPractice();
  showScreen(appMode === 'melody' ? 'melody-pick' : 'notes-pick');
});

els.melodySearch.addEventListener('input', () => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    runMelodySearch(els.melodySearch.value);
  }, 350);
});

els.melodySearch.addEventListener('search', () => {
  clearTimeout(searchDebounceTimer);
  runMelodySearch(els.melodySearch.value);
});

els.difficultyTabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    selectedDifficultyFilter = tab.dataset.difficulty ?? 'all';
    els.difficultyTabs.forEach((item) => {
      item.classList.toggle('difficulty-tab--active', item === tab);
    });
    renderLessonList();
  });
});

els.midiUpload?.addEventListener('change', () => {
  const file = els.midiUpload.files?.[0];
  if (file) loadUploadedMidi(file);
});

els.btnMidiUpload?.addEventListener('click', () => {
  els.midiUpload?.click();
});

els.btnModalRetry.addEventListener('click', () => {
  hideSessionModal();
  if (appMode === 'melody' && melodyTrainer.lesson) {
    melodyTrainer.reset();
    melodyTrainer.start();
    showFeedback('Поехали!', 'info');
  } else if (appMode === 'notes') {
    noteTrainer.reset();
    noteTrainer.start();
    showFeedback('Поехали!', 'info');
  }
});

els.btnModalPick.addEventListener('click', () => {
  exitPractice();
  showScreen(appMode === 'melody' ? 'melody-pick' : 'notes-pick');
});

els.btnModalHome.addEventListener('click', () => {
  exitPractice();
  showScreen('home');
});

els.sessionModal.querySelector('.modal__backdrop').addEventListener('click', () => {
  hideSessionModal();
});

els.btnConnectMidi.addEventListener('click', async () => {
  try {
    await connectMidiDevice();
  } catch {
    // status already updated in connectMidiDevice
  }
});

els.btnPracticeConnectMidi?.addEventListener('click', async () => {
  try {
    await connectMidiDevice();
  } catch {
    // status already updated in connectMidiDevice
  }
});

els.btnConnectMic.addEventListener('click', async () => {
  if (micPitch.isActive) {
    stopMicListening();
    return;
  }
  await startMicListening();
});

els.midiDeviceSelect.addEventListener('change', () => {
  midi.selectInput(els.midiDeviceSelect.value);
  const input = midi.listInputs().find((i) => i.id === els.midiDeviceSelect.value);
  if (input) setMidiStatus('on', `Подключено: ${input.name}`);
});

els.midiTranspose.addEventListener('change', () => {
  midi.setTranspose(parseInt(els.midiTranspose.value, 10));
});

const pressedKeys = new Set();

function isTypingTarget(target) {
  if (!(target instanceof Element)) return false;
  return Boolean(target.closest('input, textarea, select, [contenteditable="true"]'));
}

document.addEventListener('keydown', (e) => {
  if (e.repeat) return;
  if (isTypingTarget(e.target)) return;
  const note = KEYBOARD_MAP[e.key.toLowerCase()];
  if (note === undefined) return;
  e.preventDefault();
  if (!pressedKeys.has(note)) {
    pressedKeys.add(note);
    onNoteOn(note);
  }
});

document.addEventListener('keyup', (e) => {
  if (isTypingTarget(e.target)) return;
  const note = KEYBOARD_MAP[e.key.toLowerCase()];
  if (note === undefined) return;
  pressedKeys.delete(note);
  onNoteOff(note);
});

loadLessons();
initAuth().then(() => {
  updateAuthUI();
  refreshDailyGoalPanel();
});
applyNoteSettingsToForm(DEFAULT_NOTE_SETTINGS);
applySessionLimitToForm(DEFAULT_NOTE_SESSION_LIMIT);
applyTrainerPrefsToForm();
noteTrainer.sessionLimit = DEFAULT_NOTE_SESSION_LIMIT;
noteTrainer.setOptions(loadTrainerPrefs() ?? DEFAULT_TRAINER_OPTIONS);
setPianoVisible(false);
melodyTrainer.showKeyboardHints = true;
noteTrainer.showKeyboardHints = true;

window.addEventListener('resize', () => {
  if (currentScreen === 'practice') {
    piano.relayout();
  }
  if (currentScreen !== 'practice') return;
  if (appMode === 'melody' && melodyTrainer.lesson) {
    staffView.loadLesson(melodyTrainer.lesson);
    staffView.update(melodyTrainer.state);
  } else if (appMode === 'notes' && noteTrainer.currentMidi !== null && !staffView.drillMode) {
    showNoteDrillStaff(noteTrainer.currentMidi, {
      spelling: noteTrainer.spelling,
      clef: noteTrainer.currentClef,
    });
  }
});

window.visualViewport?.addEventListener('resize', () => {
  if (currentScreen === 'practice' && !els.practiceKeyboardArea.hidden) {
    piano.relayout();
  }
});
