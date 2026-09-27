import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Pinned and recent entries (navigation-surfaces.md § 5b) — the one part of the
 * menu that changes without a page load.
 *
 * What only a browser can say: that the pin on a row reaches the Livewire
 * section above the menu and comes back as the row's own state, **without a
 * reload**; that it survives a `wire:navigate` hop; and that the recent list
 * fills as pages are opened and never lists the page you are on.
 *
 * The workbench keeps preferences in the session, so this starts by unpinning
 * whatever an earlier run left behind.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const base = '/previews/zoned/admin';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}${base}/tasks`, shotPrefix: 'nav-memory', width: 1400, height: 1000 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const pinnedRows = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="admin-nav-pinned-item"]')].map(e => e.dataset.resource))`).then(JSON.parse);
const recentRows = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="admin-nav-recent-item"]')].map(e => e.dataset.resource))`).then(JSON.parse);
const pressed = (key) => eval_(`document.querySelector('[data-testid="admin-nav-pin"][data-resource="${key}"]')?.getAttribute('aria-pressed') ?? ''`);
const togglePin = (key) => eval_(`document.querySelector('[data-testid="admin-nav-pin"][data-resource="${key}"]').click()`);

try {
  await waitFor(`!! window.Livewire && !! document.querySelector('[data-testid="admin-nav-pin"]')`);

  // Clean slate: an earlier run's pins live in this browser's session.
  for (const key of await pinnedRows()) {
    await togglePin(key);
    await sleep(600);
  }
  await waitFor(`document.querySelectorAll('[data-testid="admin-nav-pinned-item"]').length === 0`);

  // ── 1. Pinning shows up at once, and on the row ──────────────────────────
  await eval_('window.__memoryMarker = 1');
  await togglePin('invoices');
  const pinned = await waitFor(`[...document.querySelectorAll('[data-testid="admin-nav-pinned-item"]')].some(e => e.dataset.resource === 'invoices')`);

  check('a pin puts the entry at the top of the menu', !! pinned, JSON.stringify(await pinnedRows()));
  check('without a reload', (await eval_('window.__memoryMarker === 1')) === true);
  check('the row says it is pinned', (await pressed('invoices')) === 'true', await pressed('invoices'));
  check('and the entry is still in its group', !! (await eval_(`document.querySelector('[data-testid="admin-nav-item"][data-resource="invoices"]')`)));
  await shot('01-pinned');

  // ── 2. It survives a hop, and the hop is remembered ──────────────────────
  await eval_(`document.querySelector('[data-testid="admin-nav-item"][data-resource="overview"]').click()`);
  await waitFor(`location.pathname === '${base}' || location.pathname === '${base}/overview'`);
  await waitFor(`!! document.querySelector('[data-testid="admin-nav-pinned-item"]')`);
  await sleep(300);

  check('the pin is still there after wire:navigate', JSON.stringify(await pinnedRows()) === JSON.stringify(['invoices']), JSON.stringify(await pinnedRows()));
  check('the row still says so', (await pressed('invoices')) === 'true', await pressed('invoices'));

  const recent = await recentRows();
  check('the page left behind is recent', recent.includes('tasks'), JSON.stringify(recent));
  check('the page you are on is not', ! recent.includes('overview'), JSON.stringify(recent));
  check('a pinned entry is not repeated as recent', ! recent.includes('invoices'), JSON.stringify(recent));
  await shot('02-recent');

  // ── 3. Unpinning ─────────────────────────────────────────────────────────
  await togglePin('invoices');
  await waitFor(`document.querySelectorAll('[data-testid="admin-nav-pinned-item"]').length === 0`);
  check('unpinning takes it off the top', (await pinnedRows()).length === 0);
  check('and the row says so', (await pressed('invoices')) === 'false', await pressed('invoices'));

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
