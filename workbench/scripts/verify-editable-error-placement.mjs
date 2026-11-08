import { openPage, checker } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for where an inline cell's validation message lands
 * (/previews/table-editable-errors).
 *
 * The message is laid out of the flow, right under the input, so a failing
 * rule moves neither the input nor the column. Out of the flow, though, it can
 * fall outside the box that clips it: the table scrolls sideways in an
 * `overflow-x: auto` wrapper, which computes `overflow-y` to `auto` as well, and
 * on the last row a message under the input was simply cut off — present in the
 * DOM, `x-show` true, invisible. `placeError()` in wireEditableCell moves it
 * above the input there. Only a browser can say what is clipped.
 *
 * Asserted per row:
 *   - the message shows, and lies wholly inside the clipping box and the window
 *   - on the first row it lies under the input; on the last, where below is
 *     clipped, above it
 *   - the input itself did not move when the message appeared
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-editable-error-placement.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-errors`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'editable-error-placement', width: 1400, height: 1000, settle: 4000,
});

const { check, finish } = checker();

const fail = (which) => eval_(`(async () => {
  const cells = Array.from(document.querySelectorAll('[data-testid="table-editable-email"]'))
    .filter((c) => c.offsetParent !== null);
  const cell = ${which === 'first' ? 'cells[0]' : 'cells[cells.length - 1]'};
  if (! cell) return JSON.stringify({ found: false });

  const input = cell.querySelector('input');
  const before = input.getBoundingClientRect().top;

  await window.Alpine.$data(cell).commit('not a number');
  await new Promise((r) => setTimeout(r, 1500));

  const message = cell.querySelector('[data-testid="table-editable-error"]');
  const m = message.getBoundingClientRect();
  const i = input.getBoundingClientRect();

  let clip = cell.parentElement;
  while (clip && clip !== document.body) {
    const s = getComputedStyle(clip);
    if (s.overflowY !== 'visible' || s.overflowX !== 'visible') break;
    clip = clip.parentElement;
  }
  const box = clip && clip !== document.body ? clip.getBoundingClientRect() : { top: 0, bottom: innerHeight };

  return JSON.stringify({
    found: true,
    shown: getComputedStyle(message).display !== 'none' && message.textContent.trim() !== '',
    inside: m.top >= Math.max(box.top, 0) - 0.5 && m.bottom <= Math.min(box.bottom, innerHeight) + 0.5,
    below: m.top >= i.bottom - 0.5,
    above: m.bottom <= i.top + 0.5,
    moved: Math.abs(i.top - before) > 0.5,
  });
})()`).then(JSON.parse);

try {
  const first = await fail('first');
  await shot('01-first-row');

  check('the first row has an editable cell', first.found === true, JSON.stringify(first));
  check('…its message shows', first.shown === true, JSON.stringify(first));
  check('…under the input', first.below === true, JSON.stringify(first));
  check('…inside the clipping box', first.inside === true, JSON.stringify(first));
  check('…and the input did not move', first.moved === false, JSON.stringify(first));

  const last = await fail('last');
  await shot('02-last-row');

  check('the last row has an editable cell', last.found === true, JSON.stringify(last));
  check('…its message shows', last.shown === true, JSON.stringify(last));
  check('…inside the clipping box, not cut off below it', last.inside === true, JSON.stringify(last));
  check('…above the input, where below would be clipped', last.above === true, JSON.stringify(last));
  check('…and the input did not move', last.moved === false, JSON.stringify(last));
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
