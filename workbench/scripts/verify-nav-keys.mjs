import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * The admin menu from the keyboard (navigation-surfaces.md § 6).
 *
 * Only a browser knows where focus is, so everything here is a question Pest
 * cannot ask:
 *
 *  - **the phone drawer keeps Tab inside it** while open, starts on the first
 *    menu row, and hands focus back to the button that opened it on Escape;
 *  - **the skip link lands in `<main>`** — without `tabindex="-1"` on the
 *    target, part of the browsers follow the fragment and leave focus behind.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/previews/zoned/admin/setup/general`, shotPrefix: 'nav-keys', width: 390, height: 844, mobile: true });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

// `keyDown` with the text for Enter, not `rawKeyDown`: a button activates on
// the character the key produces, and a raw key-down produces none.
const key = (k, code, windowsVirtualKeyCode, modifiers = 0) =>
  page('Input.dispatchKeyEvent', { type: k === 'Enter' ? 'keyDown' : 'rawKeyDown', key: k, code, windowsVirtualKeyCode, modifiers, ...(k === 'Enter' ? { text: '\r' } : {}) })
    .then(() => page('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code, windowsVirtualKeyCode, modifiers }));
const tab = (shift = false) => key('Tab', 'Tab', 9, shift ? 8 : 0);
const focused = () => eval_(`(() => {
  const el = document.activeElement;
  return JSON.stringify({ inDrawer: !! el?.closest('[data-testid="admin-sidebar"]'), row: !! el?.closest('#wire-admin-nav'), id: el?.id ?? '', testid: el?.dataset?.testid ?? '' });
})()`).then(JSON.parse);

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="admin-sidebar-toggle"]')`);

  // ── 1. The drawer takes focus to its first row ───────────────────────────
  await eval_(`document.querySelector('[data-testid="admin-sidebar-toggle"]').focus()`);
  await key('Enter', 'Enter', 13);
  const opened = await waitFor(`Alpine.store('wireAdmin').mobile === true`);
  check('Enter on the menu button opens the drawer', !! opened);
  await sleep(200);

  const first = await focused();
  check('opening the drawer puts focus on the first menu row', first.row, JSON.stringify(first));
  await shot('01-drawer');

  // ── 2. Tab stays inside ──────────────────────────────────────────────────
  let escaped = null;

  for (let i = 0; i < 40 && escaped === null; i++) {
    await tab(i % 3 === 2);
    const where = await focused();

    if (! where.inDrawer) escaped = where;
  }

  check('Tab and Shift+Tab never leave the open drawer', escaped === null, JSON.stringify(escaped));

  // ── 3. Escape closes it and hands focus back ─────────────────────────────
  await key('Escape', 'Escape', 27);
  await waitFor(`Alpine.store('wireAdmin').mobile === false`);
  await sleep(200);

  const back = await focused();
  check('Escape gives focus back to the button that opened the drawer', back.testid === 'admin-sidebar-toggle', JSON.stringify(back));

  // ── 4. The skip link lands in main ───────────────────────────────────────
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 900, deviceScaleFactor: 1, mobile: false });
  await sleep(200);
  await eval_(`document.querySelector('a[href="#wire-admin-main"]').focus()`);
  await key('Enter', 'Enter', 13);
  await sleep(200);

  check('the skip link moves focus into the content', (await focused()).id === 'wire-admin-main', JSON.stringify(await focused()));

  // ── 5. Arrows, Home/End and type-ahead in the menu (§ 6b) ────────────────
  const row = () => eval_(`(() => { const el = document.activeElement; return el?.hasAttribute('data-nav-focus') ? (el.dataset.resource ?? el.getAttribute('aria-label') ?? el.textContent.trim()) : '(' + (el?.id || el?.tagName) + ')'; })()`);
  const arrow = (k) => key(k, k, { ArrowDown: 40, ArrowUp: 38, ArrowLeft: 37, ArrowRight: 39, Home: 36, End: 35 }[k]);
  const letter = (ch) => page('Input.dispatchKeyEvent', { type: 'keyDown', key: ch, code: `Key${ch.toUpperCase()}`, text: ch })
    .then(() => page('Input.dispatchKeyEvent', { type: 'keyUp', key: ch, code: `Key${ch.toUpperCase()}` }));
  // Only rows that can take focus: an unrouted row is an `<a>` with no href.
  const order = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="admin-sidebar"] [data-nav-focus]')].filter(e => e.offsetParent !== null && ! e.closest('[hidden]') && (e.tagName === 'BUTTON' || e.hasAttribute('href'))).map(e => e.dataset.resource ?? e.getAttribute('aria-label') ?? e.textContent.trim()))`).then(JSON.parse);

  await eval_(`document.querySelector('#wire-admin-nav-filter').focus()`);
  await arrow('ArrowDown');
  const rows = await order();
  check('↓ from the filter enters the list at its first row', (await row()) === rows[0], `${await row()} vs ${rows[0]}`);

  await arrow('ArrowDown');
  check('↓ moves to the next row, across groups', (await row()) === rows[1], `${await row()} vs ${rows[1]}`);
  await arrow('ArrowUp');
  check('↑ moves back', (await row()) === rows[0], await row());
  await arrow('End');
  check('End goes to the last row', (await row()) === rows[rows.length - 1], await row());
  await arrow('Home');
  check('Home goes to the first', (await row()) === rows[0], await row());

  await letter('s');
  await letter('e');
  check('typing jumps to the row the letters start', (await row()) === 'setup', await row());
  await arrow('Home');
  await letter('t');
  check('a single letter jumps to the next row starting with it', (await row()) === 'tasks', await row());

  // A row with a submenu: → opens it, ↓ enters it, ← leaves the child for its
  // parent, and ← again closes it.
  await eval_(`document.querySelector('[data-testid="admin-nav-item"][data-resource="invoices"]').focus()`);
  const expanded = () => eval_(`document.querySelector('[data-testid="admin-nav-item"][data-resource="invoices"]').getAttribute('aria-expanded')`);
  if ((await expanded()) === 'true') await arrow('ArrowLeft');
  await arrow('ArrowRight');
  check('→ opens a submenu', (await expanded()) === 'true', await expanded());
  await sleep(150);   // x-show draws the opened list on Alpine's next tick
  await arrow('ArrowDown');
  check('↓ enters it', (await eval_(`!! document.activeElement?.closest('[data-nav-child]')`)) === true, await row());
  await arrow('ArrowLeft');
  await sleep(100);
  check('← on a child goes back to its parent', (await row()) === 'invoices', await row());
  await arrow('ArrowLeft');
  check('← on the parent closes it', (await expanded()) === 'false', await expanded());

  // ── 6. The bar (§ 6c) ────────────────────────────────────────────────────
  await page('Page.navigate', { url: `${origin}/previews/zoned/bar/tasks` });
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="admin-topnav"]')`);
  await sleep(400);

  const entry = () => eval_(`document.activeElement?.closest('[data-topnav-entry]')?.dataset?.topnavEntry ?? '(' + document.activeElement?.tagName + ')'`);
  await eval_(`document.querySelector('[data-topnav-entry] a, [data-topnav-entry] button').focus()`);
  const firstEntry = await entry();
  await arrow('ArrowRight');
  check('→ moves along the bar', (await entry()) !== firstEntry && (await entry()).startsWith('g-') || (await entry()).startsWith('i-'), `${firstEntry} → ${await entry()}`);
  await arrow('ArrowLeft');
  check('← moves back', (await entry()) === firstEntry, await entry());

  // Invoices: a lone entry with children, so a panel of four links.
  await eval_(`document.querySelector('[data-testid="admin-topnav-group"][data-group="invoices"]').focus()`);
  await arrow('ArrowDown');
  await sleep(300);
  const inPanel = () => eval_(`!! document.activeElement?.closest('[data-testid="admin-topnav-panel"]')`);
  check('↓ on a group opens it and focuses its first link', (await inPanel()) === true, await eval_('document.activeElement?.outerHTML?.slice(0, 80)'));
  const firstLink = await eval_('document.activeElement?.textContent?.trim()');
  await arrow('ArrowDown');
  check('↓ inside the panel moves to the next link', (await eval_('document.activeElement?.textContent?.trim()')) !== firstLink, firstLink);
  await key('Tab', 'Tab', 9);
  await sleep(200);
  check('Tab out of the panel closes it', (await eval_(`[...document.querySelectorAll('[data-testid="admin-topnav-panel"]')].every(p => p.style.display === 'none')`)) === true);

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
