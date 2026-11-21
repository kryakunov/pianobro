import { midiToSoundFontName } from './notes.js';

const SAMPLE_BASE = 'https://gleitz.github.io/midi-js-soundfonts/MusyngKite/acoustic_grand_piano-mp3/';

function getAudioContextClass() {
  return window.AudioContext || window.webkitAudioContext || null;
}

export class PianoSynth {
  constructor() {
    this.ctx = null;
    this.buffers = new Map();
    this.loadingBuffers = new Map();
    this._active = new Set();
    this.onLoadState = null;
  }

  get isReady() {
    return this.ctx !== null;
  }

  /** Must run synchronously inside a user gesture (tap / click). */
  unlockFromUserGesture() {
    if (!this.ctx) {
      const Ctx = getAudioContextClass();
      if (!Ctx) return;
      this.ctx = new Ctx({ latencyHint: 'interactive' });
    }

    const { ctx } = this;
    if (ctx.state === 'suspended') {
      void ctx.resume();
    }

    try {
      const buffer = ctx.createBuffer(1, 1, 22050);
      const source = ctx.createBufferSource();
      source.buffer = buffer;
      source.connect(ctx.destination);
      source.start(0);
      source.stop(ctx.currentTime + 0.001);
    } catch {
      /* ignore unlock errors */
    }
  }

  preload(midis) {
    if (!this.ctx) this.unlockFromUserGesture();
    for (const midi of midis) {
      void this.loadBuffer(midi).catch(() => {});
    }
  }

  async loadBuffer(midi) {
    if (!this.ctx || !Number.isFinite(midi)) return null;
    const name = midiToSoundFontName(midi);
    if (this.buffers.has(name)) return this.buffers.get(name);
    if (this.loadingBuffers.has(name)) return this.loadingBuffers.get(name);

    const task = (async () => {
      const response = await fetch(`${SAMPLE_BASE}${name}.mp3`);
      if (!response.ok) throw new Error(`sample ${name}`);
      const data = await response.arrayBuffer();
      const buffer = await this.ctx.decodeAudioData(data.slice(0));
      this.buffers.set(name, buffer);
      return buffer;
    })();

    this.loadingBuffers.set(name, task);
    try {
      return await task;
    } finally {
      this.loadingBuffers.delete(name);
    }
  }

  playBuffer(buffer, durationSec) {
    if (!this.ctx || !buffer) return false;
    const source = this.ctx.createBufferSource();
    const gain = this.ctx.createGain();
    const now = this.ctx.currentTime;
    const releaseAt = now + Math.max(0.12, durationSec);

    source.buffer = buffer;
    gain.gain.setValueAtTime(0.95, now);
    gain.gain.setValueAtTime(0.95, releaseAt);
    gain.gain.exponentialRampToValueAtTime(0.0001, releaseAt + 0.12);
    source.connect(gain);
    gain.connect(this.ctx.destination);
    source.start(now);
    source.stop(releaseAt + 0.14);
    this._active.add(source);
    source.onended = () => this._active.delete(source);
    return true;
  }

  async playSample(midi, durationSec) {
    if (!this.ctx) this.unlockFromUserGesture();
    if (!this.ctx) return false;
    if (this.ctx.state === 'suspended') await this.ctx.resume();
    const buffer = await this.loadBuffer(midi);
    return this.playBuffer(buffer, durationSec);
  }

  async play(midi, durationSec, whenSec = 0) {
    if (!this.ctx) this.unlockFromUserGesture();
    if (!this.ctx) return false;
    if (this.ctx.state === 'suspended') await this.ctx.resume();
    const buffer = await this.loadBuffer(midi);
    if (!buffer) return false;

    const source = this.ctx.createBufferSource();
    const gain = this.ctx.createGain();
    const startAt = this.ctx.currentTime + Math.max(0, whenSec);
    const releaseAt = startAt + Math.max(0.12, durationSec);
    source.buffer = buffer;
    gain.gain.setValueAtTime(0.95, startAt);
    gain.gain.setValueAtTime(0.95, releaseAt);
    gain.gain.exponentialRampToValueAtTime(0.0001, releaseAt + 0.12);
    source.connect(gain);
    gain.connect(this.ctx.destination);
    source.start(startAt);
    source.stop(releaseAt + 0.14);
    this._active.add(source);
    source.onended = () => this._active.delete(source);
    return true;
  }

  playFallback(midi, durationSec = 0.42) {
    if (!this.ctx || !Number.isFinite(midi)) return;

    if (this.ctx.state === 'suspended') {
      void this.ctx.resume();
    }

    try {
      const freq = 440 * Math.pow(2, (midi - 69) / 12);
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      const now = this.ctx.currentTime;

      osc.type = 'triangle';
      osc.frequency.setValueAtTime(freq, now);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.exponentialRampToValueAtTime(0.22, now + 0.015);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + durationSec);

      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start(now);
      osc.stop(now + durationSec + 0.05);
    } catch {
      /* ignore */
    }
  }

  stopAll() {
    for (const source of this._active) {
      try {
        source.stop();
      } catch {
        /* already stopped */
      }
    }
    this._active.clear();
  }
}
