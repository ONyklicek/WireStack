import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for the repeater's table layout (/previews/forms-repeater-table).
 *
 * The layout claim is that it is the *same* repeater — same state paths, same
 * add/remove/reorder endpoints — arranged as rows under one header. So the
 * checks are: the header names each field exactly once, the per-cell label is
 * gone, every row's inputs still bind to their own item path, and add/remove
 * survive the Livewire roundtrip.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-repeater-table.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/forms-repeater-table`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'repeater-table' });

const { check, finish } = checker();

try {
  await eval_(`
    window.table = () => document.querySelector('table');
    // The layout adds narrow reorder/remove columns whose heading is sr-only
    // text; the *field* headings are the ones that are not.
    window.headings = () => [...table().querySelectorAll('thead th')]
      .filter((th) => ! th.querySelector('.sr-only'))
      .map((th) => th.textContent.trim()).filter(Boolean);
    window.allHeadings = () => [...table().querySelectorAll('thead th')]
      .map((th) => th.textContent.trim()).filter(Boolean);
    window.bodyRows = () => [...table().querySelectorAll('tbody tr')];
    window.pathsOf = (row) => [...row.querySelectorAll('input')]
      .map((i) => i.getAttribute('wire:model') ?? i.getAttribute('wire:model.live') ?? '')
      .filter(Boolean);
    window.addBtn = () => document.querySelector('[data-testid="form-repeater-lines-add"]');
    window.removeBtn = (i) => document.querySelector('[data-testid="form-repeater-lines-remove-' + i + '"]');
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && !! table()`);
  check('the repeater renders as a table', booted);
  await shot('01-initial');

  // ── Headed once, not per row ──────────────────────────────────────────
  const headings = await eval_(`headings().join('|')`);
  check('each schema field heads its own column', headings === 'Description|Qty|Amount', headings);

  const allHeadings = await eval_(`allHeadings().join('|')`);
  check('reorder and remove get their own labelled columns',
    allHeadings === 'Reorder|Description|Qty|Amount|Remove', allHeadings);

  const labelCount = await eval_(`(table().textContent.match(/Description/g) ?? []).length`);
  check('the field label appears once, not on every row', labelCount === 1, `occurrences=${labelCount}`);

  // ── Rows still bind to their own item path ────────────────────────────
  const rowCount = await eval_(`bodyRows().length`);
  check('every seeded item is a row', rowCount === 2, `rows=${rowCount}`);

  const firstPaths = await eval_(`pathsOf(bodyRows()[0]).join('|')`);
  const secondPaths = await eval_(`pathsOf(bodyRows()[1]).join('|')`);
  check('row 0 binds to item 0',
    firstPaths === 'lines.0.description|lines.0.quantity|lines.0.amount', firstPaths);
  check('row 1 binds to item 1',
    secondPaths === 'lines.1.description|lines.1.quantity|lines.1.amount', secondPaths);

  // ── Reorder handle present (reorderable()) ────────────────────────────
  const handles = await eval_(`document.querySelectorAll('[data-testid^="form-repeater-lines-reorder-"]').length`);
  check('each row carries a reorder handle', handles === 2, `handles=${handles}`);

  // ── Add goes through the shared endpoint ──────────────────────────────
  await eval_(`addBtn().click()`);
  await sleep(1600);
  const afterAdd = await eval_(`bodyRows().length`);
  check('the add button appends a row', afterAdd === 3, `rows=${afterAdd}`);

  const newRowPaths = await eval_(`pathsOf(bodyRows()[2]).join('|')`);
  check('the appended row binds to the new item path',
    newRowPaths === 'lines.2.description|lines.2.quantity|lines.2.amount', newRowPaths);

  const headingsAfterAdd = await eval_(`headings().join('|')`);
  check('adding a row does not repeat the header', headingsAfterAdd === 'Description|Qty|Amount', headingsAfterAdd);
  await shot('02-after-add');

  // ── Typing into a row reaches that row's state ────────────────────────
  await eval_(`(() => {
    const input = bodyRows()[2].querySelector('input');
    input.value = 'Driver-typed line';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  })()`);
  await sleep(1400);
  const typedValue = await eval_(`bodyRows()[2].querySelector('input').value`);
  check('typing into the appended row keeps its value', typedValue === 'Driver-typed line', `value=${typedValue}`);

  // ── Remove goes through the shared endpoint ───────────────────────────
  await eval_(`removeBtn(0).click()`);
  await sleep(1600);
  const afterRemove = await eval_(`bodyRows().length`);
  check('the remove button drops a row', afterRemove === 2, `rows=${afterRemove}`);

  const shifted = await eval_(`bodyRows()[0].querySelector('input').value`);
  check('removing the first row shifts the rest up', shifted === 'Hosting', `first=${shifted}`);
  await shot('03-after-remove');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
