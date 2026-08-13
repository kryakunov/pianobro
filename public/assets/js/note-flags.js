/** Filled eighth/sixteenth flags attached to the stem tip (standard engraving shape). */
export function renderNoteFlags(stemX, stemY2, stemUp, flagCount, scale, flagStep = 5.5 * scale) {
  if (!flagCount) return '';

  let flags = '';
  for (let i = 0; i < flagCount; i++) {
    const attachY = stemUp ? stemY2 + i * flagStep : stemY2 - i * flagStep;
    flags += renderSingleFlag(stemX, attachY, stemUp, scale);
  }
  return flags;
}

function renderSingleFlag(stemX, y, stemUp, scale) {
  const s = scale;

  if (stemUp) {
    return `<path d="M ${stemX} ${y}
      C ${stemX + 0.6 * s} ${y - 1.2 * s}, ${stemX + 9.5 * s} ${y - 2 * s}, ${stemX + 11 * s} ${y - 9.5 * s}
      C ${stemX + 8.5 * s} ${y - 12 * s}, ${stemX + 3 * s} ${y - 8.5 * s}, ${stemX + 0.5 * s} ${y - 4.5 * s}
      C ${stemX + 0.1 * s} ${y - 2 * s}, ${stemX} ${y - 0.8 * s}, ${stemX} ${y}
      Z" class="staff-note__flag"/>`;
  }

  return `<path d="M ${stemX} ${y}
    C ${stemX - 0.6 * s} ${y + 1.2 * s}, ${stemX - 9.5 * s} ${y + 2 * s}, ${stemX - 11 * s} ${y + 9.5 * s}
    C ${stemX - 8.5 * s} ${y + 12 * s}, ${stemX - 3 * s} ${y + 8.5 * s}, ${stemX - 0.5 * s} ${y + 4.5 * s}
    C ${stemX - 0.1 * s} ${y + 2 * s}, ${stemX} ${y + 0.8 * s}, ${stemX} ${y}
    Z" class="staff-note__flag"/>`;
}
