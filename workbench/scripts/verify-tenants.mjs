import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Companies in the URL (ADR 0040), on the workbench's `tenants` zone: Acme and
 * Globex are the demo user's, Initech is not.
 *
 * What only a browser can say:
 *  - the bare address lands in a company and on its first page;
 *  - the switcher keeps the page, and on a record page lands on the list —
 *    a record of one company is not a record of another;
 *  - **a Livewire round trip stays in the company**: the rows a search returns
 *    are that company's, and the links built on that render point into it;
 *  - a company that is not yours is a 404, not a 403.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const base = '/previews/tenants';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}${base}`, shotPrefix: 'tenants', width: 1400, height: 1000 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const rows = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="table-row"]')].map(r => r.textContent.replace(/\\s+/g, ' ').trim()))`).then(JSON.parse);
const hasRow = (name) => rows().then((r) => r.some((text) => text.includes(name)));
const rowLinks = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="table-row"] a[href]')].map(a => new URL(a.href).pathname))`).then(JSON.parse);
const openSwitcher = async () => {
  await eval_(`document.querySelector('[data-testid="panels-tenant-switcher-trigger"]').click()`);
  await sleep(300);
};
const switchTo = async (label) => {
  await openSwitcher();
  await eval_(`[...document.querySelectorAll('[data-testid="panels-tenant-switcher-item"]')].find(a => a.textContent.includes(${JSON.stringify(label)})).click()`);
};

try {
  // ── 1. The bare address lands in a company, on its first page ───────────
  const landed = await waitFor(`location.pathname === '${base}/acme/projects' && !! document.querySelector('[data-testid="table-row"]')`);
  check('the bare address lands on the first page of the default company', !! landed, await eval_('location.pathname'));
  check('the list is that company\'s projects only', (await hasRow('Rocket skates')) && ! (await hasRow('Monorail')), JSON.stringify(await rows()));
  check('row links point into the company', (await rowLinks()).every((p) => p.startsWith(`${base}/acme/projects/`)), JSON.stringify(await rowLinks()));
  check('the menu entry links into the company', (await eval_(`document.querySelector('[data-testid="admin-nav-item"][data-resource="projects"]')?.getAttribute('href') ?? ''`)).includes('/acme/projects'));

  // ── 2. The switcher ──────────────────────────────────────────────────────
  check('the switcher names the current company', (await eval_(`document.querySelector('[data-testid="panels-tenant-switcher-trigger"]')?.textContent ?? ''`)).includes('Acme'));
  await openSwitcher();
  const offered = JSON.parse(await eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="panels-tenant-switcher-item"]')].map(a => a.textContent.trim()))`));
  // Acme and Globex are the demo user's, Initech is not. Compared by what must
  // and must not be there rather than the whole list: a company another driver
  // registered for the same user is also rightly offered.
  check('it offers the companies this person belongs to, and only those', offered.includes('Acme') && offered.includes('Globex') && ! offered.includes('Initech'), JSON.stringify(offered));
  await shot('01-switcher');
  await eval_(`document.body.click()`);

  await switchTo('Globex');
  const switched = await waitFor(`location.pathname === '${base}/globex/projects' && !! document.querySelector('[data-testid="table-row"]')`);
  check('switching keeps the page, in the other company', !! switched, await eval_('location.pathname'));
  check('and shows that company\'s rows', (await hasRow('Monorail')) && ! (await hasRow('Rocket skates')), JSON.stringify(await rows()));

  // ── 3. A Livewire round trip stays in the company ────────────────────────
  await eval_(`(() => {
    const el = document.querySelector('[data-testid="table-search"]');
    el.focus();
    el.value = 'Mono';
    el.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await waitFor(`document.querySelectorAll('[data-testid="table-row"]').length === 1`, { timeout: 8000 });

  check('a search is answered from the same company', (await hasRow('Monorail')) && (await rows()).length === 1, JSON.stringify(await rows()));
  check('and the links built on that render still point into it', (await rowLinks()).every((p) => p.startsWith(`${base}/globex/projects/`)) && (await rowLinks()).length > 0, JSON.stringify(await rowLinks()));
  await shot('02-round-trip');

  // ── 4. On a record page, switching lands on the list ─────────────────────
  const edit = (await rowLinks())[0];
  await page('Page.navigate', { url: `${origin}${edit}` });
  await waitFor(`location.pathname === '${edit}' && !! document.querySelector('[data-testid="panels-tenant-switcher-trigger"]')`);
  await switchTo('Acme');
  const listed = await waitFor(`location.pathname === '${base}/acme/projects'`);
  check('switching on a record page lands on the other company\'s list', !! listed, await eval_('location.pathname'));

  // ── 5. Another company's address is a 404 ────────────────────────────────
  const refused = await eval_(`fetch('${base}/initech/projects').then(r => r.status)`);
  check('a company that is not yours is a 404, not a 403', refused === 404, String(refused));

  await sleep(200);
  finish({ consoleErrors, badResponses: badResponses.filter((r) => ! r.includes('/initech/')), shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
