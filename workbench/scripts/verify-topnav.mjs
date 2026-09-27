import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * The menu as a bar under the header (navigation-surfaces.md § 4), on the
 * workbench's `bar` zone — the admin zone's pages with `layout.navigation` set
 * to `top`.
 *
 * What only a browser can say:
 *  - a group's panel opens **downwards**, below the bar, and Escape closes it
 *    and gives focus back to the button;
 *  - what does not fit moves under "More" and is **nowhere twice** — hidden in
 *    the bar exactly when it is shown in the panel;
 *  - below `lg` the bar is gone and the drawer is the menu.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/previews/zoned/bar/tasks`, shotPrefix: 'topnav', width: 1600, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const shown = (selector) => eval_(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); return !! el && el.offsetParent !== null && getComputedStyle(el).visibility !== 'hidden'; })()`);
const rect = (selector) => eval_(`JSON.stringify(document.querySelector(${JSON.stringify(selector)})?.getBoundingClientRect() ?? null)`).then(JSON.parse);
const inBar = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-topnav-entry]')].filter(e => ! e.hidden).map(e => e.dataset.topnavEntry))`).then(JSON.parse);
const inMore = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-topnav-more-for]')].filter(e => e.style.display !== 'none').map(e => e.dataset.topnavMoreFor))`).then(JSON.parse);
const resize = async (width, height = 900) => {
  await page('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 640 });
  await sleep(400);
};
const key = (k, code, vk) => page('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: k, code, windowsVirtualKeyCode: vk })
  .then(() => page('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code, windowsVirtualKeyCode: vk }));

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="admin-topnav"]')`);
  await sleep(300);

  // ── 1. The bar is the menu; the column is gone ───────────────────────────
  check('the bar is drawn under the header', await shown('[data-testid="admin-topnav"]'));
  check('the column is not drawn beside the page', ! (await shown('[data-testid="admin-sidebar"]')));
  check('there is no rail toggle to press', ! (await eval_(`!! document.querySelector('[data-testid="admin-rail-toggle"]')`)));
  check('the brand sits in the header instead', await shown('[data-testid="admin-topbar-brand"]'));

  const all = await inBar();
  check('everything fits on a wide screen', all.length > 3 && (await inMore()).length === 0, JSON.stringify(all));
  check('and More is out of the way', ! (await shown('[data-testid="admin-topnav-more"]')));
  await shot('01-wide');

  // ── 2. A group opens downwards, and Escape gives the button focus back ───
  const trigger = await rect('[data-testid="admin-topnav-group"]');
  await eval_(`document.querySelector('[data-testid="admin-topnav-group"]').focus(); document.querySelector('[data-testid="admin-topnav-group"]').click()`);
  await waitFor(`[...document.querySelectorAll('[data-testid="admin-topnav-panel"]')].some(p => p.style.display !== 'none')`);
  await sleep(200);

  const panel = JSON.parse(await eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="admin-topnav-panel"]')].find(p => p.style.display !== 'none').getBoundingClientRect())`));
  check('a group opens a panel below its button', panel.top >= trigger.bottom, `${panel.top} vs ${trigger.bottom}`);
  await shot('02-group');

  await key('Escape', 'Escape', 27);
  await sleep(200);
  check('Escape closes it', (await eval_(`[...document.querySelectorAll('[data-testid="admin-topnav-panel"]')].every(p => p.style.display === 'none')`)) === true);
  check('and gives focus back to the button', (await eval_(`document.activeElement?.dataset?.testid`)) === 'admin-topnav-group', await eval_('document.activeElement?.dataset?.testid'));

  // ── 3. What does not fit goes under More, and is nowhere twice ───────────
  // The workbench menu fits any lg-wide window, so the bar is narrowed the way a
  // wider brand or a longer label would narrow it: the list itself gets less
  // room, and the ResizeObserver has to notice.
  await eval_(`document.querySelector('[data-testid="admin-topnav"] ul').style.maxWidth = '380px'`);
  await waitFor(`[...document.querySelectorAll('[data-topnav-entry]')].some(e => e.hidden)`);
  await sleep(200);

  const bar2 = await inBar();
  const more = await inMore();
  check('a narrower window moves entries under More', more.length > 0, JSON.stringify({ bar2, more }));
  check('More is there to open', await shown('[data-testid="admin-topnav-more"]'));
  check('no entry is both in the bar and under More', bar2.every((id) => ! more.includes(id)), JSON.stringify({ bar2, more }));
  check('and none is lost', bar2.length + more.length === all.length, `${bar2.length} + ${more.length} of ${all.length}`);
  check('the bar keeps the menu order — the overflow is its tail', JSON.stringify([...bar2, ...more]) === JSON.stringify(all), JSON.stringify([...bar2, ...more]));
  check('the bar did not wrap onto a second line', (await rect('[data-testid="admin-topnav"]')).height < 60);
  await shot('03-narrow');

  await eval_(`document.querySelector('[data-testid="admin-topnav-more"]').click()`);
  await sleep(300);
  check('More shows the entries that left the bar', (await eval_(`document.querySelector('[data-testid="admin-topnav-more-panel"]').style.display`)) !== 'none');
  await key('Escape', 'Escape', 27);

  await eval_(`document.querySelector('[data-testid="admin-topnav"] ul').style.maxWidth = ''`);
  await waitFor(`[...document.querySelectorAll('[data-topnav-entry]')].every(e => ! e.hidden)`);
  check('widening brings them back', (await inMore()).length === 0, JSON.stringify(await inMore()));

  // ── 4. On a phone the drawer is the menu ─────────────────────────────────
  await resize(390, 844);
  check('the bar is not drawn on a phone', ! (await shown('[data-testid="admin-topnav"]')));
  await eval_(`document.querySelector('[data-testid="admin-sidebar-toggle"]').click()`);
  await sleep(400);
  check('the drawer opens with the menu', await shown('[data-testid="admin-sidebar"] [data-testid="admin-nav-item"]'));
  await shot('04-phone');

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
