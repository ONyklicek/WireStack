import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for compact density, across the surfaces it has to hold on
 * (/previews/routed/users, /previews/routed/media, /previews/routed/users/create).
 *
 * Compact is three changes rather than one, and the second and third exist only
 * because measuring found the first over- and under-reaching. This driver is
 * the measurement, kept:
 *
 *   1  `--spacing` drops, and a table row goes 65px → 52px. That is the density.
 *   2  **The top bar and the icons are pinned back.** The same token drives
 *      `h-16` and `w-4`, so without the pins compact takes the shell's top bar
 *      to 44px and an icon to 10px — 8px in the media manager, where it also
 *      drags the icon-only buttons down to 11px. Pinned, all three hold.
 *   3  **Form controls are addressed by name.** `@tailwindcss/forms` writes
 *      `padding: .5rem .75rem` as a literal in its base layer, so `--spacing`
 *      cannot reach the surface where density matters most. With the rule, the
 *      fields of a create form span 550px → 446px.
 *
 * A fourth thing this found, which is not a check but a limit worth knowing:
 * **the rules live in the shell's head.** A page rendered outside
 * `wire-admin`'s layout has none of them, and pointing this driver at
 * `/previews/forms-overview` measured a feature that was not there. A custom
 * layout has to `@include('wire-core::partials.density')` the way it has to
 * carry `@wireStackScripts`.
 *
 * Each of those is a check below, and each is written as a *floor* rather than
 * an exact number: the point is that a pin holds and a row tightens, not that
 * the framework's default spacing never changes again.
 *
 * The driver flips `data-density` on the document rather than reading a config,
 * which is deliberate — that attribute is the contract both halves of the
 * feature meet at, and it is exactly what a per-person switch will do later.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-density.mjs
 */

const ORIGIN = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';

const MEASURE = `(() => {
  const all = (sel) => [...document.querySelectorAll(sel)];
  const heights = (sel) => all(sel).map((e) => Math.round(e.getBoundingClientRect().height)).filter((x) => x > 0);
  const widths = (sel) => all(sel).map((e) => Math.round(e.getBoundingClientRect().width)).filter((x) => x > 0);
  const min = (a) => (a.length ? Math.min(...a) : null);
  const med = (a) => (a.length ? a.slice().sort((x, y) => x - y)[Math.floor(a.length / 2)] : null);

  const fields = all('[data-wire="form-field"]');
  const first = fields[0];
  const last = fields[fields.length - 1];

  return {
    header: med(heights('header')),
    iconMin: min(widths('svg')),
    btnMin: min(heights('button, a[class*="inline-flex"]')),
    rowMed: med(heights('tbody tr')),
    fieldSpan: (first && last)
      ? Math.round(last.getBoundingClientRect().bottom - first.getBoundingClientRect().top)
      : null,
    font: Math.round(parseFloat(getComputedStyle(document.body).fontSize)),
  };
})()`;

const SET_DENSITY = (value) => `(() => {
  document.documentElement.dataset.density = ${JSON.stringify(value)};
  return document.documentElement.dataset.density;
})()`;

const { check, finish } = checker();
let shotDir;

for (const [label, path] of [
  ['users', '/previews/routed/users'],
  ['media', '/previews/routed/media'],
  // A form *inside the shell*: the rules are emitted by the layout's head, so a
  // page that does not use the shell has none of them. `forms-overview` is such
  // a page, and pointing at it measured a feature that was not there.
  ['forms', '/previews/routed/users/create'],
]) {
  const page = await openPage({ url: `${ORIGIN}${path}`, shotPrefix: `density-${label}`, width: 1400, height: 1000 });
  shotDir = page.shotDir;

  try {
    const normal = await page.eval_(MEASURE);
    await page.shot(`${label}-1-normal`);

    await page.eval_(SET_DENSITY('compact'));
    const compact = await page.eval_(MEASURE);
    await page.shot(`${label}-2-compact`);

    // 1 — the density itself.
    if (normal.rowMed !== null) {
      check(`[${label}] rows tighten`, compact.rowMed < normal.rowMed,
        `${normal.rowMed}px → ${compact.rowMed}px`);
    }
    if (normal.fieldSpan !== null) {
      check(`[${label}] fields tighten`, compact.fieldSpan < normal.fieldSpan,
        `${normal.fieldSpan}px → ${compact.fieldSpan}px`);
    }

    // 2 — the pins. An unpinned compact shrinks these, and shrinking them is a
    // defect rather than density.
    if (normal.header !== null) {
      check(`[${label}] the top bar holds its height`, compact.header >= normal.header,
        `${normal.header}px → ${compact.header}px`);
    }
    check(`[${label}] no icon shrinks below 16px`, compact.iconMin >= 16,
      `smallest ${normal.iconMin}px → ${compact.iconMin}px`);
    check(`[${label}] no control drops below 16px`, compact.btnMin >= 16,
      `smallest ${normal.btnMin}px → ${compact.btnMin}px`);

    // The one thing compact must not touch.
    check(`[${label}] type is left alone`, compact.font === normal.font,
      `${normal.font}px → ${compact.font}px`);
  } finally {
    await page.close();
  }
}

finish({ consoleErrors: [], badResponses: [], shotDir });
