import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for rearranging a dashboard
 * (/previews/widgets-editable).
 *
 * Everything the server owns is covered in PHP — the draft, the clamping, save
 * and cancel. What no PHP test can see is whether the drag exists at all:
 * `x-sort` is Livewire's own Alpine plugin, and a directive that never
 * initialised leaves markup that looks perfect and a handle that does nothing.
 * That is the exact failure `wireSortableList` was written to end on a different
 * surface, and it was invisible until a browser looked.
 *
 * Three things, then:
 *
 *  1. **The mode changes the page.** No handles, no steppers and no sort
 *     directive until Customise is pressed — a dashboard somebody is reading
 *     should be a dashboard, not a dashboard wearing controls.
 *  2. **The drag is bound.** SortableJS stamps itself onto the element it bound;
 *     asked by prefix, since its key carries a timestamp.
 *  3. **The order survives a save and a reload**, which is the whole promise.
 *     Asserted from the rendered order, not from the store.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-widget-layout.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${origin}/previews/widgets-editable`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'widget-layout', width: 1280, height: 1000, settle: 2500,
});

const { check, finish } = checker();

const json = async (expr) => JSON.parse(await eval_(expr));

/** The widget keys in the order the grid currently draws them. */
const order = () => json(`JSON.stringify(
  Array.from(document.querySelectorAll('[wire\\\\:key^="widget-cell-"]'))
    .map((el) => el.getAttribute('wire:key').replace('widget-cell-', ''))
)`);

const click = async (testid) => {
  await eval_(`document.querySelector('[data-testid="${testid}"]').click(); true;`);
  await sleep(700);
};

try {
  // ─── 1. The mode ─────────────────────────────────────────────────────────
  const resting = await json(`(() => JSON.stringify({
    keys: Array.from(document.querySelectorAll('[wire\\\\:key^="widget-cell-"]')).length,
    handles: document.querySelectorAll('[data-testid^="widget-drag-"]').length,
    steppers: document.querySelectorAll('[data-testid^="widget-wider-"]').length,
    sorting: !! document.querySelector('[x-sort]'),
  }))()`);

  check('the dashboard draws its widgets', resting.keys === 4, JSON.stringify(resting));
  check('…and no editing chrome at rest',
    resting.handles === 0 && resting.steppers === 0 && resting.sorting === false,
    JSON.stringify(resting));

  await shot('01-resting');

  await click('layout-edit');

  const editing = await json(`(() => {
    const grid = document.querySelector('[x-sort]');

    // SortableJS stamps itself onto the element it bound, under a key it builds
    // as 'Sortable' + a timestamp — asked by prefix, since the timestamp is not
    // knowable. A directive that never initialised leaves the attribute and no
    // instance, which is precisely the failure that is invisible in markup.
    const bound = !! grid && Object.keys(grid).some((key) => key.startsWith('Sortable'));

    return JSON.stringify({
      grid: !! grid,
      bound,
      handles: document.querySelectorAll('[data-testid^="widget-drag-"]').length,
      steppers: document.querySelectorAll('[data-testid^="widget-wider-"]').length,
    });
  })()`);

  check('Customise reveals the handles and the steppers',
    editing.handles === 4 && editing.steppers === 4, JSON.stringify(editing));
  check('…and SortableJS is actually bound to the grid, not just declared on it',
    editing.grid === true && editing.bound === true, JSON.stringify(editing));

  await shot('02-editing');

  // ─── 2. Resizing ─────────────────────────────────────────────────────────
  const before = await order();

  await click('widget-wider-revenue');
  await click('widget-taller-revenue');

  const sized = await json(`(() => {
    const cell = document.querySelector('[wire\\\\:key="widget-cell-revenue"]');
    return JSON.stringify({ class: cell ? cell.className : null });
  })()`);

  check('a stepper resizes the tile it belongs to',
    /sm:col-span-2/.test(sized.class) && /row-span-2/.test(sized.class), JSON.stringify(sized));

  // ─── 3. Reordering, saved, and still there after a reload ────────────────
  // `Livewire.first()` *is* the `$wire` proxy in Livewire 4 — the component's
  // methods hang off it directly. `$wire` itself is an Alpine-scope name and
  // does not exist in the page, which is what the first attempt at this line
  // found out.
  //
  // Calling the method rather than simulating a pointer drag is deliberate:
  // SortableJS's own drag is SortableJS's code, and what is worth proving here
  // is that the directive is bound (checked above) and that the server's answer
  // re-renders the grid.
  await eval_(`window.Livewire.first().moveWidget('churn', 0); true;`);
  await sleep(900);

  const moved = await order();
  check('moving a widget re-renders the grid in the new order',
    moved[0] === 'churn' && moved.join() !== before.join(), JSON.stringify(moved));

  await click('layout-save');

  const afterSave = await json(`(() => JSON.stringify({
    handles: document.querySelectorAll('[data-testid^="widget-drag-"]').length,
    editButton: !! document.querySelector('[data-testid="layout-edit"]'),
  }))()`);

  check('saving leaves the mode', afterSave.handles === 0 && afterSave.editButton === true,
    JSON.stringify(afterSave));

  await shot('03-saved');

  await eval_(`window.location.reload(); true;`);
  await sleep(3000);

  const reloaded = await order();
  check('the arrangement is still there on the next visit, which is the whole promise',
    reloaded.join() === moved.join(), JSON.stringify({ reloaded, moved }));

  // ─── 4. The tray ─────────────────────────────────────────────────────────
  await click('layout-edit');

  const trayAtRest = await json(`(() => {
    const tray = document.querySelector('[data-testid="widget-tray"]');

    return JSON.stringify({
      present: !! tray,
      // One group name shared by the tray and the grid is what makes the two a
      // single drag rather than two lists needing a protocol between them.
      sameGroup: !! tray && tray.getAttribute('x-sort:group')
        === document.querySelector('.wire-widget-grid > div[x-sort]')?.getAttribute('x-sort:group'),
      bound: !! tray && Object.keys(tray).some((key) => key.startsWith('Sortable')),
      offered: document.querySelectorAll('[data-testid^="widget-tray-"]').length,
    });
  })()`);

  check('the tray appears with the mode, sharing the grid\'s drag group',
    trayAtRest.present === true && trayAtRest.sameGroup === true, JSON.stringify(trayAtRest));
  check('…and SortableJS is bound to it too, so a tile can be dropped in',
    trayAtRest.bound === true, JSON.stringify(trayAtRest));
  check('…holding nothing while every widget is on the dashboard',
    trayAtRest.offered === 0, JSON.stringify(trayAtRest));

  await click('widget-remove-orders');

  const afterRemove = await json(`(() => JSON.stringify({
    placed: Array.from(document.querySelectorAll('[wire\\\\:key^="widget-cell-"]'))
      .map((el) => el.getAttribute('wire:key').replace('widget-cell-', '')),
    offered: Array.from(document.querySelectorAll('[data-testid^="widget-tray-"]'))
      .map((el) => el.getAttribute('data-testid').replace('widget-tray-', '')),
  }))()`);

  check('removing a widget takes it off the grid and puts it in the tray',
    ! afterRemove.placed.includes('orders') && afterRemove.offered.includes('orders'),
    JSON.stringify(afterRemove));

  await click('widget-add-orders');

  const afterAdd = await json(`(() => JSON.stringify({
    placed: Array.from(document.querySelectorAll('[wire\\\\:key^="widget-cell-"]'))
      .map((el) => el.getAttribute('wire:key').replace('widget-cell-', '')),
    offered: document.querySelectorAll('[data-testid^="widget-tray-"]').length,
  }))()`);

  check('adding it back takes it out of the tray',
    afterAdd.placed.includes('orders') && afterAdd.offered === 0, JSON.stringify(afterAdd));

  await shot('05-tray');

  await click('layout-cancel');

  // ─── 5. Reset ────────────────────────────────────────────────────────────
  await click('layout-edit');
  await click('layout-reset');

  const reset = await order();
  check('reset puts the dashboard back to what it declares',
    reset[0] === 'revenue', JSON.stringify(reset));

  await shot('04-reset');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
