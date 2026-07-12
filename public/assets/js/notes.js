/** Названия с диезами */
const NOTE_NAMES_SHARP = [
  'До', 'До-диез', 'Ре', 'Ре-диез', 'Ми', 'Фа', 'Фа-диез', 'Соль', 'Соль-диез', 'Ля', 'Ля-диез', 'Си',
];

/** Названия чёрных клавиш с бемолями */
const FLAT_NAMES = {
  1: 'Ре-бемоль',
  3: 'Ми-бемоль',
  6: 'Соль-бемоль',
  8: 'Ля-бемоль',
  10: 'Си-бемоль',
};

/** Позиция на стане для бемольного написания (белая нота строки) */
const FLAT_STAFF_MIDI = {
  1: 62, 3: 64, 6: 67, 8: 69, 10: 71,
};

/** Английские названия — только для MIDI/soundfont */
const NOTE_NAMES_EN = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

export function isBlackKey(midi) {
  return [1, 3, 6, 8, 10].includes(midi % 12);
}

/**
 * @param {'sharp'|'flat'} spelling
 */
export function midiToName(midi, spelling = 'sharp') {
  const pc = midi % 12;
  if (isBlackKey(midi) && spelling === 'flat') {
    return FLAT_NAMES[pc];
  }
  return NOTE_NAMES_SHARP[pc];
}

/**
 * Данные для отрисовки на нотном стане.
 * @param {'sharp'|'flat'} spelling
 */
export function midiToStaffNote(midi, spelling = 'sharp') {
  const pc = midi % 12;
  const name = midiToName(midi, spelling);

  if (!isBlackKey(midi)) {
    return { staffMidi: midi, accidental: null, name };
  }

  if (spelling === 'flat') {
    return { staffMidi: FLAT_STAFF_MIDI[pc], accidental: 'flat', name };
  }

  return { staffMidi: midi, accidental: 'sharp', name };
}

export function midiToSoundFontName(midi) {
  const octave = Math.floor(midi / 12) - 1;
  return NOTE_NAMES_EN[midi % 12] + octave;
}

export const PIANO_START = 21;
export const PIANO_END = 108;

export const KEYBOARD_MAP = {
  a: 60, w: 61, s: 62, e: 63, d: 64, f: 65, t: 66, g: 67, y: 68, h: 69, u: 70, j: 71, k: 72,
  z: 48, x: 50, c: 52, v: 53, b: 55, n: 57, m: 59,
};

export const REVERSE_KEYBOARD_MAP = Object.fromEntries(
  Object.entries(KEYBOARD_MAP).map(([k, v]) => [v, k.toUpperCase()])
);
