import { normalizeLesson, eventMidis, eventLabel } from './lesson-utils.js';

export class MelodyTrainer {
  constructor(piano) {
    this.piano = piano;
    this.lesson = null;
    this.index = 0;
    this.correct = 0;
    this.wrong = 0;
    this.results = [];
    this.running = false;
    this.paused = false;
    this._held = new Set();
    this.onUpdate = null;
    this.onComplete = null;
    this.onFeedback = null;
    this.showKeyboardHints = true;
  }

  loadLesson(lesson, { sessionLimit = null } = {}) {
    const normalized = normalizeLesson(lesson);
    if (sessionLimit && normalized.events.length > sessionLimit) {
      this.lesson = {
        ...normalized,
        events: normalized.events.slice(0, sessionLimit),
        eventCount: sessionLimit,
      };
    } else {
      this.lesson = normalized;
    }
    this.reset();
  }

  reset() {
    this.index = 0;
    this.correct = 0;
    this.wrong = 0;
    this.results = [];
    this.running = false;
    this.paused = false;
    this._held.clear();
    this.piano.clearStates();
    this._emitUpdate();
  }

  start() {
    const events = this.lesson?.events;
    if (!events?.length) return;
    this.index = 0;
    this.correct = 0;
    this.wrong = 0;
    this.results = events.map(() => null);
    this.running = true;
    this.paused = false;
    this._held.clear();
    this._highlightCurrent();
    this._emitUpdate();
    const hint = this.lesson.twoHands
      ? 'Играйте обеими руками. Аккорд — нажмите все ноты шага.'
      : 'Играйте! Нажмите подсвеченную ноту на пианино.';
    this.onFeedback?.(hint, 'info');
  }

  pause() {
    this.paused = !this.paused;
    if (!this.paused) {
      this._highlightCurrent();
    }
    this._emitUpdate();
  }

  handleNoteOn(midi) {
    if (!this.running || this.paused || !this.lesson) return false;

    const event = this.lesson.events[this.index];
    if (!event) return false;

    const expected = new Set(eventMidis(event));
    if (!expected.has(midi)) {
      this.wrong++;
      this.piano.flashWrong(midi);
      this.onFeedback?.(
        'Неверно',
        'wrong',
      );
      this._emitUpdate();
      return false;
    }

    if (this._held.has(midi)) return true;

    this._held.add(midi);
    this.piano.flashCorrect(midi);

    if (this._held.size < expected.size) {
      const remaining = [...expected].filter((m) => !this._held.has(m));
      this.onFeedback?.(
        `Верно! Ещё: ${remaining.length}`,
        'correct',
      );
      this._emitUpdate();
      return true;
    }

    this.correct++;
    this.results[this.index] = 'correct';
    this.onFeedback?.('Верно!', 'correct');
    this.index++;
    this._held.clear();
    this._emitUpdate();

    if (this.index >= this.lesson.events.length) {
      this._finish();
    } else {
      this._highlightCurrent();
    }
    return true;
  }

  handleNoteOff(midi) {
    this.piano.releaseKey(midi);
  }

  _highlightCurrent() {
    const events = this.lesson?.events;
    if (!events || this.index >= events.length) {
      this.piano.setTargets([]);
      return;
    }
    this._held.clear();
    this.piano.clearStates(['correct', 'wrong', 'pressed']);
    const event = events[this.index];
    if (this.showKeyboardHints) {
      this.piano.setTargets(eventMidis(event), event.notes);
    } else {
      this.piano.clearStates(['target', 'target-left', 'target-right']);
    }
  }

  refreshKeyboardHighlight() {
    if (this.running) this._highlightCurrent();
  }

  _finish() {
    this.running = false;
    this.piano.setTargets([]);
    this.piano.clearStates(['target']);
    const accuracy = this.correct + this.wrong > 0
      ? Math.round((this.correct / (this.correct + this.wrong)) * 100)
      : 0;
    this.onFeedback?.(`Мелодия завершена! Точность: ${accuracy}%`, 'complete');
    this.onComplete?.({
      correct: this.correct,
      wrong: this.wrong,
      accuracy,
      total: this.lesson?.events?.length ?? 0,
    });
    this._emitUpdate();
  }

  _emitUpdate() {
    const events = this.lesson?.events ?? [];
    const current = events[this.index] ?? null;
    this.onUpdate?.({
      index: this.index,
      total: events.length,
      correct: this.correct,
      wrong: this.wrong,
      running: this.running,
      paused: this.paused,
      currentEvent: current,
      currentNote: current?.notes[0] ?? null,
      results: this.results,
      events,
      twoHands: this.lesson?.twoHands ?? false,
    });
  }

  get state() {
    return {
      index: this.index,
      total: this.lesson?.events?.length ?? 0,
      correct: this.correct,
      wrong: this.wrong,
      running: this.running,
      paused: this.paused,
      twoHands: this.lesson?.twoHands ?? false,
    };
  }
}
