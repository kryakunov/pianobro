import { writeFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const outDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'data', 'lessons');

const E = 350;
const Q = 700;
const H = 1400;

/** @param {number[]} pairs */
function notes(...pairs) {
  const out = [];
  for (let i = 0; i < pairs.length; i += 2) {
    out.push({ midi: pairs[i], duration: pairs[i + 1] });
  }
  return out;
}

/** «В лесу родилась ёлочка» / «Зимой и летом стройная» */
function phraseA() {
  return notes(
    67, E, 72, E, 72, E, 72, E, 71, E, 69, E, 67, E, 65, E,
    72, E, 72, E, 72, E, 69, E, 69, E, 67, E, 65, E, 62, Q,
  );
}

/** «В лесу она росла» / «Мороз снежком укутывал» */
function phraseB() {
  return notes(
    67, E, 72, E, 72, E, 72, E, 71, E, 69, E, 67, E, 65, E,
    72, E, 72, E, 72, E, 69, E, 69, E, 67, E, 65, E, 64, Q,
  );
}

/** «Зелёная была» / «Смотри, не замерзай!» */
function phraseClose() {
  return notes(
    67, E, 67, E, 65, E, 65, E, 64, E, 62, E, 60, E, 69, E,
    65, E, 67, E, 65, E, 64, E, 62, E, 60, H,
  );
}

function fullMelody() {
  const all = [];
  for (let verse = 0; verse < 7; verse++) {
    all.push(...phraseA(), ...phraseB(), ...phraseA(), ...phraseClose());
  }
  return all;
}

const payload = {
  id: 'v-lesu-elochka',
  title: 'В лесу родилась ёлочка',
  composer: 'Л. Бекман',
  difficulty: 'intermediate',
  category: 'popular',
  tempo: 100,
  notes: fullMelody(),
};

writeFileSync(
  join(outDir, 'v-lesu-elochka.json'),
  `${JSON.stringify(payload, null, 4)}\n`,
  'utf8',
);

console.log(`Written v-lesu-elochka.json with ${payload.notes.length} notes`);
