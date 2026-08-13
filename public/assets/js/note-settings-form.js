import {
  DEFAULT_NOTE_SETTINGS,
  DEFAULT_NOTE_SESSION_LIMIT,
  NOTE_SESSION_LIMITS,
  buildPoolFromSettings,
} from './note-trainer.js';

export function readNoteSettingsFromForm(form) {
  if (!form) return structuredClone(DEFAULT_NOTE_SETTINGS);

  const checked = (name) => form.querySelector(`[name="${name}"]`)?.checked ?? false;
  const trebleFirst = checked('treble-first');
  const trebleSecond = checked('treble-second');
  const bassSmall = checked('bass-small');
  const bassGreat = checked('bass-great');

  return {
    treble: {
      enabled: trebleFirst || trebleSecond,
      first: trebleFirst,
      second: trebleSecond,
    },
    bass: {
      enabled: bassSmall || bassGreat,
      small: bassSmall,
      great: bassGreat,
    },
    alteration: {
      sharp: checked('alt-sharp'),
      flat: checked('alt-flat'),
    },
  };
}

export function applyNoteSettingsToForm(form, settings) {
  if (!form) return;

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
}

export function readSessionLimitFromForm(form) {
  const select = form?.querySelector('[name="session-limit"]');
  const value = parseInt(select?.value ?? String(DEFAULT_NOTE_SESSION_LIMIT), 10);
  return NOTE_SESSION_LIMITS.includes(value) ? value : DEFAULT_NOTE_SESSION_LIMIT;
}

export function applySessionLimitToForm(form, limit = DEFAULT_NOTE_SESSION_LIMIT) {
  const select = form?.querySelector('[name="session-limit"]');
  if (select) select.value = String(limit);
}

function hasSelectedOctaves(settings) {
  return settings.treble.first || settings.treble.second
    || settings.bass.small || settings.bass.great;
}

export function validateNoteSettings(settings) {
  if (!hasSelectedOctaves(settings)) {
    return 'Выберите хотя бы одну октаву';
  }
  if (!buildPoolFromSettings(settings).length) {
    return 'Нет нот для выбранных настроек — включите диезы/бемоли или измените октавы';
  }
  return null;
}

export function renderNoteSettingsFormMarkup({
  formId = 'notes-settings-form',
  formClass = '',
  sessionLimit = DEFAULT_NOTE_SESSION_LIMIT,
  extraFieldsHtml = '',
  submitLabel = 'Назначить',
  submitIconId = '',
  showHint = true,
} = {}) {
  const sessionOptions = NOTE_SESSION_LIMITS.map((limit) => {
    const selected = limit === sessionLimit ? ' selected' : '';
    return `<option value="${limit}"${selected}>${limit} нот</option>`;
  }).join('');

  const formClasses = ['notes-settings', formClass].filter(Boolean).join(' ');
  const submitIcon = submitIconId
    ? `<svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#${submitIconId}"/></svg>`
    : '';

  return `
    <form class="${formClasses}" id="${formId}">
      <div class="notes-settings__grid">
        <fieldset class="settings-group settings-group--treble">
          <legend class="settings-group__head">
            <span class="settings-group__title">Скрипичный ключ</span>
          </legend>
          <div class="settings-group__options">
            <label class="settings-check">
              <input type="checkbox" name="treble-first" checked>
              <span>Первая октава</span>
            </label>
            <label class="settings-check">
              <input type="checkbox" name="treble-second">
              <span>Вторая октава</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="settings-group settings-group--bass">
          <legend class="settings-group__head">
            <span class="settings-group__title">Басовый ключ</span>
          </legend>
          <div class="settings-group__options">
            <label class="settings-check">
              <input type="checkbox" name="bass-small">
              <span>Малая октава</span>
            </label>
            <label class="settings-check">
              <input type="checkbox" name="bass-great">
              <span>Большая октава</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="settings-group settings-group--alt">
          <legend class="settings-group__head">
            <span class="settings-group__title">Знаки альтерации</span>
          </legend>
          <div class="settings-group__options">
            <label class="settings-check">
              <input type="checkbox" name="alt-sharp">
              <span class="settings-check__icon" aria-hidden="true">♯</span>
              <span>Диез</span>
            </label>
            <label class="settings-check">
              <input type="checkbox" name="alt-flat">
              <span class="settings-check__icon" aria-hidden="true">♭</span>
              <span>Бемоль</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="settings-group settings-group--session">
          <legend class="settings-group__head">
            <span class="settings-group__title">Длина сессии</span>
          </legend>
          <label class="settings-select">
            <span class="settings-select__label">Сколько нот тренировать</span>
            <select name="session-limit" class="settings-select__input">
              ${sessionOptions}
            </select>
          </label>
        </fieldset>

        ${extraFieldsHtml}
      </div>

      <div class="notes-settings__footer">
        ${showHint ? '<p class="settings-hint">Отметьте, что хотите тренировать, и назначьте задание ученику.</p>' : ''}
        <p class="settings-error" id="${formId}-error" hidden></p>
        <button type="submit" class="btn btn--primary notes-settings__submit">${submitIcon}${submitLabel}</button>
      </div>
    </form>
  `;
}
