import { execFileSync } from 'node:child_process';
import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * CDP driver for filling in a row fast: several cells of one record written one
 * after the other while the first write is still on its way back.
 *
 * Livewire sends one request per component at a time and holds a later call
 * back until the one in flight has finished — but the held call's arguments
 * are fixed when it is made. A cell used to hand its write to Livewire at once,
 * so the second cell of a row left with the optimistic-lock version it read
 * BEFORE the first cell's write landed, and the server refused it as somebody
 * else's edit: the value the user had just typed flipped back. The control was
 * also DISABLED while its own write was out, so after Enter the focus left the
 * field and the next keystroke and Tab went nowhere.
 *
 * Now the writes of one record queue in the cell (core editable/lanes.js) and
 * read their version when they leave, and the cell is locked readonly — kept
 * focused — rather than disabled. What
 * this checks, with every `updateTableCell` answer held back HOLD_MS so the
 * overlap is certain rather than lucky:
 *
 *   1. two cells of one row committed one after the other both land — the
 *      second in a request of its own, not bundled with the first;
 *   2. the cell whose write is out is locked — readonly, not disabled, so the
 *      focus stays — keystrokes and a second commit do not get in, a select
 *      changed meanwhile is put back, and the row's other cells stay free;
 *   3. somebody else's write landing between the user's two writes is still
 *      refused: the queue forgives the user's own writes, nobody else's.
 *
 * Fixture: `table-editable-row-partials` (rowPartials + stacked, the shape of an
 * order's line grid). Mutates user 1; run it against a preview database you do
 * not mind changing.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-cell-write-lane.mjs
 *   PREVIEW_DB=/path/to/database.sqlite node …   # a database elsewhere
 *
 * Exit code 0 = all checks passed; 1 = a check failed.
 */

const HOLD_MS = 1200;

const url = process.env.PREVIEW_URL
  ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-row-partials`;
const db = process.env.PREVIEW_DB ?? 'vendor/orchestra/testbench-core/laravel/database/database.sqlite';

const sql = (query) => execFileSync('sqlite3', [db, query], { encoding: 'utf8' }).trim();
const stamp = Date.now();

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'cell-write-lane', width: 1400, height: 1000, settle: 4000,
});
const { check, finish } = checker();

const install = () => eval_(`
  window.$cell = (key, col) => [...document.querySelectorAll('tbody [data-record-key][data-column-name]')]
    .find((c) => c.dataset.recordKey === key && c.dataset.columnName === col);
  window.$data = (key, col) => Alpine.$data(window.$cell(key, col));
  window.$snap = (col) => { const c = window.$data('1', col);
    return { value: c.value, serverValue: c.serverValue, error: c.error, version: c.recordVersion, saving: c.saving }; };

  // Every cell write, with the version it carried; each answer held HOLD_MS.
  window.__net = { writes: [], requests: [] };
  const original = window.fetch;
  window.fetch = async (input, options = {}) => {
    if (typeof options.body !== 'string' || ! String(input?.url ?? input).includes('/livewire')) {
      return original(input, options);
    }
    const calls = (() => { try {
      return JSON.parse(options.body).components.flatMap((c) => c.calls || []);
    } catch (e) { return []; } })();
    const writes = calls.filter((x) => x.method === 'updateTableCell');
    __net.requests.push(calls.map((x) => x.method));
    writes.forEach((x) => __net.writes.push({ column: x.params[1], value: x.params[2], version: x.params[3] }));

    const response = await original(input, options);
    if (writes.length === 0) return response;
    await new Promise((r) => setTimeout(r, ${HOLD_MS}));
    return response;
  };
  true;
`);

const snap = async (col) => JSON.parse(await eval_(`JSON.stringify($snap('${col}'))`));
const settled = async (...cols) => {
  for (const col of cols) {
    await until(async () => (await snap(col)).saving === false, { timeout: HOLD_MS * 6 + 4000 });
  }
};
const db1 = (col) => sql(`select ${col} from users where id = 1`);

try {
  await install();

  // ── premise ────────────────────────────────────────────────────────────
  const start = { email: await snap('email'), role: await snap('role') };
  check('user 1 has an editable email and role cell sharing one version',
    !! start.email.version && start.email.version === start.role.version, JSON.stringify(start));

  // ── 1. two cells of one row, one after the other ───────────────────────
  const email = `lane-${stamp}@example.test`;
  const role = start.role.serverValue === 'admin' ? 'editor' : 'admin';

  await eval_(`$data('1', 'email').commit(${JSON.stringify(email)}); true`);
  await sleep(150); // the first is on the wire before the second is made
  await eval_(`$data('1', 'role').commit(${JSON.stringify(role)}); true`);
  await settled('email', 'role');

  const one = { email: await snap('email'), role: await snap('role'), net: JSON.parse(await eval_('JSON.stringify(__net)')) };
  const [first, second] = one.net.writes;
  console.log('writes →', JSON.stringify(one.net.writes));

  check('the second write left in a request of its own', one.net.requests.filter((m) => m.includes('updateTableCell')).length === 2,
    JSON.stringify(one.net.requests));
  check('…carrying the version the first write produced, not the one it was made with',
    !! second && second.version !== first.version, `${first?.version} → ${second?.version}`);
  check('both writes are accepted', ! one.email.error && ! one.role.error
    && one.email.serverValue === email && one.role.serverValue === role, JSON.stringify(one));
  check('…and both reached the database', db1('email') === email && db1('role') === role, `${db1('email')} / ${db1('role')}`);
  await shot('01-two-cells');

  // ── 2. the cell is locked while its own write is out — and keeps the focus ─
  await eval_(`__net.writes = []; __net.requests = []; true`);
  const firstValue = `first-${stamp}@example.test`;

  await eval_(`(() => {
    const root = $cell('1', 'email');
    const input = root.querySelector('input');
    input.focus();
    const cell = Alpine.$data(root);
    cell.value = ${JSON.stringify(firstValue)};
    cell.onEnter();                                  // Enter saves and keeps the focus
    return true;
  })()`);
  await sleep(200);
  // Real keystrokes, the way a fast typist keeps going after Enter.
  await page('Input.insertText', { text: 'XYZ' });
  const during = JSON.parse(await eval_(`(() => {
    const root = $cell('1', 'email');
    const input = root.querySelector('input');
    const cell = Alpine.$data(root);
    const refused = cell.commit('second-' + Date.now());   // a second commit while the first is out
    return JSON.stringify({ readonly: input.readOnly, busy: input.getAttribute('aria-busy'), disabled: input.disabled,
      focused: document.activeElement === input, saving: cell.saving, shown: input.value, value: cell.value });
  })()`));

  // A row's other cells are not locked by it.
  const role2 = (await snap('role')).serverValue === 'admin' ? 'editor' : 'admin';
  const roleFree = JSON.parse(await eval_(`JSON.stringify({ saving: $data('1', 'role').saving,
    disabled: $cell('1', 'role').querySelector('[aria-haspopup=listbox]').disabled })`));
  await eval_(`$data('1', 'role').commit(${JSON.stringify(role2)}); true`);
  await settled('email', 'role');
  const after = JSON.parse(await eval_(`(() => { const input = $cell('1', 'email').querySelector('input');
    return JSON.stringify({ readonly: input.readOnly, focused: document.activeElement === input }); })()`));

  const two = { email: await snap('email'), role: await snap('role'), net: JSON.parse(await eval_('JSON.stringify(__net)')) };
  check('the input is locked (readonly, aria-busy) while its write is out', during.saving === true
    && during.readonly === true && during.busy === 'true', JSON.stringify(during));
  check('…but not disabled, so it keeps the focus', during.disabled === false && during.focused === true, JSON.stringify(during));
  check('…keystrokes typed meanwhile do not land in it', during.shown === firstValue && during.value === firstValue, JSON.stringify(during));
  check('…and a second commit is refused rather than sent beside the first',
    two.net.writes.filter((w) => w.column === 'email').length === 1, JSON.stringify(two.net.writes));
  check('another cell of the row is not locked by it and writes too', roleFree.saving === false && roleFree.disabled === false
    && two.role.serverValue === role2 && ! two.role.error && db1('role') === role2, `${JSON.stringify(roleFree)} ${JSON.stringify(two.role)}`);
  check('once written the cell unlocks and still has the focus', after.readonly === false && after.focused === true, JSON.stringify(after));
  check('…and holds what was written', two.email.serverValue === firstValue && ! two.email.error && db1('email') === firstValue,
    `${JSON.stringify(two.email)} db=${db1('email')}`);
  await shot('02-locked');

  // A select picked while its own write is out is put back.
  await eval_(`__net.writes = []; true`);
  const role3 = role2 === 'admin' ? 'viewer' : 'admin';
  const reverted = JSON.parse(await eval_(`(async () => {
    const root = $cell('1', 'role');
    const list = Alpine.$data(root.querySelector('[x-data^="wireSearchableSelect"]'));
    const cell = Alpine.$data(root);
    cell.change(${JSON.stringify(role3)});
    await new Promise((r) => setTimeout(r, 150));
    list.select('editor' === ${JSON.stringify(role3)} ? 'viewer' : 'editor');
    await new Promise((r) => setTimeout(r, 50));
    return JSON.stringify({ shown: list.selected, value: cell.value });
  })()`));
  await settled('role');
  check('a select changed during its own write is put back to the value being written',
    reverted.shown === role3 && reverted.value === role3, JSON.stringify(reverted));
  check('…and only that one value is written', JSON.stringify(JSON.parse(await eval_('JSON.stringify(__net.writes)')).map((w) => w.value)) === JSON.stringify([role3])
    && db1('role') === role3, db1('role'));

  // ── 3. somebody else between the user's two writes ─────────────────────
  await eval_(`__net.writes = []; __net.requests = []; true`);
  const mine = `mine-${stamp}@example.test`;
  const others = ['admin', 'editor', 'viewer'].filter((r) => r !== db1('role'));
  const theirs = others[0];
  const refused = others[1];

  await eval_(`$data('1', 'email').commit(${JSON.stringify(mine)}); true`);
  await until(() => eval_(`__net.writes.length === 1`), { timeout: 4000 });
  await sleep(300); // the write has landed on the server; its answer is held
  sql(`update users set role = '${theirs}', updated_at = datetime('now', '+30 seconds') where id = 1`);
  await eval_(`$data('1', 'role').commit(${JSON.stringify(refused)}); true`);
  await settled('email', 'role');

  const three = { email: await snap('email'), role: await snap('role') };
  check('the user\'s own first write lands', three.email.serverValue === mine && ! three.email.error, JSON.stringify(three.email));
  check('the write over somebody else\'s is refused', !! three.role.error, JSON.stringify(three.role));
  check('…their value survives in the database and is shown', db1('role') === theirs && three.role.value === theirs,
    `db=${db1('role')} shown=${three.role.value}`);
  await shot('03-foreign-write');

  // A reload is the only proof the screen and the database agree.
  await page('Page.navigate', { url });
  await sleep(4000);
  await install();
  const reloaded = { email: await snap('email'), role: await snap('role') };
  check('after a reload the grid shows exactly what is stored',
    reloaded.email.serverValue === mine && reloaded.role.serverValue === theirs, JSON.stringify(reloaded));
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
