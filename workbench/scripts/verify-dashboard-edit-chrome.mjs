import { openPage, checker, until, sleep } from './lib/cdp.mjs';

/*
 * The chrome a customisable dashboard draws over its cards must not move them
 * or cover them (/previews/widgets-editable-filtered).
 *
 * Two defects, both invisible to Pest because they are geometry, not markup:
 *
 *  - the "not filtered" mark on a widget that `ignoresDashboardFilters()` sat
 *    in the flow above its card, so while a dashboard filter narrowed, that
 *    card started a line lower than the card beside it;
 *  - the edit toolbar sat over the right end of the card header, which is
 *    where a header action (a "View all →" link) lives, so in edit mode the
 *    link was covered and a click on it hit the toolbar.
 *
 * Both are compared against a neighbour rather than against a number, so the
 * driver holds whatever the card padding or the font happens to be.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-dashboard-edit-chrome.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${origin}/previews/widgets-editable-filtered`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'dashboard-edit-chrome', width: 1200, height: 900, settle: 2500,
});

const { check, finish } = checker();

const json = async (expr) => JSON.parse(await eval_(expr));

/** Top of each card (the widget's own root, not the grid cell around it). */
const cardTops = () => json(`JSON.stringify(Object.fromEntries(
  ['orders', 'server'].map((key) => {
    const cell = document.querySelector('[wire\\\\:key="widget-cell-' + key + '"]');
    const card = cell.querySelector('.wire-list-widget');
    return [key, Math.round(card.getBoundingClientRect().top)];
  })
))`);

/**
 * Whether the edit toolbar of a card overlaps its header action, and what a
 * click in the middle of that action actually lands on.
 */
const headerVersusToolbar = (key, actionName) => json(`(() => {
  const cell = document.querySelector('[wire\\\\:key="widget-cell-${key}"]');
  const link = cell.querySelector('[data-testid="widget-action-${actionName}"]');
  const bar = cell.querySelector('[data-testid="widget-drag-${key}"]')?.parentElement;
  if (! link || ! bar) return JSON.stringify({ missing: { link: !! link, bar: !! bar } });
  const a = link.getBoundingClientRect();
  const b = bar.getBoundingClientRect();
  const overlaps = a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;
  const hit = document.elementFromPoint(a.left + a.width / 2, a.top + a.height / 2);
  return JSON.stringify({ overlaps, hitIsLink: link === hit || link.contains(hit), link: [a.top, a.right], bar: [b.top, b.bottom, b.left] });
})()`);

const click = async (testid) => {
  await eval_(`document.querySelector('[data-testid="${testid}"]').click(); true;`);
};

try {
  // ─── 1. At the default the dashboard is not narrowed: no mark, cards level ──
  const resting = await cardTops();
  check('with no filter narrowing, the two cards start level',
    resting.orders === resting.server, JSON.stringify(resting));

  // ─── 2. Narrow it: the mark appears, and must not push its card down ──────
  await click('widget-filter-period-week');
  await until(() => eval_(`!! document.querySelector('[data-testid="widget-unfiltered-server"]')`));

  const narrowed = await cardTops();
  check('the "not filtered" mark is drawn on the widget that ignores the filter',
    await eval_(`!! document.querySelector('[data-testid="widget-unfiltered-server"]')`));
  check('…and does not push that card below its neighbour',
    narrowed.orders === narrowed.server, JSON.stringify(narrowed));
  check('…nor move either card from where it stood unfiltered',
    narrowed.orders === resting.orders && narrowed.server === resting.server,
    JSON.stringify({ resting, narrowed }));

  await shot('01-narrowed');

  // ─── 3. Edit mode: the toolbar must leave the header action reachable ─────
  await click('layout-edit');
  await until(() => eval_(`!! document.querySelector('[data-testid="widget-drag-orders"]')`));
  await sleep(300);

  for (const [key, action] of [['orders', 'view-all'], ['server', 'status']]) {
    const geo = await headerVersusToolbar(key, action);
    check(`in edit mode the toolbar on "${key}" does not overlap its header action`,
      geo.overlaps === false, JSON.stringify(geo));
    check(`…and a click on "${key}"'s header action reaches the action`,
      geo.hitIsLink === true, JSON.stringify(geo));
  }

  const editing = await cardTops();
  check('in edit mode the two cards still start level',
    editing.orders === editing.server, JSON.stringify(editing));

  await shot('02-editing');

  await click('layout-cancel');
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
