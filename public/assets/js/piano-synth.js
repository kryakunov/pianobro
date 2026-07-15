import { midiToSoundFontName } from './notes.js';

let soundfontModule = null;

async function loadSoundfontLib() {
  if (!soundfontModule) {
    soundfontModule = await import('https://esm.sh/soundfont-player@0.12.0');
  }
  return soundfontModule.default ?? soundfontModule;
}

function getAudioContextClass() {
  return window.AudioContext || window.webkitAudioContext || null;
}

export class PianoSynth {
  constructor() {
    this.ctx = null;
    this.instrument = null;
    this._loading = null;
    this.onLoadState = null;
  }

  get isReady() {
    return this.instrument !== null;
  }

  /** Must run synchronously inside a user gesture (tap / click). */
  unlockFromUserGesture() {
    if (!this.ctx) {
      const Ctx = getAudioContextClass();
      if (!Ctx) return;
      this.ctx = new Ctx();
      this._startLoadingInstrument();
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

  _startLoadingInstrument() {
    if (this._loading || this.instrument || !this.ctx) return;
    this._loading = this._loadInstrument();
  }

  async _loadInstrument() {
    this.onLoadState?.('loading');
    try {
      const Soundfont = await loadSoundfontLib();
      this.instrument = await Soundfont.instrument(this.ctx, 'acoustic_grand_piano', {
        soundfont: 'FatBoy',
        format: 'mp3',
      });
      this.onLoadState?.('ready');
      return this.instrument;
    } finally {
      this._loading = null;
    }
  }

  async ensureReady() {
    if (!this.ctx) {
      this.unlockFromUserGesture();
    }
    if (!this.ctx) return null;

    if (this.ctx.state === 'suspended') {
      await this.ctx.resume();
    }

    if (this.instrument) return this.instrument;
    if (this._loading) return this._loading;

    this._startLoadingInstrument();
    return this._loading;
  }

  async play(midi, durationSec, whenSec = 0) {
    await this.ensureReady();
    if (!this.ctx || !this.instrument) return;

    if (this.ctx.state === 'suspended') {
      await this.ctx.resume();
    }

    const name = midiToSoundFontName(midi);
    const start = this.ctx.currentTime + whenSec;
    return this.instrument.play(name, start, {
      duration: durationSec,
      gain: midi < 48 ? 1.25 : 1.1,
    });
  }

  playFallback(midi, durationSec = 0.42) {
    if (!this.ctx || !Number.isFinite(midi)) return;

    if (this.ctx.state === 'suspended') {
      void this.ctx.resume();
    }

    try {
      const freq = 440 * (2 ** ((midi - 69) / 12));
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
    if (this.instrument?.stop) {
      this.instrument.stop();
    }
  }
}
