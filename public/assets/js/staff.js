import { midiToName, midiToStaffNote } from './notes.js';
import { normalizeLesson } from './lesson-utils.js';

const LINE_GAP = 11;
const TREBLE_BOTTOM = 88;
const TREBLE_REF = 64;
const BASS_BOTTOM = 210;
const BASS_REF = 43;
const NOTE_RX = 5.5;
const NOTE_RY = 4;
const STEM_LEN = 26;

function diatonicSteps(midi) {
  const map = [0, 0, 1, 1, 2, 3, 3, 4, 4, 5, 5, 6];
  return Math.floor(midi / 12) * 7 + map[midi % 12];
}

function midiToTrebleY(midi) {
  return TREBLE_BOTTOM - (diatonicSteps(midi) - diatonicSteps(TREBLE_REF)) * (LINE_GAP / 2);
}

function midiToBassY(midi) {
  return BASS_BOTTOM - (diatonicSteps(midi) - diatonicSteps(BASS_REF)) * (LINE_GAP / 2);
}

function durationToSpacing(duration, baseSpacing) {
  return baseSpacing * Math.max(0.75, Math.min(2, duration / 400));
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
  }

  loadLesson(lesson) {
    this.lesson = normalizeLesson(lesson);
    this.drillMode = false;
    this.render();
  }

  showDrillNote(midi, spelling = 'sharp') {
    this.drillMode = true;
    this.spelling = spelling;
    this.lesson = normalizeLesson({
      events: [{ duration: 400, notes: [{ midi, hand: 'right' }] }],
      twoHands: false,
    });
    this.render();
    this._updateNoteStyles({ index: 0, running: true, paused: false });
    this.viewport.scrollTo({ left: 0, behavior: 'auto' });
  }

  clear() {
    this.lesson = null;
    this.drillMode = false;
    this.svg.innerHTML = '';
    this.eventPositions = [];
  }

  update(state) {
    if (!this.lesson || (this.drillMode && !state.preview)) return;
    this._updateNoteStyles(state);
    this._scrollToIndex(state.index, state.running || state.preview);
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

    if (this.drillMode && events.length === 1 && events[0].notes.length === 1) {
      const totalWidth = viewportW;
      this.eventPositions = [totalWidth * 0.5];
      this._drawEvents(events, totalWidth, 80, totalWidth - 80, twoHands);
      this.scrollEl.style.width = `${totalWidth}px`;
      return;
    }

    const minSpacing = twoHands ? 28 : 32;
    const maxSpacing = twoHands ? 46 : 52;
    const fitSpacing = (viewportW - this.padding * 2) / events.length;
    this.spacing = Math.max(minSpacing, Math.min(maxSpacing, fitSpacing));

    let x = this.padding;
    this.eventPositions = events.map((ev) => {
      const pos = x;
      x += durationToSpacing(ev.duration, this.spacing);
      return pos;
    });

    const totalWidth = x + this.padding;
    this._drawEvents(events, totalWidth, 50, totalWidth - 20, twoHands);
    this.scrollEl.style.width = `${totalWidth}px`;
    this._scrollToIndex(0, false);
  }

  _drawEvents(events, totalWidth, lineStart, lineEnd, twoHands) {
    const svgHeight = twoHands ? 250 : 150;
    this.svg.setAttribute('width', String(totalWidth));
    this.svg.setAttribute('height', String(svgHeight));
    this.svg.setAttribute('viewBox', `0 0 ${totalWidth} ${svgHeight}`);

    const parts = [];
    if (twoHands) {
      parts.push(this._brace(lineStart, TREBLE_BOTTOM - 4 * LINE_GAP, BASS_BOTTOM));
      parts.push(this._clef('treble', 18, TREBLE_BOTTOM));
      parts.push(this._clef('bass', 18, BASS_BOTTOM));
      parts.push(this._staffLines(lineStart, lineEnd, TREBLE_BOTTOM, 0));
      parts.push(this._staffLines(lineStart, lineEnd, BASS_BOTTOM, 0));
    } else {
      parts.push(this._clef('treble', 18, TREBLE_BOTTOM));
      parts.push(this._staffLines(lineStart, lineEnd, TREBLE_BOTTOM, 0));
    }

    events.forEach((event, eventIndex) => {
      const nx = this.eventPositions[eventIndex];
      const rightNotes = event.notes.filter((n) => n.hand !== 'left');
      const leftNotes = event.notes.filter((n) => n.hand === 'left');

      rightNotes.forEach((note, ni) => {
        const x = nx + ni * 4;
        const staffInfo = midiToStaffNote(note.midi, this.spelling);
        const y = midiToTrebleY(staffInfo.staffMidi);
        parts.push(this._ledgerLines(x, y, TREBLE_BOTTOM, TREBLE_BOTTOM - 4 * LINE_GAP));
        parts.push(this._note(x, y, eventIndex, note, 'treble', staffInfo));
      });

      leftNotes.forEach((note, ni) => {
        const x = nx + ni * 4;
        const staffInfo = midiToStaffNote(note.midi, this.spelling);
        const y = midiToBassY(staffInfo.staffMidi);
        parts.push(this._ledgerLines(x, y, BASS_BOTTOM, BASS_BOTTOM - 4 * LINE_GAP));
        parts.push(this._note(x, y, eventIndex, note, 'bass', staffInfo));
      });

      if (!twoHands && leftNotes.length === 0 && rightNotes.length === 0) {
        event.notes.forEach((note, ni) => {
          const x = nx + ni * 4;
          const staffInfo = midiToStaffNote(note.midi, this.spelling);
          const y = midiToTrebleY(staffInfo.staffMidi);
          parts.push(this._note(x, y, eventIndex, note, 'treble', staffInfo));
        });
      }
    });

    this.svg.innerHTML = parts.join('');
  }

  _brace(x, topY, bottomY) {
    const mid = (topY + bottomY + LINE_GAP * 4) / 2;
    return `<text x="${x - 8}" y="${mid + 20}" class="staff-brace" font-size="72" fill="currentColor">{</text>`;
  }

  _clef(kind, x, bottomY) {
    const sym = kind === 'bass' ? '𝄢' : '𝄞';
    return `<text x="${x}" y="${bottomY + 14}" class="staff-clef" font-size="48" fill="currentColor">${sym}</text>`;
  }

  _staffLines(lineStart, lineEnd, bottomY, offsetY) {
    const lines = [];
    for (let i = 0; i < 5; i++) {
      const y = bottomY - i * LINE_GAP + offsetY;
      lines.push(`<line x1="${lineStart}" y1="${y}" x2="${lineEnd}" y2="${y}" class="staff-line"/>`);
    }
    return lines.join('');
  }

  _ledgerLines(x, y, bottom, top) {
    const lines = [];
    const hw = 14;
    if (y > bottom) {
      for (let ly = bottom + LINE_GAP; ly <= y + 1; ly += LINE_GAP) {
        lines.push(`<line x1="${x - hw}" y1="${ly}" x2="${x + hw}" y2="${ly}" class="staff-ledger"/>`);
      }
    }
    if (y < top) {
      for (let ly = top - LINE_GAP; ly >= y - 1; ly -= LINE_GAP) {
        lines.push(`<line x1="${x - hw}" y1="${ly}" x2="${x + hw}" y2="${ly}" class="staff-ledger"/>`);
      }
    }
    return lines.join('');
  }

  _note(x, y, eventIndex, note, clef, staffInfo) {
    const middleY = clef === 'bass' ? BASS_BOTTOM - 2 * LINE_GAP : TREBLE_BOTTOM - 2 * LINE_GAP;
    const stemUp = y > middleY;
    const stemX = stemUp ? x - NOTE_RX + 0.5 : x + NOTE_RX - 0.5;
    const stemY2 = stemUp ? y - STEM_LEN : y + STEM_LEN;
    const name = note.name || staffInfo.name || midiToName(note.midi, this.spelling);
    const hand = note.hand ?? 'right';
    const accidental = staffInfo.accidental
      ? `<text x="${x - 10}" y="${y}" class="staff-accidental" text-anchor="end" dominant-baseline="middle">${staffInfo.accidental === 'flat' ? '♭' : '♯'}</text>`
      : '';

    return `
      <g class="staff-note staff-note--${hand}" data-event-index="${eventIndex}" data-midi="${note.midi}">
        ${accidental}
        <line x1="${stemX}" y1="${y}" x2="${stemX}" y2="${stemY2}" class="staff-note__stem"/>
        <ellipse cx="${x}" cy="${y}" rx="${NOTE_RX}" ry="${NOTE_RY}" class="staff-note__head"/>
        <title>${name}</title>
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
    const margin = 48;
    this.viewport.scrollTo({
      left: Math.max(0, target - margin),
      behavior: running ? 'smooth' : 'auto',
    });
  }
}
