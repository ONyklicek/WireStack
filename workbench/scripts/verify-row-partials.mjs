import { openPage, checker } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for `Table::rowPartials()`
 * (/previews/table-editable-row-partials).
 *
 * The end of the chain islands could not reach. An island's name is re-evaluated
 * inside its own compiled view file, which never sees a loop variable, so there
 * is no island per record — a partial is an ordinary attribute the server picks
 * at write time instead. On a 25-column, 20-row page that is 3.2 ms and 26 kB
 * against 49.3 ms and 556 kB for the data region.
 *
 * The saving is invisible in the DOM: the value lands either way. What proves it
 * is the response; what proves it is SAFE is the page around the row.
 *
 *   - the edited row really re-rendered — read off the cell's **sync node**,
 *     which carries what the SERVER last said, so it moves only if the partial's
 *     markup was morphed in. The input's own value is the cell's optimistic
 *     state and would look right either way;
 *   - a witness set on a DIFFERENT row survives (nothing else was touched);
 *   - a witness on the chrome survives (the region was never rendered);
 *   - and the edited cell keeps its Alpine state, because an editable cell holds
 *     an optimistic value the server has not confirmed yet.
 *
 * The value written is stamped per run: a leftover from the last run must not be
 * able to pass for a fresh morph.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-row-partials.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-row-partials`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'row-partials', width: 1400, height: 1000, settle: 4000,
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
              hasIslands: componentBodies.some((c) => (c?.effects?.islandFragments ?? []).length > 0),
              partials: Object.keys(partials),
              markup: Object.values(partials).reduce((n, h) => n + String(h).length, 0),
            });
          }).catch(() => {});
        }).catch(() => {});
      }

      return promise;
    };

    window.$rows = () => Array.from(document.querySelectorAll('tbody [wire\\\\:partial]'));
    window.$cellIn = (row) => row.querySelector('[data-record-key][data-column-name] input[type="text"], [data-record-key][data-column-name] input:not([type])');
    true;
  `);

  const start = await eval_(`(() => {
    const rows = window.$rows();
    return JSON.stringify({
      anchored: rows.length,
      names: rows.slice(0, 2).map((r) => r.getAttribute('wire:partial')),
      editable: !! (rows[0] && window.$cellIn(rows[0])),
    });
  })()`);

  const info = JSON.parse(start);

  check('every row carries a partial anchor', info.anchored > 1, start);
  check('…named for its record', /^row-/.test(info.names[0] ?? ''), start);
  check('…and the first row has an editable cell', info.editable === true, start);

  await shot('01-before');

  // ── The write ──────────────────────────────────────────────────────
  const saved = await eval_(`(async () => {
    const rows = window.$rows();

    // Witnesses on what must NOT be re-rendered. A morph keeps node identity, so
    // these surviving is the point — what proves the edited row DID re-render is
    // its sync node below.
    rows[1].__witness = 'other-row';
    const chrome = document.querySelector('[data-testid="table-toolbar"], thead');
    chrome.__witness = 'chrome';

    const cell = window.$cellIn(rows[0]);
    const alpine = window.Alpine.$data(cell.closest('[data-record-key]'));

    // A value this run has not written before, so a leftover from the last run
    // cannot pass for a fresh morph.
    const written = 'partial-' + Date.now();

    // The sync node is the server-rendered child the editable cell reconciles
    // from — it holds what the SERVER last said, so it changes only if the
    // partial's markup was actually morphed in. The input's own value is the
    // cell's optimistic state and would look right either way.
    const syncBefore = rows[0].querySelector('[data-server-value]')?.getAttribute('data-server-value') ?? null;

    window.__wire = [];
    await alpine.commit(written);
    await new Promise((r) => setTimeout(r, 1500));

    const after = window.$rows();

    return JSON.stringify({
      wire: window.__wire,
      written,
      syncBefore,
      syncAfter: after[0].querySelector('[data-server-value]')?.getAttribute('data-server-value') ?? null,
      otherRowWitness: after[1].__witness ?? null,
      chromeWitness: chrome.__witness ?? null,
      cellStillAlive: typeof window.Alpine.$data(after[0].querySelector('[data-record-key]'))?.commit === 'function',
      serverValue: window.Alpine.$data(after[0].querySelector('[data-record-key]'))?.serverValue ?? null,
    });
  })()`);

  const save = JSON.parse(saved);
  console.log('cell save →', JSON.stringify(save.wire));

  check('the write reaches the server and is confirmed',
    save.serverValue === save.written, saved);

  check('…and comes back as that record alone',
    save.wire.length > 0
      && save.wire.every((r) => r.hasHtml === false && r.hasIslands === false)
      && save.wire.some((r) => r.partials.length === 2 && r.partials[0].startsWith('row-')),
    JSON.stringify(save.wire));

  // The card is the same record rendered again for the width where the table is
  // hidden. Both are in the document at once, so a write that refreshed one and
  // not the other would show two different values at two window widths.
  check('…including the card, which is the same record rendered twice',
    save.wire.some((r) => r.partials.some((n) => n.startsWith('card-'))),
    JSON.stringify(save.wire));

  check('the row was really re-rendered into the page',
    save.syncAfter === save.written && save.syncBefore !== save.written,
    `${save.syncBefore} -> ${save.syncAfter}`);

  check('…the row below it was never touched', save.otherRowWitness === 'other-row', saved);

  check('…nor was the chrome around them', save.chromeWitness === 'chrome', saved);

  check('the edited cell keeps its own Alpine state', save.cellStillAlive === true, saved);

  const markup = Math.max(...save.wire.map((r) => r.markup));
  check('one row of markup, not a page of it', markup > 0 && markup < 20000, `${markup} B`);

  await shot('02-after');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
