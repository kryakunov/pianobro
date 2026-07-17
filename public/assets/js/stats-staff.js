import { clefWidth, renderClefSymbol } from './clef-glyphs.js';
import { isBlackKey, midiToName } from './notes.js';
import { renderLedgerLines, resolveGrandStaffNote } from './staff-positions.js';

const VIEW_HEIGHT = 340;
const DEFAULT_VIEW_WIDTH = 800;
const MAX_NOTE_STEP = 22;
const STAFF_LINE_GAP = 17;

export const STATS_LEVEL_META = {
  mastered: {
    color: '#3dd68c',
    label: 'Выучено хорошо',
    hint: '2 верных попадания подряд без ошибки',
    className: 'mastered',
  },
  learning: {
    color: '#6c8cff',
    label: 'В процессе изучения',
    hint: 'Ещё нет 2 верных подряд — продолжайте тренировку',
    className: 'learning',
  },
};

export const DEFAULT_STATS_STAFF_VISIBILITY = {
  mastered: true,
  learning: true,
};

export const STATS_STAFF_MODES = {
  natural: {
    id: 'natural',
    label: 'Ноты',
    hint: 'Белые клавиши — до, ре, ми, фа, соль, ля, си.',
    empty: 'Нет натуральных нот в выбранных категориях.',
  },
  chromatic: {
    id: 'chromatic',
    label: 'Диезы и бемоли',
    hint: 'Чёрные клавиши — до-диез, ре-диез, фа-диез и другие.',
    empty: 'Нет диезов и бемолей в выбранных категориях.',
  },
};

function filterMidisByMode(statsByMidi, mode) {
  return [...statsByMidi.keys()]
    .filter((midi) => (mode === 'chromatic' ? isBlackKey(midi) : !isBlackKey(midi)))
    .sort((a, b) => a - b);
}

function hasChromatics(statsByMidi) {
  return [...statsByMidi.keys()].some((midi) => isBlackKey(midi));
}

function escapeXml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function buildMetrics(viewWidth, noteCount) {
  const lineGap = STAFF_LINE_GAP;
  const staffSpan = 4 * lineGap;
  const gapBetween = lineGap * 2;
  const clefRoom = 22;
  const topPad = 18;
  const block = staffSpan * 2 + gapBetween + clefRoom;
  const startTop = Math.max(topPad, (VIEW_HEIGHT - block) / 2);
  const trebleBottom = startTop + staffSpan;
  const bassBottom = trebleBottom + gapBetween + staffSpan;
  const lineStart = 36;
  const braceX = 4;
  const clefX = lineStart;
  const clefWidthPx = Math.max(clefWidth('treble', lineGap), clefWidth('bass', lineGap));
  const noteStartX = clefX + clefWidthPx + 14;
  const noteEndX = viewWidth - 14;
  const noteAreaWidth = Math.max(0, noteEndX - noteStartX);
  const rawStep = noteCount > 1 ? noteAreaWidth / (noteCount - 1) : 0;
  const noteStep = noteCount > 1 ? Math.min(rawStep, MAX_NOTE_STEP) : 0;
  const clusterWidth = noteCount > 1 ? noteStep * (noteCount - 1) : 0;
  const clusterStartX = noteStartX + (noteAreaWidth - clusterWidth) / 2;
  const noteR = 6.2;

  return {
    viewWidth,
    viewHeight: VIEW_HEIGHT,
    lineGap,
    trebleBottom,
    bassBottom,
    lineStart,
    braceX,
    clefX,
    noteStartX,
    noteEndX,
    noteStep,
    clusterStartX,
    noteR,
    braceSize: 68,
  };
}

function buildNoteXMap(trainedMidis, metrics) {
  const xByMidi = new Map();
  if (!trainedMidis.length) return xByMidi;

  if (trainedMidis.length === 1) {
    xByMidi.set(trainedMidis[0], (metrics.noteStartX + metrics.noteEndX) / 2);
    return xByMidi;
  }

  trainedMidis.forEach((midi, index) => {
    xByMidi.set(midi, metrics.clusterStartX + index * metrics.noteStep);
  });
  return xByMidi;
}

function renderNoteHead(midi, x, metrics, statsByMidi) {
  const stat = statsByMidi.get(midi);
  if (!stat) return '';

  const level = stat.level === 'needs_practice' ? 'learning' : stat.level;
  const meta = STATS_LEVEL_META[level];
  if (!meta) return '';

  const { y, clef } = resolveGrandStaffNote(midi, metrics);
  const tooltip = escapeXml(noteTooltip(midi, statsByMidi));
  const ledgers = renderLedgerLines(x, y, clef, metrics);

  return `
    <g class="stats-staff__note stats-staff__note--${meta.className}" data-level="${level}" data-midi="${midi}">
      ${ledgers}
      <title>${tooltip}</title>
      <circle cx="${x}" cy="${y}" r="${metrics.noteR}" class="stats-staff__dot" style="--note-color:${meta.color}"/>
    </g>
  `;
}

function staffLines(lineStart, lineEnd, bottomY, metrics) {
  const lines = [];
  for (let i = 0; i < 5; i++) {
    const y = bottomY - i * metrics.lineGap;
    lines.push(`<line x1="${lineStart}" y1="${y}" x2="${lineEnd}" y2="${y}" class="stats-staff__line"/>`);
  }
  return lines.join('');
}

function noteTooltip(midi, statsByMidi) {
  const name = midiToName(midi);
  const stat = statsByMidi.get(midi);
  if (!stat) return name;
  return `${name} — ${stat.accuracy}% · ${stat.attempts} ${pluralAttempts(stat.attempts)}`;
}

function pluralAttempts(n) {
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return 'попытка';
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return 'попытки';
  return 'попыток';
}

function buildStaffSvg(statsByMidi, viewWidth = DEFAULT_VIEW_WIDTH, mode = 'natural') {
  const trainedMidis = filterMidisByMode(statsByMidi, mode);
  const metrics = buildMetrics(viewWidth, trainedMidis.length);
  const xByMidi = buildNoteXMap(trainedMidis, metrics);
  const lineEnd = metrics.viewWidth - 6;
  const trebleCenter = metrics.trebleBottom - 2 * metrics.lineGap;
  const bassCenter = metrics.bassBottom - 2 * metrics.lineGap;
  const braceMid = (trebleCenter + bassCenter) / 2;

  const noteParts = trainedMidis
    .map((midi) => renderNoteHead(midi, xByMidi.get(midi), metrics, statsByMidi))
    .filter(Boolean);

  return {
    viewWidth: metrics.viewWidth,
    viewHeight: metrics.viewHeight,
    innerHtml: `
      <g class="stats-staff__frame" aria-hidden="true">
        <text x="${metrics.braceX}" y="${braceMid}" class="stats-staff__brace" font-size="${metrics.braceSize}" text-anchor="end" dominant-baseline="middle">{</text>
        ${renderClefSymbol('treble', metrics.clefX, metrics.trebleBottom, metrics.lineGap)}
        ${renderClefSymbol('bass', metrics.clefX, metrics.bassBottom, metrics.lineGap)}
        ${staffLines(metrics.lineStart, lineEnd, metrics.trebleBottom, metrics)}
        ${staffLines(metrics.lineStart, lineEnd, metrics.bassBottom, metrics)}
      </g>
      <g class="stats-staff__notes">
        ${noteParts.join('')}
      </g>
    `,
  };
}

function readVisibility(section) {
  const visibility = { ...DEFAULT_STATS_STAFF_VISIBILITY };
  section.querySelectorAll('.stats-staff__filter').forEach((input) => {
    const level = input.getAttribute('data-level');
    if (level && level in visibility) {
      visibility[level] = input.checked;
    }
  });
  return visibility;
}

function applyNoteVisibility(section) {
  const visibility = readVisibility(section);
  const mode = section.dataset.staffMode ?? 'natural';
  let visibleCount = 0;

  section.querySelectorAll('.stats-staff__note').forEach((note) => {
    const level = note.getAttribute('data-level');
    const show = Boolean(level && visibility[level]);
    note.classList.toggle('stats-staff__note--hidden', !show);
    if (show) visibleCount += 1;
  });

  const emptyEl = section.querySelector('.stats-staff__empty-visible');
  if (emptyEl) {
    const emptyText = STATS_STAFF_MODES[mode]?.empty ?? 'Нет нот в выбранных категориях.';
    emptyEl.textContent = visibleCount > 0 ? '' : emptyText;
    emptyEl.hidden = visibleCount > 0;
  }
}

function setStaffMode(section, mode) {
  section.dataset.staffMode = mode;
  section.querySelectorAll('[data-staff-mode]').forEach((tab) => {
    const active = tab.getAttribute('data-staff-mode') === mode;
    tab.classList.toggle('stats-staff__tab--active', active);
    tab.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  const hintEl = section.querySelector('.stats-staff__mode-hint');
  if (hintEl) {
    hintEl.textContent = STATS_STAFF_MODES[mode]?.hint ?? '';
  }
}

function drawStaff(section, statsByMidi, viewWidth, mode = 'natural') {
  const svg = section.querySelector('.stats-staff__svg');
  if (!svg) return;

  const staff = buildStaffSvg(statsByMidi, viewWidth, mode);
  svg.setAttribute('viewBox', `0 0 ${staff.viewWidth} ${staff.viewHeight}`);
  svg.innerHTML = staff.innerHtml;
  applyNoteVisibility(section);
}

/**
 * @param {Array<{midi:number, level?:string, accuracy?:number, attempts?:number}>} notes
 */
export function renderStatsStaffInfographic(notes = []) {
  const statsByMidi = new Map(notes.map((note) => [note.midi, note]));
  const hasAnyStats = notes.length > 0;
  const showChromaticTab = hasChromatics(statsByMidi);
  const staff = buildStaffSvg(statsByMidi, DEFAULT_VIEW_WIDTH, 'natural');

  const tabs = Object.values(STATS_STAFF_MODES)
    .filter((mode) => mode.id === 'natural' || showChromaticTab)
    .map((mode) => `
      <button
        type="button"
        class="stats-staff__tab${mode.id === 'natural' ? ' stats-staff__tab--active' : ''}"
        data-staff-mode="${mode.id}"
        role="tab"
        aria-selected="${mode.id === 'natural' ? 'true' : 'false'}"
      >${mode.label}</button>
    `)
    .join('');

  const legend = Object.entries(STATS_LEVEL_META).map(([level, meta]) => `
    <label class="stats-staff__legend-item stats-staff__legend-item--${meta.className}" title="${escapeXml(meta.hint ?? '')}">
      <input
        type="checkbox"
        class="stats-staff__filter"
        data-level="${level}"
        checked
      >
      <span class="stats-staff__legend-swatch" style="background:${meta.color}"></span>
      <span class="stats-staff__legend-label">${meta.label}</span>
    </label>
  `).join('');

  const hints = Object.values(STATS_LEVEL_META)
    .map((meta) => `<li><strong>${meta.label}</strong> — ${meta.hint}</li>`)
    .join('');

  return `
    <section class="stats-staff" data-stats-staff data-staff-mode="natural" aria-label="Карта выученных нот на нотном стане">
      <div class="stats-staff__header">
        <div class="stats-staff__header-row">
          <h3 class="stats-staff__title">Карта нот</h3>
          ${showChromaticTab ? `<div class="stats-staff__tabs" role="tablist" aria-label="Тип нот">${tabs}</div>` : ''}
        </div>
        <p class="stats-staff__hint stats-staff__mode-hint">${STATS_STAFF_MODES.natural.hint}</p>
      </div>
      <div class="stats-staff__viewport" role="img" aria-label="Нотный стан с цветовой разметкой прогресса">
        <p class="stats-staff__empty-visible" hidden>${STATS_STAFF_MODES.natural.empty}</p>
        <svg
          class="stats-staff__svg"
          viewBox="0 0 ${staff.viewWidth} ${staff.viewHeight}"
          preserveAspectRatio="xMidYMid meet"
          xmlns="http://www.w3.org/2000/svg"
        >
          ${staff.innerHtml}
        </svg>
      </div>
      <div class="stats-staff__legend" aria-label="Фильтры категорий">
        ${legend}
      </div>
      <ul class="stats-staff__categories-hint">${hints}</ul>
      ${hasAnyStats ? '' : '<p class="stats-staff__empty">Пройдите тренировку нот — цвета на стане покажут ваш прогресс.</p>'}
    </section>
  `;
}

/**
 * @param {ParentNode|null|undefined} root
 * @param {Array<{midi:number, level?:string}>} [notes]
 */
export function mountStatsStaffChart(root, notes = []) {
  const section = root?.querySelector?.('[data-stats-staff]') ?? root;
  if (!(section instanceof HTMLElement)) return;

  const statsByMidi = new Map(notes.map((note) => [note.midi, note]));
  const viewport = section.querySelector('.stats-staff__viewport');
  let mode = section.dataset.staffMode ?? 'natural';

  const fit = () => {
    const width = Math.max(320, viewport?.clientWidth ?? DEFAULT_VIEW_WIDTH);
    drawStaff(section, statsByMidi, width, mode);
  };

  if (section.dataset.statsStaffBound !== '1') {
    section.dataset.statsStaffBound = '1';
    section.addEventListener('change', (event) => {
      if (!(event.target instanceof HTMLInputElement)) return;
      if (!event.target.classList.contains('stats-staff__filter')) return;
      applyNoteVisibility(section);
    });
    section.addEventListener('click', (event) => {
      const tab = event.target.closest('[data-staff-mode]');
      if (!(tab instanceof HTMLButtonElement)) return;
      const nextMode = tab.getAttribute('data-staff-mode');
      if (!nextMode || nextMode === mode) return;
      mode = nextMode;
      setStaffMode(section, mode);
      fit();
    });
  }

  setStaffMode(section, mode);
  fit();

  if (viewport && typeof ResizeObserver !== 'undefined') {
    const observer = new ResizeObserver(() => fit());
    observer.observe(viewport);
    section._statsStaffObserver = observer;
  }
}
