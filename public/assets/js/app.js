import { PianoKeyboard } from './piano.js';
import { MidiInput } from './midi.js';
import { MelodyTrainer } from './trainer.js';
import { NoteTrainer, NOTE_LEVELS } from './note-trainer.js';
import { StaffView } from './staff.js';
import { MelodyPlayer } from './melody-player.js';
import { normalizeLesson, eventLabel } from './lesson-utils.js';
import { KEYBOARD_MAP, midiToName, PIANO_START, PIANO_END } from './notes.js';

const $ = (sel) => document.querySelector(sel);

const els = {
  sidebarMelody: $('#sidebar-melody'),
  sidebarNotes: $('#sidebar-notes'),
  modeTabs: document.querySelectorAll('.mode-tab'),
  lessonList: $('#lesson-list'),
  levelList: $('#level-list'),
  lessonTitle: $('#lesson-title'),
  lessonMeta: $('#lesson-meta'),
  statProgressLabel: $('#stat-progress-label'),
  statProgress: $('#stat-progress'),
  statCorrect: $('#stat-correct'),
  statWrong: $('#stat-wrong'),
  statBestWrap: $('#stat-best-wrap'),
  statBest: $('#stat-best'),
  statAccuracyWrap: $('#stat-accuracy-wrap'),
  statAccuracy: $('#stat-accuracy'),
  nextNotePanel: $('#next-note-panel'),
  nextNoteName: $('#next-note-name'),
  nextNoteHint: $('#next-note-hint'),
  feedback: $('#feedback'),
  staffViewport: $('#staff-viewport'),
  pianoWrap: $('#piano-wrap'),
  piano: $('#piano'),
  btnStart: $('#btn-start'),
  btnPreview: $('#btn-preview'),
  btnPause: $('#btn-pause'),
  btnReset: $('#btn-reset'),
  btnConnectMidi: $('#btn-connect-midi'),
  midiStatusText: $('#midi-status-text'),
  midiLiveNote: $('#midi-live-note'),
  midiDeviceSelect: $('#midi-device-select'),
  midiTranspose: $('#midi-transpose'),
  midiTransposeWrap: $('#midi-transpose-wrap'),
  midiDot: document.querySelector('.midi-dot'),
  toggleWait: $('#toggle-wait'),
  toggleKeyboard: $('#toggle-keyboard'),
  togglePianoVisible: $('#toggle-piano-visible'),
  controlsMelodyOnly: document.querySelector('.controls-melody-only'),
};

let appMode = 'melody';
let isPreviewing = false;
let selectedLessonId = null;
let selectedLevelId = NOTE_LEVELS[0].id;
let lessons = [];

const piano = new PianoKeyboard(els.piano, PIANO_START, PIANO_END);
const midi = new MidiInput();
const melodyTrainer = new MelodyTrainer(piano);
const noteTrainer = new NoteTrainer(piano);
const melodyPlayer = new MelodyPlayer();
const staffView = new StaffView(els.staffViewport);

function stopPreview() {
  if (!isPreviewing) return;
  melodyPlayer.stop();
  isPreviewing = false;
  piano.clearStates(['pressed', 'target', 'target-left', 'target-right']);
  els.btnPreview.textContent = 'Прослушать';
  els.btnPreview.classList.remove('btn--primary');
  els.btnPreview.classList.add('btn--secondary');
  els.btnStart.disabled = !melodyTrainer.lesson;
  els.btnPause.disabled = true;
  els.btnReset.disabled = !melodyTrainer.lesson;

  if (melodyTrainer.lesson) {
    staffView.update({ index: 0, running: false, paused: false, preview: false });
    updateMelodyUI(melodyTrainer.state);
  }
}

async function startPreview() {
  const lesson = melodyTrainer.lesson;
  if (!lesson?.events?.length) return;

  melodyTrainer.reset();
  stopPreview();
  isPreviewing = true;

  els.btnPreview.textContent = 'Стоп';
  els.btnPreview.classList.remove('btn--secondary');
  els.btnPreview.classList.add('btn--primary');
  els.btnStart.disabled = true;
  els.btnPause.disabled = true;
  els.btnReset.disabled = true;

  els.nextNotePanel.querySelector('.next-note-panel__label').textContent = 'Сейчас играет';
  showFeedback('Загрузка звуков пианино…', 'info');

  melodyPlayer.onLoadState = (state) => {
    if (state === 'loading') showFeedback('Загрузка звуков пианино…', 'info');
  };

  await melodyPlayer.play(lesson, {
    onEventStart: (index, event) => {
      els.nextNoteName.textContent = eventLabel(event);
      els.nextNoteHint.textContent = `Шаг ${index + 1} из ${lesson.events.length}`;
      piano.clearStates(['pressed', 'target', 'target-left', 'target-right']);
      for (const n of event.notes) {
        piano.pressKey(n.midi);
        piano.scrollKeyIntoView(n.midi);
      }
      staffView.update({ index, running: true, paused: false, preview: true });
    },
    onEventEnd: (_index, event) => {
      for (const n of event.notes) piano.releaseKey(n.midi);
    },
    onComplete: () => {
      isPreviewing = false;
      piano.clearStates(['pressed', 'target', 'target-left', 'target-right']);
      els.btnPreview.textContent = 'Прослушать';
      els.btnPreview.classList.remove('btn--primary');
      els.btnPreview.classList.add('btn--secondary');
      els.btnStart.disabled = false;
      els.btnReset.disabled = false;
      staffView.update({ index: lesson.events.length, running: false, paused: false, preview: false });
      els.nextNoteName.textContent = '✓';
      els.nextNoteHint.textContent = 'Прослушивание завершено';
      showFeedback('Мелодия завершена. Теперь попробуйте сыграть сами!', 'complete');
    },
  });
}

function setMidiStatus(state, text) {
  els.midiDot.className = 'midi-dot midi-dot--' + state;
  els.midiStatusText.textContent = text;
}

function showFeedback(text, type) {
  els.feedback.textContent = text;
  els.feedback.className = 'feedback';
  if (type === 'correct') els.feedback.classList.add('feedback--correct');
  else if (type === 'wrong') els.feedback.classList.add('feedback--wrong');
  else if (type === 'complete') els.feedback.classList.add('feedback--complete');
}

function escapeHtml(str) {
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

async function fetchJson(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

function setMode(mode) {
  appMode = mode;
  els.modeTabs.forEach((tab) => {
    tab.classList.toggle('mode-tab--active', tab.dataset.mode === mode);
  });

  const isMelody = mode === 'melody';
  els.sidebarMelody.hidden = !isMelody;
  els.sidebarNotes.hidden = isMelody;
  els.btnPause.hidden = !isMelody;
  els.btnPreview.hidden = !isMelody;
  els.toggleWait.closest('label').hidden = !isMelody;
  els.statProgressLabel.textContent = isMelody ? 'Прогресс' : 'Серия';
  els.statBestWrap.hidden = isMelody;

  if (isMelody) {
    noteTrainer.stop();
    stopPreview();
    els.btnStart.textContent = 'Начать';
    if (melodyTrainer.lesson) {
      updateMelodyUI(melodyTrainer.state);
    } else {
      resetMelodyPanel();
    }
  } else {
    stopPreview();
    melodyTrainer.reset();
    noteTrainer.setLevel(selectedLevelId);
    els.btnStart.disabled = false;
    els.btnReset.disabled = false;
    updateNoteUI(noteTrainer.state);
    showFeedback('Выберите уровень и нажмите «Начать»', 'info');
  }
}

function resetMelodyPanel() {
  els.lessonTitle.textContent = 'Выберите урок';
  els.lessonMeta.textContent = '';
  els.nextNoteName.textContent = '—';
  els.nextNoteHint.textContent = 'Выберите урок и нажмите «Начать»';
  els.btnStart.disabled = true;
  els.btnPreview.disabled = true;
  els.btnReset.disabled = true;
  staffView.clear();
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
  beginner: 'начальный',
  intermediate: 'средний',
  advanced: 'продвинутый',
};

function renderLessonList() {
  els.lessonList.innerHTML = lessons.map((l) => {
    const hands = l.twoHands ? ' · 2 руки' : '';
    return `
    <button type="button" class="lesson-card ${l.id === selectedLessonId ? 'lesson-card--active' : ''}"
            data-id="${l.id}">
      <div class="lesson-card__title">${escapeHtml(l.title)}</div>
      <div class="lesson-card__meta">${escapeHtml(l.composer)} · ${DIFFICULTY_LABELS[l.difficulty] ?? l.difficulty}${hands} · ${l.noteCount} нот</div>
    </button>
  `;
  }).join('');

  els.lessonList.querySelectorAll('.lesson-card').forEach((card) => {
    card.addEventListener('click', () => selectLesson(card.dataset.id));
  });
}

function renderLevelList() {
  els.levelList.innerHTML = NOTE_LEVELS.map((l) => `
    <button type="button" class="lesson-card ${l.id === selectedLevelId ? 'lesson-card--active' : ''}"
            data-id="${l.id}">
      <div class="lesson-card__title">${escapeHtml(l.title)}</div>
      <div class="lesson-card__meta">${escapeHtml(l.desc)}</div>
    </button>
  `).join('');

  els.levelList.querySelectorAll('.lesson-card').forEach((card) => {
    card.addEventListener('click', () => selectLevel(card.dataset.id));
  });
}

async function selectLesson(id) {
  stopPreview();
  selectedLessonId = id;
  renderLessonList();

  try {
    const lesson = normalizeLesson(await fetchJson(`/api/lessons/${id}`));
    melodyTrainer.loadLesson(lesson);
    staffView.loadLesson(lesson);
    els.staffViewport.classList.toggle('staff-viewport--grand', lesson.twoHands);
    els.lessonTitle.textContent = lesson.title;
    const handsLabel = lesson.twoHands ? ' · две руки' : '';
    els.lessonMeta.textContent = `${lesson.composer} · ${lesson.eventCount ?? lesson.events.length} шагов · ${lesson.noteCount} нот${handsLabel} · темп ${lesson.tempo}`;
    els.btnStart.disabled = false;
    els.btnPreview.disabled = false;
    els.btnReset.disabled = false;
    updateMelodyUI({
      index: 0,
      total: lesson.events.length,
      correct: 0,
      wrong: 0,
      running: false,
      paused: false,
      currentEvent: lesson.events[0] ?? null,
      currentNote: lesson.events[0]?.notes[0] ?? null,
      results: [],
      events: lesson.events,
      twoHands: lesson.twoHands,
    });
    staffView.update({ index: 0, running: false, paused: false });
    showFeedback('Урок загружен. Нажмите «Прослушать» или «Начать».', 'info');
  } catch {
    showFeedback('Не удалось загрузить урок', 'wrong');
  }
}

function selectLevel(id) {
  selectedLevelId = id;
  renderLevelList();
  noteTrainer.setLevel(id);
  const level = NOTE_LEVELS.find((l) => l.id === id);
  els.lessonTitle.textContent = 'Тренажёр нот';
  els.lessonMeta.textContent = level?.desc ?? '';
  staffView.clear();
  updateNoteUI(noteTrainer.state);
  showFeedback('Нажмите «Начать» для тренировки', 'info');
}

function updateMelodyUI(state) {
  if (isPreviewing) return;

  els.statProgress.textContent = `${state.index} / ${state.total}`;
  els.statCorrect.textContent = String(state.correct);
  els.statWrong.textContent = String(state.wrong);

  const attempts = state.correct + state.wrong;
  els.statAccuracy.textContent = attempts > 0
    ? `${Math.round((state.correct / attempts) * 100)}%`
    : '—';

  if (state.currentEvent && state.running && !state.paused) {
    els.nextNotePanel.querySelector('.next-note-panel__label').textContent = state.twoHands ? 'Следующий шаг' : 'Следующая нота';
    els.nextNoteName.textContent = eventLabel(state.currentEvent);
    els.nextNoteHint.textContent = state.twoHands
      ? 'Нажмите все подсвеченные ноты (левая — синяя, правая — золотая)'
      : 'Нажмите эту ноту на пианино';
  } else if (state.running && state.paused) {
    els.nextNoteHint.textContent = 'Пауза';
  } else if (state.index >= state.total && state.total > 0) {
    els.nextNoteName.textContent = '✓';
    els.nextNoteHint.textContent = 'Урок завершён';
  } else {
    els.nextNoteName.textContent = '—';
    els.nextNoteHint.textContent = 'Нажмите «Начать» для тренировки';
  }

  staffView.update(state);
  els.btnPause.disabled = !state.running;
  els.btnPause.textContent = state.paused ? 'Продолжить' : 'Пауза';
}

function updateNoteUI(state) {
  els.statProgress.textContent = String(state.streak);
  els.statCorrect.textContent = String(state.correct);
  els.statWrong.textContent = String(state.wrong);
  els.statBest.textContent = String(state.bestStreak);

  const attempts = state.correct + state.wrong;
  els.statAccuracy.textContent = attempts > 0
    ? `${Math.round((state.correct / attempts) * 100)}%`
    : '—';

  if (state.running && state.currentMidi !== null) {
    els.nextNotePanel.querySelector('.next-note-panel__label').textContent = 'Найдите ноту';
    els.nextNoteName.textContent = '?';
    els.nextNoteHint.textContent = 'Определите ноту на стане и нажмите на пианино';
  } else {
    els.nextNotePanel.querySelector('.next-note-panel__label').textContent = 'Тренажёр нот';
    els.nextNoteName.textContent = '♪';
    els.nextNoteHint.textContent = 'Нажмите «Начать» — появится случайная нота';
  }

  els.btnStart.textContent = state.running ? 'Стоп' : 'Начать';
}

function onNoteOn(midiNote) {
  if (isPreviewing) return;
  piano.pressKey(midiNote);
  if (appMode === 'melody') {
    melodyTrainer.handleNoteOn(midiNote);
  } else {
    noteTrainer.handleNoteOn(midiNote);
  }
}

function onNoteOff(midiNote) {
  if (isPreviewing) return;
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

midi.onActivity = ({ note, rawNote, type }) => {
  if (type === 'on') {
    const name = midiToName(note);
    const extra = rawNote !== note ? ` (MIDI ${rawNote}→${note})` : ` (MIDI ${note})`;
    els.midiLiveNote.textContent = `▸ ${name}${extra}`;
  }
};

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
  if (appMode === 'melody') updateMelodyUI(state);
};
melodyTrainer.onFeedback = showFeedback;

noteTrainer.onUpdate = (state) => {
  if (appMode === 'notes') updateNoteUI(state);
};
noteTrainer.onFeedback = showFeedback;
noteTrainer.onNoteChange = (midiNote) => {
  staffView.showDrillNote(midiNote);
};

els.modeTabs.forEach((tab) => {
  tab.addEventListener('click', () => setMode(tab.dataset.mode));
});

els.btnStart.addEventListener('click', () => {
  if (appMode === 'melody') {
    stopPreview();
    melodyTrainer.start();
  } else if (noteTrainer.running) {
    noteTrainer.stop();
    staffView.clear();
    showFeedback('Тренировка остановлена', 'info');
    updateNoteUI(noteTrainer.state);
  } else {
    noteTrainer.start();
  }
});

els.btnPreview.addEventListener('click', () => {
  if (isPreviewing || melodyPlayer.isPlaying) {
    stopPreview();
    showFeedback('Прослушивание остановлено', 'info');
  } else {
    startPreview();
  }
});

els.btnPause.addEventListener('click', () => melodyTrainer.pause());

els.btnReset.addEventListener('click', () => {
  if (appMode === 'melody') {
    stopPreview();
    melodyTrainer.reset();
    showFeedback('', 'info');
  } else {
    noteTrainer.reset();
    staffView.clear();
    showFeedback('Статистика сброшена', 'info');
  }
});

els.btnConnectMidi.addEventListener('click', async () => {
  try {
    const name = await midi.connect();
    setMidiStatus('on', `Подключено: ${name}`);
    els.btnConnectMidi.textContent = 'Переподключить';
    els.midiTransposeWrap.hidden = false;
    renderMidiDevices(midi.listInputs());
    showFeedback('Нажмите любую клавишу на пианино — надпись выше покажет распознанную ноту.', 'info');
  } catch (e) {
    setMidiStatus('error', e.message);
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

els.togglePianoVisible.addEventListener('change', () => {
  els.pianoWrap.hidden = !els.togglePianoVisible.checked;
});

const pressedKeys = new Set();

document.addEventListener('keydown', (e) => {
  if (!els.toggleKeyboard.checked) return;
  if (e.repeat) return;
  const note = KEYBOARD_MAP[e.key.toLowerCase()];
  if (note === undefined) return;
  e.preventDefault();
  if (!pressedKeys.has(note)) {
    pressedKeys.add(note);
    onNoteOn(note);
  }
});

document.addEventListener('keyup', (e) => {
  const note = KEYBOARD_MAP[e.key.toLowerCase()];
  if (note === undefined) return;
  pressedKeys.delete(note);
  onNoteOff(note);
});

loadLessons();
renderLevelList();

window.addEventListener('resize', () => {
  if (appMode === 'melody' && melodyTrainer.lesson) {
    staffView.loadLesson(melodyTrainer.lesson);
    staffView.update(melodyTrainer.state);
  } else if (appMode === 'notes' && noteTrainer.currentMidi !== null) {
    staffView.showDrillNote(noteTrainer.currentMidi);
  }
});

piano.onLayout = () => {
  piano.scrollKeyIntoView(60);
};
