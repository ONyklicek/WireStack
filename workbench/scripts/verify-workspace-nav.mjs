import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * V2.6 step 1: the first consumer of `Workspace::navigation()`, in a browser.
 *
 * Until this page existed nothing in the repository rendered a menu — the
 * workspace was tested, documented, and never once drawn. That is exactly the
 * gap the V2.6 order was rewritten around (§0b): a group with an icon is a
 * property of a menu nobody draws, so it cannot fail anywhere.
 *
 * What only a browser can say here:
 *
 *  - the arrangement survives rendering. Grouping, ordering and the label
 *    fallback are three separate rules and all three are invisible in PHP once
 *    a Blade `@foreach` has had them.
 *  - the menu is a *navigation*. Its entries carry no URL by design, so the
 *    application maps a resource key to a page; a hop has to actually move the
 *    active entry and swap the table beside it.
 *  - it survives `wire:navigate`. The sidebar is server-rendered on every hop
 *    and the table on the far side needs the Alpine controllers registered on
 *    `alpine:init` — the failure mode ADR 0024 and verify-spa-navigate exist
 *    for. A menu that only works on a cold load is not a menu.
 *
 * The fixture is three registered resources across two groups, and the
 * declaration order in WorkbenchServiceProvider deliberately disagrees with the
 * sorted order, so an unsorted menu is a visible failure rather than an
 * identical one.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

// The menu the workbench declares, in the order it must be drawn in. One copy:
// this driver asserted it twice for a while, and the second copy is what went
// stale the moment a third group appeared.
const GROUP_KEYS = ['insights', 'operations', 'billing'];
const GROUP_HEADINGS = ['Insights', 'Operations', 'Billing & invoicing'];

// Enter on a resource, not on the shell's default page — the default is the
// dashboard, and section 7 needs somewhere to navigate *to*.
const page_ = await openPage({ url: `${base}/workspace/invoices`, shotPrefix: 'workspace-nav', width: 1400, height: 1000 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  const labels = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="workspace-nav-label"]')].map(e => e.textContent.trim()))`).then(JSON.parse);
  const headings = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="workspace-group-heading"]')].map(e => e.textContent.trim()))`).then(JSON.parse);
  const groupKeys = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="workspace-group"]')].map(e => e.dataset.group))`).then(JSON.parse);
  const groupIcons = () => eval_(`document.querySelectorAll('[data-testid="workspace-group-heading"] svg').length`);
  const inGroup = (group) => eval_(`JSON.stringify([...document.querySelector('[data-group="${group}"]').querySelectorAll('[data-testid="workspace-nav-label"]')].map(e => e.textContent.trim()))`).then(JSON.parse);
  const activeKey = () => eval_(`document.querySelector('[data-testid="workspace-nav-item"][data-active="true"]')?.dataset?.resource ?? ''`);
  const badgeOf = (key) => eval_(`document.querySelector('[data-resource="${key}"] [data-testid="workspace-nav-badge"]')?.textContent?.trim() ?? ''`);
  const badgeClassOf = (key) => eval_(`document.querySelector('[data-resource="${key}"] [data-testid="workspace-nav-badge"]')?.className ?? ''`);
  const rowCount = () => eval_(`document.querySelectorAll('[data-testid="table-row"]').length`);
  const hasColumn = (column) => eval_(`!! document.querySelector('[data-testid="table-cell-${column}"]')`);
  const search = (term) => eval_(`(() => {
    const el = document.querySelector('[data-testid="table-search"]');
    el.focus();
    el.value = ${JSON.stringify(term)};
    el.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);

  // A real mouse click, so wire:navigate's own click interception is what
  // handles it — a synthesised el.click() can take a path Livewire never sees.
  const clickEntry = async (key) => {
    const box = JSON.parse(await eval_(`(() => {
      const el = document.querySelector('[data-resource="${key}"]');
      el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: box.x, y: box.y, button: 'left', clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: box.x, y: box.y, button: 'left', clickCount: 1 });
  };

  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="workspace-nav"]')`);

  // ── 1. The arrangement, drawn ────────────────────────────────────────────
  //
  // Operations is declared with sort(10) and Billing with sort(20), while the
  // billing resource is the FIRST one registered. So this order can only come
  // from the declared group sort — an implicit menu would read the other way.
  const drawnKeys = await groupKeys();
  check('groups are drawn in their declared order, against registration order', JSON.stringify(drawnKeys) === JSON.stringify(GROUP_KEYS), drawnKeys.join(' → '));

  const drawnHeadings = await headings();
  check('the heading is the group\'s label, not its key', JSON.stringify(drawnHeadings) === JSON.stringify(GROUP_HEADINGS), drawnHeadings.join(' → '));
  check('a declared group draws its icon', (await groupIcons()) === GROUP_KEYS.length, `${await groupIcons()} icon(s)`);

  const drawnLabels = await labels();
  check('every entry is named', drawnLabels.length === 4 && drawnLabels.every((l) => l.length > 0), JSON.stringify(drawnLabels));

  // An entry whose key the application has no page for renders as plain text
  // rather than a link — silent, and exactly what a mismatched key produces.
  // Caught this during step 3: a dashboard keyed itself `operations` from its
  // class name and the url map said something else.
  const unlinked = await eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="workspace-nav-item"]')].filter(a => ! a.getAttribute('href')).map(a => a.dataset.resource))`).then(JSON.parse);
  check('every entry links somewhere', unlinked.length === 0, unlinked.join(', ') || 'none unlinked');

  // Two of the three resources name no menu label of their own; the workspace
  // names them after the resource. Before that fallback existed these rendered
  // as blank rows — which is what building this page found.
  check('an entry that named no label is named by its resource', drawnLabels.includes('Invoices') && drawnLabels.includes('Tasks'), JSON.stringify(drawnLabels));
  check('an entry that named itself keeps its own name', drawnLabels.includes('Files'), JSON.stringify(drawnLabels));

  const operations = await inGroup('operations');
  check('sort() orders inside a group, against the registration order', JSON.stringify(operations) === JSON.stringify(['Files', 'Tasks']), operations.join(' → '));

  // ── 2. Badges, and the colour a badge was declared with ──────────────────
  //
  // Counted rather than compared to a fixture: the workbench database is shared
  // by 70-odd drivers and several of them edit these very rows, so an exact
  // count would be a fixture assertion dressed up as a behaviour one.
  check('the invoices entry carries its overdue count', /^\d+$/.test(await badgeOf('invoices')), await badgeOf('invoices'));
  check('badge(…, \'danger\') reaches the canonical badge surface', (await badgeClassOf('invoices')).includes('bg-red-100'), await badgeClassOf('invoices'));
  check('the tasks entry carries its own count', /^\d+$/.test(await badgeOf('tasks')), await badgeOf('tasks'));
  check('an entry that declared no badge draws none', (await badgeOf('documents')) === '');

  // ── 2b. A menu entry that is not a resource at all ───────────────────────
  //
  // The dashboard reaches this menu through NavigationSource, from its own
  // registry, without Workspace (L1) ever importing Widgets (L2).
  const insights = await inGroup('insights');
  check('a dashboard sits in the menu beside the resources', JSON.stringify(insights) === JSON.stringify(['Overview']), insights.join(', '));

  // ── 3. The page you are on is the entry that is marked ───────────────────
  check('the current resource is the active entry', (await activeKey()) === 'invoices', await activeKey());
  check('the invoice table is what renders beside it', (await rowCount()) > 0 && (await hasColumn('number')), `${await rowCount()} rows, number column ${await hasColumn('number')}`);
  await shot('01-invoices');

  // ── 4. A wire:navigate hop between two resources ─────────────────────────
  await eval_('window.__wsMarker = 1');
  await clickEntry('tasks');

  const arrived = await waitFor(`location.pathname === '/previews/workspace/tasks' && document.querySelectorAll('[data-testid="table-cell-title"]').length > 0`);
  check('clicking an entry navigates to that resource', !! arrived, await eval_('location.pathname'));

  const kept = await eval_('window.__wsMarker === 1');
  check('the hop is a wire:navigate, not a document reload', kept === true);

  check('the active entry moved with it', (await activeKey()) === 'tasks', await activeKey());
  // The columns are the resource's, so a `title` column is how the page says
  // which resource it is showing without depending on a single row's contents.
  check('the task table renders on the far side', (await rowCount()) > 0 && (await hasColumn('owner_name')), `${await rowCount()} rows`);
  await shot('02-tasks');

  // ── 5. The far side is still a live table ────────────────────────────────
  //
  // The failure this guards: every client-side controller registers inside one
  // `alpine:init` listener, which fires once per document. A page arriving on a
  // wire:navigate has to work anyway.
  const searchable = await eval_(`!! document.querySelector('[data-testid="table-search"]')`);
  check('the search box came with it', searchable);

  const before = await rowCount();
  await search('zzz-matches-nothing');
  const emptied = await waitFor(`document.querySelectorAll('[data-testid="table-row"]').length === 0`, { timeout: 8000 });
  check('a Livewire roundtrip still works after the hop', !! emptied, `${before} → ${await rowCount()} row(s)`);

  await search('');
  const restored = await waitFor(`document.querySelectorAll('[data-testid="table-row"]').length === ${before}`, { timeout: 8000 });
  check('clearing the search brings the rows back', !! restored, `${await rowCount()} of ${before}`);

  const alive = await eval_(`(() => { try { window.Alpine.$data(document.querySelector('[data-testid="workspace-nav"]')); return 'ok'; } catch (e) { return String(e.message ?? e); } })()`);
  check('Alpine is still alive after the hop', alive === 'ok', alive);

  // ── 6. And on to the third resource ──────────────────────────────────────
  await clickEntry('documents');
  const onDocuments = await waitFor(`location.pathname === '/previews/workspace/documents' && !! document.querySelector('[data-testid="table-cell-tags"]')`);
  check('a second hop reaches the third resource', !! onDocuments, `${await eval_('location.pathname')}, ${await rowCount()} rows`);
  check('the menu still names every entry after two hops', (await labels()).every((l) => l.length > 0), JSON.stringify(await labels()));
  check('the badges survived the hops', /^\d+$/.test(await badgeOf('invoices')) && /^\d+$/.test(await badgeOf('tasks')), `${await badgeOf('invoices')} / ${await badgeOf('tasks')}`);
  check('so did the group order and the headings', JSON.stringify(await groupKeys()) === JSON.stringify(GROUP_KEYS) && JSON.stringify(await headings()) === JSON.stringify(GROUP_HEADINGS), (await headings()).join(' → '));
  await shot('03-documents');

  // ── 7. And the dashboard renders, from a declaration, not a component ────
  await clickEntry('overview');
  const onDashboard = await waitFor(`location.pathname === '/previews/workspace/overview' && !! document.querySelector('.wire-widget-grid')`);
  check('the dashboard entry opens the dashboard', !! onDashboard, await eval_('location.pathname'));

  const statLabels = await eval_(`JSON.stringify([...document.querySelectorAll('.wire-widget-grid')].map(g => g.innerText).join(' ').match(/Invoices|Overdue|Open tasks|Documents/g) ?? [])`).then(JSON.parse);
  check('its widgets are the ones the dashboard declared', ['Invoices', 'Overdue', 'Open tasks', 'Documents'].every((l) => statLabels.includes(l)), statLabels.join(', '));

  // Real counts, not fixed text: a dashboard of constants would render the same
  // whether or not the declaration was ever reached.
  const numbers = await eval_(`JSON.stringify((document.querySelector('.wire-widget-grid')?.innerText ?? '').match(/\\b\\d+\\b/g) ?? [])`).then(JSON.parse);
  check('and they carry counts from the database', numbers.length >= 4, numbers.join(', '));

  check('the menu still marks where you are', (await activeKey()) === 'overview', await activeKey());
  await shot('04-dashboard');

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
