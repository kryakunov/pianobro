import { isBlackKey } from './notes.js';

const COVER_ALL_REPEATS = 2;

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
};

export const NOTE_SESSION_LIMITS = [10, 20, 30, 50];
export const DEFAULT_NOTE_SESSION_LIMIT = 10;

export const DEFAULT_TRAINER_OPTIONS = {
  soundEnabled: true,
};

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

function resolvePoolMode(settings, poolMode) {
  if (poolMode) return poolMode;
  const includeChromatics = settings.alteration.sharp || settings.alteration.flat;
  return includeChromatics ? 'all' : 'natural';
}

export function buildPoolFromSettings(settings, { poolMode } = {}) {
  const ranges = selectedRanges(settings);
  if (!ranges.length) return [];

  const min = Math.min(...ranges.map((r) => r.min));
  const max = Math.max(...ranges.map((r) => r.max));
  const mode = resolvePoolMode(settings, poolMode);

  const pool = [];
  for (let midi = min; midi <= max; midi++) {
    if (!ranges.some((r) => midi >= r.min && midi <= r.max)) continue;

    const black = isBlackKey(midi);
    if (mode === 'natural' && black) continue;
    if (mode === 'chromatic' && !black) continue;

    pool.push(midi);
  }

  return pool;
}

export function resolveSpelling(settings) {
  const { sharp, flat } = settings.alteration;
  if (sharp && flat) return 'both';
  if (flat) return 'flat';
  return 'sharp';
}

export function resolveNoteSpelling(settings, midi) {
  const mode = resolveSpelling(settings);
  if (mode === 'both' && isBlackKey(midi)) {
    return Math.random() < 0.5 ? 'sharp' : 'flat';
  }
  if (mode === 'flat') return 'flat';
  return 'sharp';
}

export function resolveClefForNote(midi, settings) {
  if (settings.treble.enabled) {
    if (settings.treble.second && midi >= OCTAVE_RANGES.second.min && midi <= OCTAVE_RANGES.second.max) return 'treble';
    if (settings.treble.first && midi >= OCTAVE_RANGES.first.min && midi <= OCTAVE_RANGES.first.max) return 'treble';
  }
  return 'bass';
}

export function usesBothClefs(settings) {
  return settings.treble.enabled && settings.bass.enabled;
}

export function deriveSettingsFromMidis(midis) {
  const settings = structuredClone(DEFAULT_NOTE_SETTINGS);
  settings.treble = { enabled: false, first: false, second: false };
  settings.bass = { enabled: false, small: false, great: false };
  settings.alteration = { sharp: false, flat: false };

  for (const midi of midis) {
    if (midi >= OCTAVE_RANGES.great.min && midi <= OCTAVE_RANGES.great.max) {
      settings.bass.enabled = true;
      settings.bass.great = true;
    }
    if (midi >= OCTAVE_RANGES.small.min && midi <= OCTAVE_RANGES.small.max) {
      settings.bass.enabled = true;
      settings.bass.small = true;
    }
    if (midi >= OCTAVE_RANGES.first.min && midi <= OCTAVE_RANGES.first.max) {
      settings.treble.enabled = true;
      settings.treble.first = true;
    }
    if (midi >= OCTAVE_RANGES.second.min && midi <= OCTAVE_RANGES.second.max) {
      settings.treble.enabled = true;
      settings.treble.second = true;
    }
    if (isBlackKey(midi)) {
      settings.alteration.sharp = true;
      settings.alteration.flat = true;
    }
  }

  if (!settings.treble.enabled && !settings.bass.enabled) {
    settings.treble.enabled = true;
    settings.treble.first = true;
  }

  return settings;
}

export function describeNoteSettings(settings) {
  const parts = [];

  if (settings.treble.enabled && settings.bass.enabled) parts.push('Оба ключа');
  else if (settings.treble.enabled) parts.push('Скрипичный ключ');
  else if (settings.bass.enabled) parts.push('Басовый ключ');

  const octaves = [];
  if (settings.treble.enabled) {
    if (settings.treble.first) octaves.push(OCTAVE_RANGES.first.label);
    if (settings.treble.second) octaves.push(OCTAVE_RANGES.second.label);
  }
  if (settings.bass.enabled) {
    if (settings.bass.small) octaves.push(OCTAVE_RANGES.small.label);
    if (settings.bass.great) octaves.push(OCTAVE_RANGES.great.label);
  }
  if (octaves.length) parts.push(octaves.join(', '));

  if (settings.alteration.sharp) parts.push('диезы');
  if (settings.alteration.flat) parts.push('бемоли');

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

function shuffleArray(items) {
  const arr = [...items];
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
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
    this.sessionAttempts = [];
    this.soundEnabled = DEFAULT_TRAINER_OPTIONS.soundEnabled;
    this.currentSpelling = 'sharp';
    this.onUpdate = null;
    this.onFeedback = null;
    this.onNoteChange = null;
    this.onComplete = null;
    this.showKeyboardHints = true;
    this.coverAll = false;
    this._noteQueue = [];
    this._queueIndex = 0;
    this.setConfig(DEFAULT_NOTE_SETTINGS);
  }

  setOptions(options = {}) {
    if (options.soundEnabled !== undefined) {
      this.soundEnabled = Boolean(options.soundEnabled);
    }
    if (this.running) this._applyKeyboardHint();
    this._emitUpdate();
  }

  setConfig(settings, { poolMode, coverAll = false } = {}) {
    this.settings = structuredClone(settings);
    this.pool = buildPoolFromSettings(this.settings, { poolMode });
    this.spelling = resolveSpelling(this.settings);
    this.customPool = false;
    this.coverAll = coverAll;
    this.reset();
  }

  setCustomPool(midis, { coverAll = false } = {}) {
    const unique = [...new Set(midis)]
      .map((midi) => Number(midi))
      .filter((midi) => Number.isFinite(midi))
      .sort((a, b) => a - b);

    this.settings = deriveSettingsFromMidis(unique);
    this.pool = unique;
    this.spelling = resolveSpelling(this.settings);
    this.customPool = true;
    this.coverAll = coverAll;
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
    this.sessionAttempts = [];
    this.running = true;

    if (this.coverAll) {
      this._noteQueue = shuffleArray(
        this.pool.flatMap((midi) => Array(COVER_ALL_REPEATS).fill(midi)),
      );
      this._queueIndex = 0;
      this.sessionLimit = this._noteQueue.length;
    } else {
      this._noteQueue = [];
      this._queueIndex = 0;
    }

    this._showCurrentNote();
    this.onFeedback?.(
      this.coverAll
        ? `Пройдите все ${this.pool.length} нот — каждая минимум ${COVER_ALL_REPEATS} раза`
        : 'Найдите и нажмите ноту на пианино',
      'info',
    );
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
    this.sessionAttempts = [];
    this.currentMidi = null;
    this.running = false;
    this._noteQueue = [];
    this._queueIndex = 0;
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
      this.sessionAttempts.push({
        expectedMidi: this.currentMidi,
        playedMidi: midi,
        correct: true,
      });
      this.piano.flashCorrect(midi);
      this.onFeedback?.('Верно!', 'correct');
      this._emitUpdate();

      if (this.coverAll) {
        this._queueIndex++;
        if (this._queueIndex >= this._noteQueue.length) {
          this._finish();
          return true;
        }
        this._showCurrentNote();
        return true;
      }

      if (this.correct >= this.sessionLimit) {
        this._finish();
        return true;
      }

      this._showCurrentNote();
      return true;
    }

    this.wrong++;
    this.streak = 0;
    this.total++;
    this.sessionAttempts.push({
      expectedMidi: this.currentMidi,
      playedMidi: midi,
      correct: false,
    });
    this.piano.flashWrong(midi);
    this.onFeedback?.('Неверно', 'wrong');
    this._emitUpdate();

    return false;
  }

  handleNoteOff(midi) {
    this.piano.releaseKey(midi);
  }

  _showCurrentNote() {
    if (this.coverAll) {
      this.currentMidi = this._noteQueue[this._queueIndex] ?? null;
    } else {
      this.currentMidi = pickRandom(this.pool, this.currentMidi);
    }

    if (this.currentMidi === null) return;

    this.currentClef = resolveClefForNote(this.currentMidi, this.settings);
    this.currentSpelling = resolveNoteSpelling(this.settings, this.currentMidi);
    this._applyKeyboardHint();
    this.onNoteChange?.(this.currentMidi, {
      spelling: this.currentSpelling,
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
    this.onFeedback?.(
      `Тренировка завершена! Точность: ${accuracy}%`,
      'complete',
    );
    this.onComplete?.({
      correct: this.correct,
      wrong: this.wrong,
      accuracy,
      total: this.sessionLimit,
      mode: 'notes',
      settings: this.settings,
      attempts: [...this.sessionAttempts],
      coverAll: this.coverAll,
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
      soundEnabled: this.soundEnabled,
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
      soundEnabled: this.soundEnabled,
    };
  }
}
