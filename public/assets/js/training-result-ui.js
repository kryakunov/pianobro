export function escapeTrainingHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** @param {{ title: HTMLElement|null, hint: HTMLElement|null, tags: HTMLElement|null }} elements */
export function renderTrainingResultWeakNotes(elements, weakNotes = []) {
  const { title, hint, tags } = elements;
  if (!title || !hint || !tags) {
    return;
  }

  if (!weakNotes.length) {
    title.textContent = 'Явных «слабых» нот пока не видно';
    hint.textContent = 'Продолжайте тренироваться — когда появятся закономерности, мы их покажем.';
    tags.innerHTML = '';
    return;
  }

  title.textContent = 'Сложнее всего давались';
  hint.textContent = 'PianoBro чаще будет повторять эти ноты в тренировках:';
  tags.innerHTML = weakNotes
    .map((note) => {
      const suffix = note.count > 1
        ? `<span class="modal-weak-notes__count">×${note.count}</span>`
        : '';
      return `<span class="weak-notes-offer__tag">${escapeTrainingHtml(note.name)}${suffix}</span>`;
    })
    .join('');
}
