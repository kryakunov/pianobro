import { midiToSoundFontName } from './notes.js';

let soundfontModule = null;

async function loadSoundfontLib() {
  if (!soundfontModule) {
    soundfontModule = await import('https://esm.sh/soundfont-player@0.12.0');
  }
  return soundfontModule.default ?? soundfontModule;
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

  async ensureReady() {
    if (this.instrument) return this.instrument;
    if (this._loading) return this._loading;

    this._loading = this._init();
    return this._loading;
  }

  async _init() {
    this.onLoadState?.('loading');
    this.ctx = new AudioContext();
    if (this.ctx.state === 'suspended') {
      await this.ctx.resume();
    }

    const Soundfont = await loadSoundfontLib();
    this.instrument = await Soundfont.instrument(this.ctx, 'acoustic_grand_piano', {
      soundfont: 'FatBoy',
      format: 'mp3',
    });

    this.onLoadState?.('ready');
    this._loading = null;
    return this.instrument;
  }

  async play(midi, durationSec, whenSec = 0) {
    await this.ensureReady();
    const name = midiToSoundFontName(midi);
    const start = this.ctx.currentTime + whenSec;
    return this.instrument.play(name, start, {
      duration: durationSec,
      gain: midi < 48 ? 1.15 : 1,
    });
  }

  stopAll() {
    if (this.instrument?.stop) {
      this.instrument.stop();
    }
  }
}
