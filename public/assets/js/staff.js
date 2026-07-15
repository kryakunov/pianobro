import { midiToStaffNote } from './notes.js';
import { normalizeLesson } from './lesson-utils.js';
import { clefWidth, renderClefSymbol } from './clef-glyphs.js';

const TREBLE_REF = 64;
const BASS_REF = 43;

function durationKind(durationMs, referenceQuarter = 400) {
  const ratio = durationMs / referenceQuarter;
  if (ratio >= 3.2) return 'whole';
  if (ratio >= 1.7) return 'half';
  if (ratio >= 0.85) return 'quarter';
  if (ratio >= 0.45) return 'eighth';
  return 'sixteenth';
}

function referenceQuarterDuration(events) {
  const counts = new Map();
  for (const event of events) {
    counts.set(event.duration, (counts.get(event.duration) ?? 0) + 1);
  }

  let bestDuration = 400;
  let bestCount = 0;
  for (const [duration, count] of counts) {
    if (count > bestCount) {
      bestDuration = duration;
      bestCount = count;
    }
  }

  return bestDuration;
}

function durationToSpacing(duration, baseSpacing, referenceQuarter = 400) {
  const ratio = duration / referenceQuarter;
  return baseSpacing * Math.max(0.55, Math.min(2.4, 0.45 + ratio * 0.55));
}

function diatonicSteps(midi) {
  const map = [0, 0, 1, 1, 2, 3, 3, 4, 4, 5, 5, 6];
  return Math.floor(midi / 12) * 7 + map[midi % 12];
}

export class StaffView {
  constructor(viewportEl) {
    this.viewport = viewportEl;
    this.scrollEl = viewportEl.querySelector('.staff-scroll');
    this.svg = viewportEl.querySelector('.staff-svg');
    this.lesson = null;
    this.eventPositions = [];
    this.spacing = 40;
    this.padding = 60;
    this.spelling = 'sharp';
    this.referenceQuarter = 400;
    this.metrics = null;
    this.drillClef = 'treble';
    this.drillDualClef = false;
    this.drillLayoutKey = null;
    this._drillViewportW = null;
  }

  loadLesson(lesson) {
    this.lesson = normalizeLesson(lesson);
    this.drillMode = false;
    this.render();
  }

  showDrillNote(midi, { spelling = 'sharp', clef = 'treble', dualClef = false } = {}) {
    const layoutKey = dualClef ? 'grand' : clef;
    const layoutChanged = !this.drillMode || this.drillLayoutKey !== layoutKey;

    this.drillMode = true;
    this.drillClef = clef;
    this.drillDualClef = dualClef;
    this.drillLayoutKey = layoutKey;
    this.spelling = spelling;

    if (layoutChanged || !this._drillViewportW) {
      this._drillViewportW = this.viewport.clientWidth || 800;
      this.metrics = null;
    }

    this.lesson = normalizeLesson({
      events: [{
        duration: 400,
        notes: [{ midi, hand: clef === 'bass' ? 'left' : 'right' }],
      }],
      twoHands: dualClef,
    });
    this.render();
    this._updateNoteStyles({ index: 0, running: true, paused: false });
    this.viewport.scrollTo({ left: 0, behavior: 'auto' });
  }

  clear() {
    this.lesson = null;
    this.drillMode = false;
    this.drillClef = 'treble';
    this.drillDualClef = false;
    this.drillLayoutKey = null;
    this._drillViewportW = null;
    this.svg.innerHTML = '';
    this.eventPositions = [];
    this.metrics = null;
  }

  update(state) {
    if (!this.lesson || (this.drillMode && !state.preview)) return;
    this._updateNoteStyles(state);
    if (!this.drillMode) {
      this._scrollToIndex(state.index, state.running || state.preview);
    }
  }

  _svgHeight(twoHands) {
    const viewportH = this.viewport.clientHeight;
    if (viewportH > 200) {
      return twoHands ? Math.min(viewportH - 16, 480) : Math.min(viewportH - 16, 360);
    }
    return twoHands ? 280 : 180;
  }

  _metrics(twoHands, singleClef = 'treble', fixedSvgHeight = null) {
    const svgHeight = fixedSvgHeight ?? this._svgHeight(twoHands);
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
    const braceX = twoHands ? 6 * scale : lineStart;
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
      braceX,
      clefX,
      clefWidth: clefWidthPx,
      noteStartX,
      noteRx: lineGap * 0.5,
      noteRy: lineGap * 0.36,
      wholeRx: lineGap * 0.56,
      wholeRy: lineGap * 0.4,
      stemLen: lineGap * 2.4,
      braceSize: 78 * scale,
      accidentalOffset: 13 * scale,
      accidentalSize: 19 * scale,
      ledgerHalfWidth: 17 * scale,
      chordOffset: 5 * scale,
      flagStep: 8 * scale,
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

  render() {
    const events = this.lesson?.events;
    if (!events?.length) {
      this.svg.innerHTML = '';
      this.eventPositions = [];
      return;
    }

    const viewportW = this.viewport.clientWidth || 800;
    const twoHands = this.lesson.twoHands;
    const dualClef = this.drillMode && this.drillDualClef;
    this.referenceQuarter = referenceQuarterDuration(events);

    if (this.drillMode && events.length === 1 && events[0].notes.length === 1) {
      const fixedHeight = dualClef ? 268 : 168;
      const clef = this.drillClef ?? 'treble';
      const totalWidth = this._drillViewportW || viewportW;

      if (!this.metrics) {
        this.metrics = this._metrics(dualClef, dualClef ? 'treble' : clef, fixedHeight);
      }

      const lineStart = this.metrics.lineStart;
      this.eventPositions = [Math.max(this.metrics.noteStartX + this.metrics.lineGap, totalWidth * 0.5)];
      this._drawEvents(events, totalWidth, lineStart, totalWidth - lineStart, dualClef, clef);
      this.scrollEl.style.width = `${totalWidth}px`;
      return;
    }

    const fixedHeight = twoHands ? 268 : 168;
    this.metrics = this._metrics(twoHands, 'treble', fixedHeight);

    const lineStart = this.metrics.lineStart;
    this.spacing = twoHands ? 40 : 44;

    let x = this.metrics.noteStartX;
    this.eventPositions = events.map((ev) => {
      const pos = x;
      x += durationToSpacing(ev.duration, this.spacing, this.referenceQuarter);
      return pos;
    });

    const contentWidth = x + this.padding;
    const totalWidth = Math.max(viewportW, contentWidth);
    const lineEnd = totalWidth - lineStart;

    this._drawEvents(events, totalWidth, lineStart, lineEnd, twoHands);
    this.scrollEl.style.width = `${totalWidth}px`;
    this._scrollToIndex(0, false);
  }

  _drawEvents(events, totalWidth, lineStart, lineEnd, twoHands, drillClef = 'treble') {
    const m = this.metrics;
    const svgHeight = m.svgHeight;
    this.svg.setAttribute('width', String(totalWidth));
    this.svg.setAttribute('height', String(svgHeight));
    this.svg.setAttribute('viewBox', `0 0 ${totalWidth} ${svgHeight}`);
    this.svg.setAttribute('preserveAspectRatio', 'xMinYMid meet');

    const trebleTop = m.trebleBottom - 4 * m.lineGap;
    const bassTop = m.bassBottom - 4 * m.lineGap;
    const parts = [];
    const drillDual = this.drillMode && this.drillDualClef;
    const drillBass = this.drillMode && !drillDual && drillClef === 'bass';
    const grandStaff = twoHands || drillDual;

    if (grandStaff) {
      parts.push(this._brace(m.braceX, trebleTop, m.bassBottom));
      parts.push(this._clef('treble', m.clefX, m.trebleBottom));
      parts.push(this._clef('bass', m.clefX, m.bassBottom));
      parts.push(this._staffLines(lineStart, lineEnd, m.trebleBottom, 0));
      parts.push(this._staffLines(lineStart, lineEnd, m.bassBottom, 0));
    } else if (drillBass) {
      parts.push(this._clef('bass', m.clefX, m.bassBottom));
      parts.push(this._staffLines(lineStart, lineEnd, m.bassBottom, 0));
    } else {
      parts.push(this._clef('treble', m.clefX, m.trebleBottom));
      parts.push(this._staffLines(lineStart, lineEnd, m.trebleBottom, 0));
    }

    events.forEach((event, eventIndex) => {
      const nx = this.eventPositions[eventIndex];
      const rightNotes = event.notes.filter((n) => n.hand !== 'left');
      const leftNotes = event.notes.filter((n) => n.hand === 'left');

      if (drillDual) {
        event.notes.forEach((note, ni) => {
          const x = nx + ni * m.chordOffset;
          const staffInfo = midiToStaffNote(note.midi, this.spelling);
          const onBass = this.drillClef === 'bass';
          const y = onBass
            ? this._midiToBassY(staffInfo.staffMidi)
            : this._midiToTrebleY(staffInfo.staffMidi);
          const staffBottom = onBass ? m.bassBottom : m.trebleBottom;
          const staffTop = onBass ? bassTop : trebleTop;
          const staffKind = onBass ? 'bass' : 'treble';
          parts.push(this._ledgerLines(x, y, staffBottom, staffTop));
          parts.push(this._note(x, y, eventIndex, note, staffKind, staffInfo, event.duration));
        });
        return;
      }

      if (drillBass) {
        event.notes.forEach((note, ni) => {
          const x = nx + ni * m.chordOffset;
          const staffInfo = midiToStaffNote(note.midi, this.spelling);
          const y = this._midiToBassY(staffInfo.staffMidi);
          parts.push(this._ledgerLines(x, y, m.bassBottom, bassTop));
          parts.push(this._note(x, y, eventIndex, note, 'bass', staffInfo, event.duration));
        });
        return;
      }

      rightNotes.forEach((note, ni) => {
        const x = nx + ni * m.chordOffset;
        const staffInfo = midiToStaffNote(note.midi, this.spelling);
        const y = this._midiToTrebleY(staffInfo.staffMidi);
        parts.push(this._ledgerLines(x, y, m.trebleBottom, trebleTop));
        parts.push(this._note(x, y, eventIndex, note, 'treble', staffInfo, event.duration));
      });

      leftNotes.forEach((note, ni) => {
        const x = nx + ni * m.chordOffset;
        const staffInfo = midiToStaffNote(note.midi, this.spelling);
        const y = this._midiToBassY(staffInfo.staffMidi);
        parts.push(this._ledgerLines(x, y, m.bassBottom, bassTop));
        parts.push(this._note(x, y, eventIndex, note, 'bass', staffInfo, event.duration));
      });

      if (!twoHands && leftNotes.length === 0 && rightNotes.length === 0) {
        event.notes.forEach((note, ni) => {
          const x = nx + ni * m.chordOffset;
          const staffInfo = midiToStaffNote(note.midi, this.spelling);
          const y = this._midiToTrebleY(staffInfo.staffMidi);
          parts.push(this._note(x, y, eventIndex, note, 'treble', staffInfo, event.duration));
        });
      }
    });

    this.svg.innerHTML = parts.join('');
  }

  _brace(x, topY, bottomY) {
    const m = this.metrics;
    const trebleCenter = m.trebleBottom - 2 * m.lineGap;
    const bassCenter = m.bassBottom - 2 * m.lineGap;
    const mid = (trebleCenter + bassCenter) / 2;
    return `<text x="${x}" y="${mid}" class="staff-brace" font-size="${m.braceSize}" fill="currentColor" text-anchor="end" dominant-baseline="middle">{</text>`;
  }

  _clef(kind, x, bottomY) {
    return renderClefSymbol(kind, x, bottomY, this.metrics.lineGap);
  }

  _staffLines(lineStart, lineEnd, bottomY, offsetY) {
    const m = this.metrics;
    const lines = [];
    for (let i = 0; i < 5; i++) {
      const y = bottomY - i * m.lineGap + offsetY;
      lines.push(`<line x1="${lineStart}" y1="${y}" x2="${lineEnd}" y2="${y}" class="staff-line"/>`);
    }
    return lines.join('');
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
    const hand = note.hand ?? 'right';
    const accidental = staffInfo.accidental
      ? `<text x="${x - m.accidentalOffset}" y="${y}" class="staff-accidental" font-size="${m.accidentalSize}" text-anchor="end" dominant-baseline="middle">${staffInfo.accidental === 'flat' ? '♭' : '♯'}</text>`
      : '';

    const headClass = isHollow ? 'staff-note__head staff-note__head--hollow' : 'staff-note__head staff-note__head--filled';
    const head = `<ellipse cx="${x}" cy="${y}" rx="${rx}" ry="${ry}" class="${headClass}"/>`;

    let stem = '';
    if (hasStem) {
      stem = `<line x1="${stemX}" y1="${y}" x2="${stemX}" y2="${stemY2}" class="staff-note__stem" stroke-width="${1.6 * m.scale}"/>`;
    }

    let flags = '';
    for (let i = 0; i < flagCount; i++) {
      const offset = i * m.flagStep;
      if (stemUp) {
        const fy = stemY2 - offset;
        flags += `<path d="M ${stemX} ${fy} C ${stemX + 2 * m.scale} ${fy - 6 * m.scale}, ${stemX + 11 * m.scale} ${fy - 8 * m.scale}, ${stemX + 9 * m.scale} ${fy - 16 * m.scale}" class="staff-note__flag"/>`;
      } else {
        const fy = stemY2 + offset;
        flags += `<path d="M ${stemX} ${fy} C ${stemX - 2 * m.scale} ${fy + 6 * m.scale}, ${stemX - 11 * m.scale} ${fy + 8 * m.scale}, ${stemX - 9 * m.scale} ${fy + 16 * m.scale}" class="staff-note__flag"/>`;
      }
    }

    return `
      <g class="staff-note staff-note--${hand} staff-note--${kind}" data-event-index="${eventIndex}" data-midi="${note.midi}">
        ${accidental}
        <g class="staff-note__graphic">
          ${head}
          ${stem}
          ${flags}
        </g>
      </g>
    `;
  }

  _updateNoteStyles(state) {
    const groups = this.svg.querySelectorAll('.staff-note');
    groups.forEach((g) => {
      const i = parseInt(g.dataset.eventIndex, 10);
      g.classList.remove('staff-note--done', 'staff-note--current', 'staff-note--upcoming', 'staff-note--playing');

      if (state.preview && i === state.index) {
        g.classList.add('staff-note--playing');
      } else if (i < state.index) {
        g.classList.add('staff-note--done');
      } else if (i === state.index && state.running && !state.paused) {
        g.classList.add('staff-note--current');
      } else {
        g.classList.add('staff-note--upcoming');
      }
    });
  }

  _scrollToIndex(index, running) {
    if (!this.eventPositions.length) return;
    const target = this.eventPositions[Math.min(index, this.eventPositions.length - 1)];
    const margin = this.metrics?.scale ? 48 * this.metrics.scale : 48;
    this.viewport.scrollTo({
      left: Math.max(0, target - margin),
      behavior: running ? 'smooth' : 'auto',
    });
  }
}
