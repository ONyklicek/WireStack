import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * The account menu and the collapse button at the foot of the menu column
 * (`wire-admin.layout.user` / `rail_toggle` => 'sidebar'), on
 * /previews/routed/invoices?chrome=sidebar.
 *
 * Only a browser can say this: the account menu opens upwards and is wholly on
 * screen, the rail keeps the account on the axis of the icons above it and its
 * menu slides out over the page, the button at the foot collapses the menu and
 * ⌘B still does, and on a phone the account is reachable from the drawer.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const url = `${base}/routed/invoices?chrome=sidebar`;
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({ url, shotPrefix: 'admin-placement', width: 1300, height: 900 });
const { check, finish } = checker();

const FOOT = '[data-testid="admin-sidebar-footer"]';
const USER = `${FOOT} [data-testid="admin-user"]`;
const TOGGLE = `${FOOT} [data-testid="admin-rail-toggle"]`;
const rect = (selector) => `(() => { const r = document.querySelector('${selector}').getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width }; })()`;
const openPanel = `[...document.querySelectorAll('[x-ref=panel]')].find((p) => p.style.display !== 'none' && p.querySelector('[data-testid=chrome-probe], a, button, form') && p.getBoundingClientRect().width > 0)`;
const panelRect = `(() => { const p = ${openPanel}; if (! p) return null; const r = p.getBoundingClientRect(); return { left: r.left, right: r.right, top: r.top, bottom: r.bottom }; })()`;
const railWidth = `document.querySelector('[data-testid="admin-sidebar"]').offsetWidth`;

try {
  await waitFor(`!! document.querySelector('${USER}') && !! window.Alpine`);
  // Start expanded, whatever an earlier run left in this browser.
  if ((await eval_(railWidth)) < 100) await eval_(`document.querySelector('${TOGGLE}').click()`);
  await waitFor(`${railWidth} > 200`);

  check('the account and the collapse button sit at the foot of the menu, not in the top bar',
    (await eval_(`!! document.querySelector('${USER}') && !! document.querySelector('${TOGGLE}') && ! document.querySelector('header[data-wire="admin-topbar"] [data-testid="admin-user"]')`)) === true);

  // ── The account menu opens upwards, wholly on screen ──────────────────
  await eval_(`document.querySelector('${USER}').click()`);
  await waitFor(`!! (${openPanel})`).catch(() => {});
  await sleep(250);
  const up = { trigger: await eval_(rect(USER)), panel: await eval_(panelRect), vh: await eval_('window.innerHeight') };
  check('the account menu opens upwards and is wholly on screen',
    !! up.panel && up.panel.bottom <= up.trigger.top + 1 && up.panel.top >= 0 && up.panel.bottom <= up.vh, JSON.stringify(up));
  await shot('01-menu-up');
  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await sleep(250);

  // ── Collapse from the foot ─────────────────────────────────────────────
  await eval_(`document.querySelector('${TOGGLE}').click()`);
  await waitFor(`${railWidth} < 100`).catch(() => {});
  await sleep(350);
  check('the button at the foot collapses the menu into its rail', (await eval_(railWidth)) < 100, String(await eval_(railWidth)));

  const axis = await eval_(`(() => {
    const aside = document.querySelector('[data-testid="admin-sidebar"]').getBoundingClientRect();
    const avatar = document.querySelector('${USER}').querySelector('img, span, svg').getBoundingClientRect();
    return { aside: aside.left + aside.width / 2, avatar: avatar.left + avatar.width / 2 };
  })()`);
  check('in the rail the account sits on the axis of the icons above it', Math.abs(axis.aside - axis.avatar) <= 2, JSON.stringify(axis));

  await eval_(`document.querySelector('${USER}').click()`);
  await waitFor(`!! (${openPanel})`).catch(() => {});
  await sleep(250);
  const out = { panel: await eval_(panelRect), vw: await eval_('window.innerWidth') };
  check('from the rail the menu slides out over the page and stays on screen',
    !! out.panel && out.panel.left >= 0 && out.panel.right <= out.vw && out.panel.right > 64 * 2, JSON.stringify(out));
  await shot('02-rail-menu');
  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await sleep(250);

  // ── ⌘B, from the page ──────────────────────────────────────────────────
  await eval_(`document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 'b', metaKey: true, ctrlKey: true, bubbles: true, cancelable: true }))`);
  await waitFor(`${railWidth} > 200`).catch(() => {});
  check('⌘B still expands it, though its button moved', (await eval_(railWidth)) > 200, String(await eval_(railWidth)));

  // ── Phone: the account is in the drawer ───────────────────────────────
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await sleep(400);
  await eval_(`document.querySelector('[data-testid="admin-sidebar-toggle"]').click()`);
  await sleep(500);
  const phone = await eval_(`(() => { const r = document.querySelector('${USER}').getBoundingClientRect(); return { visible: r.width > 0 && r.left >= 0 && r.right <= window.innerWidth && r.bottom <= window.innerHeight, toggle: document.querySelector('${TOGGLE}').getBoundingClientRect().width }; })()`);
  check('on a phone the account is reachable from the drawer', phone.visible === true, JSON.stringify(phone));
  check('…and the collapse button, which a phone has no use for, is not drawn there', phone.toggle === 0, JSON.stringify(phone));
  await shot('03-phone-drawer');
} catch (error) {
  check('driver ran to completion', false, error.message);
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
