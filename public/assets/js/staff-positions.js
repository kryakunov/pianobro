import { isBlackKey, midiToStaffNote } from './notes.js';

export const TREBLE_REF = 64;
export const BASS_REF = 43;
export const MIDDLE_C_MIDI = 60;

export function diatonicSteps(midi) {
  const map = [0, 0, 1, 1, 2, 3, 3, 4, 4, 5, 5, 6];
  return Math.floor(midi / 12) * 7 + map[midi % 12];
}

/** @param {{ trebleBottom: number, bassBottom: number, lineGap: number }} metrics */
export function midiToTrebleY(staffMidi, metrics) {
  return metrics.trebleBottom
    - (diatonicSteps(staffMidi) - diatonicSteps(TREBLE_REF)) * (metrics.lineGap / 2);
}

/** @param {{ trebleBottom: number, bassBottom: number, lineGap: number }} metrics */
export function midiToBassY(staffMidi, metrics) {
  return metrics.bassBottom
    - (diatonicSteps(staffMidi) - diatonicSteps(BASS_REF)) * (metrics.lineGap / 2);
}

/** @param {{ trebleBottom: number, bassBottom: number, lineGap: number }} metrics */
export function staffTops(metrics) {
  return {
    trebleTop: metrics.trebleBottom - 4 * metrics.lineGap,
    bassTop: metrics.bassBottom - 4 * metrics.lineGap,
  };
}

function clefForMidi(midi) {
  const anchor = isBlackKey(midi) ? midi - 1 : midi;
  return anchor >= MIDDLE_C_MIDI ? 'treble' : 'bass';
}

/** @param {{ trebleBottom: number, bassBottom: number, lineGap: number }} metrics */
function whiteKeyY(midi, metrics) {
  const { staffMidi } = midiToStaffNote(midi, 'sharp');
  if (clefForMidi(midi) === 'treble') {
    return midiToTrebleY(staffMidi, metrics);
  }
  return midiToBassY(staffMidi, metrics);
}

/**
 * Диез/бемоль — на линии или в промежутке белой клавиши снизу (как в нотации).
 * @param {{ trebleBottom: number, bassBottom: number, lineGap: number }} metrics
 */
function blackKeyY(midi, metrics) {
  return whiteKeyY(midi - 1, metrics);
}

/**
 * @param {number} midi
 * @param {{ trebleBottom: number, bassBottom: number, lineGap: number }} metrics
 */
export function resolveGrandStaffNote(midi, metrics) {
  const clef = clefForMidi(midi);
  const y = isBlackKey(midi) ? blackKeyY(midi, metrics) : whiteKeyY(midi, metrics);

  return {
    y,
    clef,
    staffMidi: midi,
  };
}

/** Ключ для добавочных линий — по положению между станами. */
export function resolveLedgerClef(y, metrics) {
  const { bassTop } = staffTops(metrics);
  const mid = (metrics.trebleBottom + bassTop) / 2;
  return y <= mid ? 'treble' : 'bass';
}

/**
 * @param {number} x
 * @param {number} y
 * @param {'treble'|'bass'} clef
 * @param {{ trebleBottom: number, bassBottom: number, lineGap: number }} metrics
 */
export function renderLedgerLines(x, y, _clef, metrics) {
  const ledgerClef = resolveLedgerClef(y, metrics);
  const bottom = ledgerClef === 'bass' ? metrics.bassBottom : metrics.trebleBottom;
  const top = bottom - 4 * metrics.lineGap;
  const halfWidth = metrics.lineGap * 1.15;
  const lines = [];

  if (y > bottom + 0.5) {
    for (let ly = bottom + metrics.lineGap; ly <= y + 1; ly += metrics.lineGap) {
      lines.push(
        `<line x1="${x - halfWidth}" y1="${ly}" x2="${x + halfWidth}" y2="${ly}" class="stats-staff__ledger"/>`,
      );
    }
  }

  if (y < top - 0.5) {
    for (let ly = top - metrics.lineGap; ly >= y - 1; ly -= metrics.lineGap) {
      lines.push(
        `<line x1="${x - halfWidth}" y1="${ly}" x2="${x + halfWidth}" y2="${ly}" class="stats-staff__ledger"/>`,
      );
    }
  }

  return lines.join('');
}
