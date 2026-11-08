import { openPage, checker } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for where an inline cell's validation message lands
 * (/previews/table-editable-errors).
 *
 * The message is out of the flow, so a failing rule moves neither the input
 * nor the column. Inside the cell it was clipped: the table scrolls sideways in
 * an `overflow-x: auto` wrapper, which clips vertically as well, and on the last
 * row the message was in the DOM, shown, and invisible. It is teleported to
 * <body> now and pinned under the input by the floating primitive. Only a
 * browser can say what is clipped.
 *
 * Asserted on the first and the last row:
 *   - the message shows, under the input, as wide as it
 *   - nothing covers or clips it: the point at its centre is the message
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

  const message = window.Alpine.$data(cell).$refs.error;
  const m = message.getBoundingClientRect();
  const i = input.getBoundingClientRect();
  const hit = document.elementFromPoint(m.left + m.width / 2, m.top + m.height / 2);

  return JSON.stringify({
    found: true,
    shown: getComputedStyle(message).display !== 'none' && message.textContent.trim() !== '',
    teleported: ! cell.contains(message),
    below: m.top >= i.bottom - 0.5,
    wide: Math.abs(m.width - i.width) <= 1,
    visible: hit === message || message.contains(hit),
    moved: Math.abs(i.top - before) > 0.5,
  });
})()`).then(JSON.parse);

try {
  for (const which of ['first', 'last']) {
    const r = await fail(which);
    await shot(`${which}-row`);
    const d = JSON.stringify(r);

    check(`the ${which} row has an editable cell`, r.found === true, d);
    check('…its message shows, teleported out of the table', r.shown === true && r.teleported === true, d);
    check('…under the input, as wide as it', r.below === true && r.wide === true, d);
    check('…and nothing clips or covers it', r.visible === true, d);
    check('…and the input did not move', r.moved === false, d);
  }
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
