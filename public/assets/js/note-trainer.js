import { isBlackKey } from './notes.js';

export const NOTE_LEVELS = [
  {
    id: 'cde',
    title: 'До, ре, ми',
    desc: '3 белые клавиши',
    buildPool: () => [60, 62, 64],
  },
  {
    id: 'c-g',
    title: 'До — соль',
    desc: '5 нот, одна октава',
    buildPool: () => rangePool(60, 67, true),
  },
  {
    id: 'c-c5',
    title: 'До — до',
    desc: '8 нот, октава',
    buildPool: () => rangePool(60, 72, true),
  },
  {
    id: 'c-g5',
    title: 'Две октавы',
    desc: 'Белые клавиши C4–G5',
    buildPool: () => rangePool(60, 79, true),
  },
  {
    id: 'with-sharps',
    title: 'С диезами',
    desc: 'Все ноты C4–C5',
    spelling: 'sharp',
    buildPool: () => rangePool(60, 72, false),
  },
  {
    id: 'with-flats',
    title: 'С бемолями',
    desc: 'Чёрные клавиши как бемоли',
    spelling: 'flat',
    buildPool: () => rangePool(60, 72, false),
  },
  {
    id: 'wide',
    title: 'Широкий диапазон',
    desc: 'C3–C5 с диезами',
    spelling: 'sharp',
    buildPool: () => rangePool(48, 72, false),
  },
  {
    id: 'wide-flats',
    title: 'Широкий с бемолями',
    desc: 'C3–C5, написание бемолями',
    spelling: 'flat',
    buildPool: () => rangePool(48, 72, false),
  },
];

function rangePool(min, max, whitesOnly) {
  const pool = [];
  for (let m = min; m <= max; m++) {
    if (!whitesOnly || !isBlackKey(m)) pool.push(m);
  }
  return pool;
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
    this.level = NOTE_LEVELS[0];
    this.pool = [];
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
  }

  setLevel(levelId) {
    const level = NOTE_LEVELS.find((l) => l.id === levelId);
    if (!level) return;
    this.level = level;
    this.pool = level.buildPool();
    this.spelling = level.spelling ?? 'sharp';
    this.reset();
  }

  start() {
    this.correct = 0;
    this.wrong = 0;
    this.streak = 0;
    this.total = 0;
    this.running = true;
    this._nextNote();
    this.onFeedback?.('Найдите и нажмите ноту на пианино', 'info');
    this._emitUpdate();
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
    this._applyKeyboardHint();
    this.onNoteChange?.(this.currentMidi, { spelling: this.spelling });
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
      level: this.level,
      currentMidi: this.currentMidi,
      correct: this.correct,
      wrong: this.wrong,
      streak: this.streak,
      bestStreak: this.bestStreak,
      total: this.total,
      sessionLimit: this.sessionLimit,
      running: this.running,
    });
  }

  get state() {
    return {
      level: this.level,
      currentMidi: this.currentMidi,
      correct: this.correct,
      wrong: this.wrong,
      streak: this.streak,
      bestStreak: this.bestStreak,
      total: this.total,
      sessionLimit: this.sessionLimit,
      running: this.running,
    };
  }
}
