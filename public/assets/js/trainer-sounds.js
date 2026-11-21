import { PianoSynth } from './piano-synth.js';

const synth = new PianoSynth();

export function unlockTrainerSoundFromGesture() {
  synth.unlockFromUserGesture();
}

export function playTrainerNote(midi, durationSec = 0.42) {
  if (!Number.isFinite(midi)) return;

  const start = () => {
    void synth.playSample(midi, durationSec).then((played) => {
      if (!played) synth.playFallback(midi, durationSec);
    }).catch(() => {
      synth.playFallback(midi, durationSec);
    });
  };

  try {
    synth.unlockFromUserGesture();
    if (synth.ctx?.state === 'suspended') {
      synth.ctx.resume().then(start).catch(start);
      return;
    }
    start();
  } catch {
    synth.playFallback(midi, durationSec);
  }
}

export function preloadTrainerNotes(midis) {
  const unique = [...new Set(midis.map((midi) => Number(midi)).filter((midi) => Number.isFinite(midi)))];
  if (!unique.length) return;
  synth.preload(unique.slice(0, 48));
}

export async function playTrainerWrong() {
  try {
    synth.unlockFromUserGesture();
    if (synth.ctx?.state === 'suspended') await synth.ctx.resume();
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
    synth.unlockFromUserGesture();
    if (synth.ctx?.state === 'suspended') await synth.ctx.resume();
  } catch {
    /* ignore */
  }
}

export function stopTrainerSound() {
  try {
    synth.stopAll();
  } catch {
    /* ignore */
  }
}
