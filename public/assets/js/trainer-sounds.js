import { PianoSynth } from './piano-synth.js';

const synth = new PianoSynth();

export async function playTrainerNote(midi, durationSec = 0.42) {
  if (!Number.isFinite(midi)) return;
  try {
    await synth.play(midi, durationSec);
  } catch {
    /* ignore audio errors */
  }
}

export async function playTrainerWrong() {
  try {
    await synth.ensureReady();
    const ctx = synth.ctx;
    if (!ctx) return;

    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(130, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(90, ctx.currentTime + 0.22);
    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.24);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.25);
  } catch {
    /* ignore audio errors */
  }
}

export async function warmupTrainerSound() {
  try {
    await synth.ensureReady();
  } catch {
    /* ignore */
  }
}
