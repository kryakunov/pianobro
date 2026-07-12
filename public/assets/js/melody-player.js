import { PianoSynth } from './piano-synth.js';
import { normalizeLesson } from './lesson-utils.js';

export class MelodyPlayer {
  constructor() {
    this.synth = new PianoSynth();
    this.playing = false;
    this._session = 0;
    this._timeouts = [];
    this.onLoadState = null;
  }

  get isPlaying() {
    return this.playing;
  }

  async ensureReady() {
    this.synth.onLoadState = (s) => this.onLoadState?.(s);
    return this.synth.ensureReady();
  }

  async play(lesson, callbacks = {}) {
    const normalized = normalizeLesson(lesson);
    const events = normalized.events;
    if (!events?.length) return;

    await this.ensureReady();
    this.stop();

    this.playing = true;
    const session = ++this._session;
    const tempo = normalized.tempo || 100;
    const scale = 100 / tempo;
    const audioStart = this.synth.ctx.currentTime + 0.1;

    let offsetMs = 0;

    for (let i = 0; i < events.length; i++) {
      const event = events[i];
      const durationMs = event.duration * scale;
      const index = i;

      const leftNotes = event.notes.filter((n) => n.hand === 'left');
      const rightNotes = event.notes.filter((n) => n.hand !== 'left');

      this._timeouts.push(setTimeout(() => {
        if (session !== this._session) return;
        callbacks.onEventStart?.(index, event);
      }, offsetMs));

      let arpDelay = 0;
      for (const note of leftNotes) {
        const when = audioStart + (offsetMs + arpDelay) / 1000;
        this.synth.play(note.midi, durationMs / 1000, when - this.synth.ctx.currentTime);
        arpDelay += 35;
      }

      for (const note of rightNotes) {
        const when = audioStart + offsetMs / 1000;
        this.synth.play(note.midi, durationMs / 1000, when - this.synth.ctx.currentTime);
      }

      this._timeouts.push(setTimeout(() => {
        if (session !== this._session) return;
        callbacks.onEventEnd?.(index, event);
      }, offsetMs + durationMs * 0.92));

      offsetMs += durationMs;
    }

    this._timeouts.push(setTimeout(() => {
      if (session !== this._session) return;
      this.playing = false;
      callbacks.onComplete?.();
    }, offsetMs + 100));
  }

  stop() {
    this.playing = false;
    this._session++;
    this.synth.stopAll();
    for (const id of this._timeouts) clearTimeout(id);
    this._timeouts = [];
  }
}
