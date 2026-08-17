import {
  buildPoolFromSettings,
  resolveClefForNote,
  resolveNoteSpelling,
  usesBothClefs,
} from './note-trainer.js';
import { DEFAULT_RUNNER_TEMPO_SCALE, runnerScrollSpeed } from './runner-staff.js';

export const RHYTHM_DURATION_OPTIONS = [
  { key: 'whole', label: 'Целая', ms: 1600, symbol: '𝅝' },
  { key: 'half', label: 'Половинная', ms: 800, symbol: '𝅗𝅥' },
  { key: 'quarter', label: 'Четверть', ms: 400, symbol: '♩' },
  { key: 'eighth', label: 'Восьмая', ms: 200, symbol: '♪' },
  { key: 'sixteenth', label: 'Шестнадцатая', ms: 100, symbol: '♬' },
];

export const DEFAULT_RHYTHM_DURATIONS = {
  whole: false,
  half: false,
  quarter: true,
  eighth: false,
  sixteenth: false,
};

/** Higher tempoScale = slower scroll. */
export const RHYTHM_SPEED_OPTIONS = [
  { key: 'very_slow', label: 'Очень медленно', tempoScale: 4.0 },
  { key: 'slow', label: 'Медленно', tempoScale: 3.0 },
  { key: 'medium', label: 'Средне', tempoScale: 2.2 },
  { key: 'fast', label: 'Быстро', tempoScale: 1.6 },
];

export const DEFAULT_RHYTHM_SPEED = 'slow';

export const DEFAULT_RHYTHM_LIVES = 3;

export const RHYTHM_LIVES_OPTIONS = [1, 2, 3, 5, 10];

export function normalizeRhythmLives(value) {
  const parsed = parseInt(value ?? '', 10);
  return RHYTHM_LIVES_OPTIONS.includes(parsed) ? parsed : DEFAULT_RHYTHM_LIVES;
}

export function tempoScaleForSpeed(speedKey) {
  const option = RHYTHM_SPEED_OPTIONS.find((item) => item.key === speedKey);
  return option?.tempoScale
    ?? RHYTHM_SPEED_OPTIONS.find((item) => item.key === DEFAULT_RHYTHM_SPEED)?.tempoScale
    ?? 3.0;
}

export function rhythmSpeedLabel(speedKey) {
  const option = RHYTHM_SPEED_OPTIONS.find((item) => item.key === speedKey);
  return option?.label
    ?? RHYTHM_SPEED_OPTIONS.find((item) => item.key === DEFAULT_RHYTHM_SPEED)?.label
    ?? 'Медленно';
}

const COUNTDOWN_MS = 3000;
/** Base hit window at «Средне» (tempoScale 2.2). Slower modes scale up automatically. */
const EARLY_MS = 1800;
const LATE_MS = 3200;
const MEDIUM_TEMPO_SCALE = 2.2;

/** @param {number} tempoScale */
export function hitWindowsForTempoScale(tempoScale) {
  const scale = tempoScale / MEDIUM_TEMPO_SCALE;
  return {
    earlyMs: EARLY_MS * scale,
    lateMs: LATE_MS * scale,
    totalMs: (EARLY_MS + LATE_MS) * scale,
  };
}
const INITIAL_BATCH = 48;
const APPEND_BATCH = 24;
const APPEND_THRESHOLD = 16;
const HIGH_SCORE_KEY = 'piano-rhythm-highscore';
const MEASURE_CAPACITY_MS = 1600;

function pickRandom(items) {
  return items[Math.floor(Math.random() * items.length)];
}

function pickDurationForMeasure(remaining, durations) {
  const fitting = durations.filter((duration) => duration <= remaining + 1e-6);
  if (!fitting.length) return null;
  return pickRandom(fitting);
}

export function loadRhythmHighScore() {
  try {
    const value = parseInt(localStorage.getItem(HIGH_SCORE_KEY) ?? '0', 10);
    return Number.isFinite(value) ? value : 0;
  } catch {
    return 0;
  }
}

export function saveRhythmHighScore(score) {
  const prev = loadRhythmHighScore();
  if (score <= prev) return prev;
  try {
    localStorage.setItem(HIGH_SCORE_KEY, String(score));
  } catch {
    // ignore
  }
  return score;
}

export class RhythmTrainer {
  constructor(piano) {
    this.piano = piano;
    this.settings = null;
    this.durations = [];
    this.pool = [];
    this.events = [];
    this.layout = null;
    this.running = false;
    this.gameOver = false;
    this.score = 0;
    this.combo = 0;
    this.bestCombo = 0;
    this.speed = 1;
    this.nextIndex = 0;
    this.scrollOffset = 0;
    this.countdownEnd = 0;
    this.gameStart = 0;
    this._rafId = null;
    this.soundEnabled = true;
    this.showKeyboardHints = true;
    this.onUpdate = null;
    this.onFeedback = null;
    this.onComplete = null;
    this.onScroll = null;
    this.onCountdown = null;
    this.onActiveNote = null;
    this.onNoteState = null;
    this.onRelayout = null;
    this.onHitLineUpdate = null;
    this.lineScreenX = 0;
    this.tempoScale = DEFAULT_RUNNER_TEMPO_SCALE;
    this.pendingEvents = [];
    this.heldMidis = new Set();
    this.maxLives = DEFAULT_RHYTHM_LIVES;
    this.lives = DEFAULT_RHYTHM_LIVES;
    this.mistakes = 0;
  }

  setMaxLives(lives) {
    this.maxLives = normalizeRhythmLives(lives);
  }

  configure(noteSettings, durationMsList) {
    this.settings = noteSettings;
    this.durations = durationMsList.slice();
    this.pool = buildPoolFromSettings(noteSettings);
    this.twoHands = usesBothClefs(noteSettings);
  }

  setTempo(speedKey, tempoScale) {
    this.rhythmSpeed = speedKey;
    this.tempoScale = tempoScale;
  }

  resetEvents(initialEvents, layout, tempoScale = null) {
    this.events = initialEvents.map((event, index) => ({
      ...event,
      index,
      hit: false,
      state: 'upcoming',
    }));
    this.tempoScale = tempoScale ?? layout?.tempoScale ?? DEFAULT_RUNNER_TEMPO_SCALE;
    this.layout = {
      ...layout,
      tempoScale: this.tempoScale,
      scrollSpeed: runnerScrollSpeed(this.tempoScale),
    };
    this.nextIndex = 0;
    this.score = 0;
    this.combo = 0;
    this.bestCombo = 0;
    this.speed = 1;
    this.scrollOffset = 0;
    this.gameOver = false;
    this.running = false;
    this.heldMidis = new Set();
    this.lives = this.maxLives;
    this.mistakes = 0;
    this.lineScreenX = this._lineScreenX(0);
    this._stopLoop();
  }

  appendEvents(laidOutEvents, layout) {
    for (const event of laidOutEvents) {
      this.events.push({
        ...event,
        hit: false,
        state: 'upcoming',
      });
    }
    this.layout = {
      ...this.layout,
      contentWidth: layout.contentWidth,
      lastX: layout.lastX,
      tempoScale: layout.tempoScale ?? this.tempoScale,
      scrollSpeed: runnerScrollSpeed(layout.tempoScale ?? this.tempoScale),
    };
  }

  generateBatch(count, measureRemaining = MEASURE_CAPACITY_MS) {
    if (!this.pool.length || !this.durations.length) return [];

    const batch = [];
    let remaining = measureRemaining;

    const pushNote = () => {
      let duration = pickDurationForMeasure(remaining, this.durations);
      if (duration == null) {
        remaining = MEASURE_CAPACITY_MS;
        duration = pickDurationForMeasure(remaining, this.durations);
        if (duration == null) return false;
      }

      const midi = pickRandom(this.pool);
      batch.push({
        midi,
        duration,
        spelling: resolveNoteSpelling(this.settings, midi),
        clef: resolveClefForNote(midi, this.settings),
      });
      remaining -= duration;
      if (remaining <= 1e-6) remaining = MEASURE_CAPACITY_MS;
      return true;
    };

    while (batch.length < count) {
      if (!pushNote()) break;
    }

    while (remaining < MEASURE_CAPACITY_MS - 1e-6 && remaining > 1e-6) {
      if (!pushNote()) break;
    }

    return batch;
  }

  start() {
    this.onRelayout?.();
    if (!this.events.length || !this.layout) return;

    this.running = true;
    this.gameOver = false;
    this.nextIndex = 0;
    this.score = 0;
    this.combo = 0;
    this.bestCombo = 0;
    this.speed = 1;
    this.scrollOffset = 0;
    this.countdownEnd = performance.now() + COUNTDOWN_MS;
    this.gameStart = 0;
    this.heldMidis = new Set();
    this.lives = this.maxLives;
    this.mistakes = 0;
    this.lineScreenX = this._lineScreenX(0);
    this.events.forEach((event) => {
      event.hit = false;
      event.state = 'upcoming';
    });

    this._highlightActiveNote();
    this._emitUpdate({ countdown: Math.ceil(COUNTDOWN_MS / 1000), lives: this.lives });
    this.onHitLineUpdate?.(this.lineScreenX);
    this.onFeedback?.('Приготовьтесь…', 'info');
    this._startLoop();
  }

  stop() {
    this.running = false;
    this._stopLoop();
    this.piano.clearStates(['target', 'target-left', 'target-right', 'correct', 'wrong']);
  }

  handleNoteOn(midi) {
    if (!this.running || this.gameOver) return false;

    const note = Number(midi);
    if (!Number.isFinite(note)) return false;
    if (this.heldMidis.has(note)) return false;

    const now = performance.now();
    if (now < this.countdownEnd) return false;

    const event = this.events[this.nextIndex];
    if (!event || event.hit) return false;

    const gameTime = now - this.countdownEnd;
    if (this._timeToHitLine(event, gameTime) <= 0) return false;

    this.heldMidis.add(note);

    if (note !== event.midi) {
      this._loseLife('wrong');
      return false;
    }

    event.hit = true;
    event.state = 'hit';
    this.score++;
    this.combo++;
    this.bestCombo = Math.max(this.bestCombo, this.combo);

    this.piano.flashCorrect(note);
    this.onNoteState?.(event.index, 'hit');
    this.onFeedback?.('Верно!', 'correct');
    this.nextIndex++;
    this._highlightActiveNote();
    this._maybeAppendEvents();
    this._emitUpdate();
    return true;
  }

  handleNoteOff(midi) {
    const note = Number(midi);
    if (Number.isFinite(note)) {
      this.heldMidis.delete(note);
    }
  }

  tick(now) {
    if (!this.running || this.gameOver) return;

    if (now < this.countdownEnd) {
      const remaining = Math.max(1, Math.ceil((this.countdownEnd - now) / 1000));
      this.onCountdown?.(remaining);
      this._emitUpdate({ countdown: remaining });
      return;
    }

    if (!this.gameStart) {
      this.gameStart = now;
      this.onCountdown?.(0);
      this.onFeedback?.('Играй!', 'info');
    }

    const gameTime = now - this.countdownEnd;
    this.scrollOffset = gameTime * this._scrollSpeed() * this.speed;
    this.lineScreenX = this._lineScreenX(gameTime);
    this.onScroll?.(this.scrollOffset);
    this.onHitLineUpdate?.(this.lineScreenX);

    const event = this.events[this.nextIndex];
    if (!event) {
      this._maybeAppendEvents();
      this._emitUpdate();
      return;
    }

    if (!event.hit && this._timeToHitLine(event, gameTime) <= 0) {
      event.state = 'missed';
      this.onNoteState?.(event.index, 'missed');
      this.nextIndex++;
      this._loseLife('miss');
      return;
    }

    const timeToHit = this._timeToHitLine(event, gameTime);
    const active = !event.hit && timeToHit > 0 && timeToHit <= this._earlyMs();
    if (active && event.state !== 'active') {
      event.state = 'active';
      this.onNoteState?.(event.index, 'active');
      this._highlightActiveNote();
    }

    this._emitUpdate();
  }

  _maybeAppendEvents() {
    if (this.events.length - this.nextIndex > APPEND_THRESHOLD) return;
    this.onAppendRequest?.(APPEND_BATCH);
  }

  refreshKeyboardHighlight() {
    if (this.running) this._highlightActiveNote();
  }

  _highlightActiveNote() {
    const event = this.events[this.nextIndex];
    if (!event) {
      this.piano.setTargets([]);
      return;
    }

    if (this.showKeyboardHints) {
      this.piano.setTargets([event.midi], [{ midi: event.midi, hand: event.clef === 'bass' ? 'left' : 'right' }]);
    } else {
      this.piano.clearStates(['target', 'target-left', 'target-right']);
    }
  }

  _scrollSpeed() {
    return runnerScrollSpeed(this.tempoScale);
  }

  _lineMoveSpeed() {
    return this.layout?.lineMoveSpeed ?? this._scrollSpeed() * 0.38;
  }

  _advanceSpeed() {
    return this.layout?.advanceSpeed ?? (this._scrollSpeed() + this._lineMoveSpeed());
  }

  _lineScreenX(gameTime) {
    const startX = this.layout?.lineStartX ?? 48;
    return startX + gameTime * this._lineMoveSpeed() * this.speed;
  }

  /** Scale hit windows with tempo — slower scroll gets more time. */
  _earlyMs() {
    return hitWindowsForTempoScale(this.tempoScale).earlyMs;
  }

  /** Milliseconds until the note reaches the play line (>0 = still approaching). */
  _timeToHitLine(event, gameTime) {
    if (!event || !this.layout) return Infinity;
    const scrollOffset = gameTime * this._scrollSpeed() * this.speed;
    const lineX = this._lineScreenX(gameTime);
    return (event.x - lineX - scrollOffset) / this._advanceSpeed();
  }

  _loseLife(reason) {
    this.mistakes++;
    this.combo = 0;
    this.heldMidis.clear();
    this.lives = Math.max(0, this.lives - 1);

    const messages = {
      wrong: 'Не та нота!',
      miss: 'Промах!',
    };
    this.onFeedback?.(messages[reason] ?? 'Ошибка', 'wrong');
    this.piano.flashWrong(this.events[this.nextIndex]?.midi);

    if (this.lives <= 0) {
      this._fail(reason);
      return;
    }

    if (reason === 'miss') {
      this._highlightActiveNote();
      this._maybeAppendEvents();
    }

    this._emitUpdate({ lives: this.lives, reason });
  }

  _fail(reason) {
    this.heldMidis.clear();
    this.gameOver = true;
    this.running = false;
    this._stopLoop();
    this.piano.clearStates(['target', 'target-left', 'target-right']);

    const highScore = saveRhythmHighScore(this.score);
    const totalAttempts = this.score + this.mistakes;
    this.onComplete?.({
      mode: 'rhythm',
      score: this.score,
      combo: this.combo,
      bestCombo: this.bestCombo,
      correct: this.score,
      wrong: this.mistakes,
      total: totalAttempts,
      accuracy: totalAttempts > 0 ? Math.round((this.score / totalAttempts) * 100) : 0,
      highScore,
      lives: this.lives,
      reason,
    });
    this._emitUpdate({ gameOver: true, reason, lives: this.lives });
  }

  _emitUpdate(extra = {}) {
    this.onUpdate?.({
      score: this.score,
      combo: this.combo,
      bestCombo: this.bestCombo,
      nextIndex: this.nextIndex,
      total: this.events.length,
      running: this.running,
      gameOver: this.gameOver,
      scrollOffset: this.scrollOffset,
      speed: this.speed,
      twoHands: this.twoHands,
      lives: this.lives,
      mistakes: this.mistakes,
      maxLives: this.maxLives,
      ...extra,
    });
  }

  _startLoop() {
    this._stopLoop();
    const frame = (now) => {
      this.tick(now);
      if (this.running) {
        this._rafId = requestAnimationFrame(frame);
      }
    };
    this._rafId = requestAnimationFrame(frame);
  }

  _stopLoop() {
    if (this._rafId != null) {
      cancelAnimationFrame(this._rafId);
      this._rafId = null;
    }
  }

  get state() {
    return {
      score: this.score,
      combo: this.combo,
      running: this.running,
      gameOver: this.gameOver,
      twoHands: this.twoHands,
    };
  }
}
