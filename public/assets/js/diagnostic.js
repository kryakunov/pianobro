import { NOTE_SESSION_LIMITS } from './note-trainer.js';
import { midiToName } from './notes.js';

export const DIAGNOSTIC_NOTE_COUNT = 15;

/** @param {Array<{expectedMidi:number, playedMidi:number|null, correct:boolean, responseMs?:number, clef?:string}>} attempts */
export function analyzeDiagnosticAttempts(attempts) {
  const wrongByMidi = new Map();
  const clefErrors = { treble: 0, bass: 0 };
  let totalResponseMs = 0;
  let responseCount = 0;

  for (const attempt of attempts) {
    if (typeof attempt.responseMs === 'number' && attempt.responseMs > 0) {
      totalResponseMs += attempt.responseMs;
      responseCount += 1;
    }

    if (attempt.correct) continue;

    const midi = attempt.expectedMidi;
    wrongByMidi.set(midi, (wrongByMidi.get(midi) ?? 0) + 1);

    const clef = attempt.clef ?? (midi >= 60 ? 'treble' : 'bass');
    if (clef === 'bass') clefErrors.bass += 1;
    else clefErrors.treble += 1;
  }

  const weakNotes = [...wrongByMidi.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    .map(([midi, count]) => ({
      midi,
      name: midiToName(midi),
      count,
    }));

  const correct = attempts.filter((a) => a.correct).length;
  const total = attempts.length;
  const accuracy = total > 0 ? Math.round((correct / total) * 100) : 0;

  return {
    correct,
    total,
    accuracy,
    avgResponseMs: responseCount > 0 ? Math.round(totalResponseMs / responseCount) : 0,
    weakNotes,
    clefErrors,
    attempts,
  };
}

export function formatWeakNotesList(weakNotes) {
  if (!weakNotes?.length) {
    return 'пока явных «слабых» нот не видно — продолжайте тренироваться';
  }
  return weakNotes.map((n) => n.name).join(', ');
}

export function getDiagnosticSessionLimit() {
  const count = window.__PRICING__?.diagnosticNoteCount ?? 15;
  const limits = NOTE_SESSION_LIMITS.filter((n) => n >= count);
  return limits[0] ?? count;
}
