import { openPage, checker } from './lib/cdp.mjs';

/*
 * The admin shell — `wire-admin`'s layout and sidebar, around a routed page.
 *
 * Pest sees this markup already (packages/admin/tests). What only a browser can
 * answer is what the chrome *does*:
 *
 *   - the menu marks the page you are on. Active state is derived from the route
 *     name, and reading it in the wrong place is the trap ADR 0027 documents:
 *     inside a Livewire update `Route::currentRouteName()` is `livewire.update`,
 *     so a sidebar that re-derived per render would be right once and wrong
 *     afterwards — while rendering perfectly.
 *   - navigating between two resources stays inside the shell. That is the whole
 *     reason the shell had to be the layout of routed pages rather than a second
 *     URL space (v2-progress §4, the two-modes finding).
 *   - the mobile handle really opens the menu. It is `x-show` over a media
 *     query, and a menu that cannot be opened on a phone is invisible to Pest.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/invoices`, shotPrefix: 'admin-shell', width: 1300, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="admin-sidebar"]')`);

  // ── 1. The frame ─────────────────────────────────────────────────────────
  check('the shell frames the routed page', await eval_(`!! document.querySelector('[data-testid="admin-content"]')`));
  check('the brand comes from the application slot', (await eval_(`document.querySelector('[data-testid="admin-brand"]')?.innerText?.trim() ?? ''`)) === 'Wire Workbench');
  check('the palette trigger sits in the chrome', await eval_(`!! document.querySelector('[data-testid="global-search-trigger"]')`));

  // ── 2. The menu knows where it is ────────────────────────────────────────
  const items = await eval_(`document.querySelectorAll('[data-testid="admin-nav-item"]').length`);
  check('the menu draws every registered entry', items >= 3, `${items} entries`);
  check('the entry for this page is active', await eval_(`!! document.querySelector('[data-testid="admin-nav-item"][data-resource="invoices"][data-active="true"]')`));
  check('exactly one entry is active', (await eval_(`document.querySelectorAll('[data-testid="admin-nav-item"][data-active="true"]').length`)) === 1);
  check('a registered entry with no route is drawn without a link', await eval_(`
    !! [...document.querySelectorAll('[data-testid="admin-nav-item"]')].find(a => a.getAttribute('aria-disabled') === 'true')
  `));
  await shot('01-shell');

  // ── 3. Active state survives a Livewire round trip ───────────────────────
  // The ADR 0027 trap: derive the zone or the key per render and this is where
  // it turns to null — the page still looks right until you look at the menu.
  await eval_(`(() => {
    const el = document.querySelector('[data-testid="table-search"]');
    el.focus();
    el.value = 'zzz-matches-nothing';
    el.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);

  await waitFor(`document.querySelectorAll('[data-testid="table-row"]').length === 0`, 8000);

  check('the active entry survives a Livewire update', await eval_(`!! document.querySelector('[data-testid="admin-nav-item"][data-resource="invoices"][data-active="true"]')`));
  check('the menu is still one list after the update', (await eval_(`document.querySelectorAll('[data-testid="admin-sidebar"]').length`)) === 1);

  // ── 4. The mobile handle ─────────────────────────────────────────────────
  await page('Emulation.setDeviceMetricsOverride', { width: 420, height: 900, deviceScaleFactor: 1, mobile: true });

  // Waited for rather than asserted immediately: the media-query listener runs
  // on Alpine's own tick, and a bare read here measures the frame before it.
  await waitFor(`document.querySelector('#wire-admin-nav')?.offsetParent === null`, 4000).catch(() => {});

  check('the menu is out of the way on a phone', await eval_(`document.querySelector('#wire-admin-nav')?.offsetParent === null`), `matchMedia=${await eval_(`window.matchMedia('(min-width: 1024px)').matches`)} innerWidth=${await eval_(`window.innerWidth`)}`);

  await eval_(`document.querySelector('[data-testid="admin-sidebar-toggle"]').click()`);
  await waitFor(`document.querySelector('#wire-admin-nav')?.offsetParent !== null`, 4000);
  check('the handle opens it', await eval_(`document.querySelector('#wire-admin-nav')?.offsetParent !== null`));
  check('the handle reports its state to assistive tech', (await eval_(`document.querySelector('[data-testid="admin-sidebar-toggle"]')?.getAttribute('aria-expanded')`)) === 'true');
  await shot('02-mobile-open');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

// finish() asserts a clean console and no 419 itself — a driver that renders the
// right markup over a broken roundtrip has verified nothing.
finish({ consoleErrors, badResponses, shotDir });
