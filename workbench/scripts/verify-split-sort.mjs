import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for SplitColumn sorting (/previews/table-split-columns).
 *
 * A split is registered under a name for the group it draws — here `identity`,
 * which is not an attribute of User. Its header is clickable because a *child*
 * (`name`) is sortable, so the click has to order by that child.
 *
 * Until the query seam asked getSortColumn(), it reused the name the header was
 * clicked under and reached SQL as `order by users.identity` — a 500 on the
 * first click. Nothing could see it: getSortColumn() had no caller, no test, and
 * SplitColumn appeared nowhere in the workbench, so there was no preview to
 * click. That is what this driver exists to hold.
 *
 * The row order is the assertion that matters; `badResponses` is what turns the
 * regression back into a hard failure rather than a table that simply does not
 * reorder.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-split-sort.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-split-columns`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'split-sort' });

const { check, finish } = checker();

const SEEDED = 4;

/** ASCII names, so JS code-unit order and SQLite's BINARY collation agree. */
const isSorted = (names) => names.every((n, i) => i === 0 || names[i - 1] <= n);

try {
  await eval_(`
    window.rowCount = () => document.querySelectorAll('tbody tr').length;
    // The split stacks its children in one wrapper; the first is the name column.
    // Read it structurally rather than by pattern: the workbench database is
    // shared with every other driver, and verify-cell-island writes into these
    // very users' email addresses, so no cell's *text* is stable across a sweep.
    // Child *nodes*, not elements: an unstyled TextColumn renders as a bare text
    // node and a styled one as a <span>, so the name half has no element to hold.
    window.splitCell = (tr) => tr.querySelector('[data-column="identity"] [class*="flex-col"]');
    window.names = () => [...document.querySelectorAll('tbody tr')]
      .map((tr) => splitCell(tr)?.firstChild?.textContent.trim() ?? '');
    window.childCount = () => splitCell(document.querySelector('tbody tr'))?.childNodes.length ?? 0;
    window.sortButton = (name) => document.querySelector('[data-testid="table-sort-' + name + '"]');
    true;
  `);

  await sleep(700);

  const booted = await eval_(`typeof Alpine !== 'undefined' && rowCount() === ${SEEDED}`);
  check(`preview renders with Alpine booted and ${SEEDED} rows`, booted,
    `rows=${await eval_('rowCount()')}`);
  await shot('01-initial');

  // ── The split renders both children in one cell ───────────────────────
  const childCount = await eval_(`childCount()`);
  check('the split cell stacks both of its children', childCount === 2, `children=${childCount}`);

  // ── The layout settings a vertical split could not express ────────────
  const layout = await eval_(`splitCell(document.querySelector('tbody tr')).className`);
  check('a named gap reaches the DOM as a literal utility', layout.includes('gap-2'), layout);
  check('an explicit alignment reaches a vertical split', layout.includes('items-start'), layout);

  // ── The header is sortable because a child is ─────────────────────────
  const hasSplitSort = await eval_(`!! sortButton('identity')`);
  check('the split header offers a sort control, taken from its sortable child', hasSplitSort);

  const roleNotSortable = await eval_(`! sortButton('role')`);
  check('a column nobody marked sortable offers none', roleNotSortable);

  // Names are read as they are, never compared against the seed: this database
  // is shared with every other driver and they rename these very users.
  const seeded = await eval_(`names()`);
  check('the unsorted table does not already happen to be in name order',
    ! isSorted(seeded), seeded.join(' | '));

  // ── First click: ascending by the child, not by the group name ────────
  await eval_(`sortButton('identity').click()`);
  await sleep(1600);

  const ascRows = await eval_(`rowCount()`);
  check('the table survives the click and still has its rows', ascRows === SEEDED,
    `rows=${ascRows}`);

  const asc = await eval_(`names()`);
  check('sorting the split orders by its child `name`, ascending',
    isSorted(asc), asc.join(' | '));
  check('that order is not simply the order the rows arrived in',
    asc.join('|') !== seeded.join('|'), `unsorted was ${seeded.join(' | ')}`);
  await shot('02-sorted-asc');

  // ── Second click: descending ──────────────────────────────────────────
  await eval_(`sortButton('identity').click()`);
  await sleep(1600);

  const desc = await eval_(`names()`);
  check('clicking again reverses it',
    isSorted([...desc].reverse()) && desc.join('|') === [...asc].reverse().join('|'),
    desc.join(' | '));
  await shot('03-sorted-desc');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
