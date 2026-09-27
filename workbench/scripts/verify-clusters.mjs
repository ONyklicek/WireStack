import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * A cluster in a browser (ADR 0039): the Setup section of the admin zone, one
 * menu entry over two pages under `setup/`.
 *
 * Pest sees one page's markup. What only a browser can say:
 *
 *  - **the address lands somewhere.** `setup` renders nothing — its mount sends
 *    the viewer to the first member — so a broken redirect is an empty page
 *    that still answers 200 to a test reading headers.
 *  - **the column is a column.** The members sit beside the content from `lg`
 *    up through plain CSS keyed on `data-cluster-frame`; markup that is right
 *    with a stylesheet that is not puts the column *above* the heading, and
 *    only a measurement notices.
 *  - **it survives a `wire:navigate` hop** with the right member marked, and
 *    the menu still lit on the cluster's one entry.
 *  - **below `lg` it is tabs above the heading**, not a column nobody scrolls to.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const base = '/previews/zoned/admin';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}${base}/setup`, shotPrefix: 'clusters', width: 1400, height: 1000 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  const members = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="panels-cluster-nav-item"]')].map(e => e.dataset.member))`).then(JSON.parse);
  const current = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="panels-cluster-nav-item"][aria-current="page"]')].map(e => e.dataset.member))`).then(JSON.parse);
  const menuRows = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="admin-nav-item"]')].map(e => e.dataset.resource))`).then(JSON.parse);
  const setupRow = () => eval_(`document.querySelector('[data-testid="admin-nav-item"][data-resource="setup"]')?.dataset?.active ?? ''`);
  const rect = (selector) => eval_(`JSON.stringify(document.querySelector(${JSON.stringify(selector)})?.getBoundingClientRect() ?? null)`).then(JSON.parse);

  const clickAt = async (selector) => {
    const box = JSON.parse(await eval_(`(() => {
      const el = document.querySelector(${JSON.stringify(selector)});
      el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: box.x, y: box.y, button: 'left', clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: box.x, y: box.y, button: 'left', clickCount: 1 });
  };

  // ── 1. The cluster's address sends you to its first member ───────────────
  const landed = await waitFor(`location.pathname === '${base}/setup/general' && !! document.querySelector('[data-testid="setup-general-content"]')`);
  check('the cluster address redirects to the first member', !! landed, await eval_('location.pathname'));

  // ── 2. The menu shows the section once, lit ──────────────────────────────
  const rows = await menuRows();
  check('the menu has one entry for the whole section', rows.includes('setup') && ! rows.includes('general') && ! rows.includes('appearance'), rows.join(', '));
  check('and it is lit on a member page', (await setupRow()) === 'true', await setupRow());

  // ── 3. The members, beside the content ───────────────────────────────────
  check('the page draws the members in their order', JSON.stringify(await members()) === JSON.stringify(['general', 'appearance']), (await members()).join(' → '));
  check('exactly one member is the current page', JSON.stringify(await current()) === JSON.stringify(['general']), JSON.stringify(await current()));

  const nav = await rect('[data-testid="panels-cluster-nav"]');
  const heading = await rect('.wire-resource-page h1');
  check('the members are a column before the content', nav && heading && nav.right <= heading.left, JSON.stringify({ nav, heading }));
  check('and the column starts level with the heading', nav && heading && Math.abs(nav.top - heading.top) < 40, `${nav?.top} vs ${heading?.top}`);
  check('the column is taller than it is wide', nav && nav.height > 60 && nav.width < 260, JSON.stringify(nav));

  const crumb = await eval_(`document.querySelector('[data-testid="breadcrumbs"] a')?.getAttribute('href') ?? ''`);
  check('the trail starts at the cluster', new URL(crumb, origin).pathname === `${base}/setup`, crumb);
  await shot('01-general');

  // ── 4. A hop moves the mark and keeps the menu lit ───────────────────────
  await eval_('window.__clusterMarker = 1');
  await clickAt('[data-testid="panels-cluster-nav-item"][data-member="appearance"]');

  const hopped = await waitFor(`location.pathname === '${base}/setup/appearance' && !! document.querySelector('[data-testid="setup-appearance-content"]')`);
  check('a member link hops to the member', !! hopped, await eval_('location.pathname'));
  check('without a full reload', (await eval_('window.__clusterMarker === 1')) === true);
  check('the mark moved with it', JSON.stringify(await current()) === JSON.stringify(['appearance']), JSON.stringify(await current()));
  check('the menu entry is still lit', (await setupRow()) === 'true', await setupRow());
  await shot('02-appearance');

  // ── 5. Below lg: tabs above the heading ──────────────────────────────────
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await sleep(300);

  const narrowNav = await rect('[data-testid="panels-cluster-nav"]');
  const narrowHeading = await rect('.wire-resource-page h1');
  check('on a phone the members are a row above the heading', narrowNav && narrowHeading && narrowNav.bottom <= narrowHeading.top + 1, JSON.stringify({ narrowNav, narrowHeading }));
  check('and the row is wider than it is tall', narrowNav && narrowNav.width > narrowNav.height, JSON.stringify(narrowNav));
  await shot('03-phone');

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
