import {
  DEFAULT_NOTE_SETTINGS,
  buildPoolFromSettings,
} from './note-trainer.js';
import {
  DEFAULT_RHYTHM_DURATIONS,
  DEFAULT_RHYTHM_LIVES,
  DEFAULT_RHYTHM_SPEED,
  RHYTHM_DURATION_OPTIONS,
  RHYTHM_LIVES_OPTIONS,
  RHYTHM_SPEED_OPTIONS,
  normalizeRhythmLives,
  normalizeRhythmSpeed,
  rhythmSpeedLabel,
} from './rhythm-trainer.js';

export {
  DEFAULT_RHYTHM_DURATIONS,
  DEFAULT_RHYTHM_LIVES,
  DEFAULT_RHYTHM_SPEED,
  RHYTHM_DURATION_OPTIONS,
  RHYTHM_LIVES_OPTIONS,
  RHYTHM_SPEED_OPTIONS,
};

export function readRhythmSettingsFromForm(form) {
  if (!form) {
    return {
      noteSettings: structuredClone(DEFAULT_NOTE_SETTINGS),
      durations: { ...DEFAULT_RHYTHM_DURATIONS },
      speed: DEFAULT_RHYTHM_SPEED,
      lives: DEFAULT_RHYTHM_LIVES,
    };
  }

  const checked = (name) => form.querySelector(`[name="${name}"]`)?.checked ?? false;

  const noteSettings = {
    treble: {
      enabled: checked('treble-first') || checked('treble-second'),
      first: checked('treble-first'),
      second: checked('treble-second'),
    },
    bass: {
      enabled: checked('bass-small') || checked('bass-great'),
      small: checked('bass-small'),
      great: checked('bass-great'),
    },
    alteration: {
      sharp: checked('alt-sharp'),
      flat: checked('alt-flat'),
    },
  };

  const durations = {};
  for (const option of RHYTHM_DURATION_OPTIONS) {
    durations[option.key] = checked(`dur-${option.key}`);
  }

  const speed = readRhythmOption(form, 'rhythm-speed', normalizeRhythmSpeed, DEFAULT_RHYTHM_SPEED);
  const lives = readRhythmOption(form, 'rhythm-lives', normalizeRhythmLives, DEFAULT_RHYTHM_LIVES);

  return { noteSettings, durations, speed, lives };
}

function readRhythmOption(form, name, normalize, fallback) {
  if (typeof FormData !== 'undefined'
    && typeof HTMLFormElement !== 'undefined'
    && form instanceof HTMLFormElement) {
    const value = new FormData(form).get(name);
    if (value != null && value !== '') {
      return normalize(value);
    }
  }

  const select = form.querySelector(`select[name="${name}"]`);
  if (select) {
    return normalize(select.value);
  }

  const radio = form.querySelector(`[name="${name}"]:checked`);
  if (radio) {
    return normalize(radio.value);
  }

  return fallback;
}

export function applyRhythmSettingsToForm(form, { noteSettings, durations, speed, lives } = {}) {
  if (!form) return;

  const settings = noteSettings ?? DEFAULT_NOTE_SETTINGS;
  const dur = durations ?? DEFAULT_RHYTHM_DURATIONS;
  const speedKey = speed ?? DEFAULT_RHYTHM_SPEED;
  const livesCount = normalizeRhythmLives(lives ?? DEFAULT_RHYTHM_LIVES);

  const set = (name, value) => {
    const input = form.querySelector(`[name="${name}"]`);
    if (input) input.checked = value;
  };

  set('treble-first', settings.treble.first);
  set('treble-second', settings.treble.second);
  set('bass-small', settings.bass.small);
  set('bass-great', settings.bass.great);
  set('alt-sharp', settings.alteration.sharp);
  set('alt-flat', settings.alteration.flat);

  for (const option of RHYTHM_DURATION_OPTIONS) {
    set(`dur-${option.key}`, dur[option.key] ?? false);
  }

  const speedSelect = form.querySelector('[name="rhythm-speed"]');
  if (speedSelect) speedSelect.value = normalizeRhythmSpeed(speedKey);

  const livesSelect = form.querySelector('[name="rhythm-lives"]');
  if (livesSelect) livesSelect.value = String(livesCount);
}

function hasSelectedOctaves(settings) {
  return settings.treble.first || settings.treble.second
    || settings.bass.small || settings.bass.great;
}

export function selectedDurationValues(durations) {
  return RHYTHM_DURATION_OPTIONS
    .filter((option) => durations[option.key])
    .map((option) => option.ms);
}

export function validateRhythmSettings(noteSettings, durations) {
  if (!hasSelectedOctaves(noteSettings)) {
    return 'Выберите хотя бы одну октаву';
  }
  if (!buildPoolFromSettings(noteSettings).length) {
    return 'Нет нот для выбранных настроек — включите диезы/бемоли или измените октавы';
  }
  if (!selectedDurationValues(durations).length) {
    return 'Выберите хотя бы одну длительность нот';
  }
  return null;
}

function rhythmLivesLabel(lives) {
  const n = normalizeRhythmLives(lives);
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod100 >= 11 && mod100 <= 14) return `${n} жизней`;
  if (mod10 === 1) return `${n} жизнь`;
  if (mod10 >= 2 && mod10 <= 4) return `${n} жизни`;
  return `${n} жизней`;
}

export function describeRhythmSettings(
  noteSettings,
  durations,
  speed = DEFAULT_RHYTHM_SPEED,
  lives = DEFAULT_RHYTHM_LIVES,
) {
  const parts = [];

  if (noteSettings.treble.enabled && noteSettings.bass.enabled) parts.push('Оба ключа');
  else if (noteSettings.treble.enabled) parts.push('Скрипичный ключ');
  else if (noteSettings.bass.enabled) parts.push('Басовый ключ');

  const durLabels = RHYTHM_DURATION_OPTIONS
    .filter((option) => durations[option.key])
    .map((option) => option.label.toLowerCase());
  if (durLabels.length) parts.push(durLabels.join(', '));

  parts.push(rhythmSpeedLabel(speed).toLowerCase());
  parts.push(rhythmLivesLabel(lives));

  return parts.join(' · ') || 'Ритм-игра';
}
