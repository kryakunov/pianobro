import { PianoKeyboard } from './piano.js';
import { MidiInput } from './midi.js';
import { MicPitchInput } from './mic-pitch.js';
import { MelodyTrainer } from './trainer.js';
import { NoteTrainer, NOTE_LEVELS } from './note-trainer.js';
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
  levelList: $('#level-list'),
  practiceTitle: $('#practice-title'),
  practiceProgress: $('#practice-progress'),
  practiceFeedback: $('#practice-feedback'),
  staffViewport: $('#staff-viewport'),
  practiceLayout: document.querySelector('.practice-layout'),
  pianoWrap: $('#piano-wrap'),
  btnTogglePiano: $('#btn-toggle-piano'),
  piano: $('#piano'),
  btnConnectMidi: $('#btn-connect-midi'),
  btnConnectMic: $('#btn-connect-mic'),
  midiStatusText: $('#midi-status-text'),
  midiLiveNote: $('#midi-live-note'),
  midiDeviceSelect: $('#midi-device-select'),
  midiTranspose: $('#midi-transpose'),
  midiTransposeWrap: $('#midi-transpose-wrap'),
  midiDot: document.querySelector('.midi-dot'),
  toggleWait: $('#toggle-wait'),
  toggleKeyboard: $('#toggle-keyboard'),
  toggleMic: $('#toggle-mic'),
  controlsMelodyOnly: document.querySelector('.controls-melody-only'),
  sessionModal: $('#session-modal'),
  modalCorrect: $('#modal-correct'),
  modalWrong: $('#modal-wrong'),
  modalAccuracy: $('#modal-accuracy'),
  btnModalRetry: $('#btn-modal-retry'),
  btnModalPick: $('#btn-modal-pick'),
  btnModalHome: $('#btn-modal-home'),
};

let currentScreen = 'home';
let appMode = 'melody';
let selectedLessonId = null;
let selectedImportedId = null;
let selectedLevelId = NOTE_LEVELS[0].id;
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
  if (type === 'correct') els.practiceFeedback.classList.add('practice-feedback--correct');
  else if (type === 'wrong') els.practiceFeedback.classList.add('practice-feedback--wrong');
  else if (type === 'complete') els.practiceFeedback.classList.add('practice-feedback--complete');
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
  if (appMode === 'melody') {
    els.practiceProgress.textContent = `${Math.min(state.index, state.total)} / ${state.total}`;
  } else {
    els.practiceProgress.textContent = `${state.correct} / ${state.sessionLimit}`;
  }
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
  els.sessionModal.hidden = false;
}

function hideSessionModal() {
  els.sessionModal.hidden = true;
}

function setPianoVisible(visible) {
  els.pianoWrap.hidden = !visible;
  els.pianoWrap.classList.toggle('practice-keyboard--hidden', !visible);
  els.practiceLayout?.classList.toggle('practice-layout--keyboard-hidden', !visible);
  if (els.btnTogglePiano) {
    els.btnTogglePiano.setAttribute('aria-expanded', String(visible));
    const label = els.btnTogglePiano.querySelector('.practice-spoiler__label');
    if (label) label.textContent = visible ? 'Скрыть клавиатуру' : 'Показать клавиатуру';
  }
  if (visible) {
    requestAnimationFrame(() => {
      piano.relayout();
      if (appMode === 'melody' && melodyTrainer.lesson) {
        staffView.loadLesson(melodyTrainer.lesson);
        staffView.update(melodyTrainer.state);
      } else if (appMode === 'notes' && noteTrainer.currentMidi !== null) {
        staffView.showDrillNote(noteTrainer.currentMidi, noteTrainer.spelling);
      }
    });
  }
}

function enterPractice(mode, title) {
  appMode = mode;
  currentPracticeTitle = title;
  els.practiceTitle.textContent = title;
  els.controlsMelodyOnly.hidden = mode !== 'melody';
  setPianoVisible(false);
  showFeedback('', 'info');
  showScreen('practice');

  requestAnimationFrame(() => {
    piano.relayout();
    if (mode === 'melody' && melodyTrainer.lesson) {
      staffView.loadLesson(melodyTrainer.lesson);
      els.staffViewport.classList.toggle('staff-viewport--grand', melodyTrainer.lesson.twoHands);
      melodyTrainer.start();
    } else if (mode === 'notes') {
      noteTrainer.start();
    }
  });
}

function exitPractice() {
  melodyTrainer.reset();
  noteTrainer.stop();
  staffView.clear();
  piano.clearStates();
  hideSessionModal();
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

function renderLevelList() {
  els.levelList.innerHTML = NOTE_LEVELS.map((l) => `
    <button type="button" class="lesson-card" data-id="${l.id}">
      <div class="lesson-card__title">${escapeHtml(l.title)}</div>
      <div class="lesson-card__meta">${escapeHtml(l.desc)} · ${SESSION_LIMIT} нот</div>
    </button>
  `).join('');

  els.levelList.querySelectorAll('.lesson-card').forEach((card) => {
    card.addEventListener('click', () => selectLevel(card.dataset.id));
  });
}

async function selectLesson(id) {
  try {
    const lesson = normalizeLesson(await fetchJson(`/api/lessons/${id}`));
    loadMelodyLesson(lesson, { activeId: id });
  } catch {
    alert('Не удалось загрузить урок');
  }
}

function selectLevel(id) {
  selectedLevelId = id;
  noteTrainer.setLevel(id);
  noteTrainer.sessionLimit = SESSION_LIMIT;
  const level = NOTE_LEVELS.find((l) => l.id === id);
  enterPractice('notes', level?.title ?? 'Тренажёр нот');
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
    if (els.toggleMic) els.toggleMic.checked = true;
  } else if (!midi.isConnected) {
    els.midiDot.className = 'midi-dot midi-dot--off';
    setMicStatus(false, 'MIDI не подключён');
    if (els.toggleMic) els.toggleMic.checked = false;
  } else {
    setMicStatus(false);
  }
};

async function startMicListening() {
  try {
    await micPitch.start();
  } catch (error) {
    if (els.toggleMic) els.toggleMic.checked = false;
    setMicStatus(false);
    setMidiStatus('error', error?.message ?? 'Не удалось включить микрофон');
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

midi.onInputsChanged = renderMidiDevices;

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
noteTrainer.onNoteChange = (midiNote, { spelling } = {}) => {
  staffView.showDrillNote(midiNote, spelling);
};

els.btnTogglePiano?.addEventListener('click', () => {
  setPianoVisible(els.pianoWrap.hidden);
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
    const name = await midi.connect();
    setMidiStatus('on', `Подключено: ${name}`);
    els.btnConnectMidi.textContent = 'Переподключить';
    els.midiTransposeWrap.hidden = false;
    renderMidiDevices(midi.listInputs());
  } catch (e) {
    setMidiStatus('error', e.message);
  }
});

els.btnConnectMic.addEventListener('click', async () => {
  if (micPitch.isActive) {
    stopMicListening();
    if (els.toggleMic) els.toggleMic.checked = false;
    return;
  }
  await startMicListening();
});

els.toggleMic?.addEventListener('change', async () => {
  if (els.toggleMic.checked) {
    await startMicListening();
  } else {
    stopMicListening();
  }
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
  if (!els.toggleKeyboard.checked) return;
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
renderLevelList();
noteTrainer.sessionLimit = SESSION_LIMIT;
setPianoVisible(false);

window.addEventListener('resize', () => {
  if (currentScreen === 'practice') {
    piano.relayout();
  }
  if (currentScreen !== 'practice') return;
  if (appMode === 'melody' && melodyTrainer.lesson) {
    staffView.loadLesson(melodyTrainer.lesson);
    staffView.update(melodyTrainer.state);
  } else if (appMode === 'notes' && noteTrainer.currentMidi !== null) {
    staffView.showDrillNote(noteTrainer.currentMidi, noteTrainer.spelling);
  }
});
