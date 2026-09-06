import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for DateRangePicker (/previews/field-date-range-picker).
 *
 * The range is composed out of two ordinary DateTimePickers, so the calendar
 * itself is already covered by the picker's own drivers. What is new here is
 * what only the pair can get wrong, and none of it is visible in the markup:
 *
 *   - a preset writes BOTH ends from one click, with the values PHP resolved —
 *     the browser is never asked what "this month" means;
 *   - the ends are entangled `.live`, so the two pickers actually redraw with
 *     what the preset wrote instead of showing the old dates until the next
 *     roundtrip;
 *   - the coupling holds after that: the end picker's lower bound is the start
 *     the preset just set, which is the bound that keeps a period from being
 *     picked backwards.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-date-range.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-date-range-picker`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'date-range' });

const { check, finish } = checker();

try {
  await eval_(`
    window.wire = () => Livewire.all()[0].$wire;
    window.state = (end) => wire().get('data.valid_' + end);
    window.preset = (index) => document.querySelector(
      '[data-testid="form-date-range-data.valid_from-preset-' + index + '"]'
    );
    window.presets = () => [...document.querySelectorAll('[data-testid^="form-date-range-"]')];
    window.trigger = (end) => document.querySelector(
      '[data-testid="form-datetime-data.valid_' + end + '-trigger"]'
    );
    window.picker = (end) => Alpine.\$data(trigger(end).closest('[x-data]'));
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !! trigger('from') && !! trigger('to')`);
  check('the preview boots with both ends', booted);
  await shot('01-initial');

  const count = await eval_(`presets().length`);
  check('the built-in periods are offered', count === 5, `presets=${count}`);

  const seeded = await eval_(`state('from') + '..' + state('to')`);
  check('both ends are filled from the record', seeded === '2026-06-01..2026-06-30', `range=${seeded}`);

  // The end cannot open before the start: the bound is the other end's value.
  const boundBefore = await eval_(`picker('to').minDay ?? '<none>'`);
  check('the end picker is bounded below by the start', boundBefore === '2026-06-01', `minDay=${boundBefore}`);
  const boundAbove = await eval_(`picker('from').maxDay ?? '<none>'`);
  check('the start picker is bounded above by the end', boundAbove === '2026-06-30', `maxDay=${boundAbove}`);

  // ── One click, both ends ──────────────────────────────────────────────
  const label = await eval_(`preset(2).textContent.trim()`);
  const expected = await eval_(`
    (() => {
      const now = new Date();
      const pad = (n) => String(n).padStart(2, '0');
      const first = new Date(now.getFullYear(), now.getMonth(), 1);
      const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      const fmt = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
      return fmt(first) + '..' + fmt(last);
    })()
  `);

  await eval_(`preset(2).click()`);
  await waitFor(`state('from') !== '2026-06-01'`);
  const after = await eval_(`state('from') + '..' + state('to')`);
  check(`the "${label}" preset writes both ends at once`, after === expected,
    `state=${after} expected=${expected}`);
  await shot('02-preset-applied');

  // `.live` is what makes the pickers redraw with what the preset wrote; a
  // deferred binding would leave both inputs showing the old dates.
  const shown = await eval_(`picker('from').value + '..' + picker('to').value`);
  check('both pickers redraw with the values the preset wrote', shown === after,
    `pickers=${shown} state=${after}`);

  // And the coupling follows the new period rather than the seeded one.
  const boundAfter = await eval_(`picker('to').minDay ?? '<none>'`);
  check('the end stays bounded by the start the preset set',
    boundAfter === after.split('..')[0], `minDay=${boundAfter}`);

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
