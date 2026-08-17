import { buildPoolFromSettings } from './note-trainer.js';
import {
  REQUIRED_STREAK,
  enrichNoteFromHistory,
  masteryLevel,
  noteProgressPercent,
  normalizeHistory,
} from './note-mastery.js';
import { midiToName } from './notes.js';

const GUEST_STATS_KEY = 'piano-roadmap-guest-stats';
const CAPSTONE_KEY = 'piano-roadmap-capstones';

export { REQUIRED_STREAK as MASTERED_CORRECT_HITS, masteryLevel, noteProgressPercent };

export function buildPoolForStage(stage) {
  if (!stage?.settings) return [];
  return buildPoolFromSettings(stage.settings, { poolMode: stage.poolMode });
}

function loadGuestNoteMap() {
  try {
    const raw = localStorage.getItem(GUEST_STATS_KEY);
    if (!raw) return {};
    const data = JSON.parse(raw);
    return typeof data === 'object' && data ? data : {};
  } catch {
    return {};
  }
}

function saveGuestNoteMap(map) {
  localStorage.setItem(GUEST_STATS_KEY, JSON.stringify(map));
}

export function mergeGuestAttempts(attempts = []) {
  if (!attempts.length) return;

  const map = loadGuestNoteMap();
  for (const attempt of attempts) {
    const midi = String(attempt.expectedMidi);
    if (!Array.isArray(map[midi])) {
      map[midi] = normalizeHistory(map[midi]);
    }
    map[midi].push(Boolean(attempt.correct));
  }
  saveGuestNoteMap(map);
}

export function getGuestNoteEntries() {
  const map = loadGuestNoteMap();
  return Object.entries(map)
    .map(([midi, value]) => ({
      midi: Number(midi),
      history: normalizeHistory(value),
    }))
    .filter((entry) => entry.history.length > 0);
}

export function clearGuestNoteMap() {
  try {
    localStorage.removeItem(GUEST_STATS_KEY);
  } catch {
    // ignore
  }
}

function loadCapstoneMap() {
  try {
    const raw = localStorage.getItem(CAPSTONE_KEY);
    if (!raw) return {};
    const data = JSON.parse(raw);
    return typeof data === 'object' && data ? data : {};
  } catch {
    return {};
  }
}

function saveCapstoneMap(map) {
  localStorage.setItem(CAPSTONE_KEY, JSON.stringify(map));
}

export function isCapstoneComplete(stageId) {
  return Boolean(loadCapstoneMap()[stageId]);
}

export function markCapstoneComplete(stageId) {
  const map = loadCapstoneMap();
  map[stageId] = true;
  saveCapstoneMap(map);
  void persistCapstoneComplete(stageId);
}

async function persistCapstoneComplete(stageId) {
  try {
    await fetch('/api/roadmap/capstone', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ stageId }),
    });
  } catch {
    // ignore — локальный прогресс сохранён, синхронизация повторится при входе
  }
}

export async function syncLocalCapstonesToServer() {
  const map = loadCapstoneMap();
  const stageIds = Object.keys(map).filter((stageId) => map[stageId]);
  if (!stageIds.length) return;

  await Promise.all(stageIds.map((stageId) => persistCapstoneComplete(stageId)));
}

function capstoneMapFromServerProgress(serverProgress) {
  const map = {};
  for (const item of serverProgress?.stages ?? []) {
    if (item.capstoneComplete) {
      map[item.id] = true;
    }
  }
  return map;
}

function mergeCapstoneMaps(...maps) {
  const merged = {};
  for (const map of maps) {
    for (const [stageId, complete] of Object.entries(map ?? {})) {
      if (complete) merged[stageId] = true;
    }
  }
  return merged;
}

export function clearCapstoneProgress() {
  try {
    localStorage.removeItem(CAPSTONE_KEY);
  } catch {
    // ignore
  }
}

function buildStageProgress(stage, noteMap, capstoneMap) {
  const pool = buildPoolForStage(stage);
  const poolSize = pool.length;
  const hasCapstone = Boolean(stage.capstone?.lessonId);

  if (!poolSize) {
    return {
      progress: 0,
      completed: false,
      notesComplete: false,
      capstoneComplete: false,
      capstoneReady: false,
      hasCapstone,
      masteredNotes: 0,
      poolSize: 0,
      inProgressNotes: 0,
    };
  }

  let sum = 0;
  let masteredNotes = 0;
  let inProgressNotes = 0;

  for (const midi of pool) {
    const history = noteMap[midi]?.history ?? noteMap[String(midi)]?.history ?? [];
    const noteProgress = noteProgressPercent(history);
    sum += noteProgress;
    if (noteProgress >= 100) masteredNotes += 1;
    else if (noteProgress > 0) inProgressNotes += 1;
  }

  const notesProgress = Math.round(sum / poolSize);
  const notesComplete = masteredNotes === poolSize;
  const capstoneComplete = hasCapstone ? Boolean(capstoneMap[stage.id]) : true;
  const capstoneReady = notesComplete && hasCapstone && !capstoneComplete;

  let progress;
  if (completedStage(notesComplete, capstoneComplete, hasCapstone)) {
    progress = 100;
  } else if (notesComplete && hasCapstone) {
    progress = 90;
  } else if (hasCapstone) {
    progress = Math.round(notesProgress * 0.9);
  } else {
    progress = notesProgress;
  }

  return {
    progress,
    completed: notesComplete && capstoneComplete,
    notesComplete,
    capstoneComplete,
    capstoneReady,
    hasCapstone,
    masteredNotes,
    poolSize,
    inProgressNotes,
  };
}

function completedStage(notesComplete, capstoneComplete, hasCapstone) {
  return notesComplete && (!hasCapstone || capstoneComplete);
}

function resolveRank(ranks, totalXp) {
  let current = ranks[0] ?? { minXp: 0, title: 'Новичок', emoji: '🌱' };
  for (const rank of ranks) {
    if (totalXp >= (rank.minXp ?? 0)) current = rank;
  }
  return current;
}

function buildProgressFromNoteMap(stages, ranks, noteMap, capstoneMap = loadCapstoneMap()) {
  const stageProgress = [];
  let totalXp = 0;
  let previousCompleted = true;

  for (const stage of stages) {
    const item = buildStageProgress(stage, noteMap, capstoneMap);
    const unlocked = previousCompleted;
    previousCompleted = item.completed;
    if (item.completed) totalXp += stage.xp ?? 0;

    stageProgress.push({
      id: stage.id,
      progress: item.progress,
      completed: item.completed,
      notesComplete: item.notesComplete,
      capstoneComplete: item.capstoneComplete,
      capstoneReady: item.capstoneReady,
      hasCapstone: item.hasCapstone,
      unlocked,
      masteredNotes: item.masteredNotes,
      poolSize: item.poolSize,
      inProgressNotes: item.inProgressNotes,
    });
  }

  let currentStageId = null;
  for (const item of stageProgress) {
    if (item.unlocked && !item.completed) {
      currentStageId = item.id;
      break;
    }
  }

  return {
    totalXp,
    rank: resolveRank(ranks, totalXp),
    stages: stageProgress,
    currentStageId,
    completedCount: stageProgress.filter((s) => s.completed).length,
    totalStages: stageProgress.length,
  };
}

function noteMapFromHistories(entries) {
  const noteMap = {};
  for (const [midi, value] of Object.entries(entries)) {
    noteMap[Number(midi)] = { history: normalizeHistory(value) };
  }
  return noteMap;
}

export function buildGuestRoadmapProgress(roadmapData) {
  return buildProgressFromNoteMap(
    roadmapData.stages,
    roadmapData.ranks,
    noteMapFromHistories(loadGuestNoteMap()),
  );
}

export function buildRoadmapProgressFromStats(roadmapData, noteStats, serverProgress = null) {
  const noteMap = {};
  for (const note of noteStats?.notes ?? []) {
    noteMap[note.midi] = {
      history: normalizeHistory(note.history ?? []),
    };
  }
  const capstoneMap = mergeCapstoneMaps(
    capstoneMapFromServerProgress(serverProgress),
    loadCapstoneMap(),
  );
  return buildProgressFromNoteMap(roadmapData.stages, roadmapData.ranks, noteMap, capstoneMap);
}

export function projectNoteStatsFromAttempts(noteStats, attempts = []) {
  const byMidi = new Map();
  for (const note of noteStats?.notes ?? []) {
    byMidi.set(note.midi, {
      ...note,
      history: normalizeHistory(note.history ?? []),
    });
  }
  for (const attempt of attempts) {
    const midi = attempt.expectedMidi;
    const row = byMidi.get(midi) ?? { midi, name: '', history: [] };
    row.history = [...row.history, Boolean(attempt.correct)];
    byMidi.set(midi, row);
  }
  return {
    ...(noteStats ?? {}),
    notes: [...byMidi.values()].map((row) => enrichNoteFromHistory(row)),
  };
}

export function mergeCapstoneIntoProgress(roadmapData, progress) {
  const capstoneMap = loadCapstoneMap();
  const stageById = new Map((roadmapData.stages ?? []).map((stage) => [stage.id, stage]));
  let totalXp = 0;
  let previousCompleted = true;
  const stages = (progress?.stages ?? []).map((item) => {
    const stage = stageById.get(item.id);
    const hasCapstone = Boolean(stage?.capstone?.lessonId);
    const notesComplete = Boolean(item.notesComplete ?? (item.masteredNotes === item.poolSize && item.poolSize > 0));
    const capstoneComplete = hasCapstone ? Boolean(capstoneMap[item.id]) : true;
    const completed = notesComplete && capstoneComplete;
    const unlocked = previousCompleted;
    previousCompleted = completed;
    if (completed) totalXp += stage?.xp ?? 0;

    let stageProgress = item.progress ?? 0;
    if (completed) stageProgress = 100;
    else if (notesComplete && hasCapstone) stageProgress = 90;
    else if (hasCapstone && notesComplete === false) {
      stageProgress = Math.round((item.progress ?? 0) * 0.9);
    }

    return {
      ...item,
      progress: stageProgress,
      completed,
      notesComplete,
      capstoneComplete,
      capstoneReady: notesComplete && hasCapstone && !capstoneComplete,
      hasCapstone,
      unlocked,
    };
  });

  let currentStageId = null;
  for (const item of stages) {
    if (item.unlocked && !item.completed) {
      currentStageId = item.id;
      break;
    }
  }

  return {
    ...progress,
    totalXp,
    rank: resolveRank(roadmapData.ranks ?? [], totalXp),
    stages,
    currentStageId,
    completedCount: stages.filter((s) => s.completed).length,
    totalStages: stages.length,
  };
}

export async function loadRoadmap() {
  const res = await fetch('/api/roadmap', { credentials: 'same-origin' });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

export function getStageIncompleteNotes(stage, noteStats) {
  const pool = buildPoolForStage(stage);
  const byMidi = new Map((noteStats?.notes ?? []).map((note) => [note.midi, note]));
  const incomplete = [];

  for (const midi of pool) {
    const note = byMidi.get(midi);
    const history = normalizeHistory(note?.history ?? []);
    if (noteProgressPercent(history) >= 100) continue;

    incomplete.push(
      note
        ? { ...note, history, level: masteryLevel(history) }
        : {
            midi,
            name: midiToName(midi),
            level: 'not_started',
            history: [],
            attempts: 0,
            accuracy: 0,
          },
    );
  }

  return incomplete;
}

export function getCurrentStageIncompleteNotes(roadmapData, noteStats) {
  const stageId = roadmapData?.progress?.currentStageId;
  if (!stageId) return [];
  const stage = findStage(roadmapData, stageId);
  if (!stage) return [];
  return getStageIncompleteNotes(stage, noteStats);
}

export function getAllIncompleteNotes(roadmapData, noteStats) {
  const stages = roadmapData?.stages ?? [];
  if (!stages.length) return [];

  const progressById = new Map(
    (roadmapData.progress?.stages ?? []).map((item) => [item.id, item]),
  );

  const byMidi = new Map();
  let previousCompleted = true;

  for (const stage of stages) {
    const progress = progressById.get(stage.id);
    const unlocked = progress?.unlocked ?? previousCompleted;
    previousCompleted = progress?.completed ?? false;

    if (!unlocked) continue;

    for (const note of getStageIncompleteNotes(stage, noteStats)) {
      byMidi.set(note.midi, note);
    }
  }

  return [...byMidi.values()].sort((a, b) => a.midi - b.midi);
}

export function enrichNotesForRoadmapDisplay(notes, roadmapData) {
  if (!roadmapData?.progress?.currentStageId) return notes;

  const stage = findStage(roadmapData, roadmapData.progress.currentStageId);
  if (!stage) return notes;

  const byMidi = new Map(notes.map((note) => [note.midi, note]));
  const pool = buildPoolForStage(stage);
  const merged = [...notes];

  for (const midi of pool) {
    if (byMidi.has(midi)) continue;
    merged.push({
      midi,
      name: midiToName(midi),
      level: 'not_started',
      history: [],
      attempts: 0,
      accuracy: 0,
    });
  }

  merged.sort((a, b) => a.midi - b.midi);
  return merged;
}

export function findStage(roadmapData, stageId) {
  return roadmapData?.stages?.find((stage) => stage.id === stageId) ?? null;
}

export function findStageProgress(roadmapData, stageId) {
  return roadmapData?.progress?.stages?.find((stage) => stage.id === stageId) ?? null;
}

export function getNextStage(roadmapData, stageId) {
  const stages = roadmapData?.stages ?? [];
  const index = stages.findIndex((stage) => stage.id === stageId);
  if (index < 0 || index >= stages.length - 1) return null;
  return stages[index + 1];
}

export function getCapstoneLabel(stage) {
  const title = stage?.capstone?.title?.trim();
  return title || 'Мелодия';
}

export function meetsCapstoneAccuracy(stage, accuracy) {
  const min = stage?.capstone?.minAccuracy ?? 75;
  return accuracy >= min;
}
