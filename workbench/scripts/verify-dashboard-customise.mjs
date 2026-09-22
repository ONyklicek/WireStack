import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for the customisable dashboard an *application* gets
 * (/previews/workspace/overview).
 *
 * `verify-widget-layout` drives the same feature on /previews/widgets-editable,
 * and the two are not a duplicate of each other. That page is a hand-written
 * Livewire component with its own `widgetLayoutKey()` and its own chrome, which
 * is what lets it instrument the grid; this is the default path, where nothing
 * was written by hand:
 *
 *  - a `Dashboard` says `customisable()` and nothing else,
 *  - `DashboardPage` turns that into the stored key and includes the controls,
 *  - the widgets carry `group()`, so the tray has headings,
 *
 * and a break in any of those three bridges leaves a page that still renders a
 * dashboard — just one nobody can rearrange. That is the failure this driver
 * exists for, and no PHP test sees it: the controls are a Blade include whose
 * condition lives in a different package from the declaration that decides it.
 *
 * It restores what it changed: the layout is stored per user in the session, so
 * a driver that saved one and left would hand the next visitor a dashboard it
 * rearranged. Reset is the last thing it does, and it is also an assertion.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-dashboard-customise.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${origin}/previews/workspace/overview`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'dashboard-customise', width: 1400, height: 1100, settle: 2500,
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
  await sleep(800);
};

const declared = ['billing-totals', 'invoiced-by-status', 'latest-invoices', 'work-totals', 'task-progress', 'due-soon'];

try {
  // ─── 1. The page an application gets, before anybody touches it ──────────
  const resting = await json(`(() => JSON.stringify({
    keys: Array.from(document.querySelectorAll('[wire\\\\:key^="widget-cell-"]'))
      .map((el) => el.getAttribute('wire:key').replace('widget-cell-', '')),
    customise: !! document.querySelector('[data-testid="widget-layout-edit"]'),
    handles: document.querySelectorAll('[data-testid^="widget-drag-"]').length,
    tray: !! document.querySelector('[data-testid="widget-tray"]'),
  }))()`);

  check('the declared dashboard renders its six widgets',
    JSON.stringify(resting.keys) === JSON.stringify(declared), JSON.stringify(resting.keys));
  check('the page offers Customise without the dashboard writing any chrome',
    resting.customise === true, JSON.stringify(resting));
  check('…and wears no editing controls at rest',
    resting.handles === 0 && resting.tray === false, JSON.stringify(resting));

  await shot('01-resting');

  // ─── 2. The mode, and the drag actually bound ────────────────────────────
  await click('widget-layout-edit');

  const editing = await json(`(() => {
    const grid = document.querySelector('.wire-widget-grid > div[x-sort]');
    const tray = document.querySelector('[data-testid="widget-tray"]');

    // SortableJS stamps itself onto the element it bound, under a key built as
    // 'Sortable' + a timestamp — asked by prefix, since the timestamp is not
    // knowable. A directive that never initialised leaves the attribute and no
    // instance, which is exactly what markup cannot show.
    const bound = (el) => !! el && Object.keys(el).some((key) => key.startsWith('Sortable'));

    return JSON.stringify({
      handles: document.querySelectorAll('[data-testid^="widget-drag-"]').length,
      steppers: document.querySelectorAll('[data-testid^="widget-wider-"]').length,
      gridBound: bound(grid),
      trayBound: bound(tray),
      sameGroup: !! grid && !! tray
        && grid.getAttribute('x-sort:group') === tray.getAttribute('x-sort:group'),
      offered: document.querySelectorAll('[data-testid^="widget-tray-"]').length,
    });
  })()`);

  check('Customise reveals a handle and steppers on every tile',
    editing.handles === 6 && editing.steppers === 6, JSON.stringify(editing));
  check('SortableJS is bound to both the grid and the tray, sharing one drag group',
    editing.gridBound === true && editing.trayBound === true && editing.sameGroup === true,
    JSON.stringify(editing));
  check('…and the tray holds nothing while every declared widget is placed',
    editing.offered === 0, JSON.stringify(editing));

  await shot('02-editing');

  // ─── 3. Removing a widget puts it back in the tray, under its group ──────
  await click('widget-remove-due-soon');

  const afterRemove = await json(`(() => {
    const tray = document.querySelector('[data-testid="widget-tray"]');

    return JSON.stringify({
      placed: Array.from(document.querySelectorAll('[wire\\\\:key^="widget-cell-"]'))
        .map((el) => el.getAttribute('wire:key').replace('widget-cell-', '')),
      offered: Array.from(document.querySelectorAll('[data-testid^="widget-tray-"]'))
        .map((el) => el.getAttribute('data-testid').replace('widget-tray-', '')),
      // The heading only exists because the widget declared group('Work'),
      // which is the bridge from the declaration to the tray.
      groupHeading: !! tray && /Work/.test(tray.textContent),
    });
  })()`);

  check('removing a widget takes it off the grid and offers it in the tray',
    ! afterRemove.placed.includes('due-soon') && afterRemove.offered.includes('due-soon'),
    JSON.stringify(afterRemove));
  check('…under the group name the widget declared',
    afterRemove.groupHeading === true, JSON.stringify(afterRemove));

  await shot('03-tray');

  // ─── 4. Reordering, saved, and still there after a reload ────────────────
  // `Livewire.first()` *is* the `$wire` proxy in Livewire 4 — the component's
  // methods hang off it. Calling the method rather than simulating a pointer
  // drag is deliberate: SortableJS's own drag is SortableJS's code, and what is
  // worth proving here is that the directive is bound (above) and that the
  // server's answer re-renders the grid.
  await eval_(`window.Livewire.first().moveWidget('task-progress', 0); true;`);
  await sleep(900);

  const moved = await order();
  check('moving a widget re-renders the grid in the new order',
    moved[0] === 'task-progress', JSON.stringify(moved));

  await click('widget-layout-save');

  const afterSave = await json(`(() => JSON.stringify({
    handles: document.querySelectorAll('[data-testid^="widget-drag-"]').length,
    customise: !! document.querySelector('[data-testid="widget-layout-edit"]'),
  }))()`);

  check('saving leaves the mode', afterSave.handles === 0 && afterSave.customise === true,
    JSON.stringify(afterSave));

  await eval_(`window.location.reload(); true;`);
  await sleep(3000);

  const reloaded = await order();
  check('the arrangement is still there on the next visit, which is the whole promise',
    reloaded.join() === moved.join(), JSON.stringify({ reloaded, moved }));

  await shot('04-saved');

  // ─── 5. More than one arrangement, each under a name ─────────────────────
  // The switcher is the half only a browser can show: the select is drawn from
  // the store, applying is a `wire:change`, and the delete lives in an Alpine
  // scope of its own. `saveWidgetLayoutAs` is called directly because the
  // shipped button asks for the name with `window.prompt`, which headless
  // Chrome answers with null.
  //
  // From the declaration, deliberately: the steps above left a widget in the
  // tray and an arrangement saved, and a named layout is only legible here if
  // what it captures is a state this file states.
  await click('widget-layout-edit');
  await click('widget-layout-reset');

  const layoutOptions = () => json(`JSON.stringify(
    Array.from(document.querySelectorAll('[data-testid="widget-layout-view"] option'))
      .map((el) => el.value)
  )`);

  check('no switcher until a layout has a name',
    ! (await eval_(`!! document.querySelector('[data-testid="widget-layout-view"]')`)));

  await eval_(`window.Livewire.first().saveWidgetLayoutAs('Driver'); true;`);
  await sleep(1000);

  check('saving under a name puts it in the switcher',
    (await layoutOptions()).includes('Driver'), JSON.stringify(await layoutOptions()));

  // Rearrange away from what was saved, and keep it — so what comes back is
  // provably the saved arrangement rather than the one on screen.
  await click('widget-layout-edit');
  await eval_(`window.Livewire.first().moveWidget('due-soon', 0); true;`);
  await sleep(800);
  await click('widget-layout-save');

  const rearranged = await order();

  check('…over an arrangement that is now different',
    rearranged[0] === 'due-soon', JSON.stringify(rearranged));

  await eval_(`(() => {
    const select = document.querySelector('[data-testid="widget-layout-view"]');
    select.value = 'Driver';
    select.dispatchEvent(new Event('change'));
  })(); true;`);
  await sleep(1400);

  const restored = await order();

  check('choosing it in the switcher puts that arrangement back',
    JSON.stringify(restored) === JSON.stringify(declared), JSON.stringify(restored));

  await shot('05-saved-layouts');

  // The delete button exists only while a name is chosen — it reads the same
  // Alpine value the select is bound to.
  await click('widget-layout-delete');

  check('deleting the last name takes the switcher with it',
    ! (await eval_(`!! document.querySelector('[data-testid="widget-layout-view"]')`)));

  // ─── 6. Reset, which is also how this driver leaves no trace ─────────────
  await click('widget-layout-edit');
  await click('widget-layout-reset');

  const reset = await order();
  check('reset puts the dashboard back to what the class declares',
    JSON.stringify(reset) === JSON.stringify(declared), JSON.stringify(reset));
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
