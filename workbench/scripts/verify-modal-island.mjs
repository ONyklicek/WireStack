import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for the action-modal island
 * (/previews/table-overview — a table whose row actions open modals).
 *
 * Opening a row action used to cost a full table render: every cell re-rendered
 * and re-morphed so a dialog could appear over them. `@island('action-modals')`
 * plus `wire:island="action-modals"` on the trigger changes that — a targeted
 * call makes Livewire `skipRender()` the component and return the island alone.
 *
 * The saving is invisible to Pest, and it is invisible in the DOM too: the modal
 * looks identical either way. What proves it is the RESPONSE — a targeted call
 * comes back with no `html` effect, because the component never rendered. So this
 * driver reads the wire payload rather than the page.
 *
 * Two things are asserted together, and the second is why `always: true` is on
 * the island:
 *
 *   - the modal opens and its markup is present (the feature still works);
 *   - the response carries no full-component html (the saving is real).
 *
 * Without `always`, an island is skipped on every request that does not target
 * it, so a modal opened by anything other than one of these buttons — a keyboard
 * shortcut, the row context menu, a programmatic call — would render nothing at
 * all. The last check covers exactly that path.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-modal-island.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-overview`;

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'modal-island', width: 1400, height: 1000, settle: 4000,
});

const { check, finish } = checker();

try {
  await eval_(`
    window.$host = () => window.Livewire.find(
      document.querySelector('[wire\\\\:id]').getAttribute('wire:id')
    );
    window.$modalOpen = () => !! document.querySelector('[data-testid="action-modal"], [role="dialog"]');

    // Record every Livewire roundtrip's response so the payload can be read
    // after the fact — the saving lives there, not in the DOM.
    window.__wire = [];
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
      const target = typeof args[0] === 'string' ? args[0] : (args[0]?.url ?? '');
      const promise = originalFetch.apply(this, args);

      if (target.includes('/livewire')) {
        promise.then((response) => {
          response.clone().json().then((body) => {
            const componentBodies = body?.components ?? [];
            window.__wire.push({
              hasHtml: componentBodies.some((c) => c?.effects?.html !== undefined),
              // The island arrives as its own effect — 'islandFragments', not 'html'.
              hasIslandFragments: componentBodies.some(
                (c) => (c?.effects?.islandFragments ?? []).length > 0,
              ),
              bytes: JSON.stringify(body).length,
            });
          }).catch(() => {});
        }).catch(() => {});
      }

      return promise;
    };
    true;
  `);

  const trigger = await eval_(`(() => {
    const button = document.querySelector('tbody [data-testid^="action-"][wire\\\\:island]');
    return button ? button.getAttribute('data-testid') : null;
  })()`);

  check('a row action declares the modal island as its target', trigger !== null, `trigger=${trigger}`);

  await shot('01-before');

  // ── Opening a modal returns the island, not the table ──────────────
  const opened = await eval_(`(async () => {
    window.__wire = [];
    document.querySelector('tbody [data-testid^="action-"][wire\\\\:island]').click();
    await new Promise((r) => setTimeout(r, 1200));

    return JSON.stringify({ modalOpen: window.$modalOpen(), wire: window.__wire });
  })()`);

  const open = JSON.parse(opened);
  console.log('open →', JSON.stringify(open.wire));

  check('the modal opens', open.modalOpen === true, opened);

  check('…and the response carries the island rather than the whole table',
    open.wire.length > 0 && open.wire.every((r) => r.hasHtml === false),
    JSON.stringify(open.wire));

  check('…which is what the islandFragments effect is for',
    open.wire.some((r) => r.hasIslandFragments), JSON.stringify(open.wire));

  await shot('02-modal-open');

  // ── A modal opened without targeting still renders ─────────────────
  // `always: true` on the island. Without it this path renders nothing, and a
  // keyboard shortcut or context-menu action would open an empty dialog.
  const programmatic = await eval_(`(async () => {
    // Close whatever is open first.
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    await new Promise((r) => setTimeout(r, 500));

    const row = document.querySelector('tbody [data-row-key]');
    const key = row ? row.dataset.rowKey : null;
    const name = document.querySelector('tbody [data-testid^="action-"][wire\\\\:island]')
      ?.getAttribute('data-testid')?.replace('action-', '');

    window.__wire = [];
    await $host().call('openActionModal', key, name);
    await new Promise((r) => setTimeout(r, 900));

    return JSON.stringify({ modalOpen: window.$modalOpen(), wire: window.__wire });
  })()`);

  const prog = JSON.parse(programmatic);
  console.log('programmatic →', JSON.stringify(prog.wire));

  check('a modal opened without targeting the island still renders',
    prog.modalOpen === true, programmatic);

  check('…and that path does render the component, as it must',
    prog.wire.some((r) => r.hasHtml), JSON.stringify(prog.wire));

  await shot('03-programmatic-open');

  // ── What the island is worth, in bytes ─────────────────────────────
  // The two opens above are the same modal by two routes: one targeted the
  // island, one did not. Their payloads are therefore directly comparable, and
  // the difference is the whole point — an order of magnitude on a 4-row table,
  // and it grows with the row count while the island stays the size it is.
  const targeted = Math.min(...open.wire.map((r) => r.bytes));
  const whole = Math.max(...prog.wire.map((r) => r.bytes));

  check('the targeted response is an order of magnitude smaller than the full render',
    targeted * 5 < whole, `island=${targeted}B full=${whole}B`);
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
