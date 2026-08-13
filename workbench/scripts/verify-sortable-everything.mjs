import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for a real drag over a table the user has narrowed.
 *
 * Row reorder mode used to fetch the bare base query, so search, filters and
 * pagination all fell away the moment it was entered — and an
 * `alwaysReorderable()` table, which never leaves reorder mode, therefore
 * rendered a search box that could never do anything. The reason they were
 * dropped is the write, not the read: the client reports each row's new
 * position as `1..n`, so a drop over a narrowed list would have stamped
 * `1, 2, 3` onto the visible rows and shoved every hidden one down the table.
 *
 * So the read was only safe to fix once the write stopped renumbering. A drop
 * now redistributes the order values the dragged rows already hold, and this
 * driver is the check that says so from the outside: drag on page two, and page
 * one must not move; drag two search matches past each other, and the four rows
 * the search hid must keep their exact positions.
 *
 * `ReorderSearchTest` asserts the same thing against the component. What it
 * cannot see is the drag: the payload comes from SortableJS reading the DOM
 * order in `onEnd`, and the response comes back through a Livewire morph that
 * the drag controller is entitled to skip. Both are browser-only.
 *
 * The drag is driven the way the column-reorder driver drives its own — move
 * the row, then hand the production `onEnd` the indices Sortable would have
 * reported — because Sortable runs with `forceFallback` and ignores synthesised
 * pointer input. The move and the handler go out in ONE synchronous evaluate,
 * so the fixture's 3s poll cannot morph the table out from under a half-applied
 * drag.
 *
 * The fixture (`sortable-everything`) is the whole surface at once: row handles,
 * draggable headers, search, pagination and that poll. It is
 * `paginatedWhileReordering()`, so the pager survives the toggle and a drag is
 * necessarily a drag over a subset.
 *
 * This driver WRITES to the workbench database (that is the point), so it puts
 * the seeded order back before it exits — `sortable-morph`, `sortable-overview`
 * and `sortable-columns` all read the same six tasks and assert on their order.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-sortable-everything.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/sortable-everything`;

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'sortable-everything', width: 1400, height: 1100, settle: 3500,
});
const { check, finish } = checker();

/** Poll for a condition rather than sleeping at it — a headless renderer is not a stopwatch. */
const waitFor = async (expression, timeout = 8000) => {
  const deadline = Date.now() + timeout;
  while (Date.now() < deadline) {
    if (await eval_(`!!(${expression})`)) return true;
    await sleep(120);
  }
  return false;
};

const type = async (text) => {
  for (const ch of text) {
    await page('Input.dispatchKeyEvent', { type: 'keyDown', text: ch });
    await page('Input.dispatchKeyEvent', { type: 'keyUp' });
    await sleep(50);
  }
};

/** The ids on every page, in order — the only view of the write this driver gets. */
const wholeTable = async () => {
  await eval_(`cmp.call('gotoPage', 1)`);
  await waitFor(`page() === 1`);
  const first = await eval_(`JSON.stringify(keys())`);
  await eval_(`cmp.call('gotoPage', 2)`);
  await waitFor(`page() === 2`);
  const second = await eval_(`JSON.stringify(keys())`);

  return { one: JSON.parse(first), two: JSON.parse(second) };
};

try {
  await eval_(`
    window.root = document.querySelector('[x-data*="wireSortable"]');
    window.d = Alpine.$data(root);
    window.cmp = Livewire.find(root.closest('[wire\\\\:id]').getAttribute('wire:id'));

    window.search = () => document.querySelector('[data-testid="table-search"]');
    window.rows = () => [...root.querySelectorAll('tbody tr[wire\\\\:key]')];
    window.keys = () => rows().map((tr) => tr.getAttribute('wire:key').replace('row-', ''));
    window.page = () => cmp.get('paginators.page') ?? 1;
    window.handles = () => root.querySelectorAll('.wire-sortable-handle').length;
    window.pagerLinks = () => root.querySelectorAll('nav a, nav button').length;

    // Move a row and report the move, exactly as Sortable does on drop. One
    // synchronous block: a poll tick landing between the two halves would morph
    // the moved row back and the handler would then read an order nobody chose.
    window.dragRow = (fromIdx, toIdx) => {
      const tbody = root.querySelector('tbody');
      const before = [...tbody.children];
      const item = rows()[fromIdx];
      const ref = rows()[toIdx];
      const oldIndex = before.indexOf(item);

      fromIdx < toIdx ? ref.after(item) : ref.before(item);

      const newIndex = [...tbody.children].indexOf(item);
      d.rowSortableInstance.options.onEnd({ item, oldIndex, newIndex });

      return { oldIndex, newIndex, order: keys() };
    };

    // Cleanup path only, never an assertion: hand every row back in seed order
    // and the slot set — unchanged by any permutation — lands where it started.
    window.restore = () => cmp.call('reorderRows',
      ['1','2','3','4','5','6'].map((value, i) => ({ value, order: i + 1 })));
    true;
  `);

  // ── the fixture ──────────────────────────────────────────────────────────
  check('the fixture booted with column reordering, search, a pager and a poll',
    await eval_(`!! root && !! d && !! d.columnSortableInstance
      && !! search() && pagerLinks() > 0 && /wire:poll/.test(root.innerHTML)`),
    `rows=${await eval_('rows().length')} pagerLinks=${await eval_('pagerLinks()')}`);

  // Row reordering is bound to the <tbody> only while the mode is on, so a null
  // instance here is the fixture opening correctly, not a failure to boot.
  check('it opens as an ordinary table — reorder mode is a toggle here, not the default',
    await eval_(`d.isReordering === false && handles() === 0 && ! d.rowSortableInstance`),
    `isReordering=${await eval_('d.isReordering')} handles=${await eval_('handles()')}`);

  const seeded = await wholeTable();
  check('the six seeded tasks are laid out three to a page',
    seeded.one.length === 3 && seeded.two.length === 3,
    `page1=${JSON.stringify(seeded.one)} page2=${JSON.stringify(seeded.two)}`);

  // ── reorder mode keeps the controls it used to throw away ────────────────
  await eval_(`cmp.call('toggleReordering')`);
  await waitFor(`d.isReordering === true && handles() > 0`);
  check('entering reorder mode binds the row Sortable and adds the drag handles',
    await eval_(`d.isReordering === true && !! d.rowSortableInstance && handles() === rows().length`),
    `handles=${await eval_('handles()')} rows=${await eval_('rows().length')}`);

  check('pagination survives the toggle (paginatedWhileReordering)',
    await eval_(`pagerLinks() > 0 && rows().length === 3`),
    `rows=${await eval_('rows().length')} pagerLinks=${await eval_('pagerLinks()')}`);

  // The regression, seen from the browser: this used to return every row on one
  // page, ignoring the term, because the fetch never reached the search at all.
  await eval_(`search().focus()`);
  await type('Amelia');
  const narrowed = await waitFor(`rows().length === 2`, 9000);
  check('searching INSIDE reorder mode narrows the table',
    narrowed, `rows=${await eval_('rows().length')} term=${await eval_('search().value')}`);
  check('the rows it kept are the ones that match, and they still have handles',
    await eval_(`JSON.stringify(keys().sort()) === JSON.stringify(['1','4']) && handles() === 2`),
    `keys=${await eval_('JSON.stringify(keys())')} handles=${await eval_('handles()')}`);
  await shot('01-searched-in-reorder-mode');

  // ── a drag over what the search left ─────────────────────────────────────
  // Tasks 1 and 4 sit on different pages unsearched. Swapping them may exchange
  // their two slots and nothing else: the four rows the search hid are not the
  // client's to renumber, and it does not know they exist.
  const swapped = JSON.parse(await eval_(`JSON.stringify(dragRow(1, 0))`));
  await waitFor(`JSON.stringify(keys()) === '["4","1"]'`);
  check('dragging one match above the other reorders the narrowed list',
    JSON.stringify(swapped.order) === JSON.stringify(['4', '1']),
    JSON.stringify(swapped));

  await eval_(`search().value = ''; search().dispatchEvent(new Event('input', { bubbles: true }))`);
  await waitFor(`rows().length === 3`, 9000);

  const afterSearchDrag = await wholeTable();
  check('the two matches exchanged places across the page break',
    afterSearchDrag.one[0] === '4' && afterSearchDrag.two[0] === '1',
    `page1=${JSON.stringify(afterSearchDrag.one)} page2=${JSON.stringify(afterSearchDrag.two)}`);
  // This is the check the whole driver exists for. Under the old write these
  // four rows were renumbered by two rows they were never shown next to.
  check('every row the search hid kept its exact position',
    afterSearchDrag.one[1] === '2' && afterSearchDrag.one[2] === '3'
      && afterSearchDrag.two[1] === '5' && afterSearchDrag.two[2] === '6',
    `page1=${JSON.stringify(afterSearchDrag.one)} page2=${JSON.stringify(afterSearchDrag.two)}`);
  await shot('02-after-searched-drag');

  await eval_(`restore()`);
  await waitFor(`JSON.stringify(keys()) === '["4","5","6"]' || JSON.stringify(keys()) === '["1","2","3"]'`);

  // ── a drag on page two ───────────────────────────────────────────────────
  await eval_(`cmp.call('gotoPage', 2)`);
  await waitFor(`page() === 2 && JSON.stringify(keys()) === '["4","5","6"]'`);

  const paged = JSON.parse(await eval_(`JSON.stringify(dragRow(2, 0))`));
  await waitFor(`JSON.stringify(keys()) === '["6","4","5"]'`);
  check('dragging the last row of page two to the top of page two reorders that page',
    JSON.stringify(paged.order) === JSON.stringify(['6', '4', '5']),
    JSON.stringify(paged));

  const afterPagedDrag = await wholeTable();
  check('page one is untouched by a drag that happened on page two',
    JSON.stringify(afterPagedDrag.one) === JSON.stringify(['1', '2', '3']),
    `page1=${JSON.stringify(afterPagedDrag.one)}`);
  check('page two kept its own three rows rather than pulling any across',
    JSON.stringify(afterPagedDrag.two) === JSON.stringify(['6', '4', '5']),
    `page2=${JSON.stringify(afterPagedDrag.two)}`);
  await shot('03-after-paged-drag');

  // ── the poll, against a half-typed term ──────────────────────────────────
  // The reason the fixture polls: a tick is a morph aimed at a table whose
  // search box the user is still typing into, and reorder mode is exactly where
  // that search box used to be inert.
  await eval_(`search().focus()`);
  await type('Sof');
  const survived = await waitFor(`rows().length === 2 && search().value === 'Sof'`, 9000);
  check('a poll tick leaves a half-typed search term, and its results, alone',
    survived && await eval_(`document.activeElement === search()`),
    `value=${await eval_('search().value')} rows=${await eval_('rows().length')} focused=${await eval_('document.activeElement === search()')}`);
  await sleep(3500); // one full poll interval, deliberately: nothing may change.
  check('and still leaves them alone a whole poll interval later',
    await eval_(`search().value === 'Sof' && rows().length === 2 && document.activeElement === search()`),
    `value=${await eval_('search().value')} rows=${await eval_('rows().length')}`);
  await shot('04-poll-vs-typing');

  // ── put the fixture back ─────────────────────────────────────────────────
  await eval_(`search().value = ''; search().dispatchEvent(new Event('input', { bubbles: true }))`);
  await waitFor(`rows().length === 3`, 9000);
  await eval_(`restore()`);
  await sleep(400);

  const restored = await wholeTable();
  check('the driver left the seeded order behind it (the other sortable fixtures read it)',
    JSON.stringify(restored.one) === JSON.stringify(['1', '2', '3'])
      && JSON.stringify(restored.two) === JSON.stringify(['4', '5', '6']),
    `page1=${JSON.stringify(restored.one)} page2=${JSON.stringify(restored.two)}`);

  await eval_(`cmp.call('toggleReordering')`);
  await waitFor(`d.isReordering === false`);
} catch (err) {
  console.error('DRIVER ERROR:', err.message);
  process.exitCode = 2;

  // A driver that dies mid-drag must still not leave the seeded order rewritten.
  try {
    await eval_(`restore()`);
    await sleep(600);
  } catch {}
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
