import { normalizeLesson } from './lesson-utils.js';
import { playTrainerNote, stopTrainerSound, warmupTrainerSound } from './trainer-sounds.js';

/** @type {{ id: string|null, timeouts: number[], onStop: (() => void)|null } | null} */
let activePreview = null;

export function isMelodyPreviewPlaying() {
  return activePreview !== null;
}

export function getMelodyPreviewId() {
  return activePreview?.id ?? null;
}

export function stopMelodyPreview() {
  if (!activePreview) return;

  const preview = activePreview;
  activePreview = null;

  for (const id of preview.timeouts) clearTimeout(id);
  stopTrainerSound();
  preview.onStop?.();
}

/**
 * @param {object} lesson
 * @param {{
 *   id?: string|null,
 *   onEvent?: (state: object) => void,
 *   onComplete?: () => void,
 *   onStop?: () => void,
 * }} [options]
 */
export async function playMelodyPreview(lesson, { id = null, onEvent = null, onComplete = null, onStop = null } = {}) {
  stopMelodyPreview();

  const normalized = normalizeLesson(lesson);
  const events = normalized.events ?? [];
  if (!events.length) return false;

  await warmupTrainerSound();

  const timeouts = [];
  activePreview = { id, timeouts, onStop: onStop ?? null };

  let offsetMs = 0;

  for (let index = 0; index < events.length; index++) {
    const event = events[index];
    const durationMs = event.duration ?? 400;
    const waitMs = offsetMs;
    const noteSec = Math.max(0.12, Math.min(durationMs / 1000 * 0.92, (durationMs - 30) / 1000));

    timeouts.push(window.setTimeout(() => {
      if (!activePreview || activePreview.id !== id) return;

      onEvent?.({
        index,
        total: events.length,
        event,
        events,
        preview: true,
        running: true,
        paused: false,
        twoHands: normalized.twoHands ?? false,
      });

      for (const note of event.notes) {
        void playTrainerNote(note.midi, noteSec);
      }
    }, waitMs));

    offsetMs += durationMs;
  }

  timeouts.push(window.setTimeout(() => {
    if (!activePreview || activePreview.id !== id) return;
    activePreview = null;
    onComplete?.();
  }, offsetMs + 60));

  return true;
}
