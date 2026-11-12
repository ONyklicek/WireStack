import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for the filter panel above the rows (/previews/table-filters-above):
 * FiltersLayout::AboveContentCollapsible, deferFilters() and a QueryBuilderFilter.
 *
 * In the browser only: the toolbar button toggles the area (and the choice
 * survives a reload), a deferred change does nothing until Apply, and the
 * builder's add / combine / remove buttons rebuild the conditions through
 * real roundtrips.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-filters-above.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-filters-above`;

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({ url, shotPrefix: 'filters-above' });
const { check, finish } = checker();

const areaOpen = `(() => { const a = document.querySelector('[data-testid=table-filters-above]'); return !! a && getComputedStyle(a).display !== 'none'; })()`;
const rows = `document.querySelectorAll('tbody tr[data-row-key]').length`;
const ruleCount = `document.querySelectorAll('[data-testid=query-builder-rule]').length`;

try {
  await eval_(`localStorage.clear()`);
  check('the collapsible area starts closed', ! (await eval_(areaOpen)));

  await eval_(`document.querySelector('[data-testid=table-filters-trigger]').click()`);
  await waitFor(areaOpen);
  check('the toolbar button opens it', true);

  // Deferred: typing changes nothing until Apply.
  const total = await eval_(rows);
  await eval_(`(() => { const s = document.querySelector('#filter-customer-operator'); s.value = 'contains'; const i = document.querySelector('#filter-customer-value'); i.value = 'Acme'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  await new Promise((r) => setTimeout(r, 900));
  check('a deferred change leaves the rows alone', (await eval_(rows)) === total);
  await eval_(`document.querySelector('[data-testid=table-filters-apply]').click()`);
  await waitFor(`${rows} < ${total}`).catch(() => {});
  check('Apply narrows them', (await eval_(rows)) < total, `${await eval_(rows)} of ${total}`);

  // The builder.
  await eval_(`document.querySelector('[data-testid=query-builder-add-rule]').click()`);
  await waitFor(`${ruleCount} === 1`);
  await eval_(`document.querySelector('[data-testid=query-builder-add-group]').click()`);
  await waitFor(`${ruleCount} === 2`);
  check('conditions and a group are added through the server', (await eval_(`document.querySelectorAll('[data-testid=query-builder-group]').length`)) === 1);
  await eval_(`document.querySelectorAll('[data-testid=query-builder-remove]')[1].click()`);
  await waitFor(`${ruleCount} === 1`);
  check('removing the last condition of a group removes the group', (await eval_(`document.querySelectorAll('[data-testid=query-builder-group]').length`)) === 0);
  await shot('01-above-builder');

  // Remembered across a reload.
  await page('Page.reload');
  await waitFor(`!! document.querySelector('[data-testid=table-filters-trigger]')`);
  await waitFor(areaOpen).catch(() => {});
  check('the open area is remembered on reload', await eval_(areaOpen));
} catch (error) {
  check('driver ran to completion', false, error.message);
} finally {
  finish({ consoleErrors, badResponses: badResponses.filter((r) => ! r.includes('favicon')), shotDir });
  await close();
}
