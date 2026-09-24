import { execFileSync } from 'node:child_process';
import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * CDP driver for the optimistic lock of an inline edit on a live table, now that
 * wire-core sequences a table's island and root requests
 * (support/island-coordination.js).
 *
 * A cell save is an island call (`targeting($wire, 'data-region')`), the poll is
 * a root one. Before the coordinator they ran side by side; now a tick in flight
 * is CANCELLED for the save, and any other root request makes the save WAIT.
 * Both change when the save leaves and what the cell knows by then, which is
 * exactly what the lock reads, so each is checked for the two answers it has to
 * keep giving:
 *
 *   1. the user's own edit during a tick lands, is not refused, and is not
 *      reverted by the ticks after it;
 *   2. somebody else's write during a tick is still caught: the cancelled tick
 *      would have brought the newer version, the cell never saw it, and the save
 *      it sends must be refused rather than overwrite the other write — then the
 *      cell recovers and can write again;
 *   3. a save deferred behind a root request leaves only after that request has
 *      finished, and is accepted.
 *
 * "Somebody else" is a write straight into the preview database with
 * `updated_at` moved on — deterministic, where a second browser would race the
 * one-second granularity of the stamp (see verify-concurrent-commits.mjs). Every
 * `refreshTable` response is held back HOLD_MS (abort-aware) so a tick is
 * reliably in flight when the save goes out.
 *
 * Fixture: `table-editable-live` — the four workbench users, live('2s').
 * Mutates user 1; run it against a preview database you do not mind changing.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-island-cell-lock.mjs
 *   PREVIEW_DB=/path/to/database.sqlite node …   # a database elsewhere
 *
 * Exit code 0 = all checks passed; 1 = a check failed.
 */

const HOLD_MS = 1500;

const url = process.env.PREVIEW_URL
  ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-live`;
const db = process.env.PREVIEW_DB ?? 'vendor/orchestra/testbench-core/laravel/database/database.sqlite';

const sql = (query) => execFileSync('sqlite3', [db, query], { encoding: 'utf8' }).trim();
const stamp = Date.now();

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'island-cell-lock', width: 1400, height: 1000, settle: 4000,
});
const { check, finish } = checker();

try {
  await eval_(`
    window.$cell = (key, col) => [...document.querySelectorAll('tbody [data-record-key][data-column-name]')]
      .find((c) => c.dataset.recordKey === key && c.dataset.columnName === col);
    window.$data = (key, col) => Alpine.$data(window.$cell(key, col));
    window.$host = () => window.Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id'));
    window.$snap = (col) => { const c = window.$data('1', col);
      return JSON.stringify({ value: c.value, serverValue: c.serverValue, error: c.error, version: c.recordVersion, saving: c.saving }); };

    // Every Livewire request, by the methods it calls, with when it left and
    // when it came back. Ticks and (on demand) $refresh are held.
    window.__net = { log: [], held: 0, aborted: 0, holdRefresh: false };
    const original = window.fetch;
    window.fetch = async (input, options = {}) => {
      if (typeof options.body !== 'string' || ! String(input?.url ?? input).includes('/livewire')) {
        return original(input, options);
      }
      const methods = (() => { try {
        return JSON.parse(options.body).components.flatMap((c) => (c.calls || []).map((x) => x.method));
      } catch (e) { return []; } })();
      const entry = { methods, sent: performance.now(), done: null, aborted: false };
      __net.log.push(entry);

      const hold = methods.includes('refreshTable') || (__net.holdRefresh && methods.includes('$refresh'));
      const response = await original(input, options);
      if (! hold) { entry.done = performance.now(); return response; }

      __net.held++;
      return new Promise((resolve, reject) => {
        const timer = setTimeout(() => { __net.held--; entry.done = performance.now(); resolve(response); }, ${HOLD_MS});
        options.signal?.addEventListener('abort', () => {
          clearTimeout(timer); __net.held--; __net.aborted++; entry.aborted = true;
          reject(new DOMException('Aborted', 'AbortError'));
        });
      });
    };
    true;
  `);

  const snap = async (col = 'email') => JSON.parse(await eval_(`$snap('${col}')`));
  const tickHeld = () => eval_(`__net.held > 0 && __net.log.some((e) => e.methods.includes('refreshTable') && ! e.done && ! e.aborted)`);
  const dbEmail = () => sql('select email from users where id = 1');

  // ── premise ────────────────────────────────────────────────────────────
  const start = await snap();
  check('user 1 has a live, commit-capable email cell with a version', !! start.version && start.saving === false, JSON.stringify(start));
  check('the table is polling', !! await until(tickHeld, { timeout: 6000 }));

  // ── 1. own edit while a tick is in flight ──────────────────────────────
  const own = `own-${stamp}@example.test`;
  await until(tickHeld, { timeout: 6000 });
  const abortedBefore = await eval_('__net.aborted');
  await eval_(`$data('1', 'email').commit(${JSON.stringify(own)}).catch(() => null); true`);
  await until(async () => (await snap()).saving === false && (await snap()).serverValue === own, { timeout: 6000 }).catch(() => null);

  const afterOwn = await snap();
  check('an own edit during a tick is accepted', afterOwn.serverValue === own && ! afterOwn.error, JSON.stringify(afterOwn));
  check('…the tick in flight was cancelled for it', await eval_('__net.aborted') > abortedBefore,
    `${abortedBefore} → ${await eval_('__net.aborted')} cancelled`);
  check('…it reached the database', dbEmail() === own, dbEmail());

  await sleep(HOLD_MS * 2 + 2500);
  const later = await snap();
  check('…and the ticks after it do not revert it', later.value === own && later.serverValue === own && ! later.error, JSON.stringify(later));
  await shot('01-own-edit');

  // ── 2. somebody else writes; the tick that would say so is cancelled ───
  const theirs = `other-${stamp}@example.test`;
  const stale = `stale-${stamp}@example.test`;
  const heldVersion = (await snap()).version;

  sql(`update users set email = '${theirs}', updated_at = datetime('now', '+30 seconds') where id = 1`);
  await until(tickHeld, { timeout: 6000 });
  const seenBeforeSave = (await snap()).version;
  await eval_(`$data('1', 'email').commit(${JSON.stringify(stale)}).catch(() => null); true`);
  await until(async () => (await snap()).saving === false && !! (await snap()).error, { timeout: 6000 }).catch(() => null);

  const conflicted = await snap();
  check('premise: the cell still held the old version when it saved', seenBeforeSave === heldVersion,
    `${heldVersion} / ${seenBeforeSave}`);
  check('a save over somebody else\'s write is refused', !! conflicted.error, JSON.stringify(conflicted));
  check('…their write survives in the database', dbEmail() === theirs, dbEmail());
  check('…the cell shows their value, not the refused one', conflicted.value === theirs && conflicted.serverValue === theirs,
    JSON.stringify(conflicted));
  check('…and adopts the new version', !! conflicted.version && conflicted.version !== heldVersion, `version=${conflicted.version}`);
  await shot('02-conflict');

  const recovered = `recovered-${stamp}@example.test`;
  await eval_(`$data('1', 'email').commit(${JSON.stringify(recovered)}).catch(() => null); true`);
  await until(async () => (await snap()).serverValue === recovered, { timeout: 6000 }).catch(() => null);
  const afterRecovery = await snap();
  check('…after which the cell can write again', afterRecovery.serverValue === recovered && ! afterRecovery.error && dbEmail() === recovered,
    `${JSON.stringify(afterRecovery)} db=${dbEmail()}`);

  // ── 3. a save deferred behind a root request ───────────────────────────
  const before = await snap('role');
  const nextRole = before.value === 'admin' ? 'editor' : 'admin';

  await eval_(`__net.holdRefresh = true; $host().$refresh(); true`);
  await until(() => eval_(`__net.log.some((e) => e.methods.includes('$refresh') && ! e.done)`), { timeout: 3000 });
  await eval_(`$data('1', 'role').commit(${JSON.stringify(nextRole)}).catch(() => null); true`);
  await until(async () => (await snap('role')).serverValue === nextRole, { timeout: HOLD_MS * 4 }).catch(() => null);
  await eval_('__net.holdRefresh = false; true');

  const order = JSON.parse(await eval_(`(() => {
    const refresh = __net.log.filter((e) => e.methods.includes('$refresh')).pop();
    const save = __net.log.filter((e) => e.methods.includes('updateTableCell')).pop();
    return JSON.stringify({ refreshDone: refresh?.done, saveSent: save?.sent, saveMethods: save?.methods });
  })()`));
  const role = await snap('role');
  check('a save fired during a root request leaves only after it',
    order.refreshDone != null && order.saveSent != null && order.saveSent >= order.refreshDone, JSON.stringify(order));
  check('…and is accepted', role.serverValue === nextRole && ! role.error, JSON.stringify(role));
  check('…in the database too', sql('select role from users where id = 1') === nextRole, sql('select role from users where id = 1'));
  await shot('03-deferred');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
