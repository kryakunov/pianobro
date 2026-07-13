import { PIANO_END, PIANO_START } from './notes.js';

const MAX_EVENTS = 400;
const GROUP_WINDOW_SEC = 0.03;
const MIN_DURATION_MS = 180;
const MAX_DURATION_MS = 1200;

let midiLib = null;

async function loadMidiLib() {
  if (!midiLib) {
    midiLib = await import('https://esm.sh/@tonejs/midi@2.0.28');
  }
  return midiLib.Midi;
}

/**
 * @param {ArrayBuffer} arrayBuffer
 * @param {{ title?: string, composer?: string, id?: string }} meta
 */
export async function midiToLesson(arrayBuffer, meta = {}) {
  const Midi = await loadMidiLib();
  const midi = new Midi(arrayBuffer);
  const tempo = Math.round(midi.header.tempos[0]?.bpm ?? 120);

  const allNotes = [];
  for (const track of midi.tracks) {
    if (track.percussion) continue;

    for (const note of track.notes) {
      const midiNum = Math.round(note.midi);
      if (midiNum < PIANO_START || midiNum > PIANO_END) continue;

      allNotes.push({
        midi: midiNum,
        time: note.time,
        duration: note.duration,
      });
    }
  }

  if (allNotes.length === 0) {
    throw new Error('В MIDI нет нот для пианино');
  }

  allNotes.sort((a, b) => a.time - b.time || a.midi - b.midi);

  const events = [];
  let index = 0;
  while (index < allNotes.length && events.length < MAX_EVENTS) {
    const time = allNotes[index].time;
    const group = [];

    while (
      index < allNotes.length
      && allNotes[index].time - time <= GROUP_WINDOW_SEC
    ) {
      group.push(allNotes[index]);
      index += 1;
    }

    const unique = new Map();
    for (const note of group) {
      unique.set(note.midi, note);
    }

    const notes = [...unique.values()].map((note) => ({
      midi: note.midi,
      hand: note.midi < 60 ? 'left' : 'right',
    }));

    const durationMs = Math.round(
      Math.max(MIN_DURATION_MS, Math.min(MAX_DURATION_MS, group[0].duration * 1000)),
    );

    events.push({ duration: durationMs, notes });
  }

  const twoHands = events.some((event) => event.notes.some((note) => note.hand === 'left'));

  return {
    id: meta.id ?? `imported-${Date.now()}`,
    title: meta.title ?? 'MIDI мелодия',
    composer: meta.composer ?? '',
    difficulty: events.length > 200 ? 'advanced' : events.length > 80 ? 'intermediate' : 'beginner',
    tempo,
    twoHands,
    events,
    noteCount: events.reduce((sum, event) => sum + event.notes.length, 0),
    eventCount: events.length,
    source: 'imported',
  };
}
