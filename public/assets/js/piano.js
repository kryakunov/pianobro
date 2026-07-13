import { isBlackKey, midiToName, REVERSE_KEYBOARD_MAP } from './notes.js';

const WHITE_KEY_WIDTH = 24;
const BLACK_KEY_WIDTH = 14;
const KEY_HEIGHT = 170;

export class PianoKeyboard {
  constructor(container, startMidi = 21, endMidi = 108) {
    this.container = container;
    this.startMidi = startMidi;
    this.endMidi = endMidi;
    this.keys = new Map();
    this.whiteMidis = [];
    this.onNoteOn = null;
    this.onNoteOff = null;
    this._build();
  }

  _build() {
    this.container.innerHTML = '';
    this.whiteMidis = [];

    for (let midi = this.startMidi; midi <= this.endMidi; midi++) {
      if (!isBlackKey(midi)) {
        this.whiteMidis.push(midi);
      }
    }

    const whitesLayer = document.createElement('div');
    whitesLayer.className = 'piano__whites';

    const blacksLayer = document.createElement('div');
    blacksLayer.className = 'piano__blacks';

    this.whiteMidis.forEach((midi) => {
      const el = this._createKey(midi, 'white');
      whitesLayer.appendChild(el);
      this.keys.set(midi, el);
    });

    for (let midi = this.startMidi; midi <= this.endMidi; midi++) {
      if (!isBlackKey(midi)) continue;

      const el = this._createKey(midi, 'black');
      blacksLayer.appendChild(el);
      this.keys.set(midi, el);
    }

    this.container.appendChild(whitesLayer);
    this.container.appendChild(blacksLayer);
    this.container.style.height = `${KEY_HEIGHT}px`;
    this._whitesLayer = whitesLayer;
    this._blacksLayer = blacksLayer;

    requestAnimationFrame(() => {
      this._positionBlackKeys();
      this.onLayout?.();
    });
  }

  _positionBlackKeys() {
    const ratios = { 1: 0.66, 3: 0.66, 6: 0.66, 8: 0.66, 10: 0.66 };

    for (let midi = this.startMidi; midi <= this.endMidi; midi++) {
      if (!isBlackKey(midi)) continue;

      const el = this.keys.get(midi);
      const whiteEl = this.keys.get(midi - 1);
      if (!el || !whiteEl) continue;

      const ratio = ratios[midi % 12] ?? 0.66;
      const left = whiteEl.offsetLeft + whiteEl.offsetWidth * ratio - BLACK_KEY_WIDTH / 2;
      el.style.left = `${left}px`;
    }

    const width = this._whitesLayer.offsetWidth;
    this._blacksLayer.style.width = `${width}px`;
    this.container.style.width = `${width}px`;
  }

  _createKey(midi, type) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `piano-key piano-key--${type}`;
    btn.dataset.midi = String(midi);
    btn.setAttribute('aria-label', midiToName(midi));

    if (type === 'white') {
      btn.style.height = `${KEY_HEIGHT}px`;
    } else {
      btn.style.width = `${BLACK_KEY_WIDTH}px`;
      btn.style.height = `${Math.round(KEY_HEIGHT * 0.6)}px`;
    }

    const name = midiToName(midi);

    const label = document.createElement('span');
    label.className = 'piano-key__name';
    label.textContent = name;
    btn.appendChild(label);

    const pcHint = REVERSE_KEYBOARD_MAP[midi];
    if (pcHint) {
      const hint = document.createElement('span');
      hint.className = 'piano-key__pc-hint';
      hint.textContent = pcHint;
      btn.appendChild(hint);
    }

    btn.addEventListener('mousedown', (e) => {
      e.preventDefault();
      this._triggerNoteOn(midi);
    });
    btn.addEventListener('mouseup', () => this._triggerNoteOff(midi));
    btn.addEventListener('mouseleave', () => this._triggerNoteOff(midi));

    return btn;
  }

  _triggerNoteOn(midi) {
    this.pressKey(midi);
    this.onNoteOn?.(midi);
  }

  _triggerNoteOff(midi) {
    this.releaseKey(midi);
    this.onNoteOff?.(midi);
  }

  pressKey(midi) {
    this.keys.get(midi)?.classList.add('piano-key--pressed');
  }

  releaseKey(midi) {
    this.keys.get(midi)?.classList.remove('piano-key--pressed');
  }

  setTarget(midi) {
    this.setTargets(midi === null ? [] : [midi]);
  }

  setTargets(midis, noteMeta = []) {
    this.clearStates(['target', 'target-left', 'target-right']);
    if (!midis?.length) return;

    const metaByMidi = new Map(noteMeta.map((n) => [n.midi, n]));

    midis.forEach((midi) => {
      const el = this.keys.get(midi);
      if (!el) return;
      const hand = metaByMidi.get(midi)?.hand ?? 'right';
      el.classList.add(hand === 'left' ? 'piano-key--target-left' : 'piano-key--target-right');
      if (!hand || hand === 'right') {
        el.classList.add('piano-key--target');
      }
    });
  }

  flashCorrect(midi) {
    const el = this.keys.get(midi);
    if (!el) return;
    el.classList.add('piano-key--correct');
    setTimeout(() => el.classList.remove('piano-key--correct'), 400);
  }

  flashWrong(midi) {
    const el = this.keys.get(midi);
    if (!el) return;
    el.classList.add('piano-key--wrong');
    setTimeout(() => el.classList.remove('piano-key--wrong'), 500);
  }

  clearStates(states = ['target', 'target-left', 'target-right', 'correct', 'wrong', 'pressed']) {
    const map = {
      target: 'piano-key--target',
      'target-left': 'piano-key--target-left',
      'target-right': 'piano-key--target-right',
      correct: 'piano-key--correct',
      wrong: 'piano-key--wrong',
      pressed: 'piano-key--pressed',
    };
    for (const el of this.keys.values()) {
      for (const s of states) {
        el.classList.remove(map[s]);
      }
    }
  }
}
