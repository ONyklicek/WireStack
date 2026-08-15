import { openPage, checker } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for an inline cell save
 * (/previews/table-editable-fill).
 *
 * Livewire targets an island by itself only for an action fired from a DOM
 * element — `supportIslands.js` reads `action.origin` and returns when there is
 * none. An editable cell commits through `$wire.updateTableCell(…)` from Alpine,
 * which has no origin, so the most frequent write on an ERP grid re-rendered the
 * whole component while a sort header two rows up re-rendered only the table.
 *
 * The cells now pass `island: 'data-region'` and the controller routes the call
 * through `$wire.$island(…)`. Nothing about that is visible in the DOM — the
 * value lands either way — so this reads the response, and checks the write
 * really happened, since a call that changed nothing would satisfy a
 * payload-only test.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-cell-island.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-fill`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'cell-island', width: 1400, height: 1000, settle: 4000,
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
              markup: componentBodies.reduce((n, c) => n + (c?.effects?.html?.length ?? 0), 0)
                + fragments.reduce((n, f) => n + String(f).length, 0),
            });
          }).catch(() => {});
        }).catch(() => {});
      }

      return promise;
    };

    // A cell that really commits: its Alpine data exposes commit().
    window.$cell = () => Array.from(document.querySelectorAll('[data-record-key][data-column-name]'))
      .find((c) => typeof (window.Alpine.$data(c) || {}).commit === 'function');
    true;
  `);

  const found = await eval_(`(() => {
    const cell = window.$cell();
    return JSON.stringify({
      found: !! cell,
      column: cell ? cell.dataset.columnName : null,
      declares: cell ? /data-region/.test(cell.getAttribute('x-data') || '') : false,
    });
  })()`);

  const start = JSON.parse(found);

  check('an editable cell is on the page', start.found === true, found);
  check('…and it names the island its writes belong to', start.declares === true, found);

  await shot('01-before');

  const saved = await eval_(`(async () => {
    const cell = window.$cell();
    window.__wire = [];

    await window.Alpine.$data(cell).commit('driver-wrote-this');
    await new Promise((r) => setTimeout(r, 1500));

    return JSON.stringify({
      value: window.Alpine.$data(cell).serverValue,
      wire: window.__wire,
    });
  })()`);

  const save = JSON.parse(saved);
  console.log('cell save →', JSON.stringify(save.wire));

  // The write has to have landed — serverValue is only advanced by a response
  // the server confirmed.
  check('the cell save reaches the server and is confirmed',
    save.value === 'driver-wrote-this', saved);

  check('…and comes back as the data region, not the page',
    save.wire.length > 0
      && save.wire.every((r) => r.hasHtml === false)
      && save.wire.some((r) => r.islands.includes('data-region')),
    JSON.stringify(save.wire));

  await shot('02-saved');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
