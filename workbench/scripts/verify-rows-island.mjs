import { openPage, checker } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for the data-region island
 * (/previews/table-overview — sortable headers and a search box).
 *
 * The table, the stacked cards and the pagination footer live in
 * `@island('data-region', always: true)`. Two claims follow from that, and they
 * pull in opposite directions, so both are asserted here:
 *
 *   - anything fired from INSIDE the island targets it automatically. Livewire's
 *     JS walks up to the nearest island fragment (`closestIsland()`), so a sort,
 *     a page or a cell save needs no attribute and comes back as the region
 *     alone — the toolbar, the filter panels and the modals are neither rendered
 *     nor morphed;
 *   - anything fired from OUTSIDE it — the search box, a filter, a column toggle
 *     — must still re-render the region, or the rows go stale behind a toolbar
 *     that moved. That is what `always: true` is for.
 *
 * As with the modal island, the saving is invisible in the DOM: the table looks
 * the same either way. What proves it is the RESPONSE, so this reads the wire
 * payload rather than the page — while still checking the rows actually moved,
 * because a response that changes nothing would pass a payload-only test.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-rows-island.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-overview`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'rows-island', width: 1400, height: 1000, settle: 4000,
});

const { check, finish } = checker();

try {
  await eval_(`
    window.__wire = [];
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
      const target = typeof args[0] === 'string' ? args[0] : (args[0]?.url ?? '');
      const promise = originalFetch.apply(this, args);

      if (target.includes('/livewire')) {
        promise.then((response) => {
          response.clone().json().then((body) => {
            const componentBodies = body?.components ?? [];
            const fragments = componentBodies.flatMap((c) => c?.effects?.islandFragments ?? []);
            window.__wire.push({
              hasHtml: componentBodies.some((c) => c?.effects?.html !== undefined),
              islands: fragments.map((f) => (String(f).match(/name=([a-z-]+)/) ?? [])[1] ?? '?'),
              // The MARKUP each response carries. Total payload is the wrong
              // measure: the snapshot rides along either way and dwarfs the
              // difference, so comparing bodies would flatter or hide the saving
              // depending on the page.
              markup: componentBodies.reduce((n, c) => n + (c?.effects?.html?.length ?? 0), 0)
                + fragments.reduce((n, f) => n + String(f).length, 0),
              bytes: JSON.stringify(body).length,
            });
          }).catch(() => {});
        }).catch(() => {});
      }

      return promise;
    };

    window.$firstRow = () => document.querySelector('tbody [data-testid="table-cell-name"]')?.textContent?.trim() ?? null;
    window.$rowCount = () => document.querySelectorAll('tbody [data-testid="table-row"]').length;
    // A node OUTSIDE the island. Marking it proves whether the region's own
    // roundtrips reach it at all.
    window.$mark = () => {
      const el = document.querySelector('[data-testid="table-search"], input[type="search"]');
      el.__islandWitness = 'original';
      return !! el;
    };
    window.$witness = () => document.querySelector('[data-testid="table-search"], input[type="search"]')?.__islandWitness ?? null;
    true;
  `);

  const present = await eval_(`(() => {
    const markers = document.documentElement.innerHTML.match(/FRAGMENT:type=island\\|name=data-region/g) ?? [];
    return JSON.stringify({ markers: markers.length, marked: window.$mark(), rows: window.$rowCount() });
  })()`);

  const start = JSON.parse(present);

  check('the data region is an island in the document', start.markers >= 1, present);
  check('…with rows in it, and a search box outside it', start.rows > 0 && start.marked === true, present);

  await shot('01-before');

  // ── A sort comes back as the region alone ──────────────────────────
  const sorted = await eval_(`(async () => {
    const before = window.$firstRow();
    window.__wire = [];
    document.querySelector('[data-testid="table-sort-name"]').click();
    await new Promise((r) => setTimeout(r, 1400));

    return JSON.stringify({ before, after: window.$firstRow(), witness: window.$witness(), wire: window.__wire });
  })()`);

  const sort = JSON.parse(sorted);
  console.log('sort →', JSON.stringify(sort.wire));

  check('sorting re-orders the rows', sort.before !== sort.after && sort.after !== null,
    `${sort.before} -> ${sort.after}`);

  check('…and the response carries the region rather than the page',
    sort.wire.length > 0 && sort.wire.every((r) => r.hasHtml === false),
    JSON.stringify(sort.wire));

  check('…as a data-region island fragment',
    sort.wire.some((r) => r.islands.includes('data-region')), JSON.stringify(sort.wire));

  check('…leaving the toolbar outside it untouched', sort.witness === 'original', sorted);

  await shot('02-sorted');

  // ── A search from outside it still renders the region ──────────────
  // `always: true`. Without it this response would skip the island and the rows
  // would keep showing what the old search matched.
  const searched = await eval_(`(async () => {
    const before = window.$rowCount();
    window.__wire = [];

    const box = document.querySelector('[data-testid="table-search"], input[type="search"]');
    box.value = 'zzzznotarealrecord';
    box.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((r) => setTimeout(r, 2200));

    return JSON.stringify({ before, after: window.$rowCount(), wire: window.__wire });
  })()`);

  const search = JSON.parse(searched);
  console.log('search →', JSON.stringify(search.wire));

  check('a search from outside the island still empties the rows',
    search.before > 0 && search.after === 0, searched);

  check('…because that path renders the component, as it must',
    search.wire.some((r) => r.hasHtml), JSON.stringify(search.wire));

  await shot('03-searched');

  // ── What the island is worth, measured like for like ───────────────
  // Clearing the search is a full render of the SAME rows the sort produced, so
  // the two markup sizes are comparable — the difference is the chrome the
  // targeted call did not have to render or morph. Comparing against the search
  // response instead would compare a page holding four rows with one holding
  // none, and report a saving that is really the rows.
  const restored = await eval_(`(async () => {
    window.__wire = [];
    const box = document.querySelector('[data-testid="table-search"], input[type="search"]');
    box.value = '';
    box.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise((r) => setTimeout(r, 2200));

    return JSON.stringify({ rows: window.$rowCount(), wire: window.__wire });
  })()`);

  const full = JSON.parse(restored);
  console.log('restored →', JSON.stringify(full.wire));

  check('clearing the search brings the same rows back', full.rows === start.rows, restored);

  const targeted = Math.min(...sort.wire.map((r) => r.markup));
  const whole = Math.max(...full.wire.map((r) => r.markup));

  check('the targeted render carries less markup than the full one, on the same rows',
    targeted < whole, `region=${targeted}B full=${whole}B saved=${whole - targeted}B (${Math.round((1 - targeted / whole) * 100)}%)`);
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
