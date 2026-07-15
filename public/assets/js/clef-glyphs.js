const VEXFLOW_LINE_DISTANCE = 10;
const CLEF_POINT = 39;
const FONT_RESOLUTION = 1000;

const OutlineCode = {
  MOVE: 0,
  LINE: 1,
  QUADRATIC: 2,
  BEZIER: 3,
};

const GLYPHS = {
  gClef: {
    x_max: 671,
    outline: 'm 541 598 b 550 625 539 615 541 616 b 824 1174 706 770 824 953 b 730 1509 824 1299 789 1423 b 655 1581 708 1541 671 1581 b 562 1512 635 1581 590 1544 b 420 1064 455 1394 420 1214 b 441 828 420 981 431 887 b 428 793 444 811 445 808 b 0 125 220 622 0 416 b 524 -363 0 -125 171 -363 b 624 -354 557 -363 595 -360 b 645 -367 639 -351 642 -350 b 684 -657 662 -464 684 -589 b 455 -896 684 -870 540 -896 b 340 -854 377 -896 340 -873 b 386 -829 340 -844 353 -840 b 482 -694 431 -816 482 -778 b 344 -547 482 -615 432 -547 b 190 -713 248 -547 190 -624 b 464 -948 190 -806 246 -948 b 747 -660 560 -948 747 -904 b 706 -351 747 -577 721 -441 b 724 -327 703 -334 704 -336 b 966 16 870 -269 966 -147 b 619 363 966 200 831 363 b 577 389 582 363 582 363 z m 677 1358 b 763 1240 724 1358 763 1319 b 513 851 763 1080 626 950 b 494 863 503 842 497 844 b 485 995 488 900 485 949 b 677 1358 485 1220 589 1358 z m 520 377 b 498 343 524 350 524 351 b 289 63 372 300 289 186 b 455 -192 289 -66 357 -158 b 494 -200 467 -196 484 -200 b 511 -184 505 -200 511 -193 b 490 -166 511 -174 500 -170 b 386 -12 429 -140 386 -78 b 530 157 386 71 442 132 b 559 145 553 163 556 161 l 631 -284 b 611 -304 634 -300 632 -300 b 530 -311 588 -308 559 -311 b 115 29 278 -311 115 -171 b 249 363 115 114 130 228 b 469 567 336 459 402 513 b 490 562 484 579 487 577 z m 619 148 b 635 168 616 166 618 170 b 848 -66 752 158 848 60 b 713 -271 848 -157 793 -230 b 690 -262 696 -279 693 -279 z',
  },
  fClef: {
    x_max: 684,
    outline: 'm 363 377 b 0 56 112 377 0 194 b 177 -158 0 -59 60 -158 b 330 -6 268 -158 330 -95 b 192 144 330 86 262 144 b 120 134 153 144 138 134 b 96 160 101 134 96 145 b 330 323 96 217 183 323 b 549 -53 482 323 549 173 b 14 -871 549 -455 350 -680 b -7 -897 1 -878 -7 -886 b 12 -914 -7 -906 -1 -914 b 36 -907 19 -914 27 -912 b 765 -40 390 -734 765 -478 b 363 377 765 210 612 377 z m 906 259 b 827 180 861 259 827 225 b 906 101 827 135 861 101 b 985 180 950 101 985 135 b 906 259 985 225 950 259 z m 907 -102 b 829 -180 863 -102 829 -135 b 907 -258 829 -225 863 -258 b 985 -180 952 -258 985 -225 b 907 -102 985 -135 952 -102 z',
  },
};

const CLEF_LINES = {
  treble: 3,
  bass: 1,
};

function parseOutline(str) {
  const result = [];
  const parts = str.split(' ');
  let i = 0;

  while (i < parts.length) {
    switch (parts[i++]) {
      case 'm':
        result.push(OutlineCode.MOVE, Number(parts[i++]), Number(parts[i++]));
        break;
      case 'l':
        result.push(OutlineCode.LINE, Number(parts[i++]), Number(parts[i++]));
        break;
      case 'q':
        result.push(
          OutlineCode.QUADRATIC,
          Number(parts[i++]),
          Number(parts[i++]),
          Number(parts[i++]),
          Number(parts[i++]),
        );
        break;
      case 'b':
        result.push(
          OutlineCode.BEZIER,
          Number(parts[i++]),
          Number(parts[i++]),
          Number(parts[i++]),
          Number(parts[i++]),
          Number(parts[i++]),
          Number(parts[i++]),
        );
        break;
      default:
        break;
    }
  }

  return result;
}

const OUTLINE_CACHE = new Map();
function getOutline(code) {
  if (!OUTLINE_CACHE.has(code)) {
    OUTLINE_CACHE.set(code, parseOutline(GLYPHS[code].outline));
  }
  return OUTLINE_CACHE.get(code);
}

export function clefScale(lineGap) {
  return (CLEF_POINT * 72.0) / (FONT_RESOLUTION * 100.0) * (lineGap / VEXFLOW_LINE_DISTANCE);
}

export function clefWidth(kind, lineGap) {
  const code = kind === 'treble' ? 'gClef' : 'fClef';
  return GLYPHS[code].x_max * clefScale(lineGap);
}

function staffLineY(bottomY, lineGap, lineFromTop) {
  return bottomY - (4 - lineFromTop) * lineGap;
}

function outlineToSvgPath(outline, originX, originY, scale) {
  const cmds = [];
  let i = 0;

  const nextX = () => (originX + outline[i++] * scale).toFixed(2);
  const nextY = () => (originY - outline[i++] * scale).toFixed(2);

  while (i < outline.length) {
    switch (outline[i++]) {
      case OutlineCode.MOVE:
        cmds.push(`M ${nextX()} ${nextY()}`);
        break;
      case OutlineCode.LINE:
        cmds.push(`L ${nextX()} ${nextY()}`);
        break;
      case OutlineCode.QUADRATIC: {
        const cx = nextX();
        const cy = nextY();
        const x = nextX();
        const y = nextY();
        cmds.push(`Q ${cx} ${cy} ${x} ${y}`);
        break;
      }
      case OutlineCode.BEZIER: {
        const x = nextX();
        const y = nextY();
        const cx1 = nextX();
        const cy1 = nextY();
        const cx2 = nextX();
        const cy2 = nextY();
        cmds.push(`C ${cx1} ${cy1} ${cx2} ${cy2} ${x} ${y}`);
        break;
      }
      default:
        break;
    }
  }

  return cmds.join(' ');
}

export function renderClef(kind, x, bottomY, lineGap) {
  const code = kind === 'treble' ? 'gClef' : 'fClef';
  const line = CLEF_LINES[kind];
  const anchorY = staffLineY(bottomY, lineGap, line);
  const scale = clefScale(lineGap);
  const path = outlineToSvgPath(getOutline(code), x, anchorY, scale);

  return path;
}

export function renderClefSymbol(kind, x, bottomY, lineGap) {
  const path = renderClef(kind, x, bottomY, lineGap);
  return `<g class="staff-clef staff-clef--${kind}"><path d="${path}" fill="currentColor"/></g>`;
}
