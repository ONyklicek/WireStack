import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * A record's own pages, in a browser: the tabs above an invoice, and the one
 * thing only a browser can say about them.
 *
 * Pest sees the markup of one page at a time. What it cannot see is the pair of
 * rules that only exist *between* two surfaces on one screen:
 *
 *  - **exactly one `aria-current="page"` in the document.** The menu row for
 *    Invoices stays active on a record's page — you are inside that resource —
 *    and the tab above the form is the page you are actually on. Both used to
 *    claim `page`, which is two answers to one question, and no unit test of
 *    either file alone can notice.
 *  - **the tab moves on a `wire:navigate` hop.** The current tab is read from
 *    the route name at mount and carried in a public property (ADR 0027); a
 *    version that re-derived it per render marks the right tab on the first
 *    paint and none afterwards — which is precisely what a SPA hop produces.
 *
 * The fixture is the workbench's own routed resource. `previews/routed/invoices`
 * declares `index`, `create`, `view`, `edit` and `history` — and the demo user
 * is the first seeded one, which does **not** hold `invoices.update`. So this
 * server draws `view` and `history` and leaves `edit` out, which is the third
 * thing worth driving: the tab bar asks the same ability the route is guarded
 * by, and `previews/routed/invoices/1/edit` answers 403 to this very browser.
 *
 * `history` is the other half of that fixture: a page the framework does not
 * know, at a URI of the application's own, in the bar because its URI carries
 * `{record}`.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/previews/routed/invoices/1`, shotPrefix: 'record-subnav', width: 1400, height: 1000 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  const tabs = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="panels-sub-nav-item"]')].map(e => e.dataset.page))`).then(JSON.parse);
  const tabLabels = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="panels-sub-nav-item"]')].map(e => e.textContent.trim()))`).then(JSON.parse);
  const activeTab = () => eval_(`document.querySelector('[data-testid="panels-sub-nav-item"][data-active="true"]')?.dataset?.page ?? ''`);
  // Scoped to the two surfaces that used to disagree. The breadcrumb's last
  // crumb says `page` too and is right to — it names the page you are on, in a
  // trail, which is the pattern's own convention. The menu is the one that must
  // not: its row stands for the resource, not for this page of it.
  const menuCurrent = () => eval_(`document.querySelectorAll('[data-testid="admin-sidebar"] [aria-current="page"]').length`);
  const tabCurrent = () => eval_(`document.querySelectorAll('[data-testid="panels-sub-nav-item"][aria-current="page"]').length`);
  const menuClaims = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="admin-sidebar"] [aria-current="page"]')].map(e => new URL(e.getAttribute('href') ?? '', location.origin).pathname))`).then(JSON.parse);
  const menuRow = () => eval_(`(() => {
    const el = document.querySelector('[data-testid="admin-nav-item"][data-resource="invoices"]');

    return JSON.stringify({ active: el?.dataset?.active ?? '', current: el?.getAttribute('aria-current') ?? '' });
  })()`).then(JSON.parse);

  // A real mouse click, so wire:navigate's own click interception handles it —
  // a synthesised el.click() can take a path Livewire never sees.
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

  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="panels-sub-nav"]')`);

  // ── 1. The tabs are the record's pages, and only those ───────────────────
  const drawn = await tabs();
  check('a record page draws a tab per page of that record', JSON.stringify(drawn) === JSON.stringify(['view', 'history']), drawn.join(' → '));
  check('the list and the create screen are not tabs', ! drawn.includes('index') && ! drawn.includes('create'), drawn.join(' → '));
  check('a page of the application\'s own is a tab, because its URI takes a record', drawn.includes('history'), drawn.join(' → '));

  // The same ability the route is guarded by, asked before the link is drawn —
  // and the 403 below is this browser being told so by the router.
  check('a page this reader may not open is left out', ! drawn.includes('edit'), drawn.join(' → '));
  const edit = await eval_(`fetch('/previews/routed/invoices/1/edit').then(r => r.status)`);
  check('and the route refuses the same reader', edit === 403, String(edit));

  const labels = await tabLabels();
  check('every tab is named', labels.length === drawn.length && labels.every((l) => l.length > 0), JSON.stringify(labels));
  check('no tab is an unresolved translation key', labels.every((l) => ! l.includes('::')), JSON.stringify(labels));

  check('the tab you are standing on is marked', (await activeTab()) === 'view', await activeTab());
  await shot('01-view');

  // ── 2. One `aria-current="page"` on the whole screen ─────────────────────
  //
  // The rule that needs two surfaces at once. The menu row is active — you are
  // inside Invoices — without claiming to be the page you are on.
  const row = await menuRow();
  check('the menu row stays active on a record page', row.active === 'true', JSON.stringify(row));
  check('and says `true` rather than `page`', row.current === 'true', JSON.stringify(row));
  check('so nothing in the menu claims to be the current page', (await menuCurrent()) === 0, `${await menuCurrent()} element(s)`);
  check('and exactly one tab does', (await tabCurrent()) === 1, `${await tabCurrent()} tab(s)`);

  // ── 3. The hop, which is where the current tab used to be lost ───────────
  await eval_('window.__subnavMarker = 1');
  await clickAt('[data-testid="panels-sub-nav-item"][data-page="history"]');

  const arrived = await waitFor(`location.pathname === '/previews/routed/invoices/1/history' && !! document.querySelector('[data-testid="invoice-history"]')`);
  check('a tab navigates to that page of the record', !! arrived, await eval_('location.pathname'));
  check('and does it as a wire:navigate, not a document reload', (await eval_('window.__subnavMarker === 1')) === true);

  check('the marked tab moved with it', (await activeTab()) === 'history', await activeTab());
  check('the tabs are still both there', JSON.stringify(await tabs()) === JSON.stringify(['view', 'history']), (await tabs()).join(' → '));
  check('and exactly one tab is still the current page', (await tabCurrent()) === 1, `${await tabCurrent()} tab(s)`);
  check('with the menu still not claiming it', (await menuCurrent()) === 0, `${await menuCurrent()} element(s)`);
  check('the menu row is still where you are, not what you are on', (await menuRow()).current === 'true', JSON.stringify(await menuRow()));
  await shot('02-history');

  // ── 4. The list has no record, and therefore no tabs ─────────────────────
  //
  // Reached through the breadcrumb, which is the trail this bar sits under —
  // the two answer different questions and must not draw each other's rows.
  await clickAt('[data-testid="breadcrumbs"] a');

  const onList = await waitFor(`location.pathname === '/previews/routed/invoices' && !! document.querySelector('[data-testid="table-row"]')`);
  check('the breadcrumb leads back to the list', !! onList, await eval_('location.pathname'));
  check('a page that shows no record draws no tab bar', (await eval_(`!! document.querySelector('[data-testid="panels-sub-nav"]')`)) === false);
  // And on the list the claim appears in the menu — but on the row that can
  // actually take you there. This entry declares a submenu, so its top row is a
  // disclosure `<button>`: it says it is the branch you are in, and the child
  // that links here says it is the page. A button claiming `page` would be
  // claiming that pressing it lands you where you already are.
  check('the menu row is the branch you are in', (await menuRow()).current === 'true', JSON.stringify(await menuRow()));

  const claims = await menuClaims();
  check(
    'and the claim in the menu is a row that points at this very page',
    claims.length === 1 && claims[0] === '/previews/routed/invoices',
    JSON.stringify(claims),
  );
  await shot('03-list');

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
