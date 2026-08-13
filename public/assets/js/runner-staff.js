import { midiToStaffNote } from './notes.js';
import { usesBothClefs } from './note-trainer.js';
import { clefWidth, renderClefSymbol } from './clef-glyphs.js';
import { renderNoteFlags } from './note-flags.js';

const TREBLE_REF = 64;
const BASS_REF = 43;

/** Reference quarter length for note glyphs and spacing baseline. */
const RUNNER_REFERENCE_QUARTER = 400;
/** Horizontal distance for a quarter note — other values scale linearly by duration. */
const RUNNER_BASE_SPACING = 44;
export const DEFAULT_RUNNER_TEMPO_SCALE = 3.0;

export function runnerScrollSpeed(tempoScale = DEFAULT_RUNNER_TEMPO_SCALE) {
  return (RUNNER_BASE_SPACING / RUNNER_REFERENCE_QUARTER) / tempoScale;
}
const RUNNER_BEATS_PER_MEASURE = 4;
/** Time from «Играй!» until the first note reaches the hit line. */
const RUNNER_FIRST_HIT_MS = 4500;

function durationKind(durationMs, referenceQuarter = RUNNER_REFERENCE_QUARTER) {
  const ratio = durationMs / referenceQuarter;
  if (ratio >= 3.2) return 'whole';
  if (ratio >= 1.7) return 'half';
  if (ratio >= 0.85) return 'quarter';
  if (ratio >= 0.45) return 'eighth';
  return 'sixteenth';
}

function durationToSpacing(durationMs, baseSpacing = RUNNER_BASE_SPACING, referenceQuarter = RUNNER_REFERENCE_QUARTER) {
  const ratio = durationMs / referenceQuarter;
  return baseSpacing * Math.max(0.55, Math.min(2.4, 0.45 + ratio * 0.55));
}

/** Minimum center-to-center distance so heads, stems, and flags do not overlap. */
function minNoteAdvance(durationMs, scale, lineGap, referenceQuarter = RUNNER_REFERENCE_QUARTER) {
  const kind = durationKind(durationMs, referenceQuarter);
  const headSpan = lineGap;
  const tail = kind === 'sixteenth' ? 16 * scale : kind === 'eighth' ? 12 * scale : 6 * scale;
  return headSpan + tail;
}

function measureCapacityMs() {
  return RUNNER_BEATS_PER_MEASURE * RUNNER_REFERENCE_QUARTER;
}

function measureBarGap(baseSpacing = RUNNER_BASE_SPACING, scale = 1) {
  return Math.max(22 * scale, baseSpacing * 0.55);
}

function measureAccumulatedMs(events, referenceQuarter = RUNNER_REFERENCE_QUARTER) {
  const capacity = RUNNER_BEATS_PER_MEASURE * referenceQuarter;
  let accumulated = 0;
  for (const event of events) {
    accumulated += event.duration ?? referenceQuarter;
    if (accumulated >= capacity - 1e-6) {
      accumulated = 0;
    }
  }
  return accumulated;
}

export function measureRemainingMs(events, referenceQuarter = RUNNER_REFERENCE_QUARTER) {
  return measureCapacityMs() - measureAccumulatedMs(events, referenceQuarter);
}

function layoutRunnerEvents(events, startX, hitLineX, scrollSpeed, scale = 1, lineGap = 14 * scale, { initialAccumulated = 0, startIndex = 0 } = {}) {
  const positions = [];
  const hitTimes = [];
  const barXs = [];
  let x = startX;
  let accumulated = initialAccumulated;
  const barGap = measureBarGap(RUNNER_BASE_SPACING, scale);

  for (let i = 0; i < events.length; i++) {
    const duration = events[i].duration ?? RUNNER_REFERENCE_QUARTER;
    const startsNewMeasure = accumulated === 0 && (i > 0 || startIndex > 0);

    if (startsNewMeasure) {
      barXs.push(x + barGap * 0.32);
      x += barGap;
    }

    positions.push(x);
    hitTimes.push((x - hitLineX) / scrollSpeed);

    x += Math.max(
      minNoteAdvance(duration, scale, lineGap),
      durationToSpacing(duration),
    );
    accumulated += duration;

    if (accumulated >= measureCapacityMs() - 1e-6) {
      accumulated = 0;
    }
  }

  const lastDuration = events.at(-1)?.duration ?? RUNNER_REFERENCE_QUARTER;
  const tail = Math.max(
    minNoteAdvance(lastDuration, scale, lineGap),
    durationToSpacing(lastDuration),
  );

  return {
    positions,
    hitTimes,
    barXs,
    contentWidth: x + tail + 80 * scale,
    measureAccumulated: accumulated,
  };
}

function diatonicSteps(midi) {
  const map = [0, 0, 1, 1, 2, 3, 3, 4, 4, 5, 5, 6];
  return Math.floor(midi / 12) * 7 + map[midi % 12];
}

export class RunnerStaffView {
  constructor(viewportEl) {
    this.viewport = viewportEl;
    this.scrollEl = viewportEl?.querySelector('.staff-scroll');
    this.svg = viewportEl?.querySelector('.staff-svg');
    this.hitLineEl = viewportEl?.querySelector('.runner-hit-line');
    this.countdownEl = viewportEl?.querySelector('.runner-countdown');
    this.events = [];
    this.settings = null;
    this.metrics = null;
    this.referenceQuarter = RUNNER_REFERENCE_QUARTER;
    this.spacing = RUNNER_BASE_SPACING;
    this.tempoScale = DEFAULT_RUNNER_TEMPO_SCALE;
    this.layout = null;
  }

  get hitLineX() {
    if (!this.viewport) return 240;
    let width = this.viewport.clientWidth;
    if (width < 200) {
      width = this.viewport.parentElement?.clientWidth
        ?? document.documentElement.clientWidth
        ?? 800;
    }
    return Math.max(96, Math.round(width * 0.28));
  }

  clear() {
    if (this.svg) this.svg.innerHTML = '';
    this.events = [];
    this.layout = null;
    this.setScrollOffset(0);
    this.setCountdown(null);
  }

  setScrollOffset(px) {
    if (!this.scrollEl) return;
    this.scrollEl.style.transform = `translateX(${-Math.max(0, px)}px)`;
  }

  setCountdown(value) {
    if (!this.countdownEl) return;
    if (value == null || value <= 0) {
      this.countdownEl.hidden = true;
      this.countdownEl.textContent = '';
      return;
    }
    this.countdownEl.hidden = false;
    this.countdownEl.textContent = String(value);
  }

  buildLayout(events, { startX = null, startIndex = 0, initialAccumulated = 0 } = {}) {
    const twoHands = usesBothClefs(this.settings);
    const bassOnly = !twoHands && this.settings?.bass?.enabled && !this.settings?.treble?.enabled;
    this.metrics = this._metrics(twoHands, bassOnly ? 'bass' : 'treble');
    this.referenceQuarter = RUNNER_REFERENCE_QUARTER;
    this.spacing = RUNNER_BASE_SPACING;

    const hitLineX = this.hitLineX;
    const scrollSpeed = runnerScrollSpeed(this.tempoScale);
    const noteStartX = startX ?? hitLineX + RUNNER_FIRST_HIT_MS * scrollSpeed;
    const { positions, hitTimes, barXs, contentWidth, measureAccumulated } = layoutRunnerEvents(
      events,
      noteStartX,
      hitLineX,
      scrollSpeed,
      this.metrics.scale,
      this.metrics.lineGap,
      { initialAccumulated, startIndex },
    );

    const laidOut = events.map((event, offset) => {
      const index = startIndex + offset;
      return {
        ...event,
        index,
        x: positions[offset],
        hitTime: hitTimes[offset],
      };
    });

    return {
      events: laidOut,
      contentWidth: Math.max(this.viewport.clientWidth * 1.5, contentWidth),
      tempoScale: this.tempoScale,
      scrollSpeed,
      hitLineX,
      noteStartX,
      lastX: positions.at(-1) ?? noteStartX,
      barXs,
      measureAccumulated,
    };
  }

  load(events, settings, { startX = null, startIndex = 0, append = false, tempoScale = null } = {}) {
    if (!this.viewport || !this.svg) return null;

    this.settings = settings;
    if (tempoScale != null) {
      this.tempoScale = tempoScale;
    }
    const batch = events.slice();
    const initialAccumulated = append ? measureAccumulatedMs(this.events) : 0;

    if (append && this.events.length) {
      const last = this.events.at(-1);
      if (!startX) {
        const scale = this.metrics?.scale ?? 1;
        const lineGap = this.metrics?.lineGap ?? 14 * scale;
        const lastDuration = last.duration ?? RUNNER_REFERENCE_QUARTER;
        startX = last.x + Math.max(
          minNoteAdvance(lastDuration, scale, lineGap),
          durationToSpacing(lastDuration),
        );
      }
      startIndex = this.events.length;
    }

    const layout = this.buildLayout(batch, { startX, startIndex, initialAccumulated });

    if (append) {
      this.events = [...this.events, ...layout.events];
      this.layout = {
        ...layout,
        contentWidth: Math.max(this.layout?.contentWidth ?? 0, layout.contentWidth),
        barXs: [...(this.layout?.barXs ?? []), ...layout.barXs],
      };
    } else {
      this.events = layout.events;
      this.layout = layout;
      this.setScrollOffset(0);
    }

    const twoHands = usesBothClefs(settings);
    this._render(this.events, this.layout.contentWidth, twoHands, this.layout.barXs ?? []);
    if (this.scrollEl) {
      this.scrollEl.style.width = `${this.layout.contentWidth}px`;
    }

    return this.layout;
  }

  setNoteState(index, state) {
    const group = this.svg?.querySelector(`.staff-note[data-event-index="${index}"]`);
    if (!group) return;
    group.classList.remove(
      'staff-note--upcoming',
      'staff-note--current',
      'staff-note--done',
      'staff-note--hit',
      'staff-note--missed',
    );
    if (state === 'active') group.classList.add('staff-note--current');
    else if (state === 'hit') group.classList.add('staff-note--done', 'staff-note--hit');
    else if (state === 'missed') group.classList.add('staff-note--missed');
    else group.classList.add('staff-note--upcoming');
  }

  _metrics(twoHands, singleClef = 'treble') {
    const svgHeight = twoHands ? 268 : 168;
    const baseHeight = twoHands ? 280 : 180;
    const scale = Math.max(1.2, svgHeight / baseHeight);
    const lineGap = 14 * scale;
    const staffSpan = 4 * lineGap;
    const clefRoom = 22 * scale;
    const topPad = 18 * scale;

    let trebleBottom;
    let bassBottom;

    if (twoHands) {
      const gapBetween = 28 * scale;
      const block = staffSpan * 2 + gapBetween + clefRoom;
      const startTop = Math.max(topPad, (svgHeight - block) / 2);
      trebleBottom = startTop + staffSpan;
      bassBottom = trebleBottom + gapBetween + staffSpan;
    } else {
      const block = staffSpan + clefRoom;
      const startTop = Math.max(topPad, (svgHeight - block) / 2);
      if (singleClef === 'bass') {
        trebleBottom = 0;
        bassBottom = startTop + staffSpan;
      } else {
        trebleBottom = startTop + staffSpan;
        bassBottom = 0;
      }
    }

    const lineStart = twoHands ? 36 * scale : 12 * scale;
    const clefX = lineStart + 2 * scale;
    const clefWidthPx = twoHands
      ? Math.max(clefWidth('treble', lineGap), clefWidth('bass', lineGap))
      : clefWidth(singleClef === 'bass' ? 'bass' : 'treble', lineGap);
    const noteStartX = clefX + clefWidthPx + lineGap * 3.5;

    return {
      scale,
      svgHeight,
      lineGap,
      trebleBottom,
      bassBottom,
      lineStart,
      clefX,
      noteRx: lineGap * 0.5,
      noteRy: lineGap * 0.36,
      wholeRx: lineGap * 0.56,
      wholeRy: lineGap * 0.4,
      stemLen: lineGap * 2.4,
      accidentalOffset: 13 * scale,
      accidentalSize: 19 * scale,
      ledgerHalfWidth: 17 * scale,
      flagStep: 5.5 * scale,
      noteStartX,
    };
  }

  _midiToTrebleY(midi) {
    const m = this.metrics;
    return m.trebleBottom - (diatonicSteps(midi) - diatonicSteps(TREBLE_REF)) * (m.lineGap / 2);
  }

  _midiToBassY(midi) {
    const m = this.metrics;
    return m.bassBottom - (diatonicSteps(midi) - diatonicSteps(BASS_REF)) * (m.lineGap / 2);
  }

  _render(events, totalWidth, twoHands, barXs = []) {
    const m = this.metrics;
    const svgHeight = m.svgHeight;
    const lineStart = m.lineStart;
    const lineEnd = totalWidth - lineStart;

    this.svg.setAttribute('width', String(totalWidth));
    this.svg.setAttribute('height', String(svgHeight));
    this.svg.setAttribute('viewBox', `0 0 ${totalWidth} ${svgHeight}`);
    this.svg.setAttribute('preserveAspectRatio', 'xMinYMid meet');

    const trebleTop = m.trebleBottom - 4 * m.lineGap;
    const bassTop = m.bassBottom - 4 * m.lineGap;
    const bassOnly = !twoHands && this.settings?.bass?.enabled && !this.settings?.treble?.enabled;
    const parts = [];

    if (twoHands) {
      parts.push(this._clef('treble', m.clefX, m.trebleBottom));
      parts.push(this._clef('bass', m.clefX, m.bassBottom));
      parts.push(this._staffLines(lineStart, lineEnd, m.trebleBottom));
      parts.push(this._staffLines(lineStart, lineEnd, m.bassBottom));
    } else if (bassOnly) {
      parts.push(this._clef('bass', m.clefX, m.bassBottom));
      parts.push(this._staffLines(lineStart, lineEnd, m.bassBottom));
    } else {
      parts.push(this._clef('treble', m.clefX, m.trebleBottom));
      parts.push(this._staffLines(lineStart, lineEnd, m.trebleBottom));
    }

    const minBarX = m.noteStartX + m.lineGap * 0.5;
    for (const barX of barXs) {
      if (barX <= minBarX) continue;
      parts.push(this._barLine(barX, twoHands, bassOnly, trebleTop, bassTop));
    }

    events.forEach((event) => {
      const x = event.x;
      const staffInfo = midiToStaffNote(event.midi, event.spelling ?? 'sharp');
      const onBass = twoHands
        ? event.clef === 'bass'
        : (this.settings?.bass?.enabled && !this.settings?.treble?.enabled) || event.clef === 'bass';
      const y = onBass
        ? this._midiToBassY(staffInfo.staffMidi)
        : this._midiToTrebleY(staffInfo.staffMidi);
      const staffBottom = onBass ? m.bassBottom : m.trebleBottom;
      const staffTop = onBass ? bassTop : trebleTop;
      const staffKind = onBass ? 'bass' : 'treble';

      parts.push(this._ledgerLines(x, y, staffBottom, staffTop));
      parts.push(this._note(x, y, event.index, event, staffKind, staffInfo, event.duration));
    });

    this.svg.innerHTML = parts.join('');
  }

  _clef(kind, x, bottomY) {
    return renderClefSymbol(kind, x, bottomY, this.metrics.lineGap);
  }

  _staffLines(lineStart, lineEnd, bottomY) {
    const m = this.metrics;
    const lines = [];
    for (let i = 0; i < 5; i++) {
      const y = bottomY - i * m.lineGap;
      lines.push(`<line x1="${lineStart}" y1="${y}" x2="${lineEnd}" y2="${y}" class="staff-line"/>`);
    }
    return lines.join('');
  }

  _barLine(x, grandStaff, bassOnly, trebleTop, bassTop) {
    const m = this.metrics;
    let yTop;
    let yBottom;

    if (grandStaff) {
      yTop = trebleTop;
      yBottom = m.bassBottom;
    } else if (bassOnly) {
      yTop = bassTop;
      yBottom = m.bassBottom;
    } else {
      yTop = trebleTop;
      yBottom = m.trebleBottom;
    }

    const strokeW = 1.5 * m.scale;
    return `<line x1="${x}" y1="${yTop}" x2="${x}" y2="${yBottom}" class="staff-bar" stroke-width="${strokeW}"/>`;
  }

  _ledgerLines(x, y, bottom, top) {
    const m = this.metrics;
    const lines = [];
    const hw = m.ledgerHalfWidth;
    if (y > bottom) {
      for (let ly = bottom + m.lineGap; ly <= y + 1; ly += m.lineGap) {
        lines.push(`<line x1="${x - hw}" y1="${ly}" x2="${x + hw}" y2="${ly}" class="staff-ledger"/>`);
      }
    }
    if (y < top) {
      for (let ly = top - m.lineGap; ly >= y - 1; ly -= m.lineGap) {
        lines.push(`<line x1="${x - hw}" y1="${ly}" x2="${x + hw}" y2="${ly}" class="staff-ledger"/>`);
      }
    }
    return lines.join('');
  }

  _note(x, y, eventIndex, note, clef, staffInfo, durationMs = 400) {
    const m = this.metrics;
    const middleY = clef === 'bass' ? m.bassBottom - 2 * m.lineGap : m.trebleBottom - 2 * m.lineGap;
    const stemUp = y > middleY;
    const kind = durationKind(durationMs, this.referenceQuarter);
    const hasStem = kind !== 'whole';
    const isHollow = kind === 'whole' || kind === 'half';
    const flagCount = kind === 'eighth' ? 1 : kind === 'sixteenth' ? 2 : 0;
    const rx = kind === 'whole' ? m.wholeRx : m.noteRx;
    const ry = kind === 'whole' ? m.wholeRy : m.noteRy;
    const stemX = stemUp ? x + rx - 0.5 : x - rx + 0.5;
    const stemY2 = stemUp ? y - m.stemLen : y + m.stemLen;
    const hand = note.clef === 'bass' ? 'left' : 'right';
    const accidental = staffInfo.accidental
      ? `<text x="${x - m.accidentalOffset}" y="${y}" class="staff-accidental" font-size="${m.accidentalSize}" text-anchor="end" dominant-baseline="middle">${staffInfo.accidental === 'flat' ? '♭' : '♯'}</text>`
      : '';

    const headClass = isHollow ? 'staff-note__head staff-note__head--hollow' : 'staff-note__head staff-note__head--filled';
    const head = `<ellipse cx="${x}" cy="${y}" rx="${rx}" ry="${ry}" class="${headClass}"/>`;

    let stem = '';
    if (hasStem) {
      stem = `<line x1="${stemX}" y1="${y}" x2="${stemX}" y2="${stemY2}" class="staff-note__stem" stroke-width="${1.6 * m.scale}"/>`;
    }

    let flags = renderNoteFlags(stemX, stemY2, stemUp, flagCount, m.scale, m.flagStep);

    return `
      <g class="staff-note staff-note--${hand} staff-note--${kind} staff-note--upcoming" data-event-index="${eventIndex}" data-midi="${note.midi}">
        ${accidental}
        <g class="staff-note__graphic">
          ${head}
          ${stem}
          ${flags}
        </g>
      </g>
    `;
  }
}
