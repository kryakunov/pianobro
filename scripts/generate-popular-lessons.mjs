import { writeFileSync, unlinkSync, readdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const outDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'data', 'lessons');

const RUSSIAN_IDS = [
  'katyusha', 'podmoskovnye-vechera', 'kalinka', 'oi-moroz-moroz', 'bereza', 'murka',
  'temnaya-noch', 'siniy-platochik', 'smuglyanka', 'zhuravli', 'v-lesu-prifrontovom',
  'tri-belyh-konya', 'moskovskie-okna', 'gorod-nad-nevoy', 'odinokaya-garmon', 'landyshi',
  'staryy-klyon', 'chernoglazaya', 'sviril', 'pesnya-o-druge', 'shiroka-strana',
  'vyidu-na-ulitsu', 'kogda-my-byli-na-voyne', 'marsh-entuziastov', 'pesnya-o-vstrechnom',
  'proschay-lyubimyy-gorod', 'valenki', 'kacheli', 'uchitelnitsa-pervaya-moya', 'plavno-sneg',
];

for (const id of RUSSIAN_IDS) {
  try {
    unlinkSync(join(outDir, `${id}.json`));
  } catch {
    // already removed
  }
}

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
const E = 200;

const melodies = [
  {
    id: 'star-wars',
    title: 'Звёздные войны',
    composer: 'J. Williams',
    tempo: 104,
    notes: notes(60, Q, 67, Q, 65, Q, 64, Q, 62, Q, 60, Q, 67, Q, 65, Q, 64, Q, 62, H),
  },
  {
    id: 'harry-potter',
    title: 'Гарри Поттер',
    composer: 'J. Williams',
    tempo: 88,
    notes: notes(62, H, 60, Q, 67, Q, 64, H, 62, Q, 60, Q, 59, H, 60, Q, 62, Q, 64, H),
  },
  {
    id: 'pirates',
    title: 'Пираты Карибского моря',
    composer: 'K. Badelt',
    tempo: 110,
    notes: notes(60, E, 62, E, 64, Q, 65, Q, 67, E, 65, E, 64, Q, 62, Q, 60, H),
  },
  {
    id: 'mario',
    title: 'Super Mario',
    composer: 'K. Kondo',
    tempo: 120,
    notes: notes(76, E, 76, E, 76, E, 72, E, 76, E, 79, E, 72, E, 76, H),
  },
  {
    id: 'tetris',
    title: 'Тетрис',
    composer: 'Traditional',
    tempo: 112,
    notes: notes(64, Q, 71, Q, 72, Q, 74, Q, 72, E, 71, E, 69, Q, 69, Q, 72, Q, 76, Q, 74, Q, 72, E, 71, E, 69, H),
  },
  {
    id: 'pink-panther',
    title: 'Розовая пантера',
    composer: 'H. Mancini',
    tempo: 108,
    notes: notes(60, E, 61, E, 63, E, 64, E, 63, E, 61, E, 60, Q, 58, Q, 60, H),
  },
  {
    id: 'james-bond',
    title: 'Джеймс Бонд',
    composer: 'M. Arnold',
    tempo: 100,
    notes: notes(62, E, 65, E, 69, Q, 72, Q, 71, E, 69, E, 67, Q, 65, Q, 64, H),
  },
  {
    id: 'amazing-grace',
    title: 'Amazing Grace',
    composer: 'Traditional',
    tempo: 90,
    notes: notes(60, H, 62, Q, 64, Q, 65, H, 64, Q, 62, Q, 60, H, 59, Q, 60, Q, 62, H),
  },
  {
    id: 'greensleeves',
    title: 'Greensleeves',
    composer: 'Traditional',
    tempo: 86,
    notes: notes(67, Q, 65, Q, 64, H, 62, Q, 60, Q, 59, H, 60, Q, 62, Q, 64, H),
  },
  {
    id: 'when-saints',
    title: 'When the Saints',
    composer: 'Traditional',
    tempo: 105,
    notes: notes(60, Q, 62, Q, 64, Q, 65, H, 64, Q, 62, Q, 60, H, 62, Q, 64, Q, 65, H),
  },
  {
    id: 'chopsticks',
    title: 'Чопстикс',
    composer: 'A. Allen',
    tempo: 116,
    notes: notes(76, E, 77, E, 76, E, 77, E, 76, E, 72, E, 74, E, 76, E, 74, E, 72, E, 69, H),
  },
  {
    id: 'frere-jacques',
    title: 'Брат Яков',
    composer: 'Traditional',
    tempo: 100,
    notes: notes(60, Q, 62, Q, 64, Q, 60, Q, 60, Q, 62, Q, 64, Q, 60, Q, 64, Q, 65, Q, 67, H),
  },
  {
    id: 'row-your-boat',
    title: 'На лодочке',
    composer: 'Traditional',
    tempo: 98,
    notes: notes(60, Q, 60, Q, 60, Q, 62, Q, 64, H, 62, Q, 64, Q, 65, H),
  },
  {
    id: 'london-bridge',
    title: 'London Bridge',
    composer: 'Traditional',
    tempo: 102,
    notes: notes(60, Q, 62, Q, 64, Q, 65, Q, 64, Q, 62, Q, 60, H, 62, Q, 64, Q, 65, H),
  },
  {
    id: 'old-macdonald',
    title: 'Old MacDonald',
    composer: 'Traditional',
    tempo: 104,
    notes: notes(60, Q, 60, Q, 60, Q, 65, H, 64, Q, 62, Q, 60, H, 65, Q, 64, Q, 62, H),
  },
  {
    id: 'yankee-doodle',
    title: 'Yankee Doodle',
    composer: 'Traditional',
    tempo: 110,
    notes: notes(60, Q, 62, Q, 64, Q, 65, Q, 64, E, 62, E, 60, Q, 62, Q, 64, H),
  },
  {
    id: 'baby-shark',
    title: 'Baby Shark',
    composer: 'Pinkfong',
    tempo: 115,
    notes: notes(60, E, 62, E, 64, Q, 64, Q, 64, Q, 64, H, 62, E, 60, E, 57, H),
  },
  {
    id: 'we-will-rock-you',
    title: 'We Will Rock You',
    composer: 'Queen',
    tempo: 81,
    notes: notes(64, H, 62, H, 60, H, 64, H, 62, H, 60, H, 64, Q, 64, Q, 62, H),
  },
  {
    id: 'seven-nation-army',
    title: 'Seven Nation Army',
    composer: 'The White Stripes',
    tempo: 124,
    notes: notes(60, H, 60, Q, 63, Q, 60, Q, 58, Q, 55, H, 60, H),
  },
  {
    id: 'smoke-on-water',
    title: 'Smoke on the Water',
    composer: 'Deep Purple',
    tempo: 112,
    notes: notes(60, E, 63, E, 65, E, 60, E, 63, E, 66, E, 65, E, 60, E, 63, E, 65, E, 63, E, 60, H),
  },
  {
    id: 'mountain-king',
    title: 'В пещере горного короля',
    composer: 'E. Grieg',
    tempo: 120,
    notes: notes(62, E, 64, E, 65, E, 67, E, 69, E, 67, E, 65, E, 64, E, 62, E, 60, E, 62, H),
  },
  {
    id: 'sugar-plum',
    title: 'Фея Драже',
    composer: 'P. Tchaikovsky',
    tempo: 94,
    notes: notes(72, E, 74, E, 76, E, 77, E, 76, E, 74, E, 72, E, 71, E, 72, H),
  },
  {
    id: 'swan-lake',
    title: 'Лебединое озеро',
    composer: 'P. Tchaikovsky',
    tempo: 80,
    notes: notes(74, H, 72, Q, 71, Q, 69, H, 67, Q, 66, Q, 64, H),
  },
  {
    id: 'william-tell',
    title: 'Увертюра «Вильгельм Телль»',
    composer: 'G. Rossini',
    tempo: 118,
    notes: notes(67, E, 69, E, 71, E, 72, E, 71, E, 69, E, 67, E, 65, E, 64, E, 62, E, 60, H),
  },
  {
    id: 'over-rainbow',
    title: 'Over the Rainbow',
    composer: 'H. Arlen',
    tempo: 92,
    notes: notes(67, Q, 71, H, 72, Q, 71, Q, 69, Q, 67, H, 65, Q, 64, Q, 62, H),
  },
  {
    id: 'zelda',
    title: 'The Legend of Zelda',
    composer: 'K. Kondo',
    tempo: 100,
    notes: notes(64, Q, 67, Q, 71, Q, 76, H, 74, Q, 71, Q, 67, H),
  },
  {
    id: 'mission-impossible',
    title: 'Миссия невыполнима',
    composer: 'L. Schifrin',
    tempo: 118,
    notes: notes(63, E, 65, E, 66, E, 63, E, 65, E, 66, E, 63, E, 62, E, 60, H),
  },
  {
    id: 'happy-pharrell',
    title: 'Happy',
    composer: 'Pharrell Williams',
    tempo: 100,
    notes: notes(62, Q, 62, Q, 64, Q, 66, Q, 66, Q, 64, Q, 62, H, 59, Q, 62, H),
  },
  {
    id: 'let-it-go',
    title: 'Let It Go',
    composer: 'R. Lopez',
    tempo: 96,
    notes: notes(64, Q, 65, Q, 67, H, 69, Q, 67, Q, 65, H, 64, Q, 62, Q, 60, H),
  },
  {
    id: 'imperial-march',
    title: 'Имперский марш',
    composer: 'J. Williams',
    tempo: 104,
    notes: notes(60, Q, 60, Q, 60, Q, 63, E, 65, Q, 60, Q, 63, E, 65, Q, 60, H),
  },
];

for (const melody of melodies) {
  const payload = {
    id: melody.id,
    title: melody.title,
    composer: melody.composer,
    difficulty: 'beginner',
    category: 'popular',
    tempo: melody.tempo,
    notes: melody.notes,
  };

  writeFileSync(
    join(outDir, `${melody.id}.json`),
    `${JSON.stringify(payload, null, 4)}\n`,
    'utf8',
  );
}

console.log(`Replaced catalog with ${melodies.length} popular lessons`);
