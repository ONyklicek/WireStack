import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for where an inline cell's validation message lands
 * (/previews/table-editable-errors).
 *
 * The message is plain text under the input: as wide as the input, wrapping
 * inside it, never widening the column. The row grows to hold it — and nothing
 * in the row moves when it does. A cell is aligned to the middle of its row, so
 * a message simply added to its cell would shift every other cell's content
 * down by half its height; it is laid out of the flow instead and the row makes
 * the same room in every cell (wire-table `tables.partials.cell-error-styles`,
 * keyed on the line count `wireEditableCell` measures).
 *
 * It was a floating panel before — teleported to <body>, on a surface of its
 * own — because inside the cell the table's scroll box clipped it on the last
 * row. A row that grows is not clipped: the scroll box grows with it. Only a
 * browser can say what is clipped and what moved.
 *
 * Asserted on the first and the last row:
 *   - the message shows in its cell, as text: no surface, no shadow
 *   - under the input and exactly as wide as it, also after a re-render
 *   - nothing covers or clips it: the point at its centre is the message
 *   - the row grew by the message, and the column did not widen
 *   - neither the input, nor the next input of the row, nor its plain cell moved
 *   - with the message gone the row is as tall as it was
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-editable-error-placement.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-errors`;

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'editable-error-placement', width: 1400, height: 1000, settle: 4000,
});

const { check, finish } = checker();

const fail = (which) => eval_(`(async () => {
  const cells = Array.from(document.querySelectorAll('[data-testid="table-editable-email"]'))
    .filter((c) => c.offsetParent !== null);
  const cell = ${which === 'first' ? 'cells[0]' : 'cells[cells.length - 1]'};
  if (! cell) return JSON.stringify({ found: false });

  const row = cell.closest('tr');
  const input = cell.querySelector('input');
  const neighbour = row.querySelector('[data-testid="table-editable-bio"] input');
  const plain = row.querySelector('[data-testid="table-cell-name"]').firstElementChild
    ?? row.querySelector('[data-testid="table-cell-name"]');
  const tops = () => [input, neighbour, plain].map((el) => el.getBoundingClientRect().top - row.getBoundingClientRect().top);
  const before = { tops: tops(), height: row.getBoundingClientRect().height, width: input.getBoundingClientRect().width };

  await window.Alpine.$data(cell).commit('not a number');
  await new Promise((r) => setTimeout(r, 1500));

  const message = window.Alpine.$data(cell).$refs.error;
  const first = message.getBoundingClientRect();

  // A re-render morphs the row; the message has to come out of it the same.
  const host = cell.closest('[wire\\\\:id]');
  await window.Livewire.find(host.getAttribute('wire:id')).$refresh();
  await new Promise((r) => setTimeout(r, 800));

  const m = message.getBoundingClientRect();
  const i = input.getBoundingClientRect();
  const style = getComputedStyle(message);
  const hit = document.elementFromPoint(m.left + m.width / 2, m.top + m.height / 2);
  const after = { tops: tops(), height: row.getBoundingClientRect().height, width: i.width };

  // Read now: the style object is live, and the message is about to go.
  const result = {
    found: true,
    shown: style.display !== 'none' && message.textContent.trim() !== '',
    inCell: cell.contains(message),
    plain: style.boxShadow === 'none' && ['rgba(0, 0, 0, 0)', 'transparent'].includes(style.backgroundColor),
    below: m.top >= i.bottom - 0.5 && m.top - i.bottom <= 8,
    wide: Math.abs(m.left - i.left) <= 1 && Math.abs(m.width - i.width) <= 1 && Math.abs(first.width - i.width) <= 1,
    widths: [Math.round(first.width), Math.round(m.width), Math.round(i.width)],
    textLines: Math.round(m.height / parseFloat(style.lineHeight)),
    lines: Number(cell.dataset.errorLines ?? 0),
    visible: hit === message || message.contains(hit),
    grew: Math.round(after.height - before.height),
    room: Math.round(m.height + parseFloat(style.marginTop)),
    widened: Math.abs(after.width - before.width) > 0.5,
    shifts: after.tops.map((top, k) => Math.round((top - before.tops[k]) * 10) / 10),
  };

  window.Alpine.$data(cell).error = null;
  await new Promise((r) => setTimeout(r, 300));

  return JSON.stringify({ ...result, restored: Math.abs(row.getBoundingClientRect().height - before.height) <= 0.5 });
})()`).then(JSON.parse);

try {
  // Wide, then narrow, where the same message wraps into several lines inside
  // a narrow input and the row has to make room for every one of them.
  for (const [which, width] of [['first', 1400], ['last', 1400], ['first', 520], ['last', 520]]) {
    await page('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: false });
    await sleep(400);

    const r = await fail(which);
    await shot(`${which}-row-${width}`);
    const d = JSON.stringify(r);

    check(`[${width}px] the ${which} row has an editable cell`, r.found === true, d);
    check('…its message shows in the cell, as plain text', r.shown === true && r.inCell === true && r.plain === true, d);
    check('…under the input, exactly as wide as it', r.below === true && r.wide === true, d);
    check(width > 1000 ? '…wrapping inside it, every line counted' : '…over more lines in a narrow input, every one counted',
      r.lines === r.textLines && (width > 1000 ? r.lines >= 1 : r.lines > 2), d);
    check('…and nothing clips or covers it', r.visible === true, d);
    check('…the row grew by the message, the column did not widen',
      Math.abs(r.grew - r.room) <= 1 && r.grew > 0 && r.widened === false, d);
    check('…and neither the input, the next input of the row nor its plain cell moved',
      r.shifts.every((shift) => Math.abs(shift) <= 0.5), d);
    check('…with the message gone the row is as tall as it was', r.restored === true, d);
  }
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
