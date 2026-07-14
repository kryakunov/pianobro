import { PianoKeyboard } from './piano.js';
import { MidiInput } from './midi.js';
import { MicPitchInput } from './mic-pitch.js';
import { MelodyTrainer } from './trainer.js';
import { NoteTrainer, DEFAULT_NOTE_SETTINGS, DEFAULT_NOTE_SESSION_LIMIT, NOTE_SESSION_LIMITS, buildPoolFromSettings, describeNoteSettings, usesBothClefs } from './note-trainer.js';
import { StaffView } from './staff.js';
import { normalizeLesson } from './lesson-utils.js';
import { midiToLesson } from './midi-import.js';
import { KEYBOARD_MAP, midiToName, PIANO_START, PIANO_END } from './notes.js';

const SESSION_LIMIT = 10;

const $ = (sel) => document.querySelector(sel);

const els = {
  app: $('#app'),
  mainHeader: $('#main-header'),
  screenHome: $('#screen-home'),
  screenMelodyPick: $('#screen-melody-pick'),
  screenNotesPick: $('#screen-notes-pick'),
  screenPractice: $('#screen-practice'),
  btnGoMelodies: $('#btn-go-melodies'),
  btnGoNotes: $('#btn-go-notes'),
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
  practiceSessionProgress: $('#practice-session-progress'),
  practiceSessionProgressFill: $('#practice-session-progress-fill'),
  practiceInputStatus: $('#practice-input-status'),
  practiceInputDot: $('#practice-input-dot'),
  practiceInputStatusText: $('#practice-input-status-text'),
  btnPracticeConnectMidi: $('#btn-practice-connect-midi'),
  practiceFeedback: $('#practice-feedback'),
  staffViewport: $('#staff-viewport'),
  practiceLayout: document.querySelector('.practice-layout'),
  practiceKeyboardArea: $('#practice-keyboard-area'),
  pianoWrap: $('#piano-wrap'),
  btnTogglePiano: $('#btn-toggle-piano'),
  keyboardHintTabs: document.querySelectorAll('.keyboard-mode__tab'),
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
  els.practiceInputStatusText.textContent = 'Пианино не подключено — нажимайте клавиши на экране, клавиатуре ПК (A–L) или подключите MIDI';
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

async function fetchJson(url) {
  const res = await fetch(url);
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
  if (els.modalSubtitle) {
    const total = stats.total ?? stats.correct;
    els.modalSubtitle.textContent = `Вы прошли ${total} ${pluralNotes(total)}`;
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

function hideSessionModal() {
  els.sessionModal.hidden = true;
}

let keyboardHints = true;

function setPianoVisible(visible) {
  els.practiceKeyboardArea.hidden = !visible;
  els.practiceKeyboardArea.classList.toggle('practice-keyboard-area--hidden', !visible);
  els.practiceLayout?.classList.toggle('practice-layout--keyboard-hidden', !visible);
  if (els.btnTogglePiano) {
    els.btnTogglePiano.setAttribute('aria-expanded', String(visible));
    const label = els.btnTogglePiano.querySelector('.practice-spoiler__label');
    const hint = els.btnTogglePiano.querySelector('.practice-spoiler__hint');
    if (label) label.textContent = visible ? 'Скрыть клавиатуру' : 'Показать клавиатуру';
    if (hint) {
      hint.textContent = visible
        ? 'Клавиши пианино на экране'
        : 'Нажмите, чтобы открыть клавиши пианино';
    }
    els.btnTogglePiano.classList.toggle('practice-spoiler--open', visible);
  }
  if (visible) {
    requestAnimationFrame(() => {
      piano.relayout();
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
      setTimeout(() => piano.relayout(), 120);
    });
  }
}

function setKeyboardHints(enabled) {
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
  setPianoVisible(false);
  setKeyboardHints(true);
  resetPracticeProgress();
  showFeedback('', 'info');
  updatePracticeInputStatus();
  showScreen('practice');

  requestAnimationFrame(() => {
    piano.relayout();
    if (mode === 'melody' && melodyTrainer.lesson) {
      staffView.loadLesson(melodyTrainer.lesson);
      els.staffViewport.classList.toggle('staff-viewport--grand', melodyTrainer.lesson.twoHands);
      melodyTrainer.start();
    } else if (mode === 'notes') {
      els.staffViewport.classList.toggle('staff-viewport--grand', usesBothClefs(noteTrainer.settings));
      noteTrainer.start();
    }
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
}

function onSessionComplete(stats) {
  showSessionModal(stats);
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
      <div class="lesson-card__title">${escapeHtml(lesson.title)}</div>
      <div class="lesson-card__meta">${escapeHtml(metaParts.join(' · '))}</div>
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
    tonality: {
      sharpKeys: checked('tonal-sharp'),
      flatKeys: checked('tonal-flat'),
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
  set('tonal-sharp', settings.tonality.sharpKeys);
  set('tonal-flat', settings.tonality.flatKeys);
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
  const error = validateNoteSettings(settings);

  if (error) {
    els.notesSettingsError.textContent = error;
    els.notesSettingsError.hidden = false;
    return;
  }

  els.notesSettingsError.hidden = true;
  noteSettings = settings;
  noteTrainer.setConfig(settings);
  noteTrainer.sessionLimit = readSessionLimitFromForm();
  enterPractice('notes', describeNoteSettings(settings));
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
});

els.btnTogglePiano?.addEventListener('click', () => {
  setPianoVisible(els.practiceKeyboardArea.hidden);
});

els.keyboardHintTabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    setKeyboardHints(tab.dataset.hints === 'on');
  });
});

els.btnGoMelodies.addEventListener('click', () => showScreen('melody-pick'));
els.btnGoNotes.addEventListener('click', () => showScreen('notes-pick'));

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
applyNoteSettingsToForm(DEFAULT_NOTE_SETTINGS);
applySessionLimitToForm(DEFAULT_NOTE_SESSION_LIMIT);
noteTrainer.sessionLimit = DEFAULT_NOTE_SESSION_LIMIT;
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
