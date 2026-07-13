import { readFileSync, writeFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const outDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'data', 'lessons');

/** @param {number[]} pairs */
function notes(...pairs) {
  const out = [];
  for (let i = 0; i < pairs.length; i += 2) {
    out.push({ midi: pairs[i], duration: pairs[i + 1] });
  }
  return out;
}

const Q = 400;
const H = 800;
const E = 300;
const L = 500;

const intermediate = [
  {
    id: 'gymnopedie-no1',
    title: 'Gymnopédie No. 1',
    composer: 'E. Satie',
    tempo: 76,
    notes: notes(67, L, 69, L, 71, L, 72, L, 71, E, 69, E, 67, H, 64, L, 67, L, 71, H),
  },
  {
    id: 'clair-de-lune',
    title: 'Clair de Lune',
    composer: 'C. Debussy',
    tempo: 66,
    notes: notes(68, L, 71, L, 73, L, 76, L, 75, E, 73, E, 71, H, 68, L, 71, L, 73, L, 76, H),
  },
  {
    id: 'interstellar',
    title: 'Interstellar',
    composer: 'H. Zimmer',
    tempo: 60,
    notes: notes(60, L, 64, L, 67, L, 72, L, 76, L, 79, L, 84, L, 83, E, 79, E, 76, H, 72, L, 67, L, 64, H),
  },
  {
    id: 'una-mattina',
    title: 'Una Mattina',
    composer: 'Ludovico Einaudi',
    tempo: 74,
    notes: notes(72, L, 76, L, 79, L, 84, L, 83, E, 79, E, 76, H, 72, L, 69, L, 72, L, 76, H),
  },
  {
    id: 'autumn-leaves',
    title: 'Autumn Leaves',
    composer: 'J. Kosma',
    tempo: 88,
    notes: notes(69, Q, 72, Q, 74, Q, 76, H, 74, Q, 72, Q, 69, H, 67, Q, 69, Q, 72, H),
  },
  {
    id: 'hallelujah',
    title: 'Hallelujah',
    composer: 'L. Cohen',
    tempo: 82,
    notes: notes(60, Q, 64, Q, 67, Q, 72, H, 71, Q, 69, Q, 67, H, 64, Q, 62, Q, 60, H),
  },
  {
    id: 'fly-me-to-moon',
    title: 'Fly Me to the Moon',
    composer: 'B. Howard',
    tempo: 96,
    notes: notes(60, Q, 64, Q, 67, Q, 71, H, 69, Q, 67, Q, 64, H, 62, Q, 64, Q, 67, H),
  },
  {
    id: 'turkish-march',
    title: 'Турецкий марш',
    composer: 'W. A. Mozart',
    tempo: 120,
    notes: notes(76, E, 74, E, 72, E, 71, E, 69, E, 71, E, 72, E, 74, E, 76, E, 79, E, 76, E, 74, E, 72, H),
  },
  {
    id: 'clocks',
    title: 'Clocks',
    composer: 'Coldplay',
    tempo: 131,
    notes: notes(72, E, 76, E, 79, E, 84, E, 79, E, 76, E, 72, Q, 69, Q, 72, H, 76, E, 79, E, 84, H),
  },
  {
    id: 'time-inception',
    title: 'Time (Inception)',
    composer: 'H. Zimmer',
    tempo: 54,
    notes: notes(48, L, 55, L, 60, L, 64, L, 67, L, 72, L, 76, L, 79, L, 76, E, 72, E, 67, H),
  },
];

const advanced = [
  {
    id: 'comptine-duo',
    title: 'Амели (две руки)',
    composer: 'Yann Tiersen',
    tempo: 100,
    twoHands: true,
    events: [
      { duration: 300, notes: [{ midi: 45, hand: 'left' }] },
      { duration: 300, notes: [{ midi: 52, hand: 'left' }] },
      { duration: 300, notes: [{ midi: 57, hand: 'left' }] },
      { duration: 300, notes: [{ midi: 76, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 41, hand: 'left' }] },
      { duration: 300, notes: [{ midi: 48, hand: 'left' }] },
      { duration: 300, notes: [{ midi: 53, hand: 'left' }] },
      { duration: 300, notes: [{ midi: 75, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 76, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 45, hand: 'left' }, { midi: 75, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 52, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 57, hand: 'left' }, { midi: 75, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 43, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 50, hand: 'left' }, { midi: 71, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 55, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 52, hand: 'left' }, { midi: 71, hand: 'right' }] },
      { duration: 600, notes: [{ midi: 45, hand: 'left' }, { midi: 69, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 48, hand: 'left' }, { midi: 71, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 52, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 57, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 55, hand: 'left' }, { midi: 79, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 52, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 300, notes: [{ midi: 48, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 900, notes: [{ midi: 45, hand: 'left' }, { midi: 71, hand: 'right' }] },
    ],
  },
  {
    id: 'interstellar-duo',
    title: 'Interstellar (две руки)',
    composer: 'H. Zimmer',
    tempo: 60,
    twoHands: true,
    events: [
      { duration: 500, notes: [{ midi: 48, hand: 'left' }] },
      { duration: 500, notes: [{ midi: 55, hand: 'left' }] },
      { duration: 500, notes: [{ midi: 60, hand: 'left' }, { midi: 72, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 52, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 57, hand: 'left' }, { midi: 79, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 60, hand: 'left' }, { midi: 84, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 55, hand: 'left' }, { midi: 83, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 52, hand: 'left' }, { midi: 79, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 48, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 45, hand: 'left' }, { midi: 72, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 50, hand: 'left' }, { midi: 67, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 55, hand: 'left' }, { midi: 64, hand: 'right' }] },
      { duration: 1000, notes: [{ midi: 48, hand: 'left' }, { midi: 60, hand: 'right' }] },
    ],
  },
  {
    id: 'una-mattina-duo',
    title: 'Una Mattina (две руки)',
    composer: 'Ludovico Einaudi',
    tempo: 74,
    twoHands: true,
    events: [
      { duration: 500, notes: [{ midi: 48, hand: 'left' }] },
      { duration: 500, notes: [{ midi: 55, hand: 'left' }] },
      { duration: 500, notes: [{ midi: 60, hand: 'left' }, { midi: 72, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 52, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 57, hand: 'left' }, { midi: 79, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 60, hand: 'left' }, { midi: 84, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 55, hand: 'left' }, { midi: 83, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 52, hand: 'left' }, { midi: 79, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 48, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 45, hand: 'left' }, { midi: 72, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 50, hand: 'left' }, { midi: 69, hand: 'right' }] },
      { duration: 500, notes: [{ midi: 55, hand: 'left' }, { midi: 72, hand: 'right' }] },
      { duration: 1000, notes: [{ midi: 48, hand: 'left' }, { midi: 76, hand: 'right' }] },
    ],
  },
  {
    id: 'river-flows-duo',
    title: 'River Flows in You (две руки)',
    composer: 'Yiruma',
    tempo: 72,
    twoHands: true,
    events: [
      { duration: 450, notes: [{ midi: 50, hand: 'left' }] },
      { duration: 450, notes: [{ midi: 57, hand: 'left' }] },
      { duration: 450, notes: [{ midi: 62, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 54, hand: 'left' }, { midi: 78, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 59, hand: 'left' }, { midi: 81, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 62, hand: 'left' }, { midi: 78, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 57, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 50, hand: 'left' }, { midi: 71, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 48, hand: 'left' }, { midi: 69, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 52, hand: 'left' }, { midi: 71, hand: 'right' }] },
      { duration: 450, notes: [{ midi: 57, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 900, notes: [{ midi: 50, hand: 'left' }, { midi: 78, hand: 'right' }] },
    ],
  },
  {
    id: 'turkish-march-duo',
    title: 'Турецкий марш (две руки)',
    composer: 'W. A. Mozart',
    tempo: 120,
    twoHands: true,
    events: [
      { duration: 200, notes: [{ midi: 40, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 52, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 40, hand: 'left' }, { midi: 72, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 52, hand: 'left' }, { midi: 71, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 38, hand: 'left' }, { midi: 69, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 50, hand: 'left' }, { midi: 71, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 38, hand: 'left' }, { midi: 72, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 50, hand: 'left' }, { midi: 74, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 40, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 52, hand: 'left' }, { midi: 79, hand: 'right' }] },
      { duration: 200, notes: [{ midi: 40, hand: 'left' }, { midi: 76, hand: 'right' }] },
      { duration: 400, notes: [{ midi: 52, hand: 'left' }, { midi: 74, hand: 'right' }] },
    ],
  },
];

function writeLesson(fileName, payload) {
  writeFileSync(join(outDir, fileName), `${JSON.stringify(payload, null, 4)}\n`, 'utf8');
}

for (const melody of intermediate) {
  writeLesson(`${melody.id}.json`, {
    ...melody,
    difficulty: 'intermediate',
    category: 'popular',
  });
}

for (const melody of advanced) {
  writeLesson(`${melody.id}.json`, {
    ...melody,
    difficulty: 'advanced',
    category: 'popular',
  });
}

const patches = {
  'comptine.json': { title: 'Амели', category: 'popular' },
  'experience.json': { title: 'Experience', category: 'popular' },
  'experience-duo.json': { title: 'Experience (две руки)', category: 'popular' },
  'nuvole-bianche.json': { category: 'popular' },
  'nuvole-bianche-duo.json': { category: 'popular' },
  'river-flows.json': { category: 'popular' },
  'moonlight.json': { category: 'international' },
  'moonlight-duo.json': { category: 'international' },
  'fur-elise.json': { category: 'international' },
  'fur-elise-duo.json': { category: 'international' },
  'canon-in-d.json': { category: 'international' },
  'clair-de-lune-duo.json': { category: 'popular' },
};

for (const [fileName, patch] of Object.entries(patches)) {
  const path = join(outDir, fileName);
  const data = JSON.parse(readFileSync(path, 'utf8'));
  Object.assign(data, patch);
  writeLesson(fileName, data);
}

console.log(`Added ${intermediate.length} intermediate and ${advanced.length} advanced lessons`);
