import { midiToName } from './notes.js';

/** @typedef {{ midi: number, hand?: string, duration?: number, name?: string }} LessonNote */
/** @typedef {{ duration: number, notes: LessonNote[] }} LessonEvent */

export function normalizeLesson(lesson) {
  if (!lesson) return lesson;

  if (lesson.events?.length) {
    const events = lesson.events.map((ev) => ({
      duration: ev.duration ?? 400,
      notes: ev.notes.map((n) => ({
        midi: n.midi,
        hand: n.hand ?? 'right',
        name: n.name ?? midiToName(n.midi),
      })),
    }));
    return {
      ...lesson,
      events,
      twoHands: lesson.twoHands ?? events.some((e) => e.notes.some((n) => n.hand === 'left')),
      noteCount: lesson.noteCount ?? countNotes(events),
    };
  }

  const events = (lesson.notes ?? []).map((n) => ({
    duration: n.duration ?? 400,
    notes: [{
      midi: n.midi,
      hand: 'right',
      name: n.name ?? midiToName(n.midi),
    }],
  }));

  return {
    ...lesson,
    events,
    twoHands: false,
    noteCount: events.length,
  };
}

export function countNotes(events) {
  return events.reduce((sum, ev) => sum + ev.notes.length, 0);
}

export function eventMidis(event) {
  return event.notes.map((n) => n.midi);
}

export function eventLabel(event) {
  return event.notes.map((n) => n.name ?? midiToName(n.midi)).join(' + ');
}

export function flatDisplayNotes(lesson) {
  const events = lesson.events ?? [];
  const rows = [];
  events.forEach((ev, eventIndex) => {
    ev.notes.forEach((note, noteIndex) => {
      rows.push({
        ...note,
        eventIndex,
        noteIndex,
        duration: ev.duration,
        name: note.name ?? midiToName(note.midi),
      });
    });
  });
  return rows;
}
