import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for ColumnFilterLayout::Popover and the cell filter menu
 * (/previews/table-column-filters-popover).
 *
 * What Pest cannot see: the funnel opens a teleported panel, the operator
 * picker re-renders the value box away and back, typing narrows the rows
 * through a real roundtrip while the panel stays open, the funnel is marked,
 * Clear and Escape work, the date preset resolves, and a right-click on a
 * cell offers "Exclude this value" and applies it. On a phone the panel is a
 * bottom sheet, and an Escape in a sheet opened from it closes that one only.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-column-filter-popover.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${origin}/previews/table-column-filters-popover`;

const { check, finish, results } = checker();
const errors = [];
const bad = [];
let lastShotDir;

const rows = `document.querySelectorAll('tbody tr[data-row-key]').length`;
const panelOpen = (name) => `(() => { const p = document.querySelector('[data-testid=column-filter-panel-${name}]'); return !! p && getComputedStyle(p).display !== 'none'; })()`;
const marked = (name) => `!! document.querySelector('[data-testid=column-filter-trigger-${name}][data-filtered]')`;
const setSelect = (selector, value) => `(() => { const s = document.querySelector('${selector}'); s.value = '${value}'; s.dispatchEvent(new Event('change', { bubbles: true })); })()`;

// ── Desktop ────────────────────────────────────────────────────────────────
{
  const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({ url, shotPrefix: 'column-filter-popover' });
  lastShotDir = shotDir;

  try {
    const total = await eval_(rows);
    check('the header carries a funnel per filterable column, and no filter row', await eval_(`document.querySelectorAll('[data-testid^=column-filter-trigger-]').length === 5 && ! document.querySelector('[data-testid^=table-filter-]')`));

    await eval_(`document.querySelector('[data-testid=column-filter-trigger-email]').click()`);
    await waitFor(panelOpen('email'));
    check('the funnel opens the column\'s filter panel', true);

    await eval_(setSelect('#colfilter-email-operator', 'is_empty'));
    await waitFor(`document.querySelector('[data-filter-operator=is_empty]') !== null`);
    check('"is empty" takes the value box away', await eval_(`! document.querySelector('[data-testid=column-filter-panel-email] input[type=text]')`));

    await eval_(setSelect('#colfilter-email-operator', 'not_contains'));
    await waitFor(`!! document.querySelector('[data-testid=column-filter-panel-email] input[type=text]')`);
    // The first row's own address, so the check does not depend on the seed:
    // "does not contain <it>" must drop at least that row.
    const needle = await eval_(`document.querySelector('tbody tr[data-row-key]').children[1].textContent.trim()`);
    await eval_(`(() => { const i = document.querySelector('[data-testid=column-filter-panel-email] input[type=text]'); i.value = ${JSON.stringify(needle)}; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
    await waitFor(`${rows} < ${total}`).catch(() => {});
    check('typing narrows the rows ("does not contain <the first address>")', (await eval_(rows)) < total, `${await eval_(rows)} of ${total}`);
    check('the panel stays open through the roundtrip', await eval_(panelOpen('email')));
    check('the funnel is marked while the column is filtered', await eval_(marked('email')));
    await shot('01-email-not-contains');

    await eval_(`document.querySelector('[data-testid=column-filter-panel-email] input[type=text]').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
    await waitFor(`! ${panelOpen('email')}`).catch(() => {});
    check('Escape closes the panel', ! (await eval_(panelOpen('email'))));

    await eval_(`document.querySelector('[data-testid=column-filter-trigger-email]').click()`);
    await waitFor(panelOpen('email'));
    await eval_(`document.querySelector('[data-testid=column-filter-clear-email]').click()`);
    await waitFor(`${rows} === ${total}`).catch(() => {});
    check('Clear brings every row back and unmarks the funnel', (await eval_(rows)) === total && ! (await eval_(marked('email'))));
    await eval_(`document.body.click()`);

    // Date range: the forms DateRangePicker, its preset written by NAME.
    await eval_(`document.querySelector('[data-testid=column-filter-trigger-created_at]').click()`);
    await waitFor(panelOpen('created_at'));
    await eval_(`document.querySelector('[data-testid=column-filter-panel-created_at] [data-testid$=-trigger]').click()`);
    await waitFor(`[...document.querySelectorAll('button')].some((b) => b.offsetParent && b.textContent.trim() === 'Last 30 days')`);
    await eval_(`[...document.querySelectorAll('button')].find((b) => b.offsetParent && b.textContent.trim() === 'Last 30 days').click()`);
    const stateOf = `JSON.stringify(Livewire.all().map((c) => c.$wire).find((w) => w.tableState !== undefined).tableState.columnFilters.created_at)`;
    await waitFor(`JSON.parse(${stateOf}).preset === 'last_30_days'`).catch(() => {});
    const range = JSON.parse(await eval_(stateOf));
    check('a range preset is kept by name, with the dates it stands for', range.preset === 'last_30_days' && /^\d{4}-\d{2}-\d{2}$/.test(range.from ?? ''), JSON.stringify(range));
    check('and marks the column', await waitFor(marked('created_at')).then(() => true, () => false));
    await shot('02-date-preset');
    await eval_(`document.querySelector('[data-testid=column-filter-clear-created_at]').click()`);
    await waitFor(`! ${marked('created_at')}`).catch(() => {});
    await eval_(`document.body.click()`);

    // Cell menu: exclude the first row's role
    const role = await eval_(`document.querySelector('tbody tr[data-row-key]').children[2].textContent.trim()`);
    await eval_(`(() => { const td = document.querySelector('tbody tr[data-row-key]').children[2]; const r = td.getBoundingClientRect(); td.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: r.x + 10, clientY: r.y + 10 })); })()`);
    await waitFor(`(() => { const p = document.querySelector('[data-testid=cell-filter-panel]'); return !! p && getComputedStyle(p).display !== 'none'; })()`);
    check('a right-click on a filterable cell offers to exclude its value', await waitFor(`getComputedStyle(document.querySelector('[data-testid=cell-filter-exclude]')).display !== 'none'`).then(() => true, () => false));
    await shot('03-cell-menu');
    await eval_(`document.querySelector('[data-testid=cell-filter-exclude]').click()`);
    await waitFor(marked('role')).catch(() => {});
    const roles = await eval_(`[...document.querySelectorAll('tbody tr[data-row-key]')].map((tr) => tr.children[2].textContent.trim())`);
    check('"Exclude this value" drops every row holding it', roles.length > 0 && ! roles.includes(role), `${role} → ${roles.join(', ')}`);
    check('and marks the role funnel', await eval_(marked('role')));
    await shot('04-cell-excluded');
  } catch (error) {
    check('desktop pass ran to completion', false, error.message);
  } finally {
    errors.push(...consoleErrors);
    bad.push(...badResponses);
    await close();
  }
}

// ── Phone ──────────────────────────────────────────────────────────────────
{
  const { eval_, waitFor, shot, consoleErrors, badResponses, close } = await openPage({ url, shotPrefix: 'column-filter-popover-phone', width: 390, height: 844, mobile: true });

  try {
    await eval_(`document.querySelector('[data-testid=column-filter-trigger-role]').click()`);
    await waitFor(panelOpen('role'));
    // The sheet slides up; measure where it comes to rest.
    await waitFor(`Math.round(document.querySelector('[data-testid=column-filter-panel-role]').getBoundingClientRect().bottom) <= innerHeight`).catch(() => {});
    const box = await eval_(`(() => { const r = document.querySelector('[data-testid=column-filter-panel-role]').getBoundingClientRect(); return { left: r.left, right: r.right, bottom: r.bottom, w: innerWidth, h: innerHeight }; })()`);
    check('on a phone the panel is a bottom sheet, edge to edge', box.left === 0 && Math.round(box.right) === box.w && Math.round(box.bottom) === box.h, JSON.stringify(box));
    check('with a grabber and a Done button', await eval_(`!! document.querySelector('[data-testid=column-filter-panel-role] [x-sheet-dismiss]')`));
    await shot('05-phone-sheet');

    // A sheet inside the sheet: the role list opens its own touch sheet. One
    // Escape there closes that one only — the window listener of the column
    // sheet must see the key as taken (defaultPrevented) and stay open.
    const touchOpen = `(() => { const t = [...document.querySelectorAll('[data-testid=select-touch-sheet]')].find((n) => getComputedStyle(n).display !== 'none'); return !! t; })()`;
    await eval_(`document.querySelector('[data-testid=column-filter-panel-role] [data-testid=select-trigger]').click()`);
    const nested = await waitFor(touchOpen).then(() => true, () => false);
    check('the role list opens its own sheet on top', nested);
    if (nested) {
      await eval_(`[...document.querySelectorAll('[data-testid=select-touch-sheet]')].find((n) => getComputedStyle(n).display !== 'none').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
      await waitFor(`! ${touchOpen}`).catch(() => {});
      check('one Escape closes the inner sheet only', ! (await eval_(touchOpen)) && (await eval_(panelOpen('role'))));
      await eval_(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
      await waitFor(`! ${panelOpen('role')}`).catch(() => {});
      check('a second Escape closes the column sheet', ! (await eval_(panelOpen('role'))));
    }
  } catch (error) {
    check('phone pass ran to completion', false, error.message);
  } finally {
    errors.push(...consoleErrors);
    bad.push(...badResponses);
    await close();
  }
}

finish({ consoleErrors: errors, badResponses: bad.filter((r) => ! r.includes('favicon')), shotDir: lastShotDir });
void results;
