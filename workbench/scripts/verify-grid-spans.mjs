import { openPage, checker, until } from './lib/cdp.mjs';

/*
 * A column span, measured against the grid it is drawn in
 * (/previews/forms-grid-spans).
 *
 * This is the one thing about a span that PHP cannot see. A span wider than its
 * grid does not clip and does not warn — **CSS Grid adds the missing track** —
 * so the markup is exactly what the test asserted, the page still renders, and
 * every other field on the row is squeezed into what is left. The only witness
 * is the computed `grid-template-columns` of the grid itself, which is what this
 * counts.
 *
 * Measured here before the fix landed, all three of them silent:
 *
 *   700px  Section columns(2)  → 2 tracks (declared one until `md`), 494 / 510
 *   700px  Grid    columns(3)  → 2 tracks (declared one until `md`)
 *   1400px a columnSpan(3) field in a 3-column grid → 369px, the width of its
 *          one-column neighbour: the forms wrapper's own span map stopped at two
 *          columns and emitted no class at all for 3 or 4
 *
 * Three widths, because the answer differs at each and only the middle one is
 * ever looked at by hand: a phone (one column everywhere), the `sm`/`md` window
 * where the two ladders disagree, and a desktop where every column exists.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-grid-spans.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${origin}/previews/forms-grid-spans`;

const { check, finish } = checker();

/**
 * What each layout declares, and what that means at each width.
 *
 * A `Section` and a `Grid` resolve through `ResponsiveGrid::cols()` — one column
 * until `md`, then the declared count. A `Fieldset` climbs the field ladder —
 * two from `sm`, three from `md`, four from `lg` — because a field still reads
 * at half a phone's width where a card does not.
 */
const LAYOUTS = {
  two: { fields: ['data.two_a', 'data.two_b'], tracks: { 700: 1, 900: 2, 1400: 2 } },
  three: { fields: ['data.three_a', 'data.three_b', 'data.three_c'], tracks: { 700: 1, 900: 3, 1400: 3 } },
  four: { fields: ['data.four_a', 'data.four_b', 'data.four_c', 'data.four_d'], tracks: { 700: 2, 900: 3, 1400: 4 } },
};

const READ = `JSON.stringify((() => {
  const out = {};
  const groups = ${JSON.stringify(Object.fromEntries(Object.entries(LAYOUTS).map(([k, v]) => [k, v.fields])))};

  for (const name of Object.keys(groups)) {
    const els = groups[name].map((f) => document.querySelector('[data-field="' + f + '"]'));
    const grid = els[0] ? els[0].parentElement : null;

    out[name] = {
      // The assertion. A declared count that does not match this is a grid that
      // grew tracks nobody asked for.
      tracks: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').length : null,
      gridWidth: grid ? Math.round(grid.getBoundingClientRect().width) : null,
      widths: els.map((el) => (el ? Math.round(el.getBoundingClientRect().width) : null)),
      spans: els.map((el) => (el ? (el.className.match(/[a-z0-9:]*col-span-[a-z0-9]+/g) || []).join(' ') : null)),
    };
  }

  return out;
})())`;

let shotDir;
const consoleErrors = [];
const badResponses = [];

try {
  for (const width of [700, 900, 1400]) {
    const page = await openPage({ url, shotPrefix: `grid-spans-${width}`, width, height: 1200, settle: 2000 });

    shotDir = page.shotDir;

    try {
      // `eval_` returns the value itself, not its text: comparing against 'true'
      // is a condition that is never met, so the wait becomes a full timeout
      // that then carries on as if it had succeeded.
      await until(() => page.eval_(`!! document.querySelector('[data-field="data.four_d"]')`));

      const read = JSON.parse(await page.eval_(READ));

      for (const [name, layout] of Object.entries(LAYOUTS)) {
        const expected = layout.tracks[width];
        const got = read[name];

        check(`${width}px · the ${name}-column layout draws ${expected} track(s)`,
          got.tracks === expected, JSON.stringify(got));

        // The widest field must fill the grid exactly, never overflow it: a tile
        // wider than its grid is the shape that adds a track, and a tile that
        // silently lost its span is the shape that renders one column wide.
        const widest = Math.max(...got.widths);

        check(`${width}px · …and the widest field fits it exactly`,
          widest <= got.gridWidth + 1, JSON.stringify({ widest, gridWidth: got.gridWidth, widths: got.widths }));
      }

      // The three-column grid's `columnSpan(3)` field is the regression that was
      // invisible: it rendered as wide as its one-column neighbour.
      if (width === 1400) {
        const three = read.three;

        check('a columnSpan(3) field really is three columns wide, not one',
          three.widths[2] > three.widths[0] * 2.5,
          JSON.stringify({ span3: three.widths[2], plain: three.widths[0] }));

        const four = read.four;

        check('…and a columnSpan(4) field fills a four-column grid',
          four.widths[3] >= four.gridWidth - 1,
          JSON.stringify({ span4: four.widths[3], gridWidth: four.gridWidth }));
      }

      await page.shot(`${width}`);

      consoleErrors.push(...page.consoleErrors);
      badResponses.push(...page.badResponses);
    } finally {
      await page.close();
    }
  }
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
}
