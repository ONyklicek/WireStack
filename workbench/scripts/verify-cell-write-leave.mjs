import { execFileSync } from 'node:child_process';
import { writeFileSync, rmSync } from 'node:fs';
import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * CDP driver for leaving a page while inline edits are still on their way.
 *
 * Three states an edit can be in when the user closes the tab, reloads or
 * navigates, and each one has its own answer (core editable/leave-guard.js):
 *
 *   - ON THE SERVER — the request arrived and is being processed. It finishes
 *     there however the browser goes away (`ignore_user_abort()` on the cell
 *     endpoints), so the value is in the database afterwards.
 *   - STILL IN THE BROWSER — a write queued behind the record's previous one, or
 *     text typed and never committed (no blur, no Enter). Leaving would drop it,
 *     so the browser's own prompt is raised; staying lets it land, the typed
 *     text included, because the guard commits it on the way.
 *   - `wire:navigate` — nothing need be lost, so nothing is asked: the
 *     navigation waits for the writes and then carries on by itself. Only a
 *     value that cannot be saved (refused, invalid) asks before it is dropped.
 *
 * The server half needs a write that is reliably still on the server when the
 * page goes: the workbench sleeps in `CellUpdating` for as long as the flag file
 * says (WorkbenchServiceProvider). A `beforeunload` prompt needs a user
 * activation, so the cell is clicked with a real mouse event first.
 *
 * Fixture: `table-editable-row-partials`. Mutates user 1.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-cell-write-leave.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed.
 */

const SLOW_MS = 1500;

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${origin}/previews/table-editable-row-partials`;
const db = process.env.PREVIEW_DB ?? 'vendor/orchestra/testbench-core/laravel/database/database.sqlite';
const flag = process.env.SLOW_FLAG ?? 'vendor/orchestra/testbench-core/laravel/storage/framework/slow-cell-write';

const sql = (query) => execFileSync('sqlite3', [db, query], { encoding: 'utf8' }).trim();
const db1 = (col) => sql(`select ${col} from users where id = 1`);
const slow = (ms) => (ms ? writeFileSync(flag, String(ms)) : rmSync(flag, { force: true }));
const stamp = Date.now();

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close, on } = await openPage({
  url, shotPrefix: 'cell-write-leave', width: 1400, height: 1000, settle: 4000,
});
const { check, finish } = checker();

// Every dialog the page raises, answered with whatever the scenario set.
const dialogs = [];
let answer = false;
on((msg) => {
  if (msg.method !== 'Page.javascriptDialogOpening') return;
  dialogs.push(msg.params.type);
  page('Page.handleJavaScriptDialog', { accept: answer }).catch(() => {});
});

const install = () => eval_(`
  window.$cell = (key, col) => [...document.querySelectorAll('tbody [data-record-key][data-column-name]')]
    .find((c) => c.dataset.recordKey === key && c.dataset.columnName === col);
  window.$data = (key, col) => Alpine.$data(window.$cell(key, col));
  true;
`);

const open = async (target = url) => {
  await page('Page.navigate', { url: target });
  await sleep(3500);
  await install();
};

// A real click into the email input: the user activation a beforeunload prompt needs.
const clickEmail = async () => {
  const box = JSON.parse(await eval_(`JSON.stringify((() => {
    const r = $cell('1', 'email').querySelector('input').getBoundingClientRect();
    return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
  })())`));
  for (const type of ['mousePressed', 'mouseReleased']) {
    await page('Input.dispatchMouseEvent', { type, x: box.x, y: box.y, button: 'left', clickCount: 1 });
  }
};

const leave = () => eval_(`(() => { location.href = ${JSON.stringify(`${url}?left=${stamp}`)}; return true; })()`);
const onPage = async () => (await eval_('location.search')).includes('left=') === false;
const otherRole = () => (db1('role') === 'admin' ? 'editor' : 'admin');

try {
  await page('Page.enable');
  slow(0);
  await install();

  // ── 1. a write already on the server finishes after the page is gone ────
  slow(SLOW_MS);
  await clickEmail();
  const onServer = `on-server-${stamp}@example.test`;
  dialogs.length = 0;
  answer = true;                                    // the user leaves anyway
  await eval_(`$data('1', 'email').commit(${JSON.stringify(onServer)}); true`);
  await sleep(400);                                 // the request is asleep on the server
  await leave();
  await sleep(SLOW_MS + 1500);
  check('leaving while a write is out raises the browser\'s prompt', dialogs.includes('beforeunload'), JSON.stringify(dialogs));
  check('a write that reached the server is in the database after the page has gone', db1('email') === onServer, db1('email'));

  // ── 2. a write still queued in the browser: prompt, stay, it lands ───────
  await open();
  await clickEmail();
  const queuedEmail = `queued-${stamp}@example.test`;
  const queuedRole = otherRole();
  dialogs.length = 0;
  answer = false;                                   // the user stays
  await eval_(`$data('1', 'email').commit(${JSON.stringify(queuedEmail)}); true`);
  await sleep(150);
  await eval_(`$data('1', 'role').commit(${JSON.stringify(queuedRole)}); true`);   // queued behind it
  await sleep(150);
  await leave();
  await sleep(300);
  check('leaving with a write still queued in the browser raises the prompt', dialogs.includes('beforeunload'), JSON.stringify(dialogs));
  check('…and staying keeps the page', await onPage());
  await until(() => db1('role') === queuedRole, { timeout: SLOW_MS * 3 + 3000 }).catch(() => null);
  check('…where both writes then land', db1('email') === queuedEmail && db1('role') === queuedRole,
    `${db1('email')} / ${db1('role')}`);

  // ── 3. text typed and never committed: prompt, stay, it is saved ─────────
  slow(0);
  await open();
  await clickEmail();
  const typed = `typed-${stamp}@example.test`;
  await eval_(`(() => { const i = $cell('1', 'email').querySelector('input'); i.select(); return true; })()`);
  await page('Input.insertText', { text: typed });  // no blur, no Enter
  dialogs.length = 0;
  answer = false;
  await leave();
  await sleep(300);
  check('leaving with typed, uncommitted text raises the prompt', dialogs.includes('beforeunload'), JSON.stringify(dialogs));
  await until(() => db1('email') === typed, { timeout: 5000 }).catch(() => null);
  check('…and staying saves the text without a blur', db1('email') === typed && await onPage(), db1('email'));

  // ── 4. nothing pending: no prompt at all ─────────────────────────────────
  await open();
  await clickEmail();
  dialogs.length = 0;
  answer = true;
  await leave();
  await sleep(1500);
  check('with nothing pending the page leaves without a prompt', dialogs.length === 0 && ! await onPage(), JSON.stringify(dialogs));

  // ── 5. wire:navigate waits for the write, asks nothing, then goes ────────
  slow(SLOW_MS);
  await open();
  const navEmail = `navigate-${stamp}@example.test`;
  dialogs.length = 0;
  await eval_(`$data('1', 'email').commit(${JSON.stringify(navEmail)}); true`);
  await sleep(150);
  await eval_(`Livewire.navigate(${JSON.stringify(`${url}?navigated=${stamp}`)}); true`);
  await sleep(500);
  const heldThere = ! (await eval_('location.search')).includes('navigated=');
  await until(async () => (await eval_('location.search')).includes('navigated='), { timeout: SLOW_MS * 3 + 3000 }).catch(() => null);
  const arrived = (await eval_('location.search')).includes('navigated=');
  check('wire:navigate is held while a write is out', heldThere);
  check('…then carries on by itself, without a dialog', arrived && dialogs.length === 0, JSON.stringify({ arrived, dialogs }));
  check('…and the write is in the database', db1('email') === navEmail, db1('email'));

  // ── 6. wire:navigate over a value that cannot be saved asks first ────────
  slow(0);
  await open();
  dialogs.length = 0;
  answer = false;                                   // stay
  await eval_(`(() => { const c = $data('1', 'email'); c.value = 'not saved'; c.error = 'Invalid'; return true; })()`);
  await eval_(`Livewire.navigate(${JSON.stringify(`${url}?dropped=${stamp}`)}); true`);
  await sleep(800);
  check('wire:navigate over an unsavable value asks before dropping it', dialogs.includes('confirm'), JSON.stringify(dialogs));
  check('…and "stay" keeps the page and the value', ! (await eval_('location.search')).includes('dropped=')
    && await eval_(`$data('1', 'email').value`) === 'not saved');
  await shot('06-unsavable');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  slow(0);
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
