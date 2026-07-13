import { PIANO_END, PIANO_START } from './notes.js';

const MIN_FREQ = 27.5;
const MAX_FREQ = 4186;

function freqToMidi(freq) {
  return Math.round(69 + 12 * Math.log2(freq / 440));
}

/** Autocorrelation pitch detection (monophonic). Returns Hz or -1. */
function detectPitch(buffer, sampleRate) {
  const size = buffer.length;
  let rms = 0;
  for (let i = 0; i < size; i++) {
    rms += buffer[i] * buffer[i];
  }
  rms = Math.sqrt(rms / size);
  if (rms < 0.008) return { freq: -1, clarity: 0, rms };

  let start = 0;
  let end = size - 1;
  const thres = 0.2;
  for (let i = 0; i < size / 2; i++) {
    if (Math.abs(buffer[i]) < thres) {
      start = i;
      break;
    }
  }
  for (let i = 1; i < size / 2; i++) {
    if (Math.abs(buffer[size - i]) < thres) {
      end = size - i;
      break;
    }
  }

  const trimmed = buffer.subarray(start, end);
  const len = trimmed.length;
  if (len < 64) return { freq: -1, clarity: 0, rms };

  const corr = new Float32Array(len);
  for (let lag = 0; lag < len; lag++) {
    let sum = 0;
    for (let i = 0; i < len - lag; i++) {
      sum += trimmed[i] * trimmed[i + lag];
    }
    corr[lag] = sum;
  }

  let d = 0;
  while (d + 1 < len && corr[d] > corr[d + 1]) d++;

  let peak = d;
  let peakVal = corr[d];
  const minLag = Math.floor(sampleRate / MAX_FREQ);
  const maxLag = Math.ceil(sampleRate / MIN_FREQ);
  for (let i = Math.max(d, minLag); i < Math.min(len - 1, maxLag); i++) {
    if (corr[i] > peakVal) {
      peakVal = corr[i];
      peak = i;
    }
  }

  if (peak < minLag || peak >= maxLag) return { freq: -1, clarity: 0, rms };

  const y1 = corr[peak - 1] ?? corr[peak];
  const y2 = corr[peak];
  const y3 = corr[peak + 1] ?? corr[peak];
  const denom = y1 - 2 * y2 + y3;
  const shift = denom ? (y1 - y3) / (2 * denom) : 0;
  const freq = sampleRate / (peak + shift);
  const clarity = corr[0] ? peakVal / corr[0] : 0;

  if (!Number.isFinite(freq) || freq < MIN_FREQ || freq > MAX_FREQ) {
    return { freq: -1, clarity: 0, rms };
  }

  return { freq, clarity, rms };
}

export class MicPitchInput {
  constructor() {
    this.onNoteOn = null;
    this.onNoteOff = null;
    this.onActivity = null;
    this.onStatusChange = null;
    this.sensitivity = 0.82;
    this._stream = null;
    this._ctx = null;
    this._analyser = null;
    this._buffer = null;
    this._raf = 0;
    this._active = false;
    this._currentNote = null;
    this._candidate = null;
    this._candidateHits = 0;
    this._missFrames = 0;
    this._onFrames = 3;
    this._offFrames = 6;
  }

  get isSupported() {
    return Boolean(navigator.mediaDevices?.getUserMedia);
  }

  get isActive() {
    return this._active;
  }

  async start() {
    if (this._active) return;
    if (!this.isSupported) {
      throw new Error('Микрофон недоступен в этом браузере');
    }

    this._stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: false,
        noiseSuppression: false,
        autoGainControl: false,
      },
      video: false,
    });

    this._ctx = new AudioContext();
    if (this._ctx.state === 'suspended') {
      await this._ctx.resume();
    }

    const source = this._ctx.createMediaStreamSource(this._stream);
    const highpass = this._ctx.createBiquadFilter();
    highpass.type = 'highpass';
    highpass.frequency.value = 70;

    this._analyser = this._ctx.createAnalyser();
    this._analyser.fftSize = 4096;
    this._analyser.smoothingTimeConstant = 0.2;

    source.connect(highpass);
    highpass.connect(this._analyser);

    this._buffer = new Float32Array(this._analyser.fftSize);
    this._active = true;
    this._currentNote = null;
    this._candidate = null;
    this._candidateHits = 0;
    this._missFrames = 0;
    this.onStatusChange?.('on');
    this._tick();
  }

  stop() {
    this._active = false;
    if (this._raf) {
      cancelAnimationFrame(this._raf);
      this._raf = 0;
    }

    if (this._currentNote !== null) {
      const note = this._currentNote;
      this._currentNote = null;
      this.onActivity?.({ note, type: 'off', source: 'mic' });
      this.onNoteOff?.(note);
    }

    for (const track of this._stream?.getTracks() ?? []) {
      track.stop();
    }
    this._stream = null;

    if (this._ctx) {
      this._ctx.close();
      this._ctx = null;
    }

    this._analyser = null;
    this._buffer = null;
    this._candidate = null;
    this._candidateHits = 0;
    this._missFrames = 0;
    this.onStatusChange?.('off');
  }

  _tick() {
    if (!this._active || !this._analyser || !this._buffer) return;

    this._analyser.getFloatTimeDomainData(this._buffer);
    const { freq, clarity, rms } = detectPitch(this._buffer, this._ctx.sampleRate);
    const confident = clarity >= this.sensitivity && rms >= 0.01;
    const midi = confident ? freqToMidi(freq) : null;
    const inRange = midi !== null && midi >= PIANO_START && midi <= PIANO_END;

    if (inRange) {
      this._missFrames = 0;
      if (this._candidate === midi) {
        this._candidateHits++;
      } else {
        this._candidate = midi;
        this._candidateHits = 1;
      }

      if (this._candidateHits >= this._onFrames && this._currentNote !== midi) {
        if (this._currentNote !== null) {
          const prev = this._currentNote;
          this.onActivity?.({ note: prev, type: 'off', source: 'mic' });
          this.onNoteOff?.(prev);
        }
        this._currentNote = midi;
        this.onActivity?.({ note: midi, type: 'on', source: 'mic', freq, clarity });
        this.onNoteOn?.(midi, Math.min(127, Math.round(rms * 400)));
      } else if (this._currentNote === midi) {
        this.onActivity?.({ note: midi, type: 'on', source: 'mic', freq, clarity });
      }
    } else {
      this._candidate = null;
      this._candidateHits = 0;
      this._missFrames++;
      if (this._currentNote !== null && this._missFrames >= this._offFrames) {
        const note = this._currentNote;
        this._currentNote = null;
        this.onActivity?.({ note, type: 'off', source: 'mic' });
        this.onNoteOff?.(note);
      }
    }

    this._raf = requestAnimationFrame(() => this._tick());
  }
}
