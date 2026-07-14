import { isBlackKey } from './notes.js';

export const OCTAVE_RANGES = {
  great: { min: 36, max: 47, label: 'Большая октава' },
  small: { min: 48, max: 59, label: 'Малая октава' },
  first: { min: 60, max: 71, label: 'Первая октава' },
  second: { min: 72, max: 83, label: 'Вторая октава' },
};

export const DEFAULT_NOTE_SETTINGS = {
  treble: { enabled: true, first: true, second: false },
  bass: { enabled: false, small: false, great: false },
  alteration: { sharp: false, flat: false },
  tonality: { sharpKeys: false, flatKeys: false },
};

const SHARP_TONAL_PCS = new Set([1, 3, 6, 8, 10]);
const FLAT_TONAL_PCS = new Set([1, 3, 6, 8, 10]);

function selectedRanges(settings) {
  const ranges = [];
  if (settings.treble.enabled) {
    if (settings.treble.first) ranges.push(OCTAVE_RANGES.first);
    if (settings.treble.second) ranges.push(OCTAVE_RANGES.second);
  }
  if (settings.bass.enabled) {
    if (settings.bass.small) ranges.push(OCTAVE_RANGES.small);
    if (settings.bass.great) ranges.push(OCTAVE_RANGES.great);
  }
  return ranges;
}

export function buildPoolFromSettings(settings) {
  const ranges = selectedRanges(settings);
  if (!ranges.length) return [];

  const min = Math.min(...ranges.map((r) => r.min));
  const max = Math.max(...ranges.map((r) => r.max));

  const includeChromatics = settings.alteration.sharp
    || settings.alteration.flat
    || settings.tonality.sharpKeys
    || settings.tonality.flatKeys;

  const pool = [];
  for (let midi = min; midi <= max; midi++) {
    if (!ranges.some((r) => midi >= r.min && midi <= r.max)) continue;

    if (isBlackKey(midi)) {
      if (!includeChromatics) continue;

      const pc = midi % 12;
      const { sharpKeys, flatKeys } = settings.tonality;

      if (sharpKeys && !flatKeys && !SHARP_TONAL_PCS.has(pc)) continue;
      if (flatKeys && !sharpKeys && !FLAT_TONAL_PCS.has(pc)) continue;
    }

    pool.push(midi);
  }

  return pool;
}

export function resolveSpelling(settings) {
  if (settings.alteration.flat && !settings.alteration.sharp) return 'flat';
  if (settings.tonality.flatKeys && !settings.tonality.sharpKeys && !settings.alteration.sharp) return 'flat';
  return 'sharp';
}

export function resolveClefForNote(midi, settings) {
  if (settings.treble.enabled) {
    if (settings.treble.second && midi >= OCTAVE_RANGES.second.min && midi <= OCTAVE_RANGES.second.max) return 'treble';
    if (settings.treble.first && midi >= OCTAVE_RANGES.first.min && midi <= OCTAVE_RANGES.first.max) return 'treble';
  }
  return 'bass';
}

export function describeNoteSettings(settings) {
  const parts = [];

  if (settings.treble.enabled && settings.bass.enabled) parts.push('Оба ключа');
  else if (settings.treble.enabled) parts.push('Скрипичный ключ');
  else if (settings.bass.enabled) parts.push('Басовый ключ');

  const octaves = [];
  if (settings.treble.enabled) {
    if (settings.treble.second) octaves.push(OCTAVE_RANGES.second.label);
    if (settings.treble.first) octaves.push(OCTAVE_RANGES.first.label);
  }
  if (settings.bass.enabled) {
    if (settings.bass.small) octaves.push(OCTAVE_RANGES.small.label);
    if (settings.bass.great) octaves.push(OCTAVE_RANGES.great.label);
  }
  if (octaves.length) parts.push(octaves.join(', '));

  if (settings.alteration.sharp) parts.push('диезы');
  if (settings.alteration.flat) parts.push('бемоли');
  if (settings.tonality.sharpKeys) parts.push('диезные тональности');
  if (settings.tonality.flatKeys) parts.push('бемольные тональности');

  return parts.join(' · ') || 'Тренажёр нот';
}

function pickRandom(pool, exclude) {
  if (pool.length === 0) return null;
  if (pool.length === 1) return pool[0];

  let note;
  let attempts = 0;
  do {
    note = pool[Math.floor(Math.random() * pool.length)];
    attempts++;
  } while (note === exclude && attempts < 20);

  return note;
}

export class NoteTrainer {
  constructor(piano) {
    this.piano = piano;
    this.settings = structuredClone(DEFAULT_NOTE_SETTINGS);
    this.pool = [];
    this.spelling = 'sharp';
    this.currentClef = 'treble';
    this.currentMidi = null;
    this.correct = 0;
    this.wrong = 0;
    this.streak = 0;
    this.bestStreak = 0;
    this.total = 0;
    this.running = false;
    this.sessionLimit = 10;
    this.onUpdate = null;
    this.onFeedback = null;
    this.onNoteChange = null;
    this.onComplete = null;
    this.showKeyboardHints = true;
    this.setConfig(DEFAULT_NOTE_SETTINGS);
  }

  setConfig(settings) {
    this.settings = structuredClone(settings);
    this.pool = buildPoolFromSettings(this.settings);
    this.spelling = resolveSpelling(this.settings);
    this.reset();
  }

  start() {
    if (!this.pool.length) {
      this.onFeedback?.('Выберите ключ и хотя бы одну октаву', 'wrong');
      return false;
    }

    this.correct = 0;
    this.wrong = 0;
    this.streak = 0;
    this.total = 0;
    this.running = true;
    this._nextNote();
    this.onFeedback?.('Найдите и нажмите ноту на пианино', 'info');
    this._emitUpdate();
    return true;
  }

  stop() {
    this.running = false;
    this.piano.setTarget(null);
    this.piano.clearStates();
    this._emitUpdate();
  }

  reset() {
    this.correct = 0;
    this.wrong = 0;
    this.streak = 0;
    this.bestStreak = 0;
    this.total = 0;
    this.currentMidi = null;
    this.running = false;
    this.piano.setTarget(null);
    this.piano.clearStates();
    this._emitUpdate();
  }

  handleNoteOn(midi) {
    if (!this.running || this.currentMidi === null) return false;

    if (midi === this.currentMidi) {
      this.correct++;
      this.streak++;
      this.bestStreak = Math.max(this.bestStreak, this.streak);
      this.total++;
      this.piano.flashCorrect(midi);
      this.onFeedback?.('Верно!', 'correct');
      this._emitUpdate();

      if (this.correct >= this.sessionLimit) {
        this._finish();
        return true;
      }

      this._nextNote();
      return true;
    }

    this.wrong++;
    this.streak = 0;
    this.total++;
    this.piano.flashWrong(midi);
    this.onFeedback?.('Неверно', 'wrong');
    this._emitUpdate();
    return false;
  }

  handleNoteOff(midi) {
    this.piano.releaseKey(midi);
  }

  _nextNote() {
    const prev = this.currentMidi;
    this.currentMidi = pickRandom(this.pool, prev);
    this.currentClef = resolveClefForNote(this.currentMidi, this.settings);
    this._applyKeyboardHint();
    this.onNoteChange?.(this.currentMidi, {
      spelling: this.spelling,
      clef: this.currentClef,
    });
  }

  _applyKeyboardHint() {
    this.piano.clearStates(['correct', 'wrong', 'pressed', 'target', 'target-left', 'target-right']);
    if (this.showKeyboardHints && this.currentMidi !== null) {
      this.piano.setTarget(this.currentMidi);
    }
  }

  refreshKeyboardHint() {
    if (this.running) this._applyKeyboardHint();
  }

  _finish() {
    this.running = false;
    this.piano.setTarget(null);
    this.piano.clearStates();
    const attempts = this.correct + this.wrong;
    const accuracy = attempts > 0 ? Math.round((this.correct / attempts) * 100) : 0;
    this.onFeedback?.(`Тренировка завершена! Точность: ${accuracy}%`, 'complete');
    this.onComplete?.({
      correct: this.correct,
      wrong: this.wrong,
      accuracy,
      total: this.sessionLimit,
    });
    this._emitUpdate();
  }

  _emitUpdate() {
    this.onUpdate?.({
      settings: this.settings,
      currentMidi: this.currentMidi,
      currentClef: this.currentClef,
      correct: this.correct,
      wrong: this.wrong,
      streak: this.streak,
      bestStreak: this.bestStreak,
      total: this.total,
      sessionLimit: this.sessionLimit,
      poolSize: this.pool.length,
      running: this.running,
    });
  }

  get state() {
    return {
      settings: this.settings,
      currentMidi: this.currentMidi,
      currentClef: this.currentClef,
      correct: this.correct,
      wrong: this.wrong,
      streak: this.streak,
      bestStreak: this.bestStreak,
      total: this.total,
      sessionLimit: this.sessionLimit,
      poolSize: this.pool.length,
      running: this.running,
    };
  }
}
