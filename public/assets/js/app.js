import { PianoKeyboard } from './piano.js';
import { MidiInput } from './midi.js';
import { MicPitchInput } from './mic-pitch.js';
import { MelodyTrainer } from './trainer.js';
import { NoteTrainer, DEFAULT_NOTE_SETTINGS, DEFAULT_NOTE_SESSION_LIMIT, DEFAULT_TRAINER_OPTIONS, describeNoteSettings, usesBothClefs } from './note-trainer.js';
import {
  readNoteSettingsFromForm as readNoteSettingsFromFormElement,
  applyNoteSettingsToForm as applyNoteSettingsToFormElement,
  readSessionLimitFromForm as readSessionLimitFromFormElement,
  applySessionLimitToForm as applySessionLimitToFormElement,
  validateNoteSettings,
} from './note-settings-form.js';
import { StaffView } from './staff.js';
import { normalizeLesson } from './lesson-utils.js';
import { midiToLesson } from './midi-import.js';
import { KEYBOARD_MAP, midiToName, PIANO_START, PIANO_END } from './notes.js';
import { initAuth, getUser, hasRole, isLoggedIn, login, register, logout, saveSessionStats, loadNoteStats, mergeGuestNoteStats, loadOAuthProviders, redirectToOAuth, setInviteToken, getInviteToken } from './auth.js';
import { icon, iconBadgeColored } from './icons.js';
import { playTrainerNote, warmupTrainerSound, unlockTrainerSoundFromGesture } from './trainer-sounds.js';
import {
  playMelodyPreview,
  stopMelodyPreview,
  isMelodyPreviewPlaying,
  getMelodyPreviewId,
} from './melody-preview.js';
import {
  loadRoadmap,
  buildGuestRoadmapProgress,
  buildRoadmapProgressFromStats,
  buildPoolForStage,
  findStage,
  findStageProgress,
  getNextStage,
  mergeGuestAttempts,
  projectNoteStatsFromAttempts,
  getGuestNoteEntries,
  clearGuestNoteMap,
  markCapstoneComplete,
  mergeCapstoneIntoProgress,
  getCapstoneLabel,
  meetsCapstoneAccuracy,
  getStageIncompleteNotes,
  getCurrentStageIncompleteNotes,
  enrichNotesForRoadmapDisplay,
} from './note-roadmap.js';
import { renderStatsStaffInfographic, mountStatsStaffChart } from './stats-staff.js';
import { ROUTES, routeForScreen, navigateTo, setNavigateImpl } from './routes.js';
import { initMetrikaPageview, trackGoal, trackPracticePageView } from './metrika.js';
import { initAnalytics } from './analytics.js';

const SESSION_LIMIT = 10;
const TRAINER_PREFS_KEY = 'piano-trainer-prefs';
const PENDING_NOTES_PRACTICE_KEY = 'piano-pending-notes-practice';
const PENDING_HOMEWORK_KEY = 'piano-pending-homework';

const $ = (sel) => document.querySelector(sel);

const els = {
  app: $('#app'),
  mainHeader: $('#main-header'),
  screenHome: $('#screen-home'),
  screenMelodyPick: $('#screen-melody-pick'),
  screenNotesPick: $('#screen-notes-pick'),
  screenRoadmap: $('#screen-roadmap'),
  screenStats: $('#screen-stats'),
  screenHomework: $('#screen-homework'),
  screenTeacher: $('#screen-teacher'),
  homeworkPanel: $('#homework-panel'),
  screenPractice: $('#screen-practice'),
  btnGoMelodies: $('#btn-go-melodies'),
  btnGoNotes: $('#btn-go-notes'),
  btnGoRoadmap: $('#btn-go-roadmap'),
  btnGoRoadmapCard: $('#btn-go-roadmap-card'),
  btnBackRoadmap: $('#btn-back-roadmap'),
  roadmapPath: $('#roadmap-path'),
  roadmapRankEmoji: $('#roadmap-rank-emoji'),
  roadmapRankTitle: $('#roadmap-rank-title'),
  roadmapXpTotal: $('#roadmap-xp-total'),
  roadmapStagesDone: $('#roadmap-stages-done'),
  roadmapGuestHint: $('#roadmap-guest-hint'),
  btnRoadmapLogin: $('#btn-roadmap-login'),
  btnGoStatsHome: $('#btn-go-stats-home'),
  btnGoStats: $('#btn-go-stats'),
  btnGoHomework: $('#btn-go-homework'),
  btnGoTeacher: $('#btn-go-teacher'),
  btnBackStats: $('#btn-back-stats'),
  btnBackHomework: $('#btn-back-homework'),
  btnBackTeacher: $('#btn-back-teacher'),
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
  authTeacherOption: $('#auth-teacher-option'),
  authSocial: $('#auth-social'),
  authSocialButtons: $('#auth-social-buttons'),
  btnBackMelody: $('#btn-back-melody'),
  btnBackNotes: $('#btn-back-notes'),
  btnBackPractice: $('#btn-back-practice'),
  lessonList: $('#lesson-list'),
  melodySearch: $('#melody-search'),
  midiUpload: $('#midi-upload'),
  btnMidiUpload: $('#btn-midi-upload'),
  difficultyTabs: document.querySelectorAll('.difficulty-tab'),
  notesSettingsForm: $('#notes-settings-form'),
  notesSettingsError: $('#notes-settings-error'),
  practiceTitle: $('#practice-title'),
  practiceProgress: $('#practice-progress'),
  melodyPreviewPanel: $('#melody-preview-panel'),
  btnPreviewMelody: $('#btn-preview-melody'),
  practiceSessionProgress: $('#practice-session-progress'),
  practiceSessionProgressFill: $('#practice-session-progress-fill'),
  inputStatusBanner: $('#input-status-banner'),
  inputStatusDot: $('#input-status-dot'),
  inputStatusText: $('#input-status-text'),
  inputStatusMidiSelect: $('#input-status-midi-select'),
  btnInputConnectMidi: $('#btn-input-connect-midi'),
  btnInputConnectMic: $('#btn-input-connect-mic'),
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
  sessionModal: $('#session-modal'),
  modalCorrect: $('#modal-correct'),
  modalWrong: $('#modal-wrong'),
  modalAccuracy: $('#modal-accuracy'),
  modalSubtitle: $('#modal-subtitle'),
  btnModalRetry: $('#btn-modal-retry'),
  btnModalRoadmapNext: $('#btn-modal-roadmap-next'),
  btnModalRoadmapCapstone: $('#btn-modal-roadmap-capstone'),
  btnModalRoadmap: $('#btn-modal-roadmap'),
  btnModalPick: $('#btn-modal-pick'),
  btnModalHome: $('#btn-modal-home'),
};

let practiceWidgetsReady = true;

/** @type {PianoKeyboard} */
let piano;
/** @type {MelodyTrainer} */
let melodyTrainer;
/** @type {NoteTrainer} */
let noteTrainer;
/** @type {StaffView} */
let staffView;

try {
  piano = new PianoKeyboard(els.piano, PIANO_START, PIANO_END);
  melodyTrainer = new MelodyTrainer(piano);
  noteTrainer = new NoteTrainer(piano);
  staffView = new StaffView(els.staffViewport);
} catch (error) {
  practiceWidgetsReady = false;
  console.error('Не удалось инициализировать тренажёр.', error);
  piano = new PianoKeyboard(null, PIANO_START, PIANO_END);
  melodyTrainer = new MelodyTrainer(piano);
  noteTrainer = new NoteTrainer(piano);
  staffView = new StaffView(null);
}

let currentScreen = 'home';
let appMode = 'melody';
let selectedLessonId = null;
let selectedImportedId = null;
let noteSettings = structuredClone(DEFAULT_NOTE_SETTINGS);
let currentPracticeTitle = '';
let lessons = [];
const lessonCache = new Map();
let previewUiLessonId = null;
let remoteSearchResults = [];
let remoteSearchDone = false;
let searchQuery = '';
let searchRequestId = 0;
let selectedDifficultyFilter = 'all';
let lastSessionStats = null;
let cachedNoteStats = null;
let cachedRoadmapData = null;
let activeRoadmapStageId = null;
let activeRoadmapCapstone = false;
let lastRoadmapStageCompleted = false;
let lastRoadmapCapstoneReady = false;
let practiceReturnPath = ROUTES.home;
let activeHomeworkSubmissionId = null;

const midi = new MidiInput();
const micPitch = new MicPitchInput();

const PIANO_INPUT_SCREENS = new Set(['practice']);
const MIDI_DEVICE_KEY = 'piano-midi-device-id';
const INPUT_PREFS_KEY = 'piano-input-prefs';

function loadInputPrefs() {
  try {
    return JSON.parse(localStorage.getItem(INPUT_PREFS_KEY) ?? '{}');
  } catch {
    return {};
  }
}

function saveInputPrefs(partial) {
  try {
    localStorage.setItem(INPUT_PREFS_KEY, JSON.stringify({ ...loadInputPrefs(), ...partial }));
  } catch {
    // ignore quota / private mode
  }
}

function loadSavedMidiDeviceId() {
  try {
    return localStorage.getItem(MIDI_DEVICE_KEY) || null;
  } catch {
    return null;
  }
}

function persistMidiDeviceId() {
  if (!midi.selectedInputId) return;
  try {
    localStorage.setItem(MIDI_DEVICE_KEY, midi.selectedInputId);
  } catch {
    // ignore
  }
}

function showScreen(name) {
  currentScreen = name;
  const screens = {
    home: els.screenHome,
    'melody-pick': els.screenMelodyPick,
    'notes-pick': els.screenNotesPick,
    roadmap: els.screenRoadmap,
    stats: els.screenStats,
    homework: els.screenHomework,
    teacher: els.screenTeacher,
    practice: els.screenPractice,
  };

  for (const [key, el] of Object.entries(screens)) {
    if (!el) continue;
    const active = key === name;
    el.hidden = !active;
    el.classList.toggle('screen--active', active);
  }

  const isPractice = name === 'practice';
  const isHome = name === 'home';
  els.app?.classList.toggle('app--practice', isPractice);
  els.app?.classList.toggle('app--home', isHome);
  if (els.mainHeader) els.mainHeader.hidden = isPractice;
  document.body.classList.toggle('body--practice', isPractice);

  if (name === 'roadmap') {
    renderRoadmapScreen();
  }

  if (!['melody-pick', 'roadmap', 'practice'].includes(name) && isMelodyPreviewPlaying()) {
    stopMelodyPreview();
    handlePreviewStop();
  }

  updateInputStatusBanner();
}

function updateInputStatusBanner({ error } = {}) {
  if (!els.inputStatusBanner) return;

  if (!PIANO_INPUT_SCREENS.has(currentScreen)) {
    els.inputStatusBanner.hidden = true;
    return;
  }

  if (error) {
    els.inputStatusBanner.hidden = false;
    els.inputStatusBanner.className = 'input-status-banner practice-input-status practice-input-status--off';
    els.inputStatusText.textContent = error;
    if (els.btnInputConnectMidi) els.btnInputConnectMidi.hidden = !midi.isSupported;
    if (els.btnInputConnectMic) {
      els.btnInputConnectMic.hidden = !micPitch.isSupported;
      els.btnInputConnectMic.textContent = micPitch.isActive ? 'Выключить' : 'Микрофон';
    }
    return;
  }

  if (micPitch.isActive) {
    els.inputStatusBanner.hidden = false;
    els.inputStatusBanner.className = 'input-status-banner practice-input-status practice-input-status--on';
    els.inputStatusText.textContent = 'Микрофон: слушаю ноты';
    if (els.btnInputConnectMidi) els.btnInputConnectMidi.hidden = true;
    if (els.inputStatusMidiSelect) els.inputStatusMidiSelect.hidden = true;
    if (els.btnInputConnectMic) {
      els.btnInputConnectMic.hidden = !micPitch.isSupported;
      els.btnInputConnectMic.textContent = 'Выключить';
      els.btnInputConnectMic.classList.add('practice-input-status__btn--active');
    }
    return;
  }

  if (midi.isConnected) {
    els.inputStatusBanner.hidden = true;
    return;
  }

  els.inputStatusBanner.hidden = false;
  els.inputStatusBanner.className = 'input-status-banner practice-input-status practice-input-status--off';
  els.inputStatusText.textContent = prefersTouchInput()
    ? 'Пианино не подключено — нажимайте клавиши на экране или подключите MIDI'
    : 'Пианино не подключено — нажимайте клавиши на экране, клавиатуре ПК (A–L) или подключите MIDI';
  if (els.btnInputConnectMidi) {
    els.btnInputConnectMidi.hidden = !midi.isSupported;
    els.btnInputConnectMidi.textContent = 'Подключить MIDI';
  }
  if (els.btnInputConnectMic) {
    els.btnInputConnectMic.hidden = !micPitch.isSupported;
    els.btnInputConnectMic.textContent = 'Микрофон';
    els.btnInputConnectMic.classList.remove('practice-input-status__btn--active');
  }
  renderMidiDevices(midi.listInputs());
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

async function connectMidiDevice() {
  try {
    if (micPitch.isActive) stopMicListening({ persist: false });
    const name = await midi.connect();
    persistMidiDeviceId();
    saveInputPrefs({ micEnabled: false });
    renderMidiDevices(midi.listInputs());
    updateInputStatusBanner();
    return name;
  } catch (e) {
    updateInputStatusBanner({ error: e.message });
    throw e;
  }
}

async function restoreInputConnections() {
  const prefs = loadInputPrefs();

  if (prefs.micEnabled && micPitch.isSupported) {
    try {
      await startMicListening({ persist: false });
      return;
    } catch {
      saveInputPrefs({ micEnabled: false });
    }
  }

  const savedId = loadSavedMidiDeviceId();
  if (savedId && midi.isSupported) {
    midi.selectedInputId = savedId;
    try {
      await connectMidiDevice();
    } catch {
      // banner shows error when on a piano screen
    }
  }

  updateInputStatusBanner();
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
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
    current = state.correct;
    total = state.sessionLimit;
    els.practiceProgress.textContent = `${current} / ${total}`;
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

  const fromRoadmap = Boolean(stats.roadmapStageId);
  const stageCompleted = fromRoadmap && lastRoadmapStageCompleted;
  const capstoneReady = fromRoadmap && lastRoadmapCapstoneReady;
  const stage = fromRoadmap ? findStage(cachedRoadmapData, stats.roadmapStageId) : null;

  if (els.modalSubtitle) {
    if (stageCompleted) {
      els.modalSubtitle.textContent = stage
        ? `Уровень «${stage.title}» завершён! +${stage.xp} XP`
        : 'Уровень завершён!';
    } else if (capstoneReady && stage?.capstone) {
      els.modalSubtitle.textContent = `Все ноты освоены! Закрепите уровень мелодией «${getCapstoneLabel(stage)}»`;
    } else if (fromRoadmap && stats.roadmapCapstoneFailed) {
      els.modalSubtitle.textContent = `Нужна точность от ${stage?.capstone?.minAccuracy ?? 75}% — попробуйте ещё раз`;
    } else {
      const total = stats.total ?? stats.correct;
      els.modalSubtitle.textContent = fromRoadmap && isLoggedIn()
        ? `Сохраняем прогресс… (${total} ${pluralNotes(total)})`
        : `Вы прошли ${total} ${pluralNotes(total)}`;
    }
  }

  if (els.btnModalRoadmap) els.btnModalRoadmap.hidden = !fromRoadmap;
  if (els.btnModalRoadmapCapstone) {
    els.btnModalRoadmapCapstone.hidden = !(capstoneReady && stage?.capstone?.lessonId);
    if (!els.btnModalRoadmapCapstone.hidden) {
      els.btnModalRoadmapCapstone.textContent = `Закрепить: ${getCapstoneLabel(stage)}`;
    }
  }
  if (els.btnModalRoadmapNext) {
    const showNext = stageCompleted && stats.nextRoadmapStage;
    els.btnModalRoadmapNext.hidden = !showNext;
  }
  if (els.btnModalPick) {
    els.btnModalPick.hidden = fromRoadmap;
    els.btnModalPick.textContent = 'Другой урок';
  }

  els.sessionModal.hidden = false;
}

function refreshSessionModalRoadmap(stats) {
  if (!stats?.roadmapStageId || els.sessionModal?.hidden) return;

  const fromRoadmap = Boolean(stats.roadmapStageId);
  const stageCompleted = fromRoadmap && lastRoadmapStageCompleted;
  const capstoneReady = fromRoadmap && lastRoadmapCapstoneReady;
  const stage = findStage(cachedRoadmapData, stats.roadmapStageId);

  if (els.modalSubtitle) {
    if (stageCompleted) {
      els.modalSubtitle.textContent = stage
        ? `Уровень «${stage.title}» завершён! +${stage.xp} XP`
        : 'Уровень завершён!';
    } else if (capstoneReady && stage?.capstone) {
      els.modalSubtitle.textContent = `Все ноты освоены! Закрепите уровень мелодией «${getCapstoneLabel(stage)}»`;
    } else {
      const total = stats.total ?? stats.correct;
      els.modalSubtitle.textContent = `Вы прошли ${total} ${pluralNotes(total)}`;
    }
  }
  if (els.btnModalRoadmapCapstone) {
    els.btnModalRoadmapCapstone.hidden = !(capstoneReady && stage?.capstone?.lessonId);
    if (!els.btnModalRoadmapCapstone.hidden) {
      els.btnModalRoadmapCapstone.textContent = `Закрепить: ${getCapstoneLabel(stage)}`;
    }
  }
  if (els.btnModalRoadmapNext) {
    els.btnModalRoadmapNext.hidden = !(stageCompleted && stats.nextRoadmapStage);
  }
}

function pluralNotes(count) {
  const mod10 = count % 10;
  const mod100 = count % 100;
  if (mod10 === 1 && mod100 !== 11) return 'ноту';
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'ноты';
  return 'нот';
}

function readSessionLimitFromForm() {
  return readSessionLimitFromFormElement(els.notesSettingsForm);
}

function readTrainerOptionsFromPrefs() {
  const prefs = loadTrainerPrefs();
  return {
    soundEnabled: prefs?.soundEnabled ?? DEFAULT_TRAINER_OPTIONS.soundEnabled,
  };
}

function loadTrainerPrefs() {
  try {
    const raw = localStorage.getItem(TRAINER_PREFS_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw);
    return {
      soundEnabled: data.soundEnabled ?? DEFAULT_TRAINER_OPTIONS.soundEnabled,
    };
  } catch {
    return null;
  }
}

function saveTrainerPrefs(options) {
  localStorage.setItem(TRAINER_PREFS_KEY, JSON.stringify({
    soundEnabled: options.soundEnabled,
  }));
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

function isHomeworkPractice() {
  return activeHomeworkSubmissionId != null;
}

function syncPracticeControls() {
  if (!els.practiceControls) return;

  const inPractice = currentScreen === 'practice';
  els.practiceControls.hidden = !inPractice;
  if (els.melodyPreviewPanel) {
    els.melodyPreviewPanel.hidden = !(inPractice && appMode === 'melody' && melodyTrainer.lesson);
  }
  if (!inPractice) return;

  syncPracticeSoundPanel();
  syncKeyboardToggleUI();

  if (els.keyboardHintsPanel) {
    els.keyboardHintsPanel.hidden = isHomeworkPractice();
  }
}

function syncPreviewButton() {
  if (!els.btnPreviewMelody) return;

  const visible = currentScreen === 'practice' && appMode === 'melody' && melodyTrainer.lesson;
  if (els.melodyPreviewPanel) els.melodyPreviewPanel.hidden = !visible;
  if (!visible) return;

  const playing = isMelodyPreviewPlaying();
  els.btnPreviewMelody.classList.toggle('practice-preview__btn--active', playing);
  els.btnPreviewMelody.setAttribute('aria-pressed', playing ? 'true' : 'false');
  const label = els.btnPreviewMelody.querySelector('.practice-preview__label');
  if (label) label.textContent = playing ? 'Стоп' : 'Прослушать';
  const icon = els.btnPreviewMelody.querySelector('.practice-preview__icon use');
  if (icon) icon.setAttribute('href', playing ? '#ico-stop' : '#ico-volume');
}

function updatePreviewUi(activeId = null) {
  previewUiLessonId = activeId;
  document.querySelectorAll('[data-preview-lesson]').forEach((btn) => {
    const playing = activeId !== null && btn.dataset.previewLesson === activeId && isMelodyPreviewPlaying();
    btn.classList.toggle('lesson-card__preview--active', playing);
    btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
    btn.setAttribute('aria-label', playing ? 'Остановить прослушивание' : 'Прослушать мелодию');
  });
  syncPreviewButton();
}

function handlePreviewEvent(state) {
  if (!state?.event || currentScreen !== 'practice' || appMode !== 'melody') return;

  piano.clearStates(['correct', 'wrong', 'pressed']);
  piano.setTargets(state.event.notes.map((note) => note.midi), state.event.notes);
  staffView.update({
    ...state,
    results: [],
    correct: 0,
    wrong: 0,
  });
}

function handlePreviewStop() {
  piano.clearStates(['target', 'target-left', 'target-right', 'correct', 'wrong', 'pressed']);
  updatePreviewUi(null);
  melodyTrainer.resumeAfterPreview();

  if (currentScreen === 'practice' && appMode === 'melody' && melodyTrainer.lesson) {
    staffView.update({
      ...melodyTrainer.state,
      preview: false,
      events: melodyTrainer.lesson.events,
    });
    if (melodyTrainer.running) melodyTrainer.refreshKeyboardHighlight();
  }
}

async function fetchLessonById(id) {
  if (lessonCache.has(id)) return lessonCache.get(id);
  const lesson = normalizeLesson(await fetchJson(`/api/lessons/${id}`));
  lessonCache.set(id, lesson);
  return lesson;
}

async function previewLessonById(id, { remoteId = null, remoteTitle = '' } = {}) {
  unlockTrainerSoundFromGesture();

  if (isMelodyPreviewPlaying() && getMelodyPreviewId() === id) {
    stopMelodyPreview();
    handlePreviewStop();
    return;
  }

  try {
    let lesson;
    if (remoteId) {
      const res = await fetch(`/api/midi/${remoteId}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      lesson = normalizeLesson(await midiToLesson(await res.arrayBuffer(), {
        id,
        title: remoteTitle.trim() || 'MIDI мелодия',
        composer: 'FreeMidi',
      }));
    } else {
      lesson = await fetchLessonById(id);
    }

    stopMelodyPreview();
    if (currentScreen === 'practice' && appMode === 'melody' && melodyTrainer.running) {
      melodyTrainer.pauseForPreview();
    }

    updatePreviewUi(id);
    await playMelodyPreview(lesson, {
      id,
      onEvent: handlePreviewEvent,
      onComplete: handlePreviewStop,
      onStop: handlePreviewStop,
    });
  } catch {
    handlePreviewStop();
    alert('Не удалось прослушать мелодию');
  }
}

function previewCurrentMelody() {
  if (!melodyTrainer.lesson) return;
  unlockTrainerSoundFromGesture();
  const id = selectedLessonId ?? selectedImportedId ?? 'practice-current';
  if (isMelodyPreviewPlaying() && getMelodyPreviewId() === id) {
    stopMelodyPreview();
    handlePreviewStop();
    return;
  }

  if (melodyTrainer.running) melodyTrainer.pauseForPreview();

  updatePreviewUi(id);
  void playMelodyPreview(melodyTrainer.lesson, {
    id,
    onEvent: handlePreviewEvent,
    onComplete: handlePreviewStop,
    onStop: handlePreviewStop,
  });
}

function setTrainerSoundEnabled(enabled) {
  noteTrainer.soundEnabled = Boolean(enabled);
  syncSoundToggleUI();

  if (enabled) {
    void warmupTrainerSound();
  }

  saveTrainerPrefs({
    soundEnabled: noteTrainer.soundEnabled,
  });
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
          spelling: noteTrainer.currentSpelling,
          clef: noteTrainer.currentClef,
        });
      }
      setTimeout(() => piano.relayout({ scrollToDefault: true }), 120);
      setTimeout(() => piano.relayout({ scrollToDefault: true }), 320);
    });
  }
}

function setKeyboardHints(enabled, { persist = true } = {}) {
  if (persist) {
    keyboardHints = enabled;
  }
  melodyTrainer.showKeyboardHints = enabled;
  noteTrainer.showKeyboardHints = enabled;
  els.keyboardHintTabs?.forEach((tab) => {
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

function enterPractice(mode, title, { keyboardHints: hintsOverride, returnTo, returnPath } = {}) {
  if (returnPath) {
    practiceReturnPath = returnPath;
  } else if (returnTo) {
    practiceReturnPath = routeForScreen(returnTo);
  } else if (currentScreen !== 'practice') {
    practiceReturnPath = routeForScreen(currentScreen);
  }
  appMode = mode;
  currentPracticeTitle = title;
  els.practiceTitle.textContent = title;

  const homeworkHints = isHomeworkPractice() ? false : undefined;
  const resolvedHints = homeworkHints ?? hintsOverride;

  if (mode === 'notes') {
    if (resolvedHints === undefined) {
      setKeyboardHints(keyboardHints);
    } else {
      setKeyboardHints(resolvedHints, { persist: false });
    }
    syncPracticeControls();
    if (noteTrainer.soundEnabled) {
      void warmupTrainerSound();
    }
  } else {
    setKeyboardHints(resolvedHints ?? true, { persist: false });
    syncPracticeControls();
    if (noteTrainer.soundEnabled) {
      void warmupTrainerSound();
    }
  }
  resetPracticeProgress();
  showFeedback('', 'info');
  updateInputStatusBanner();
  showScreen('practice');
  setPianoVisible(true);
  trackPracticePageView(
    mode,
    title,
    mode === 'melody' ? melodyTrainer.lesson?.id ?? null : null,
  );
  trackGoal(mode === 'melody' ? 'practice_melody_start' : 'practice_notes_start');

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
      syncPreviewButton();
    });
  });
}

function exitPractice() {
  stopMelodyPreview();
  handlePreviewStop();
  melodyTrainer.reset();
  noteTrainer.stop();
  activeRoadmapStageId = null;
  activeRoadmapCapstone = false;
  activeHomeworkSubmissionId = null;
  staffView.clear();
  els.staffViewport.classList.remove('staff-viewport--grand');
  piano.clearStates();
  hideSessionModal();
  resetPracticeProgress();
  showFeedback('', 'info');
  syncPracticeControls();
}

function leavePractice() {
  const path = practiceReturnPath || ROUTES.home;
  exitPractice();
  navigateTo(path);
}

async function onSessionComplete(stats) {
  if (activeRoadmapStageId && !stats.roadmapStageId) {
    stats.roadmapStageId = activeRoadmapStageId;
  }

  lastRoadmapStageCompleted = false;
  lastRoadmapCapstoneReady = false;
  stats.nextRoadmapStage = null;

  if (stats.roadmapStageId && stats.mode === 'notes' && stats.attempts?.length) {
    if (!isLoggedIn()) {
      mergeGuestAttempts(stats.attempts);
    } else if (!cachedNoteStats) {
      try {
        cachedNoteStats = await loadNoteStats();
      } catch {
        // ignore — ниже используем только попытки текущей сессии
      }
    }
    const projected = isLoggedIn()
      ? projectNoteStatsFromAttempts(cachedNoteStats, stats.attempts)
      : null;
    updateRoadmapProgressFromSession(stats, projected);
  } else if (stats.roadmapStageId && stats.mode === 'melody' && activeRoadmapCapstone) {
    const stage = findStage(cachedRoadmapData, stats.roadmapStageId);
    if (stage && meetsCapstoneAccuracy(stage, stats.accuracy)) {
      markCapstoneComplete(stats.roadmapStageId);
      updateRoadmapProgressFromSession(stats);
    } else {
      stats.roadmapCapstoneFailed = true;
    }
  }

  showSessionModal(stats);
  trackGoal(stats.mode === 'melody' ? 'practice_melody_complete' : 'practice_notes_complete', {
    accuracy: stats.accuracy,
    correct: stats.correct,
    wrong: stats.wrong,
  });

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
    if (activeHomeworkSubmissionId) {
      await completeHomeworkSubmission(stats);
    }
    if (payload.mode === 'notes') {
      try {
        const data = await loadNoteStats();
        cachedNoteStats = data;
        if (stats.roadmapStageId) {
          updateRoadmapProgressFromSession(stats, data);
          refreshSessionModalRoadmap(stats);
        }
        await refreshRoadmapData(data);
        if (currentScreen === 'stats') {
          renderStatsPanel(data);
        }
      } catch {
        /* ignore */
      }
    }
  });
}

function updateRoadmapProgressFromSession(stats, noteStats = null) {
  if (!stats.roadmapStageId) return;

  if (!cachedRoadmapData) return;

  const before = findStageProgress(cachedRoadmapData, stats.roadmapStageId);
  const refreshed = isLoggedIn()
    ? buildRoadmapProgressFromStats(
      cachedRoadmapData,
      noteStats ?? projectNoteStatsFromAttempts(cachedNoteStats, stats.attempts ?? []),
    )
    : buildGuestRoadmapProgress(cachedRoadmapData);
  const after = refreshed.stages.find((item) => item.id === stats.roadmapStageId);
  lastRoadmapStageCompleted = Boolean(after?.completed && !before?.completed);
  lastRoadmapCapstoneReady = Boolean(after?.capstoneReady && !after?.completed);
  stats.nextRoadmapStage = lastRoadmapStageCompleted
    ? getNextStage(cachedRoadmapData, stats.roadmapStageId)
    : null;
  cachedRoadmapData.progress = refreshed;

  if (currentScreen === 'roadmap') {
    renderRoadmapScreen();
  }
}

function updateAuthUI() {
  const user = getUser();
  const loggedIn = Boolean(user);
  const isTeacher = hasRole('teacher');
  const isStudent = hasRole('student');

  if (els.btnOpenAuth) els.btnOpenAuth.hidden = loggedIn;
  if (els.authUser) els.authUser.hidden = !loggedIn;
  if (els.btnLogout) els.btnLogout.hidden = !loggedIn;
  if (els.authUserName) els.authUserName.textContent = user?.name ?? '';
  if (els.btnGoHomework) els.btnGoHomework.hidden = !isStudent;
  if (els.btnGoTeacher) els.btnGoTeacher.hidden = !isTeacher;
}

function updateRegisterTeacherOptionVisibility() {
  const hasInvite = Boolean(getInviteToken());
  if (els.authTeacherOption) {
    els.authTeacherOption.hidden = hasInvite;
  }
  const checkbox = els.authFormRegister?.querySelector('[name="is_teacher"]');
  if (checkbox) {
    if (hasInvite) {
      checkbox.checked = false;
    }
  }
}

function openAuthModal(tab = 'login') {
  if (!els.authModal) return;
  els.authModal.hidden = false;
  setAuthTab(tab);
  updateRegisterTeacherOptionVisibility();
  if (els.authErrorLogin) els.authErrorLogin.hidden = true;
  if (els.authErrorRegister) els.authErrorRegister.hidden = true;
}

function closeAuthModal() {
  if (!els.authModal) return;
  els.authModal.hidden = true;
}

function bindCriticalUi() {
  function handleClientNavClick(event, path) {
    if (!isClientAppPath(path)) {
      return;
    }
    event.preventDefault();
    void navigateApp(path);
  }

  function handleStatsNavClick(event) {
    if (!isLoggedIn()) {
      event.preventDefault();
      openAuthModal('login');
      return;
    }
    handleClientNavClick(event, ROUTES.stats);
  }

  document.querySelector('.header__brand-link')?.addEventListener('click', (event) => {
    handleClientNavClick(event, ROUTES.home);
  });

  document.querySelectorAll('.header__nav-link[href]').forEach((link) => {
    link.addEventListener('click', (event) => {
      const href = link.getAttribute('href');
      if (!href) return;
      handleClientNavClick(event, href);
    });
  });

  els.btnGoMelodies?.addEventListener('click', (event) => {
    event.preventDefault();
    navigateTo(ROUTES.melodies);
  });

  els.btnGoNotes?.addEventListener('click', (event) => {
    event.preventDefault();
    navigateTo(ROUTES.notes);
  });

  els.btnGoRoadmap?.addEventListener('click', (event) => {
    event.preventDefault();
    navigateTo(ROUTES.roadmap);
  });

  els.btnGoRoadmapCard?.addEventListener('click', (event) => {
    event.preventDefault();
    navigateTo(ROUTES.roadmap);
  });

  els.btnRoadmapLogin?.addEventListener('click', () => openAuthModal('login'));

  window.addEventListener('piano:open-auth', (event) => {
    openAuthModal(event.detail?.tab ?? 'login');
  });

  document.querySelectorAll('a[href="/statistika"]').forEach((link) => {
    link.addEventListener('click', handleStatsNavClick);
  });

  els.btnGoHomework?.addEventListener('click', (event) => {
    handleClientNavClick(event, ROUTES.homework);
  });

  els.btnGoTeacher?.addEventListener('click', (event) => {
    handleClientNavClick(event, ROUTES.teacher);
  });

  els.btnGoStats?.addEventListener('click', handleStatsNavClick);

  els.btnOpenAuth?.addEventListener('click', () => openAuthModal('login'));
  els.btnLogout?.addEventListener('click', async () => {
    await logout();
    cachedNoteStats = null;
    updateAuthUI();
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
    if (els.authErrorLogin) els.authErrorLogin.hidden = true;

    try {
      await login(form.get('email'), form.get('password'));
      await afterAuthSuccess();
    } catch (err) {
      if (els.authErrorLogin) {
        els.authErrorLogin.textContent = err.message;
        els.authErrorLogin.hidden = false;
      }
    }
  });

  els.authFormRegister?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    if (els.authErrorRegister) els.authErrorRegister.hidden = true;

    if (form.get('website')) {
      return;
    }

    const password = String(form.get('password') ?? '');
    const passwordConfirm = String(form.get('password_confirm') ?? '');
    if (password !== passwordConfirm) {
      if (els.authErrorRegister) {
        els.authErrorRegister.textContent = 'Пароли не совпадают';
        els.authErrorRegister.hidden = false;
      }
      return;
    }

    try {
      await register(
        form.get('name'),
        form.get('email'),
        password,
        passwordConfirm,
        form.get('website'),
        form.get('is_teacher') === '1',
      );
      await afterAuthSuccess();
    } catch (err) {
      if (els.authErrorRegister) {
        els.authErrorRegister.textContent = err.message;
        els.authErrorRegister.hidden = false;
      }
    }
  });
}

const OAUTH_PROVIDER_ICONS = {
  google: 'G',
  yandex: 'Я',
  vk: 'VK',
};

function renderOAuthProviders(providers) {
  if (!els.authSocial || !els.authSocialButtons) return;

  if (!providers.length) {
    els.authSocial.hidden = true;
    els.authSocialButtons.innerHTML = '';
    return;
  }

  els.authSocial.hidden = false;
  els.authSocialButtons.innerHTML = providers.map((provider) => `
    <button
      type="button"
      class="auth-social__btn auth-social__btn--${provider.id}"
      data-oauth-provider="${provider.id}"
    >
      <span class="auth-social__icon" aria-hidden="true">${OAUTH_PROVIDER_ICONS[provider.id] ?? '•'}</span>
      <span class="auth-social__label">${provider.label}</span>
    </button>
  `).join('');

  els.authSocialButtons.querySelectorAll('[data-oauth-provider]').forEach((btn) => {
    btn.addEventListener('click', () => {
      redirectToOAuth(btn.dataset.oauthProvider);
    });
  });
}

async function setupOAuthProviders() {
  const providers = await loadOAuthProviders();
  renderOAuthProviders(providers);
}

function handleOAuthRedirect() {
  const params = new URLSearchParams(window.location.search);
  const success = params.get('oauth') === 'success';
  const error = params.get('oauth_error');

  if (!success && !error) return;

  if (error) {
    openAuthModal('login');
    if (els.authErrorLogin) {
      els.authErrorLogin.textContent = error;
      els.authErrorLogin.hidden = false;
    }
  }

  params.delete('oauth');
  params.delete('oauth_error');
  const query = params.toString();
  const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
  window.history.replaceState({}, '', nextUrl);
}

async function initInviteFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('invite');
  if (!token) return;

  setInviteToken(token);
  updateRegisterTeacherOptionVisibility();
  params.delete('invite');
  const query = params.toString();
  const nextUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
  window.history.replaceState({}, '', nextUrl);

  const banner = document.getElementById('invite-banner');
  const bannerText = document.getElementById('invite-banner-text');
  const registerBtn = document.getElementById('btn-invite-register');

  try {
    const preview = await fetchJson(`/api/teacher/invite/${encodeURIComponent(token)}`);
    if (bannerText) {
      bannerText.textContent = `${preview.teacherName} приглашает вас зарегистрироваться и заниматься в Piano Bro.`;
    }
    if (banner) banner.hidden = false;
    if (registerBtn) {
      registerBtn.addEventListener('click', () => {
        openAuthModal('register');
        const emailInput = els.authFormRegister?.querySelector('[name="email"]');
        if (emailInput && preview.email) {
          emailInput.value = preview.email;
        }
      });
    }
    if (!isLoggedIn()) {
      openAuthModal('register');
      const emailInput = els.authFormRegister?.querySelector('[name="email"]');
      if (emailInput && preview.email) {
        emailInput.value = preview.email;
      }
    }
  } catch {
    if (bannerText) {
      bannerText.textContent = 'Ссылка-приглашение недействительна или уже использована.';
    }
    if (banner) banner.hidden = false;
  }
}

function setAuthTab(tab) {
  els.authTabs.forEach((btn) => {
    const active = btn.dataset.authTab === tab;
    btn.classList.toggle('auth-tab--active', active);
    btn.setAttribute('aria-selected', String(active));
  });
  els.authFormLogin && (els.authFormLogin.hidden = tab !== 'login');
  els.authFormRegister && (els.authFormRegister.hidden = tab !== 'register');
  if (tab === 'register') {
    updateRegisterTeacherOptionVisibility();
  }
  const title = tab === 'login' ? 'Вход' : 'Регистрация';
  const titleEl = $('#auth-modal-title');
  if (titleEl) titleEl.textContent = title;
}

function getLearningNotes(notes = []) {
  if (cachedRoadmapData) {
    const incomplete = getCurrentStageIncompleteNotes(cachedRoadmapData, { notes });
    if (incomplete.length) return incomplete;
  }
  return notes.filter((note) => note.level === 'learning' || note.level === 'needs_practice');
}

function bindStatsPanelTabs(root) {
  const tabs = root.querySelectorAll('[data-stats-tab]');
  const panels = root.querySelectorAll('[data-stats-panel]');
  if (!tabs.length || !panels.length) return;

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const tabId = tab.getAttribute('data-stats-tab');
      if (!tabId) return;

      tabs.forEach((item) => {
        const active = item.getAttribute('data-stats-tab') === tabId;
        item.classList.toggle('stats-tab--active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panels.forEach((panel) => {
        const active = panel.getAttribute('data-stats-panel') === tabId;
        panel.hidden = !active;
      });

      if (tabId === 'map') {
        const notesData = root._statsNotes;
        if (notesData) {
          mountStatsStaffChart(root, notesData);
        }
      }
    });
  });
}

function renderLearningNotesOffer(notes = []) {
  const learningNotes = getLearningNotes(notes);
  if (!learningNotes.length) return '';

  const maxTags = 10;
  const visible = learningNotes.slice(0, maxTags);
  const rest = learningNotes.length - visible.length;

  const tags = visible.map((note) => (
    `<span class="stats-practice-cta__tag">${escapeHtml(note.name)}</span>`
  )).join('');

  const moreTag = rest > 0
    ? `<span class="stats-practice-cta__tag stats-practice-cta__more">+${rest}</span>`
    : '';

  const stage = cachedRoadmapData
    ? findStage(cachedRoadmapData, cachedRoadmapData.progress?.currentStageId)
    : null;
  const offerText = stage
    ? `На уровне «${stage.title}» ${learningNotes.length} ${pluralNotes(learningNotes.length)} ещё не дошли до 2 верных подряд — можно потренировать только их.`
    : `${learningNotes.length} ${pluralNotes(learningNotes.length)} ещё не дошли до 2 верных подряд без ошибки — можно потренировать только их.`;

  return `
    <section class="stats-practice-cta" aria-label="Тренировка невыученных нот">
      <div class="stats-practice-cta__head">
        ${iconBadgeColored('learning', 'primary')}
        <h3 class="stats-practice-cta__title">Потренировать невыученные ноты</h3>
      </div>
      <p class="stats-practice-cta__text">
        ${offerText}
      </p>
      <div class="stats-practice-cta__tags">${tags}${moreTag}</div>
      <button type="button" class="btn btn--primary btn--sm" id="btn-stats-practice-learning">
        Начать тренировку
      </button>
    </section>
  `;
}

function startLearningNotesTraining(notes) {
  const learningNotes = getLearningNotes(notes);
  if (!learningNotes.length) return;

  activeRoadmapStageId = null;
  const midis = learningNotes.map((note) => note.midi);
  const options = readTrainerOptionsFromPrefs();

  saveTrainerPrefs(options);
  noteTrainer.setCustomPool(midis, { coverAll: true });
  noteTrainer.setOptions(options);
  noteTrainer.sessionLimit = DEFAULT_NOTE_SESSION_LIMIT;
  noteSettings = noteTrainer.settings;
  enterPractice('notes', 'Ноты в процессе', { returnPath: ROUTES.stats });
}

function bindStatsPanelActions(notes) {
  $('#btn-stats-practice-learning')?.addEventListener('click', () => {
    startLearningNotesTraining(notes);
  });
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
        Выучено — ноты, которые в этот день впервые дошли до 2 верных подряд. Повторено — ноты, уже выученные ранее.
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

  const { notes, dailyProgress } = data;
  const displayNotes = enrichNotesForRoadmapDisplay(notes, cachedRoadmapData);
  const offerHtml = renderLearningNotesOffer(notes);
  const staffHtml = renderStatsStaffInfographic(displayNotes);
  const chartHtml = renderStatsChart(dailyProgress);

  els.statsPanel.innerHTML = `
    <div class="stats-page">
      ${offerHtml}
      <nav class="stats-tabs" role="tablist" aria-label="Разделы статистики">
        <button type="button" class="stats-tab stats-tab--active" data-stats-tab="map" role="tab" aria-selected="true">Карта нот</button>
        <button type="button" class="stats-tab" data-stats-tab="activity" role="tab" aria-selected="false">Занятия</button>
      </nav>
      <div class="stats-tabpanels">
        <section class="stats-tabpanel" data-stats-panel="map" role="tabpanel">
          ${staffHtml}
        </section>
        <section class="stats-tabpanel" data-stats-panel="activity" role="tabpanel" hidden>
          ${chartHtml || '<p class="stats-tabpanel__empty">Пройдите тренировку нот — график по дням появится здесь.</p>'}
        </section>
      </div>
    </div>
  `;

  els.statsPanel._statsNotes = displayNotes;
  mountStatsStaffChart(els.statsPanel, displayNotes);
  bindStatsPanelTabs(els.statsPanel);
  bindStatsPanelActions(notes);
}

async function openStatsScreen() {
  showScreen('stats');
  if (!isLoggedIn()) {
    renderStatsPanel(null);
    return;
  }
  els.statsPanel.innerHTML = '<p class="loading">Загрузка статистики…</p>';
  try {
    const data = await loadNoteStats();
    cachedNoteStats = data;
    await refreshRoadmapData(data);
    renderStatsPanel(data);
  } catch {
    els.statsPanel.innerHTML = '<p class="loading">Не удалось загрузить статистику</p>';
  }
}

async function afterAuthSuccess() {
  updateAuthUI();
  await syncGuestProgressAfterAuth();
  closeAuthModal();
  if (window.location.pathname === ROUTES.stats) {
    await openStatsScreen();
  }
  if (window.location.pathname === ROUTES.homework) {
    await openHomeworkScreen();
  }
  if (window.location.pathname === ROUTES.teacher) {
    await openTeacherScreen();
  }
}

const HOMEWORK_STATUS_LABELS = {
  pending: 'Не выполнено',
  submitted: 'На проверке',
  completed: 'Выполнено',
  reviewed: 'Проверено',
};

function formatHomeworkDate(value) {
  if (!value) return '';
  try {
    return new Date(String(value).replace(' ', 'T') + 'Z').toLocaleString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return String(value);
  }
}

function renderHomeworkPanel(items) {
  if (!els.homeworkPanel) return;

  if (!isLoggedIn()) {
    els.homeworkPanel.innerHTML = `
      <div class="homework-guest">
        <p>Войдите в аккаунт, чтобы видеть задания от преподавателя.</p>
        <button type="button" class="btn btn--primary" id="btn-homework-login">Войти</button>
      </div>
    `;
    els.homeworkPanel.querySelector('#btn-homework-login')?.addEventListener('click', () => openAuthModal('login'));
    return;
  }

  if (!hasRole('student')) {
    els.homeworkPanel.innerHTML = `
      <p class="homework-empty">Раздел «Домашка» доступен ученикам, которых добавил преподаватель. Если вас пригласили — войдите по ссылке из письма или дождитесь добавления в список.</p>
    `;
    return;
  }

  if (!items.length) {
    els.homeworkPanel.innerHTML = `
      <p class="homework-empty">Пока нет заданий. Преподаватель назначит их после добавления вас в список учеников.</p>
    `;
    return;
  }

  const list = items.map((item) => {
    const pending = item.status === 'pending';
    const result = item.result ?? {};
    const accuracy = result.accuracy != null ? `${result.accuracy}%` : '';
    const noteCount = item.payload?.sessionLimit;
    const typeLabel = item.type === 'melody'
      ? 'Мелодия'
      : `Ноты${noteCount ? ` (${noteCount})` : ''}`;
    const due = item.dueAt ? `Срок: ${formatHomeworkDate(item.dueAt)}` : '';
    const teacherLabel = item.teacherName ?? item.className ?? 'Преподаватель';

    return `
      <article class="homework-card homework-card--${item.status}">
        <div class="homework-card__main">
          <h3 class="homework-card__title">${escapeHtml(item.title)}</h3>
          <p class="homework-card__meta">${escapeHtml(teacherLabel)} · ${typeLabel}${due ? ` · ${due}` : ''}</p>
          <p class="homework-card__status">${HOMEWORK_STATUS_LABELS[item.status] ?? item.status}${accuracy ? ` · ${accuracy}` : ''}</p>
          ${item.teacherComment ? `<p class="homework-card__comment">${escapeHtml(item.teacherComment)}</p>` : ''}
        </div>
        <div class="homework-card__actions">
          ${pending
    ? `<button type="button" class="btn btn--primary btn--sm" data-start-homework="${item.submissionId}">Выполнить</button>`
    : `<button type="button" class="btn btn--secondary btn--sm" data-start-homework="${item.submissionId}">Повторить</button>`}
        </div>
      </article>
    `;
  }).join('');

  els.homeworkPanel.innerHTML = `<div class="homework-list">${list}</div>`;
  els.homeworkPanel.querySelectorAll('[data-start-homework]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const submissionId = Number(btn.dataset.startHomework);
      const item = items.find((entry) => entry.submissionId === submissionId);
      if (item) startHomework(item);
    });
  });
}

function startHomework(item) {
  const payload = item.payload ?? {};
  activeHomeworkSubmissionId = item.submissionId;
  const config = {
    submissionId: item.submissionId,
    type: item.type,
    returnPath: ROUTES.homework,
  };

  if (item.type === 'melody' && payload.lessonId) {
    sessionStorage.setItem(PENDING_HOMEWORK_KEY, JSON.stringify({
      ...config,
      lessonId: payload.lessonId,
    }));
    navigateTo(ROUTES.practiceMelody(payload.lessonId));
    return;
  }

  sessionStorage.setItem(PENDING_HOMEWORK_KEY, JSON.stringify({
    ...config,
    settings: payload.settings ?? structuredClone(DEFAULT_NOTE_SETTINGS),
    sessionLimit: payload.sessionLimit ?? DEFAULT_NOTE_SESSION_LIMIT,
    options: payload.options ?? readTrainerOptionsFromPrefs(),
  }));
  navigateTo(ROUTES.practiceNotes);
}

async function openHomeworkScreen() {
  showScreen('homework');
  if (!isLoggedIn()) {
    renderHomeworkPanel([]);
    return;
  }

  if (!hasRole('student')) {
    renderHomeworkPanel([]);
    return;
  }

  els.homeworkPanel.innerHTML = '<p class="loading">Загрузка…</p>';
  try {
    const data = await fetchJson('/api/homework');
    renderHomeworkPanel(data.items ?? []);
  } catch {
    els.homeworkPanel.innerHTML = '<p class="loading">Не удалось загрузить задания</p>';
  }
}

let teacherModulePromise = null;

function loadTeacherModule() {
  if (!teacherModulePromise) {
    teacherModulePromise = import('./teacher.js');
  }
  return teacherModulePromise;
}

async function openTeacherScreen() {
  showScreen('teacher');
  const { initTeacher } = await loadTeacherModule();
  await initTeacher();
}

const CLIENT_APP_PATHS = new Set([
  ROUTES.home,
  ROUTES.roadmap,
  ROUTES.notes,
  ROUTES.melodies,
  ROUTES.stats,
  ROUTES.homework,
  ROUTES.teacher,
]);

function normalizeAppPath(path) {
  try {
    const url = new URL(path, window.location.origin);
    return url.pathname.replace(/\/+$/, '') || '/';
  } catch {
    return ROUTES.home;
  }
}

function isClientAppPath(path) {
  return CLIENT_APP_PATHS.has(normalizeAppPath(path));
}

function updateHeaderNavActive(path = window.location.pathname) {
  const normalized = normalizeAppPath(path);
  document.querySelectorAll('.header__nav-link[href]').forEach((link) => {
    const href = link.getAttribute('href');
    link.classList.toggle('header__nav-link--active', normalizeAppPath(href ?? '') === normalized);
  });
}

async function openScreenForPath(path) {
  const normalized = normalizeAppPath(path);

  switch (normalized) {
    case ROUTES.home:
      showScreen('home');
      break;
    case ROUTES.roadmap:
      await openRoadmapScreen();
      break;
    case ROUTES.notes:
      showScreen('notes-pick');
      break;
    case ROUTES.melodies:
      showScreen('melody-pick');
      break;
    case ROUTES.stats:
      await openStatsScreen();
      break;
    case ROUTES.homework:
      await openHomeworkScreen();
      break;
    case ROUTES.teacher:
      await openTeacherScreen();
      break;
    default:
      window.location.assign(path);
      return;
  }

  updateHeaderNavActive(normalized);
}

async function navigateApp(path, { replace = false } = {}) {
  const normalized = normalizeAppPath(path);

  if (!isClientAppPath(normalized)) {
    window.location.assign(path);
    return;
  }

  if (currentScreen === 'practice') {
    exitPractice();
  }

  if (normalized !== normalizeAppPath(window.location.pathname)) {
    history[replace ? 'replaceState' : 'pushState']({ path: normalized }, '', normalized);
  }

  await openScreenForPath(normalized);
}

setNavigateImpl(navigateApp);
bindCriticalUi();

window.addEventListener('popstate', () => {
  void openScreenForPath(window.location.pathname);
});

async function completeHomeworkSubmission(stats) {
  if (!activeHomeworkSubmissionId || !isLoggedIn()) return;

  const errors = (stats.attempts ?? [])
    .filter((attempt) => !attempt.correct)
    .map((attempt) => ({
      midi: attempt.midi,
      name: midiToName(attempt.midi),
      expected: attempt.expectedMidi != null ? midiToName(attempt.expectedMidi) : null,
    }));

  try {
    await fetchJson(`/api/homework/${activeHomeworkSubmissionId}/complete`, {
      method: 'POST',
      body: JSON.stringify({
        result: {
          correct: stats.correct,
          wrong: stats.wrong,
          accuracy: stats.accuracy,
          total: stats.total,
          mode: stats.mode ?? appMode,
        },
        errors,
      }),
    });
  } catch {
    /* ignore */
  } finally {
    activeHomeworkSubmissionId = null;
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

  const cardInner = `
        <span class="lesson-card__icon icon-badge icon-badge--melody" aria-hidden="true">${icon('melody', 'icon icon--badge')}</span>
        <span class="lesson-card__body">
          <span class="lesson-card__title">${escapeHtml(lesson.title)}</span>
          <span class="lesson-card__meta">${escapeHtml(metaParts.join(' · '))}</span>
        </span>
        <span class="lesson-card__play" aria-hidden="true">${icon('play', 'icon icon--sm')}</span>`;

  const cardOpen = remote
    ? `<button type="button" class="lesson-card lesson-card--remote${lesson.category === 'popular' ? ' lesson-card--popular' : ''}" data-id="${escapeHtml(lesson.id)}" data-remote-id="${lesson.remoteId}">`
    : `<a href="${escapeHtml(ROUTES.practiceMelody(lesson.id))}" class="lesson-card${lesson.category === 'popular' ? ' lesson-card--popular' : ''}">`;
  const cardClose = remote ? '</button>' : '</a>';

  return `
    <article class="lesson-card-wrap">
      <button type="button"
              class="lesson-card__preview"
              data-preview-lesson="${escapeHtml(lesson.id)}"
              ${remote ? `data-preview-remote-id="${lesson.remoteId}"` : ''}
              aria-label="Прослушать мелодию">
        ${icon('volume', 'icon icon--sm')}
      </button>
      ${cardOpen}
        ${cardInner}
      ${cardClose}
    </article>
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
  updatePreviewUi(previewUiLessonId);

  els.lessonList.querySelectorAll('.lesson-card[data-remote-id]').forEach((card) => {
    card.addEventListener('click', (event) => {
      event.preventDefault();
      stopMelodyPreview();
      handlePreviewStop();
      loadRemoteMidi(Number(card.dataset.remoteId), card.querySelector('.lesson-card__title')?.textContent ?? '');
    });
  });

  els.lessonList.querySelectorAll('[data-preview-lesson]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      void previewLessonById(btn.dataset.previewLesson, {
        remoteId: btn.dataset.previewRemoteId ? Number(btn.dataset.previewRemoteId) : null,
        remoteTitle: btn.closest('.lesson-card-wrap')?.querySelector('.lesson-card__title')?.textContent ?? '',
      });
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

function loadMelodyLesson(lesson, { activeId = null, title = null, sessionLimit = SESSION_LIMIT, returnPath } = {}) {
  stopMelodyPreview();
  handlePreviewStop();
  const normalized = normalizeLesson(lesson);
  melodyTrainer.loadLesson(normalized, { sessionLimit });
  noteTrainer.sessionLimit = SESSION_LIMIT;

  if (activeId?.startsWith('remote-')) {
    selectedLessonId = null;
    selectedImportedId = activeId;
  } else if (activeId) {
    selectedLessonId = activeId;
    selectedImportedId = null;
  }

  enterPractice('melody', title ?? normalized.title, { returnPath });
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
  return readNoteSettingsFromFormElement(els.notesSettingsForm);
}

function applyNoteSettingsToForm(settings) {
  applyNoteSettingsToFormElement(els.notesSettingsForm, settings);
}

function applySessionLimitToForm(limit = DEFAULT_NOTE_SESSION_LIMIT) {
  applySessionLimitToFormElement(els.notesSettingsForm, limit);
}

async function refreshRoadmapData(noteStats = null) {
  try {
    const data = await loadRoadmap();
    if (noteStats) {
      data.progress = buildRoadmapProgressFromStats(data, noteStats);
    } else if (!isLoggedIn()) {
      data.progress = buildGuestRoadmapProgress(data);
    } else {
      try {
        cachedNoteStats = await loadNoteStats();
        data.progress = buildRoadmapProgressFromStats(data, cachedNoteStats);
      } catch {
        if (data.progress) {
          data.progress = mergeCapstoneIntoProgress(data, data.progress);
        }
      }
    }
    cachedRoadmapData = data;
    if (currentScreen === 'roadmap') {
      renderRoadmapScreen();
    }
    return data;
  } catch {
    return null;
  }
}

async function syncGuestProgressAfterAuth() {
  if (!isLoggedIn()) return;

  const entries = getGuestNoteEntries();
  if (entries.length) {
    try {
      await mergeGuestNoteStats(entries);
      clearGuestNoteMap();
    } catch {
      // ignore — серверная статистика остаётся как есть
    }
  }

  cachedNoteStats = null;
  await refreshRoadmapData();
}

function renderRoadmapScreen() {
  if (!cachedRoadmapData || !els.roadmapPath) return;

  const { stages, progress, ranks } = cachedRoadmapData;
  const rank = progress?.rank ?? ranks?.[0] ?? { title: 'Новичок', emoji: '🌱' };

  if (els.roadmapRankEmoji) els.roadmapRankEmoji.textContent = rank.emoji ?? '🌱';
  if (els.roadmapRankTitle) els.roadmapRankTitle.textContent = rank.title ?? 'Новичок';
  if (els.roadmapXpTotal) els.roadmapXpTotal.textContent = String(progress?.totalXp ?? 0);
  if (els.roadmapStagesDone) {
    els.roadmapStagesDone.textContent = `${progress?.completedCount ?? 0}/${progress?.totalStages ?? stages.length}`;
  }
  if (els.roadmapGuestHint) els.roadmapGuestHint.hidden = isLoggedIn();

  els.roadmapPath.innerHTML = stages.map((stage) => {
    const item = progress?.stages?.find((entry) => entry.id === stage.id) ?? {
      progress: 0,
      completed: false,
      unlocked: false,
      masteredNotes: 0,
      poolSize: 0,
    };
    const isCurrent = progress?.currentStageId === stage.id;
    const stateClass = item.completed
      ? 'roadmap-stage--completed'
      : isCurrent
        ? item.capstoneReady
          ? 'roadmap-stage--current roadmap-stage--capstone-ready'
          : 'roadmap-stage--current'
        : item.unlocked
          ? 'roadmap-stage--active'
          : 'roadmap-stage--locked';

    const isLocked = !item.unlocked && !item.completed;
    const displayProgress = isLocked ? 0 : item.progress;

    const status = item.completed
      ? `<span class="roadmap-stage__status roadmap-stage__status--done">✓ Пройдено · ${item.masteredNotes}/${item.poolSize} нот</span>`
      : isLocked
        ? '<span class="roadmap-stage__status roadmap-stage__status--locked">🔒 Закрыто</span>'
        : item.capstoneReady
          ? `<span class="roadmap-stage__status roadmap-stage__status--capstone">Шаг 2 из 2 · ноты освоены, осталось сыграть мелодию</span>`
          : `<span class="roadmap-stage__progress-text"><strong>${item.progress}%</strong> · ${item.masteredNotes}/${item.poolSize} нот</span>`;

    const incompleteNotes = !isLocked && !item.notesComplete
      ? getStageIncompleteNotes(stage, cachedNoteStats)
      : [];
    const remainingHtml = incompleteNotes.length
      ? `<p class="roadmap-stage__remaining">Осталось: ${incompleteNotes.map((note) => escapeHtml(note.name)).join(', ')}</p>`
      : '';

    const capstoneBlock = stage.capstone?.lessonId && !isLocked
      ? `<div class="roadmap-stage__capstone${item.capstoneReady ? ' roadmap-stage__capstone--next' : ''}">
          <span class="roadmap-stage__capstone-label">${item.capstoneReady ? '▶ Следующий шаг' : '🎼 Закрепление:'}</span>
          <span class="roadmap-stage__capstone-title">${getCapstoneLabel(stage)}</span>
          ${item.capstoneComplete ? '<span class="roadmap-stage__capstone-done">✓</span>' : ''}
          <button type="button" class="roadmap-stage__capstone-preview" data-roadmap-preview="${stage.capstone.lessonId}" aria-label="Прослушать мелодию">
            ${icon('volume', 'icon icon--sm')}
          </button>
        </div>`
      : '';

    let actions = '';
    if (item.completed) {
      actions = `<button type="button" class="btn btn--secondary btn--sm" data-roadmap-play="${stage.id}">Повторить ноты</button>`;
      if (stage.capstone?.lessonId) {
        actions += `<button type="button" class="btn btn--secondary btn--sm" data-roadmap-melody="${stage.id}">Мелодия</button>`;
      }
    } else if (item.unlocked) {
      if (item.capstoneReady && stage.capstone?.lessonId) {
        actions = `<button type="button" class="btn btn--primary btn--sm" data-roadmap-melody="${stage.id}">Сыграть мелодию · завершить уровень</button>`;
        actions += `<button type="button" class="btn btn--secondary btn--sm" data-roadmap-play="${stage.id}">Повторить ноты</button>`;
      } else {
        const notesLabel = isCurrent ? 'Продолжить' : 'Начать';
        actions = `<button type="button" class="btn btn--primary btn--sm" data-roadmap-play="${stage.id}">${notesLabel} · ${item.poolSize} нот</button>`;
      }
    } else {
      actions = '<span class="roadmap-stage__lock">🔒 Пройдите предыдущий уровень</span>';
    }

    return `
      <article class="roadmap-stage ${stateClass}">
        <div class="roadmap-stage__track">
          <div class="roadmap-stage__node">
            <div class="roadmap-stage__ring" style="--progress: ${displayProgress}%"></div>
            <span class="roadmap-stage__badge">${stage.emoji ?? stage.badge ?? '•'}</span>
          </div>
          <div class="roadmap-stage__connector"></div>
        </div>
        <div class="roadmap-stage__card">
          <div class="roadmap-stage__head">
            <div>
              <h3 class="roadmap-stage__title">${stage.title}</h3>
              <p class="roadmap-stage__subtitle">${stage.subtitle ?? ''}</p>
            </div>
            <span class="roadmap-stage__xp">+${stage.xp} XP</span>
          </div>
          <p class="roadmap-stage__desc">${stage.description ?? ''}</p>
          ${capstoneBlock}
          <div class="roadmap-stage__meta">${status}</div>
          ${remainingHtml}
          <div class="roadmap-stage__bar" aria-hidden="true">
            <div class="roadmap-stage__bar-fill" style="width: ${displayProgress}%"></div>
          </div>
          <div class="roadmap-stage__actions">${actions}</div>
        </div>
      </article>
    `;
  }).join('');

  els.roadmapPath.querySelectorAll('[data-roadmap-play]').forEach((btn) => {
    btn.addEventListener('click', () => startRoadmapStage(btn.dataset.roadmapPlay));
  });
  els.roadmapPath.querySelectorAll('[data-roadmap-melody]').forEach((btn) => {
    btn.addEventListener('click', () => startRoadmapMelody(btn.dataset.roadmapMelody));
  });
  els.roadmapPath.querySelectorAll('[data-roadmap-preview]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      void previewLessonById(btn.dataset.roadmapPreview);
    });
  });
}

async function openRoadmapScreen() {
  if (isLoggedIn()) {
    try {
      cachedNoteStats = await loadNoteStats();
    } catch {
      cachedNoteStats = null;
    }
  }
  await refreshRoadmapData(cachedNoteStats);
  showScreen('roadmap');

  const pendingStageId = sessionStorage.getItem('piano-pending-roadmap-stage');
  if (pendingStageId) {
    sessionStorage.removeItem('piano-pending-roadmap-stage');
    void startRoadmapStage(pendingStageId);
  }
}

async function startRoadmapStage(stageId) {
  const stage = findStage(cachedRoadmapData, stageId);
  const progress = findStageProgress(cachedRoadmapData, stageId);
  if (!stage) return;
  if (progress && !progress.unlocked && !progress.completed) return;

  if (!cachedRoadmapData) {
    await refreshRoadmapData(cachedNoteStats);
  }

  const pool = buildPoolForStage(stage);
  if (!pool.length) return;

  activeRoadmapStageId = stageId;
  activeRoadmapCapstone = false;
  const options = readTrainerOptionsFromPrefs();
  saveTrainerPrefs(options);
  noteSettings = stage.settings;
  noteTrainer.setCustomPool(pool, { coverAll: true });
  noteTrainer.setOptions(options);
  enterPractice('notes', `Путь: ${stage.title}`, { keyboardHints: false, returnPath: ROUTES.roadmap });
}

async function startRoadmapMelody(stageId) {
  const stage = findStage(cachedRoadmapData, stageId);
  const progress = findStageProgress(cachedRoadmapData, stageId);
  if (!stage?.capstone?.lessonId) return;
  if (progress && !progress.unlocked && !progress.completed) return;
  if (!progress?.notesComplete && !progress?.completed) return;

  if (!cachedRoadmapData) {
    await refreshRoadmapData(cachedNoteStats);
  }

  try {
    const res = await fetch(`/api/lessons/${stage.capstone.lessonId}`, { credentials: 'same-origin' });
    const lesson = await res.json();
    if (!res.ok) throw new Error(lesson.error || `HTTP ${res.status}`);

    activeRoadmapStageId = stageId;
    activeRoadmapCapstone = true;
    selectedLessonId = stage.capstone.lessonId;
    selectedImportedId = null;
    loadMelodyLesson(lesson, {
      activeId: stage.capstone.lessonId,
      title: `Закрепление: ${getCapstoneLabel(stage)}`,
      sessionLimit: null,
      returnPath: ROUTES.roadmap,
    });
  } catch {
    alert('Не удалось загрузить мелодию для закрепления.');
  }
}

function startNotesTraining() {
  activeRoadmapStageId = null;
  const settings = readNoteSettingsFromForm();
  const options = readTrainerOptionsFromPrefs();
  const error = validateNoteSettings(settings);

  if (error) {
    els.notesSettingsError.textContent = error;
    els.notesSettingsError.hidden = false;
    return;
  }

  els.notesSettingsError.hidden = true;
  sessionStorage.setItem(PENDING_NOTES_PRACTICE_KEY, JSON.stringify({
    settings,
    options,
    sessionLimit: readSessionLimitFromForm(),
  }));
  navigateTo(ROUTES.practiceNotes);
}

function readPendingHomework() {
  try {
    const homeworkRaw = sessionStorage.getItem(PENDING_HOMEWORK_KEY);
    if (!homeworkRaw) return null;
    sessionStorage.removeItem(PENDING_HOMEWORK_KEY);
    return JSON.parse(homeworkRaw);
  } catch {
    return null;
  }
}

function bootNotesPractice(homework = null) {
  activeRoadmapStageId = null;
  let config = null;

  if (!homework) {
    try {
      const raw = sessionStorage.getItem(PENDING_NOTES_PRACTICE_KEY);
      if (raw) {
        config = JSON.parse(raw);
        sessionStorage.removeItem(PENDING_NOTES_PRACTICE_KEY);
      }
    } catch {
      config = null;
    }
  }

  if (homework?.submissionId) {
    activeHomeworkSubmissionId = homework.submissionId;
  }

  const settings = homework?.settings ?? config?.settings ?? structuredClone(DEFAULT_NOTE_SETTINGS);
  const options = homework?.options ?? config?.options ?? readTrainerOptionsFromPrefs();
  const sessionLimit = homework?.sessionLimit ?? config?.sessionLimit ?? DEFAULT_NOTE_SESSION_LIMIT;
  const returnPath = homework?.returnPath ?? config?.returnPath ?? ROUTES.notes;

  saveTrainerPrefs(options);
  noteSettings = settings;
  noteTrainer.setConfig(settings);
  noteTrainer.setOptions(options);
  noteTrainer.sessionLimit = sessionLimit;
  enterPractice('notes', describeNoteSettings(settings, options), { returnPath });
}

async function selectLesson(id, { returnPath } = {}) {
  try {
    const lesson = normalizeLesson(await fetchJson(`/api/lessons/${id}`));
    loadMelodyLesson(lesson, {
      activeId: id,
      returnPath: returnPath ?? ROUTES.melodies,
    });
  } catch {
    alert('Не удалось загрузить урок');
  }
}

async function bootPractice(boot) {
  const homework = readPendingHomework();

  if (homework?.submissionId) {
    activeHomeworkSubmissionId = homework.submissionId;
  }

  if (boot.mode === 'melody' && boot.lessonId) {
    practiceReturnPath = homework?.returnPath ?? boot.returnPath ?? ROUTES.melodies;
    await selectLesson(boot.lessonId, { returnPath: practiceReturnPath });
    return;
  }
  if (boot.mode === 'notes') {
    practiceReturnPath = homework?.returnPath ?? boot.returnPath ?? ROUTES.notes;
    bootNotesPractice(homework);
  }
}

async function bootApp() {
  const boot = window.__BOOT__ ?? { screen: 'home' };

  switch (boot.screen) {
    case 'roadmap':
      await openRoadmapScreen();
      break;
    case 'stats':
      await openStatsScreen();
      break;
    case 'homework':
      await openHomeworkScreen();
      break;
    case 'teacher':
      await openTeacherScreen();
      break;
    case 'melody-pick':
      if (boot.redirectToPractice && boot.focusLessonId) {
        navigateTo(ROUTES.practiceMelody(boot.focusLessonId));
        break;
      }
      showScreen('melody-pick');
      break;
    case 'notes-pick':
      showScreen('notes-pick');
      break;
    case 'practice':
      await bootPractice(boot);
      break;
    default:
      showScreen('home');
  }
}

function onNoteOn(midiNote) {
  piano.pressKey(midiNote);
  const trainerRunning = appMode === 'melody'
    ? melodyTrainer.running
    : noteTrainer.running;
  if (noteTrainer.soundEnabled && trainerRunning) {
    void playTrainerNote(midiNote, 0.55);
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

micPitch.onStatusChange = () => {
  updateInputStatusBanner();
};

async function startMicListening({ persist = true } = {}) {
  try {
    await micPitch.start();
    if (persist) saveInputPrefs({ micEnabled: true });
    updateInputStatusBanner();
  } catch (error) {
    if (persist) saveInputPrefs({ micEnabled: false });
    updateInputStatusBanner({ error: error?.message ?? 'Не удалось включить микрофон' });
  }
}

function stopMicListening({ persist = true } = {}) {
  micPitch.stop();
  if (persist) saveInputPrefs({ micEnabled: false });
  updateInputStatusBanner();
}

function renderMidiDevices(inputs) {
  const select = els.inputStatusMidiSelect;
  if (!select) return;

  if (!inputs.length || inputs.length < 2 || midi.isConnected || micPitch.isActive) {
    select.hidden = true;
    select.disabled = true;
    return;
  }

  select.hidden = false;
  select.disabled = false;
  select.replaceChildren();
  for (const i of inputs) {
    const opt = document.createElement('option');
    opt.value = i.id;
    opt.textContent = i.name;
    opt.selected = i.selected;
    select.appendChild(opt);
  }
}

midi.onInputsChanged = (inputs) => {
  renderMidiDevices(inputs);
  updateInputStatusBanner();
};

melodyTrainer.onUpdate = (state) => {
  if (appMode === 'melody' && currentScreen === 'practice') updateMelodyUI(state);
};
melodyTrainer.onFeedback = showFeedback;
melodyTrainer.onComplete = (stats) => {
  if (activeRoadmapStageId) {
    stats.roadmapStageId = activeRoadmapStageId;
  }
  onSessionComplete(stats);
};

noteTrainer.onUpdate = (state) => {
  if (appMode === 'notes' && currentScreen === 'practice') updateNoteUI(state);
};
noteTrainer.onFeedback = showFeedback;
noteTrainer.onComplete = (stats) => {
  if (activeRoadmapStageId) {
    stats.roadmapStageId = activeRoadmapStageId;
  }
  onSessionComplete(stats);
};
noteTrainer.onNoteChange = (midiNote, { spelling, clef } = {}) => {
  showNoteDrillStaff(midiNote, { spelling, clef });
};

els.notesSettingsForm?.addEventListener('submit', (e) => {
  e.preventDefault();
  startNotesTraining();
});

els.notesSettingsForm?.addEventListener('change', () => {
  if (!els.notesSettingsError.hidden) els.notesSettingsError.hidden = true;
});

function openNotesPickScreen() {
  showScreen('notes-pick');
}

els.keyboardToggleTabs?.forEach((tab) => {
  tab.addEventListener('click', () => {
    setPianoVisible(tab.dataset.keyboard === 'on');
  });
});

els.keyboardHintTabs?.forEach((tab) => {
  tab.addEventListener('click', () => {
    if (isHomeworkPractice()) return;
    setKeyboardHints(tab.dataset.hints === 'on');
  });
});

els.soundToggleTabs?.forEach((tab) => {
  tab.addEventListener('click', () => {
    setTrainerSoundEnabled(tab.dataset.sound === 'on');
  });
});

els.screenPractice?.addEventListener('touchstart', () => {
  if (currentScreen === 'practice' && noteTrainer.soundEnabled) {
    void warmupTrainerSound();
  }
}, { passive: true });

els.btnBackMelody?.addEventListener('click', (event) => {
  event.preventDefault();
  navigateTo(ROUTES.home);
});
els.btnBackNotes?.addEventListener('click', (event) => {
  event.preventDefault();
  navigateTo(ROUTES.home);
});
els.btnBackRoadmap?.addEventListener('click', (event) => {
  event.preventDefault();
  navigateTo(ROUTES.home);
});
els.btnBackStats?.addEventListener('click', (event) => {
  event.preventDefault();
  navigateTo(ROUTES.home);
});
els.btnBackHomework?.addEventListener('click', (event) => {
  event.preventDefault();
  navigateTo(ROUTES.home);
});
els.btnBackTeacher?.addEventListener('click', (event) => {
  event.preventDefault();
  navigateTo(ROUTES.home);
});

els.btnBackPractice?.addEventListener('click', () => {
  leavePractice();
});

els.btnPreviewMelody?.addEventListener('click', () => {
  unlockTrainerSoundFromGesture();
  previewCurrentMelody();
});

els.melodySearch?.addEventListener('input', () => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    runMelodySearch(els.melodySearch.value);
  }, 350);
});

els.melodySearch?.addEventListener('search', () => {
  clearTimeout(searchDebounceTimer);
  runMelodySearch(els.melodySearch.value);
});

els.difficultyTabs?.forEach((tab) => {
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

els.btnModalRetry?.addEventListener('click', () => {
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

els.btnModalPick?.addEventListener('click', () => {
  leavePractice();
});

els.btnModalRoadmap?.addEventListener('click', () => {
  hideSessionModal();
  exitPractice();
  navigateTo(ROUTES.roadmap);
});

els.btnModalRoadmapNext?.addEventListener('click', () => {
  const nextStage = lastSessionStats?.nextRoadmapStage;
  hideSessionModal();
  exitPractice();
  if (nextStage?.id) {
    sessionStorage.setItem('piano-pending-roadmap-stage', nextStage.id);
  }
  navigateTo(ROUTES.roadmap);
});

els.btnModalRoadmapCapstone?.addEventListener('click', () => {
  const stageId = lastSessionStats?.roadmapStageId;
  hideSessionModal();
  exitPractice();
  if (stageId) void startRoadmapMelody(stageId);
});

els.btnModalHome?.addEventListener('click', () => {
  exitPractice();
  navigateTo(ROUTES.home);
});

els.sessionModal?.querySelector('.modal__backdrop')?.addEventListener('click', () => {
  hideSessionModal();
});

els.btnInputConnectMidi?.addEventListener('click', async () => {
  try {
    await connectMidiDevice();
  } catch {
    // status already updated in connectMidiDevice
  }
});

els.btnInputConnectMic?.addEventListener('click', async () => {
  if (micPitch.isActive) {
    stopMicListening();
    return;
  }
  await startMicListening();
});

els.inputStatusMidiSelect?.addEventListener('change', () => {
  midi.selectInput(els.inputStatusMidiSelect.value);
  persistMidiDeviceId();
  renderMidiDevices(midi.listInputs());
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
setupOAuthProviders();
handleOAuthRedirect();
initMetrikaPageview();
initAnalytics();
void initInviteFromUrl();
if (window.__USER__ !== undefined) {
  updateAuthUI();
}
initAuth().then(async () => {
  updateAuthUI();
  const inviteToken = getInviteToken();
  if (inviteToken && isLoggedIn()) {
    try {
      await fetchJson('/api/invite/accept', {
        method: 'POST',
        body: JSON.stringify({ token: inviteToken }),
      });
      setInviteToken('');
      const banner = document.getElementById('invite-banner');
      if (banner) banner.hidden = true;
    } catch {
      /* ignore */
    }
  }
  await syncGuestProgressAfterAuth();
  await bootApp();
});
applyNoteSettingsToForm(DEFAULT_NOTE_SETTINGS);
applySessionLimitToForm(DEFAULT_NOTE_SESSION_LIMIT);
noteTrainer.sessionLimit = DEFAULT_NOTE_SESSION_LIMIT;
noteTrainer.setOptions(loadTrainerPrefs() ?? DEFAULT_TRAINER_OPTIONS);
setPianoVisible(false);
melodyTrainer.showKeyboardHints = true;
noteTrainer.showKeyboardHints = true;

const savedMidiId = loadSavedMidiDeviceId();
if (savedMidiId) midi.selectedInputId = savedMidiId;

void restoreInputConnections();

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
      spelling: noteTrainer.currentSpelling,
      clef: noteTrainer.currentClef,
    });
  }
});

window.visualViewport?.addEventListener('resize', () => {
  if (currentScreen === 'practice' && !els.practiceKeyboardArea.hidden) {
    piano.relayout();
  }
});
