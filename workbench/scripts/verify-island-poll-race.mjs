import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * CDP driver for a table control fired while a poll tick is in flight.
 *
 * The per-page select and the sort headers sit inside the table's `data-region`
 * island, so Livewire gives their requests the island's scope; `wire:poll` sits
 * outside it, with the component's scope. Livewire coordinates overlapping
 * requests within one scope only, so the two went out side by side carrying the
 * same snapshot — and whichever came back LAST won. When that was the tick, the
 * select jumped back and the rows never changed. wire-core's
 * support/island-coordination.js sequences them for a component that renders
 * `data-wire-islands="shared-state"`, which the table does.
 *
 * Nothing but a browser can see this: every response is correct on its own, the
 * bug is the order two of them land in. A real tick is too quick to hit on
 * purpose, so this holds every `refreshTable` response back for HOLD_MS (the
 * technique livewire/livewire#10513 tests its own ordering with) and fires the
 * control while one is held. The hold honours the request's AbortSignal, so a
 * tick the coordinator cancels is cancelled for real rather than delivered late.
 *
 * Fixture: `table-gestures-poll-url` — poll('1s'), paginated 10 a page, 40 rows,
 * sortable Name and Amount.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-island-poll-race.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed.
 */

const HOLD_MS = 1500;

const url = process.env.PREVIEW_URL
  ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-gestures-poll-url`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'island-poll-race', width: 1400, height: 1100, settle: 3000,
});
const { check, finish } = checker();

try {
  await eval_(`
    window.$q = (s) => document.querySelector(s);
    window.rows = () => Array.from(document.querySelectorAll('tbody tr[data-row-key]'));
    window.perPage = () => $q('[data-testid="table-per-page"]');
    window.firstName = () => rows()[0]?.querySelector('[data-testid="table-cell-name"]')?.innerText.trim()
      ?? rows()[0]?.innerText.trim().split('\\n')[0];

    window.__polls = { held: 0, sent: 0, aborted: 0 };
    const original = window.fetch;
    window.fetch = async (input, options = {}) => {
      const isPoll = typeof options.body === 'string' && options.body.includes('"method":"refreshTable"');
      if (! isPoll) return original(input, options);

      __polls.sent++;
      const response = await original(input, options);
      __polls.held++;

      return new Promise((resolve, reject) => {
        const timer = setTimeout(() => { __polls.held--; resolve(response); }, ${HOLD_MS});
        options.signal?.addEventListener('abort', () => {
          clearTimeout(timer);
          __polls.held--;
          __polls.aborted++;
          reject(new DOMException('Aborted', 'AbortError'));
        });
      });
    };
    true;
  `);

  const rowCount = () => eval_('rows().length');
  const pollHeld = () => eval_('__polls.held > 0');

  const baseline = await rowCount();
  check('fixture shows its first page', baseline === 10, `${baseline} rows`);

  // ── 1. page size changed while a tick is held ──────────────────────────
  await until(pollHeld, { timeout: 5000 });
  check('a poll tick is in flight', await pollHeld() === true);

  await eval_(`perPage().value = '25'; perPage().dispatchEvent(new Event('change', { bubbles: true })); true`);

  // Long enough for the held tick to have landed had it been let through.
  await sleep(HOLD_MS + 1500);
  check('the new page size is rendered', await rowCount() === 25, `${await rowCount()} rows`);
  check('the select still says 25', await eval_('perPage().value') === '25', await eval_('perPage().value'));
  await shot('01-per-page');

  // ── 2. sort changed while a tick is held ───────────────────────────────
  const before = await eval_('firstName()');
  await until(pollHeld, { timeout: 5000 });
  await eval_(`$q('[data-testid="table-sort-name"]').click(); true`);

  await until(async () => await eval_('firstName()') !== before, { timeout: HOLD_MS * 3 });
  const after = await eval_('firstName()');
  check('the sort moved the first row', after !== before, `${before} → ${after}`);

  await sleep(HOLD_MS + 1500);
  check('a later tick does not undo the sort', await eval_('firstName()') === after, await eval_('firstName()'));
  check('the page size survived the sort', await rowCount() === 25, `${await rowCount()} rows`);
  await shot('02-sort');

  // ── 3. polling carries on afterwards ───────────────────────────────────
  const sent = await eval_('__polls.sent');
  await sleep(2500);
  check('the table keeps polling', await eval_('__polls.sent') > sent,
    `${sent} → ${await eval_('__polls.sent')} ticks, ${await eval_('__polls.aborted')} cancelled`);
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
