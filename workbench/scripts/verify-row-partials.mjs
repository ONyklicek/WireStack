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
    // cannot pass for a fresh morph — and an address, because the cell this
    // picks is the email column on a MustVerifyEmail user: module-users mails a
    // fresh verification from the model's updated hook, inside the write's own
    // transaction, so anything the mailer cannot parse rolls the write back.
    // See verify-cell-island.mjs, where that cost a whole driver.
    const written = 'partial-' + Date.now() + '@example.test';

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

  check('…and comes back as regions, never as the page',
    save.wire.length > 0
      && save.wire.every((r) => r.hasHtml === false && r.hasIslands === false)
      && save.wire.some((r) => r.partials[0].startsWith('row-')),
    JSON.stringify(save.wire));

  // Everything one write moves, and nothing else. The totals are computed over
  // the whole set, so any write moves them. The card is the same record rendered
  // again for the width where the table is hidden — and this browser is wide and
  // has said so (the wire_viewport cookie), so the write sends neither the card
  // nor the card footer. This page was its first visit and still holds both
  // halves; the hidden cards it keeps are re-rendered the moment the window
  // crosses the breakpoint (verify-visible-layout drives that). VisibleLayoutOnlyTest
  // pins the narrow side and the no-cookie side.
  check('…the totals with it, because one write moves both',
    save.wire.some((r) => r.partials.some((p) => p.startsWith('summary'))),
    JSON.stringify(save.wire));

  check('…and no card, which this wide window does not have',
    save.wire.every((r) => ! r.partials.some((p) => p.startsWith('card-') || p === 'summary-mobile')),
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

  // ── Moving on while the save is out ────────────────────────────────
  // What somebody filling a grid does: type into a cell, click the next one, keep
  // going. The click blurs the first cell, which saves, and its partial comes back
  // while the focus is already in the next control. The full render had left the
  // cell views' `[if BLOCK]` comments on the page and the partial carried none, so
  // the morph — pairing by position — swapped the next control's wrapper for a new
  // one, and the focus fell to <body> as the save landed.
  //
  // Each case starts on a row no partial has touched yet: the first partial into a
  // row replaces what the full render left there, so only the FIRST save after a
  // full render could show it — which is every first edit of a row on a fresh page.
  // The node has to be the SAME one: a re-focused copy would still lose what was
  // typed into it meanwhile.
  for (const [label, row, target] of [
    ['the next input of the same row', 1, `window.$rows()[1].querySelector('[data-column-name="bio"] input')`],
    ['a select of the same row', 2, `window.$rows()[2].querySelector('[data-column-name="role"] select')`],
    ['a cell of another row', 3, `window.$cellIn(window.$rows()[0])`],
  ]) {
    const moved = await eval_(`(async () => {
      const input = window.$cellIn(window.$rows()[${row}]);
      const next = ${target};

      input.focus();
      const setValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
      setValue.call(input, 'focus-' + Date.now() + '@example.test');
      input.dispatchEvent(new Event('input', { bubbles: true }));

      window.__wire = [];
      next.focus();   // blurs the first cell, which saves it

      // …and starts typing there before the save has answered. What the user
      // reported as "it resets": a swapped cell is a new component that starts
      // from the server's value, so what was typed meanwhile is gone with it.
      let typed = null;
      if (next.tagName === 'INPUT') {
        typed = 'typed-' + Date.now();
        setValue.call(next, typed);
        next.dispatchEvent(new Event('input', { bubbles: true }));
      }

      await new Promise((r) => setTimeout(r, 1500));

      return JSON.stringify({
        wire: window.__wire,
        focusedNext: document.activeElement === next,
        stillOnPage: next.isConnected,
        focused: document.activeElement?.tagName ?? null,
        typed,
        shown: typed === null ? null : (document.activeElement?.value ?? null),
      });
    })()`);

    const focus = JSON.parse(moved);

    check(`the save behind ${label} came back as a row partial`,
      focus.wire.some((r) => r.partials.some((p) => p.startsWith('row-'))), moved);

    check(`…and ${label} kept the focus through it, as the same node`,
      focus.focusedNext === true && focus.stillOnPage === true, moved);

    if (focus.typed !== null) {
      check(`…and what was typed into ${label} meanwhile is still there`,
        focus.shown === focus.typed, moved);
    }
  }

  await shot('03-moved-on');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
