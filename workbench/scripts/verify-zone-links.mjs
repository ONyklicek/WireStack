import { openPage, checker } from './lib/cdp.mjs';

/*
 * Zones (ADR 0027), in the only place the design can actually be checked.
 *
 * The same invoices resource is mounted twice — `previews/zoned/business` and
 * `previews/zoned/admin` — and the shell layout carries the command palette. A
 * result's URL is not written anywhere: it is built from the resource key, the
 * record key and the **zone**, and the zone is read once while the page renders
 * and then carried in the component's snapshot.
 *
 * That last part is the whole reason this driver exists. `Route::currentRouteName()`
 * answers `livewire.update` on a round trip, and the palette searches on every
 * keystroke — so an implementation that re-derived the zone would render exactly
 * the same page, return exactly the same rows, and send the user out of their
 * zone on Enter. Pest sees the markup; only a browser presses Enter.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

// One browser, navigated twice, rather than two `openPage()` calls: the shared
// helper takes its DevTools port from a fixed default, so a second launch inside
// one driver races the first one's shutdown and dies with "Received network
// error or non-101 status code" — a failure that reads like the page, not like
// the harness.
const {
  page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close,
} = await openPage({
  url: `${base}/zoned/business/invoices`,
  shotPrefix: 'zone-links',
  width: 1280,
  height: 900,
});

async function followFrom(zone) {
  await page('Page.navigate', { url: `${base}/zoned/${zone}/invoices` });
  await waitFor(
    `location.pathname === '/previews/zoned/${zone}/invoices'
      && !! window.Alpine
      && !! document.querySelector('[data-testid="global-search-trigger"]')`,
    `the ${zone} page to boot`,
  );

  // The zone the palette read while the page rendered. Asserted from the
  // component's own state rather than inferred from the link, so a failure
  // says which half broke.
  const carried = await eval_(`(() => {
    const el = document.querySelector('[data-testid="global-search"]')?.closest('[wire\\\\:id]')
      ?? document.querySelector('[wire\\\\:id]');
    return el ? el.getAttribute('wire:id') !== null : false;
  })()`);
  check(`[${zone}] the palette is mounted in the shell`, carried === true);

  await eval_(`document.querySelector('[data-testid="global-search-trigger"]').click()`);
  await waitFor(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return !! el && getComputedStyle(el).display !== 'none';
  })()`, 'the palette to open');

  // Typing is a Livewire request — the one where the zone must already be
  // carried rather than asked for.
  await eval_(`(() => {
    const input = document.querySelector('[data-testid="global-search-input"]');
    input.focus();
    input.value = 'INV';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);

  const rows = await waitFor(
    `document.querySelectorAll('[data-testid="global-search-result"]').length > 0`,
    'results from the search request',
  );
  check(`[${zone}] typing returns results`, rows);
  await shot(`${zone}-01-results`);

  await eval_(`document.querySelector('[data-testid="global-search-result"]').click()`);

  const landed = await waitFor(
    `location.pathname.startsWith('/previews/zoned/${zone}/invoices/')`,
    `a record page inside the ${zone} zone`,
  );
  check(
    `[${zone}] the palette sends the user back into its own zone`,
    landed,
    await eval_('location.pathname'),
  );
  await shot(`${zone}-02-followed`);

}

/**
 * The menu of both zones, in both modes (ADR 0027 open question 2).
 *
 * Server-rendered markup, so a Pest test could read it — but it is on this page
 * that the decision was made, and a preview nobody drives is a preview that
 * quietly stops matching the rule it exists to show.
 */
async function menus() {
  await page('Page.navigate', { url: `${base}/zones` });
  await waitFor(`!! document.querySelector('[data-testid="zone-menus"]')`, 'the zones page');

  const read = await eval_(`(() => Object.fromEntries(
    [...document.querySelectorAll('[data-testid="zone-menu"]')].map((el) => [
      el.dataset.zone,
      [...el.querySelectorAll('[data-testid="zone-nav-item"]')]
        .map((a) => a.dataset.key + (a.dataset.linked === 'true' ? '*' : '')),
    ]),
  ))()`);

  check(
    'a zone links what it routes and greys out what it does not',
    JSON.stringify(read['business · as registered']) === JSON.stringify(['overview*', 'documents', 'tasks', 'invoices*']),
    JSON.stringify(read['business · as registered']),
  );
  check(
    'linkedOnly keeps only what this zone can reach',
    JSON.stringify(read['business · linkedOnly']) === JSON.stringify(['overview*', 'invoices*']),
    JSON.stringify(read['business · linkedOnly']),
  );
  check(
    'a second zone reaches more, from the same registration',
    JSON.stringify(read['admin · linkedOnly']) === JSON.stringify(['overview*', 'tasks*', 'invoices*']),
    JSON.stringify(read['admin · linkedOnly']),
  );

  await shot('03-menus');
}

/**
 * The zone's landing page: the bare prefix is a page, not a 404.
 *
 * `routePrefix()` of `ConfiguresRoutes::ROOT` adds no segment, so the dashboard's
 * index lands on the group's own path. Only a request can tell the difference
 * between "registered at the root" and "registered at the root of something
 * else" — a unit test reads the URI either way.
 */
async function landings() {
  for (const zone of ['business', 'admin']) {
    await page('Page.navigate', { url: `${base}/zoned/${zone}` });
    const ok = await waitFor(
      `location.pathname === '/previews/zoned/${zone}'
        && !! document.body
        && document.body.innerText.trim().length > 0`,
      `the ${zone} landing page`,
    );
    check(`[${zone}] the bare zone prefix is its landing page`, ok, await eval_('location.pathname'));
  }

  await shot('04-landing');
}

try {
  // Both zones, because one alone cannot fail the way this is meant to catch: a
  // hardcoded or re-derived zone still lands correctly in whichever one happens
  // to own the unprefixed route name.
  await followFrom('business');
  await followFrom('admin');
  await menus();
  await landings();
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
