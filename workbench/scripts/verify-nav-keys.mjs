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

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
