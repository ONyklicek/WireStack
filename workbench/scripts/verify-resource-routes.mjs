import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Route::wireResources() — a resource's declared pages, reached as real routes.
 *
 * The pages themselves already have a driver (verify-resource-pages). What is
 * new here is the *route*: a full-page Livewire component behind a URL rather
 * than a component the preview shell mounts. Three things only a browser can
 * answer about that:
 *
 *   - the layout resolves. A full-page component needs one, and the framework
 *     deliberately does not supply it — getting the config key wrong fails with
 *     "No hint path defined for [layouts]", which reads like a missing view.
 *   - Livewire still round-trips from that URL. The update endpoint is the same,
 *     but the page arrived a different way, and a table that renders once and
 *     then cannot search is a table nobody can use.
 *   - the permission declared on one page reaches its route. `RoutePage::permission()`
 *     lands as Laravel's own `can:` middleware, so an unauthenticated visitor is
 *     refused *by the router* — before any of this framework's code runs.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const routed = `${base}/routed/invoices`;
const { check, finish } = checker();

const page_ = await openPage({ url: routed, shotPrefix: 'resource-routes', width: 1300, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  const rows = () => eval_(`document.querySelectorAll('[data-testid="table-row"]').length`);
  const status = (path) => eval_(`fetch('${path}', { headers: { 'Accept': 'text/html' } }).then(r => r.status)`);

  await waitFor(`!! window.Alpine && document.querySelectorAll('[data-testid="table-row"]').length > 0`);

  // ── 1. The route renders the page, inside the application's layout ───────
  check('the index route renders the resource table', (await rows()) > 0, `${await rows()} rows`);
  // The layout the workbench names is `wire-admin`'s shell, so "inside the
  // application's layout" now means the chrome around the page is really there:
  // the sidebar over Workspace and the brand the application put in a slot. It
  // used to assert a body class, which stopped meaning anything the moment the
  // frame moved into a package.
  check('it came up inside the application shell', await eval_(`!! document.querySelector('[data-testid="admin-sidebar"]')`));
  check('the shell shows what the application put in its slots', (await eval_(`document.querySelector('[data-testid="admin-brand"]')?.innerText?.trim() ?? ''`)) === 'Wire Workbench');
  check('the menu entry for the page being shown is the active one', await eval_(`!! document.querySelector('[data-testid="admin-nav-item"][data-resource="invoices"][data-active="true"]')`));
  check('the heading is the resource plural', (await eval_(`document.querySelector('h1')?.innerText?.trim() ?? ''`)) === 'Invoices');
  await shot('01-index');

  // ── 2. Livewire round-trips from a routed URL ────────────────────────────
  const before = await rows();

  await eval_(`(() => {
    const el = document.querySelector('[data-testid="table-search"]');
    el.focus();
    el.value = 'zzz-matches-nothing';
    el.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);

  const emptied = await waitFor(`document.querySelectorAll('[data-testid="table-row"]').length === 0`, { timeout: 8000 });
  check('a Livewire round trip works from the routed page', !! emptied, `${before} → ${await rows()}`);

  await eval_(`(() => {
    const el = document.querySelector('[data-testid="table-search"]');
    el.focus();
    el.value = '';
    el.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await waitFor(`document.querySelectorAll('[data-testid="table-row"]').length === ${before}`, { timeout: 8000 });

  // ── 3. The other page kinds are routed too ───────────────────────────────
  check('create is routed', (await status(`${routed}/create`)) === 200);
  check('view is routed, with the record as a key', (await status(`${routed}/1`)) === 200);

  // ── 4. …and the one behind a permission is refused by the router ─────────
  //
  // Nobody is signed in here, so `can:invoices.update` denies. The point is not
  // that it denies — it is that the refusal comes from the route, so no page
  // renders and no query runs before the answer.
  check('a page declaring a permission is refused when it is not held', (await status(`${routed}/1/edit`)) === 403);

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
