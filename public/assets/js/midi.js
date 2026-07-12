const NOTE_ON = 0x90;
const NOTE_OFF = 0x80;

const DEVICE_KEYWORDS = [
  'piano', 'keyboard', 'key', 'yamaha', 'roland', 'casio', 'kawai', 'korg', 'nord', 'midi',
];

const DEVICE_AVOID = ['through', 'virtual', 'session', 'network', 'iac'];

export class MidiInput {
  constructor() {
    this.access = null;
    this.boundInputs = new Map();
    this.selectedInputId = null;
    this.transpose = 0;
    this.onNoteOn = null;
    this.onNoteOff = null;
    this.onActivity = null;
    this.onInputsChanged = null;
    this._runningStatus = 0;
  }

  get isSupported() {
    return 'requestMIDIAccess' in navigator;
  }

  get isConnected() {
    return this.boundInputs.size > 0;
  }

  get deviceName() {
    if (this.selectedInputId) {
      return this.boundInputs.get(this.selectedInputId)?.name ?? null;
    }
    const names = [...this.boundInputs.values()].map((i) => i.name);
    return names.length ? names.join(', ') : null;
  }

  async connect() {
    if (!this.isSupported) {
      throw new Error('Web MIDI API не поддерживается. Используйте Chrome или Edge.');
    }

    this.access = await navigator.requestMIDIAccess({ sysex: false });
    this.access.onstatechange = () => {
      this._refreshInputs();
      this.onInputsChanged?.(this.listInputs());
    };

    const inputs = [...this.access.inputs.values()];
    if (inputs.length === 0) {
      throw new Error('MIDI-устройство не найдено. Подключите пианино по USB.');
    }

    if (!this.selectedInputId) {
      this.selectedInputId = this._pickBestInput(inputs)?.id ?? inputs[0].id;
    }

    this._refreshInputs();
    return this.boundInputs.get(this.selectedInputId)?.name ?? inputs[0].name;
  }

  setTranspose(semitones) {
    this.transpose = semitones;
  }

  selectInput(id) {
    this.selectedInputId = id || null;
    if (this.access) {
      this._refreshInputs();
    }
  }

  listInputs() {
    if (!this.access) return [];
    return [...this.access.inputs.values()].map((i) => ({
      id: i.id,
      name: i.name,
      manufacturer: i.manufacturer ?? '',
      selected: i.id === this.selectedInputId,
    }));
  }

  _pickBestInput(inputs) {
    const scored = inputs.map((input) => {
      const name = input.name.toLowerCase();
      let score = 0;
      for (const kw of DEVICE_KEYWORDS) {
        if (name.includes(kw)) score += 10;
      }
      for (const avoid of DEVICE_AVOID) {
        if (name.includes(avoid)) score -= 8;
      }
      return { input, score };
    });
    scored.sort((a, b) => b.score - a.score);
    return scored[0]?.input;
  }

  _refreshInputs() {
    for (const input of this.boundInputs.values()) {
      input.onmidimessage = null;
    }
    this.boundInputs.clear();

    if (!this.access) return;

    const inputs = [...this.access.inputs.values()];
    if (inputs.length === 0) return;

    if (this.selectedInputId && !inputs.some((i) => i.id === this.selectedInputId)) {
      this.selectedInputId = this._pickBestInput(inputs)?.id ?? inputs[0].id;
    }

    for (const input of inputs) {
      if (this.selectedInputId && input.id !== this.selectedInputId) continue;
      input.onmidimessage = (e) => this._handleMessage(e);
      this.boundInputs.set(input.id, input);
    }
  }

  _handleMessage(event) {
    const data = event.data;
    let offset = 0;

    while (offset < data.length) {
      let status = data[offset];

      if (status >= 0x80 && status <= 0xef) {
        this._runningStatus = status;
        offset++;
      } else if (status < 0x80) {
        if (!this._runningStatus) {
          offset++;
          continue;
        }
        status = this._runningStatus;
      } else {
        offset = this._skipSystemMessage(data, offset);
        continue;
      }

      const command = status & 0xf0;

      if (command === NOTE_ON || command === NOTE_OFF) {
        if (offset + 1 >= data.length) break;
        const note = data[offset];
        const velocity = data[offset + 1];
        offset += 2;
        this._processNote(command, note, velocity);
        continue;
      }

      if (command === 0xc0 || command === 0xd0) {
        offset += 1;
        continue;
      }

      if (command >= 0x80 && command <= 0xe0) {
        offset += 2;
        continue;
      }

      offset++;
    }
  }

  _skipSystemMessage(data, offset) {
    const status = data[offset];
    if (status === 0xf0) {
      while (offset < data.length && data[offset] !== 0xf7) offset++;
      return Math.min(offset + 1, data.length);
    }
    const lengths = { 0xf1: 2, 0xf2: 3, 0xf3: 2 };
    return offset + (lengths[status] ?? 1);
  }

  _processNote(command, note, velocity) {
    if (note < 0 || note > 127) return;

    const isNoteOn = command === NOTE_ON && velocity > 0;
    const isNoteOff = command === NOTE_OFF || (command === NOTE_ON && velocity === 0);

    const normalized = note - this.transpose;
    if (normalized < 0 || normalized > 127) return;

    if (isNoteOn) {
      this.onActivity?.({ note: normalized, rawNote: note, velocity, type: 'on' });
      this.onNoteOn?.(normalized, velocity);
    } else if (isNoteOff) {
      this.onActivity?.({ note: normalized, rawNote: note, velocity: 0, type: 'off' });
      this.onNoteOff?.(normalized);
    }
  }
}
