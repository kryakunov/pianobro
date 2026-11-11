import { PianoKeyboard } from './piano.js';
import { MelodyTrainer } from './trainer.js';
import { StaffView } from './staff.js';
import { playTrainerNote, unlockTrainerSoundFromGesture } from './trainer-sounds.js';

const PREVIEW_EVENTS = 8;

let piano = null;
let trainer = null;
let staff = null;
let started = false;
let openAuth = null;

export function startMelodyLanding({ openAuthModal } = {}) {
  openAuth = openAuthModal ?? null;
  const lessonId = window.__BOOT__?.lessonId;
  const staffEl = document.getElementById('melody-play-staff');
  const pianoEl = document.getElementById('melody-play-piano');
  if (!lessonId || !staffEl || !pianoEl || started) return;

  started = true;
  staff = new StaffView(staffEl);
  piano = new PianoKeyboard(pianoEl);
  trainer = new MelodyTrainer(piano);
  trainer.showKeyboardHints = true;
  piano.onNoteOn = (midi) => handleLandingNote(midi);
  piano.onNoteOff = (midi) => trainer?.handleNoteOff(midi);

  document.getElementById('melody-play-again')?.addEventListener('click', () => {
    hideGate();
    trainer?.start();
  });
  document.getElementById('melody-play-save')?.addEventListener('click', () => {
    openAuth?.('register');
  });

  void loadPreview(lessonId);
}

export function isMelodyLandingPlaying() {
  return Boolean(trainer?.running);
}

export function handleLandingNote(midi) {
  if (!trainer?.running) return;
  unlockTrainerSoundFromGesture();
  void playTrainerNote(midi, 0.5);
  trainer.handleNoteOn(midi);
}

async function loadPreview(lessonId) {
  const prompt = document.getElementById('melody-play-prompt');
  try {
    const response = await fetch(`/api/lessons/${encodeURIComponent(lessonId)}`);
    if (!response.ok) throw new Error('lesson');
    const lesson = await response.json();
    const events = Array.isArray(lesson.events) ? lesson.events : [];
    const slice = events.slice(0, PREVIEW_EVENTS);
    const preview = {
      ...lesson,
      events: slice,
      eventCount: slice.length,
    };
    const hasMore = events.length > slice.length;

    staffElClass(lesson.twoHands);
    trainer.onUpdate = (state) => {
      staff.update(state);
      const count = document.getElementById('melody-play-count');
      if (count) count.textContent = `${Math.min(state.index, state.total)} / ${state.total}`;
      const midi = state.currentNote?.midi;
      if (midi) piano.scrollKeyIntoView(midi);
    };
    trainer.onFeedback = (_text, kind) => {
      if (!prompt || kind === 'complete') return;
      prompt.hidden = false;
      prompt.textContent = kind === 'wrong'
        ? 'Не та клавиша — смотрите подсветку'
        : 'Нажмите подсвеченную клавишу';
    };
    trainer.onComplete = () => showGate(hasMore);
    await new Promise((resolve) => {
      requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
    trainer.loadLesson(preview);
    trainer.start();
    staff.loadLesson(preview);
    staff.update(trainer.state);
    const firstMidi = slice[0]?.notes?.[0]?.midi;
    if (firstMidi) piano.scrollKeyIntoView(firstMidi);
  } catch {
    if (prompt) prompt.textContent = 'Не удалось открыть партию';
  }
}

function staffElClass(twoHands) {
  document.getElementById('melody-play-staff')?.classList.toggle('staff-viewport--grand', Boolean(twoHands));
}

function hideGate() {
  const gate = document.getElementById('melody-play-gate');
  const prompt = document.getElementById('melody-play-prompt');
  if (gate) gate.hidden = true;
  if (prompt) {
    prompt.hidden = false;
    prompt.textContent = 'Нажмите подсвеченную клавишу';
  }
}

function showGate(hasMore) {
  const gate = document.getElementById('melody-play-gate');
  const prompt = document.getElementById('melody-play-prompt');
  const text = document.getElementById('melody-play-gate-text');
  if (prompt) prompt.hidden = true;
  if (text && !hasMore) text.textContent = 'Начало сыграно. Можно пройти партию целиком.';
  if (gate) gate.hidden = false;
}
