import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * The menu filter in a browser (navigation-surfaces.md § 5a).
 *
 * The server decides whether the field exists; everything it *does* happens in
 * the browser, so this is the only check over it:
 *
 *  - rows that do not match go, and the structure around the ones that do
 *    stays — a matching child keeps its parent, and the parent's submenu opens;
 *  - a group with nothing left in it goes with its rows;
 *  - nothing matching says so, rather than leaving an empty column;
 *  - `/` reaches the field and Escape clears it and hands the keyboard back;
 *  - a page change clears it — a filter that followed you would be a menu with
 *    half its rows missing and no reason on the screen.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/previews/zoned/admin/setup/general`, shotPrefix: 'nav-filter', width: 1400, height: 1000 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const key = (k, code, windowsVirtualKeyCode, text) =>
  page('Input.dispatchKeyEvent', { type: text ? 'keyDown' : 'rawKeyDown', key: k, code, windowsVirtualKeyCode, ...(text ? { text } : {}) })
    .then(() => page('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code, windowsVirtualKeyCode }));
const type = async (text) => {
  for (const ch of text) await page('Input.dispatchKeyEvent', { type: 'char', text: ch });
};
const visibleRows = () => eval_(`JSON.stringify([...document.querySelectorAll('#wire-admin-nav [data-nav-row]')].filter(r => r.offsetParent !== null).map(r => r.dataset.navLabel))`).then(JSON.parse);
const visibleGroups = () => eval_(`JSON.stringify([...document.querySelectorAll('#wire-admin-nav [data-nav-group]')].filter(g => ! g.hidden).map(g => g.dataset.group))`).then(JSON.parse);
const announced = () => eval_(`document.querySelector('#wire-admin-nav [aria-live]')?.textContent?.trim() ?? ''`);

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="admin-nav-filter"]')`);
  const all = await visibleRows();

  // ── 1. `/` reaches the field ─────────────────────────────────────────────
  await eval_('document.activeElement?.blur()');
  await key('/', 'Slash', 191, '/');
  await sleep(100);
  check('/ puts the caret in the filter', (await eval_(`document.activeElement?.id`)) === 'wire-admin-nav-filter', await eval_('document.activeElement?.id'));
  check('and types nothing into it', (await eval_(`document.querySelector('#wire-admin-nav-filter').value`)) === '');

  // ── 2. A term narrows, keeping the structure ─────────────────────────────
  await type('overdue');
  await sleep(200);

  const rows = await visibleRows();
  check('a matching child is shown with its parent', JSON.stringify(rows) === JSON.stringify(['invoices', 'overdue']), JSON.stringify(rows));
  check('and every other row is gone', ! rows.includes('tasks') && ! rows.includes('setup'), JSON.stringify(rows));
  check('the groups with nothing left go too', JSON.stringify(await visibleGroups()) === JSON.stringify(['billing']), JSON.stringify(await visibleGroups()));
  check('the count is announced', /1|One/.test(await announced()), await announced());
  await shot('01-overdue');

  // ── 3. Nothing matching says so ──────────────────────────────────────────
  await eval_(`(() => { const f = document.querySelector('#wire-admin-nav-filter'); f.value = ''; f.dispatchEvent(new Event('input')); })()`);
  await type('zzzz');
  await sleep(200);
  check('no match leaves no row', (await visibleRows()).length === 0, JSON.stringify(await visibleRows()));
  check('and says so', (await eval_(`getComputedStyle(document.querySelector('[data-testid="admin-nav-filter-empty"]')).display`)) !== 'none');

  // ── 4. Escape clears and hands the keyboard back ─────────────────────────
  await key('Escape', 'Escape', 27);
  await sleep(200);
  check('Escape clears the filter', (await eval_(`document.querySelector('#wire-admin-nav-filter').value`)) === '');
  check('every row is back', (await visibleRows()).length === all.length, `${(await visibleRows()).length} of ${all.length}`);
  check('and focus is on the first row', (await eval_(`!! document.activeElement?.closest('[data-nav-row]')`)) === true, await eval_('document.activeElement?.outerHTML?.slice(0, 80)'));

  // ── 5. A page change clears it ───────────────────────────────────────────
  await eval_(`document.querySelector('#wire-admin-nav-filter').focus()`);
  await type('tasks');
  await sleep(200);
  await eval_(`document.querySelector('#wire-admin-nav [data-nav-label="tasks"] a').click()`);
  await waitFor(`location.pathname.endsWith('/tasks') && !! document.querySelector('[data-testid="admin-nav-filter"]')`);
  await sleep(300);
  check('the next page starts with an empty filter', (await eval_(`document.querySelector('#wire-admin-nav-filter').value`)) === '');
  check('and the whole menu', (await visibleRows()).length === all.length, `${(await visibleRows()).length} of ${all.length}`);

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
