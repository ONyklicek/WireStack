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

  // ── 1b. a change held back behind a root request, released into a tick ─
  // The change waits for the root request, then sits in Livewire's send buffer
  // for a few ms. A tick landing in that window used to see nothing in flight
  // (message interceptors attach only when a request leaves) and went out
  // beside it. Four times over: each change must land, and no uncancelled tick
  // may overlap its request.
  await eval_(`
    window.__net = { log: [], holdRefresh: false };
    const inner = window.fetch;
    window.fetch = async (input, options = {}) => {
      if (typeof options.body !== 'string') return inner(input, options);
      const methods = (() => { try {
        return JSON.parse(options.body).components.flatMap((c) => (c.calls || []).map((x) => x.method));
      } catch (e) { return []; } })();
      const updates = (() => { try {
        return JSON.parse(options.body).components.some((c) => Object.keys(c.updates || {}).length);
      } catch (e) { return false; } })();
      const entry = { methods, updates, sent: performance.now(), done: null, aborted: false };
      __net.log.push(entry);
      try {
        const response = await inner(input, options);
        if (__net.holdRefresh && methods.includes('$refresh')) await new Promise((r) => setTimeout(r, ${HOLD_MS}));
        entry.done = performance.now();
        return response;
      } catch (e) { entry.aborted = true; entry.done = performance.now(); throw e; }
    };
    window.$host = () => Livewire.find(perPage().closest('[wire\\\\:id]').getAttribute('wire:id'));

    // The moment the held root request finishes, the change it held back is
    // released into Livewire's 5 ms send buffer. A tick fired right then —
    // through Livewire's own public fireAction(), with the metadata wire:poll
    // gives it — is the worst case, reached deterministically.
    window.__tickOnRelease = false;
    Livewire.interceptMessage(({ message, onFinish }) => {
      if (! Array.from(message.actions).some((a) => a.name === '$refresh')) return;
      onFinish(() => {
        if (! __tickOnRelease) return;
        // The catch is for fireAction() alone: its promise rejects unhandled when
        // an interceptor cancels the action, which wire:poll's own path does not.
        setTimeout(() => Livewire.fireAction($host().__instance ?? $host(), 'refreshTable', [], { type: 'poll' })
          .catch(() => null), 0);
      });
    });
    true;
  `);

  const overlaps = [];
  let landed = 0;
  for (const size of ['50', '25', '50', '25']) {
    await eval_(`__net.log = []; __net.holdRefresh = true; __tickOnRelease = true; $host().$refresh(); true`);
    await until(() => eval_(`__net.log.some((e) => e.methods.includes('$refresh') && ! e.done)`), { timeout: 3000 });
    await eval_(`perPage().value = '${size}'; perPage().dispatchEvent(new Event('change', { bubbles: true })); true`);
    await until(() => eval_(`__net.log.some((e) => e.methods.includes('$refresh') && e.done)`), { timeout: HOLD_MS * 3 });
    await sleep(200);
    await eval_('__net.holdRefresh = false; __tickOnRelease = false; true');
    await sleep(2500);

    const expected = size === '50' ? 40 : 25;
    if (await rowCount() === expected && await eval_('perPage().value') === size) landed++;

    overlaps.push(...JSON.parse(await eval_(`JSON.stringify((() => {
      const change = __net.log.find((e) => e.updates && e.methods.includes('$commit'));
      if (! change) return ['change never sent'];
      return __net.log
        .filter((e) => e.methods.includes('refreshTable') && ! e.aborted)
        .filter((e) => e.sent < (change.done ?? Infinity) && (e.done ?? Infinity) > change.sent)
        .map((e) => 'tick ' + Math.round(e.sent) + '..' + Math.round(e.done ?? -1) + ' over change ' + Math.round(change.sent) + '..' + Math.round(change.done ?? -1));
    })())`)));
  }
  check('a change held back behind a root request lands every time', landed === 4, `${landed}/4`);
  check('…and no tick runs beside it once it is released', overlaps.length === 0, overlaps.slice(0, 2).join('; ') || 'none');
  await eval_(`perPage().value = '25'; perPage().dispatchEvent(new Event('change', { bubbles: true })); true`);
  await until(async () => await rowCount() === 25, { timeout: 5000 });

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
