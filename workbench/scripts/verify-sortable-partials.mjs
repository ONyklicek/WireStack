import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for row reordering × `Table::rowPartials()`
 * (/previews/sortable-partials).
 *
 * The drag handle `<td>` is not server markup. `sortable.js` creates it and
 * prepends it to every `<tr>`, and rebuilds it from Livewire's `morph.updated`
 * hook whenever the rows are patched. A row partial is morphed by
 * `partials.js`, which calls `Alpine.morph()` with its own config and never
 * reaches that hook — so an inline save in reorder mode replaced a three-cell
 * row with the server's two-cell one and left the handle gone, with nothing to
 * put it back. The row stayed in the DOM and looked fine; it just could not be
 * dragged any more, and only for the rows someone had edited.
 *
 * Pest cannot see any of this: the server markup it reads never had the handle
 * in it, and the loss happens in a morph.
 *
 * What is checked, in order:
 *   - the handle exists at all before the write (the fixture is what it claims);
 *   - the edited row really re-rendered — read off the cell's sync node, which
 *     carries what the SERVER last said, so it moves only if the partial's
 *     markup was morphed in;
 *   - the write came back as a partial, not as the page (otherwise Livewire's
 *     own morph would have fired the hook and the bug would be untestable here);
 *   - the handle is still on the edited row afterwards, and on every other row;
 *   - the handle still answers a drag — present but detached from SortableJS
 *     would look identical in the DOM.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-sortable-partials.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/sortable-partials`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'sortable-partials', width: 1400, height: 1000, settle: 4000,
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
            const partials = componentBodies.reduce((acc, c) => ({ ...acc, ...(c?.effects?.wirePartials ?? {}) }), {});
            window.__wire.push({
              hasHtml: componentBodies.some((c) => c?.effects?.html !== undefined),
              partials: Object.keys(partials),
            });
          }).catch(() => {});
        }).catch(() => {});
      }

      return promise;
    };

    window.$rows = () => Array.from(document.querySelectorAll('tbody tr[wire\\\\:partial]'));
    window.$handles = () => Array.from(document.querySelectorAll('tbody .wire-sortable-handle-cell'));
    window.$cellIn = (row) => row.querySelector('[data-record-key][data-column-name] input[type="text"], [data-record-key][data-column-name] input:not([type])');
    true;
  `);

  // ── The fixture ────────────────────────────────────────────────────
  const start = await eval_(`(() => {
    const rows = window.$rows();
    return JSON.stringify({
      rows: rows.length,
      handles: window.$handles().length,
      cellsInFirstRow: rows[0] ? rows[0].querySelectorAll('td').length : 0,
      editable: !! (rows[0] && window.$cellIn(rows[0])),
      reordering: !! document.querySelector('.wire-sortable-handle'),
    });
  })()`);

  const info = JSON.parse(start);

  check('the table is in reorder mode with anchored rows', info.rows > 1 && info.reordering, start);
  check('every row has a drag handle cell before the write',
    info.handles === info.rows, start);
  check('…and the first row has an editable cell', info.editable === true, start);

  await shot('01-before');

  // ── The write ──────────────────────────────────────────────────────
  const saved = await eval_(`(async () => {
    const rows = window.$rows();
    const cell = window.$cellIn(rows[0]);
    const alpine = window.Alpine.$data(cell.closest('[data-record-key]'));

    // Stamped per run, so a leftover value cannot pass for a fresh morph.
    const written = 'sortable-partial-' + Date.now();

    const syncBefore = rows[0].querySelector('[data-server-value]')?.getAttribute('data-server-value') ?? null;

    window.__wire = [];
    await alpine.commit(written);
    // Long enough for the response, the microtask that applies the partials, and
    // whatever puts the handle back.
    await new Promise((r) => setTimeout(r, 1800));

    const after = window.$rows();

    return JSON.stringify({
      wire: window.__wire,
      written,
      syncBefore,
      syncAfter: after[0].querySelector('[data-server-value]')?.getAttribute('data-server-value') ?? null,
      rows: after.length,
      handles: window.$handles().length,
      firstRowHasHandle: !! after[0].querySelector('.wire-sortable-handle-cell'),
      firstRowCells: after[0].querySelectorAll('td').length,
      // A handle that is present but no longer inside the row SortableJS knows
      // about would still read as "present".
      handleIsFirstChild: after[0].firstElementChild?.classList.contains('wire-sortable-handle-cell') ?? false,
    });
  })()`);

  const save = JSON.parse(saved);
  console.log('cell save →', JSON.stringify(save.wire), 'handles:', save.handles, '/', save.rows);

  check('the edited row really re-rendered',
    save.syncAfter === save.written && save.syncAfter !== save.syncBefore, saved);

  check('…and came back as a partial, not as the whole page',
    save.wire.length > 0
      && save.wire.every((r) => r.hasHtml === false)
      && save.wire.some((r) => (r.partials[0] ?? '').startsWith('row-')),
    JSON.stringify(save.wire));

  // The bug.
  check('the edited row still has its drag handle',
    save.firstRowHasHandle === true, `cells=${save.firstRowCells}`);

  check('…as the leading cell, where SortableJS expects it',
    save.handleIsFirstChild === true, saved);

  check('…and no other row lost one',
    save.handles === save.rows, `${save.handles} handles / ${save.rows} rows`);

  await shot('02-after-save');

  // ── Still draggable ────────────────────────────────────────────────
  // Present in the DOM is not the same as wired: SortableJS holds a reference to
  // the tbody and its rows, and a handle re-created outside its knowledge would
  // look identical here.
  const draggable = await eval_(`(() => {
    const row = window.$rows()[0];
    const handle = row.querySelector('.wire-sortable-handle');
    if (! handle) return JSON.stringify({ ok: false, why: 'no handle' });

    const rect = handle.getBoundingClientRect();

    return JSON.stringify({
      ok: rect.width > 0 && rect.height > 0,
      draggableRow: row.draggable === true || !! row.closest('tbody'),
      width: Math.round(rect.width),
      height: Math.round(rect.height),
    });
  })()`);

  const drag = JSON.parse(draggable);
  check('the restored handle is a real, visible grab target',
    drag.ok === true && drag.draggableRow === true, draggable);

  check('no console errors during the run', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  // The preview ships no favicon, and a fresh Chrome profile asks for one — a
  // 404 there is not this feature's problem. Same exclusion as
  // verify-fill-handle.mjs. (It only shows up in a sweep, where every driver
  // gets its own user-data-dir; a repeated standalone run reuses the cache and
  // never re-requests it.)
  const realFailures = badResponses.filter((r) => ! r.endsWith('/favicon.ico'));
  check('no failed requests', realFailures.length === 0, realFailures.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${shotDir}`);
} finally {
  await close();
}

finish();
