/** Названия для интерфейса (русская классическая система) */
const NOTE_NAMES_RU = [
  'До', 'До-диез', 'Ре', 'Ре-диез', 'Ми', 'Фа', 'Фа-диез', 'Соль', 'Соль-диез', 'Ля', 'Ля-диез', 'Си',
];

/** Английские названия — только для MIDI/soundfont */
const NOTE_NAMES_EN = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

export function midiToName(midi) {
  return NOTE_NAMES_RU[midi % 12];
}

export function midiToSoundFontName(midi) {
  const octave = Math.floor(midi / 12) - 1;
  return NOTE_NAMES_EN[midi % 12] + octave;
}

export function isBlackKey(midi) {
  return [1, 3, 6, 8, 10].includes(midi % 12);
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
