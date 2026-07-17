import { buildPoolFromSettings } from './note-trainer.js';
import {
  REQUIRED_STREAK,
  enrichNoteFromHistory,
  masteryLevel,
  noteProgressPercent,
  normalizeHistory,
} from './note-mastery.js';

const GUEST_STATS_KEY = 'piano-roadmap-guest-stats';

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

function buildStageProgress(stage, noteMap) {
  const pool = buildPoolForStage(stage);
  const poolSize = pool.length;

  if (!poolSize) {
    return {
      progress: 0,
      completed: false,
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
    const progress = noteProgressPercent(history);
    sum += progress;
    if (progress >= 100) masteredNotes += 1;
    else if (progress > 0) inProgressNotes += 1;
  }

  const progress = Math.round(sum / poolSize);

  return {
    progress,
    completed: poolSize > 0 && masteredNotes === poolSize,
    masteredNotes,
    poolSize,
    inProgressNotes,
  };
}

function resolveRank(ranks, totalXp) {
  let current = ranks[0] ?? { minXp: 0, title: 'Новичок', emoji: '🌱' };
  for (const rank of ranks) {
    if (totalXp >= (rank.minXp ?? 0)) current = rank;
  }
  return current;
}

function buildProgressFromNoteMap(stages, ranks, noteMap) {
  const stageProgress = [];
  let totalXp = 0;
  let previousCompleted = true;

  for (const stage of stages) {
    const item = buildStageProgress(stage, noteMap);
    const unlocked = previousCompleted;
    previousCompleted = item.completed;
    if (item.completed) totalXp += stage.xp ?? 0;

    stageProgress.push({
      id: stage.id,
      progress: item.progress,
      completed: item.completed,
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

export function buildRoadmapProgressFromStats(roadmapData, noteStats) {
  const noteMap = {};
  for (const note of noteStats?.notes ?? []) {
    noteMap[note.midi] = {
      history: normalizeHistory(note.history ?? []),
    };
  }
  return buildProgressFromNoteMap(roadmapData.stages, roadmapData.ranks, noteMap);
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

export async function loadRoadmap() {
  const res = await fetch('/api/roadmap', { credentials: 'same-origin' });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
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
