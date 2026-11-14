import { execFileSync } from 'node:child_process';
import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * SelectColumn as the shared combobox (/previews/table-select-cells).
 *
 * Only a browser can say this: the list a trigger opens is the column's one
 * list, cloned into <body> under that row's scope on first open (not drawn per
 * row); a pick writes through the cell's own updateTableCell(); a multiple cell
 * writes once, as its list closes; the native column is a plain <select> that
 * writes on change; "Create" opens the modal and the new option lands in the
 * row it came from; and on a phone the searchable column opens as a touch sheet.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-select-cells`;
const db = process.env.PREVIEW_DB ?? 'vendor/orchestra/testbench-core/laravel/database/database.sqlite';
const sql = (query) => execFileSync('sqlite3', [db, query], { encoding: 'utf8' }).trim();

// Before the page loads: the cells start from what the database holds.
const firstIdAtStart = Number(sql('select min(id) from tasks'));
sql(`update tasks set status = 'todo', labels = null where id = ${firstIdAtStart}`);

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({ url, shotPrefix: 'select-cells', width: 1300, height: 900 });
const { check, finish } = checker();

const firstId = Number(sql('select min(id) from tasks'));
const secondId = Number(sql('select min(id) from tasks where id > (select min(id) from tasks)'));
const cellOf = (id, col) => `document.querySelector('[data-record-key="${id}"][data-column-name="${col}"]')`;
const triggerOf = (id, col) => `${cellOf(id, col)}.querySelector('[aria-haspopup=listbox]')`;
const openPanel = `[...document.body.children].find((el) => el.querySelector?.('[role=listbox]') && [...el.querySelectorAll('[x-ref=panel]')].some((p) => p.style.display !== 'none'))`;
const clones = `document.querySelectorAll('[data-teleport-target]:has(template[x-for])').length`;
const writes = `window.__writes`;

try {
  await waitFor(`!! ${triggerOf(firstId, 'status')} && !! window.Alpine`);
  await eval_(`(() => {
    window.__writes = [];
    const original = window.fetch;
    window.fetch = (input, options = {}) => {
      try {
        JSON.parse(options.body).components.flatMap((c) => c.calls || []).filter((x) => x.method === 'updateTableCell')
          .forEach((x) => window.__writes.push({ column: x.params[1], value: x.params[2] }));
      } catch (e) {}
      return original(input, options);
    };
  })()`);

  // Other selects on the page (the toolbar's) teleport lists of their own.
  const baseline = await eval_(clones);
  check('no row carries a list of its own before one is opened', (await eval_(`document.querySelectorAll('tbody [data-teleport-target], tbody template[x-teleport]').length`)) === 0);
  check('the column list is drawn once, as a template', (await eval_(`document.querySelectorAll('template[data-select-panel="select-column:status"]').length`)) === 1);

  // ── A single pick ──────────────────────────────────────────────────────
  await eval_(`${triggerOf(firstId, 'status')}.click()`);
  await waitFor(`!! (${openPanel})`);
  check('opening a trigger clones the column list into <body>', (await eval_(clones)) === baseline + 1, `${baseline} → ${await eval_(clones)}`);
  check('…with the column\'s options', (await eval_(`(${openPanel}).textContent.includes('In progress')`)) === true);
  await shot('01-open');

  await eval_(`(() => { const i = (${openPanel}).querySelector('[data-testid=select-search]'); i.value = 'don'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  await waitFor(`(${openPanel}).querySelectorAll('[role=option] [data-testid^=select-option-]').length === 1`).catch(() => {});
  check('the search narrows the list', (await eval_(`(${openPanel}).querySelectorAll('[data-testid^=select-option-]').length`)) === 1);
  await eval_(`(${openPanel}).querySelector('[data-testid=select-option-done]').click()`);
  await waitFor(`Alpine.$data(${cellOf(firstId, 'status')}).saving === false && ${writes}.length === 1`).catch(() => {});
  await sleep(300);
  check('a pick writes through updateTableCell', JSON.stringify(await eval_(writes)) === JSON.stringify([{ column: 'status', value: 'done' }]), JSON.stringify(await eval_(writes)));
  check('…and the database holds it', sql(`select status from tasks where id = ${firstId}`) === 'done');
  check('the trigger shows the label', (await eval_(`${triggerOf(firstId, 'status')}.textContent.trim()`)) === 'Done');

  // A second row opens the same template, under its own scope.
  await eval_(`${triggerOf(secondId, 'status')}.click()`);
  await waitFor(`!! (${openPanel})`);
  const secondStatus = sql(`select status from tasks where id = ${secondId}`);
  check('another row\'s list marks that row\'s value', (await eval_(`(${openPanel}).querySelector('[aria-selected=true]')?.id ?? ''`)).endsWith(`-option-${secondStatus}`), secondStatus);
  await eval_(`document.body.click()`);
  await sleep(200);

  // ── Refused on the server ─────────────────────────────────────────────
  const refused = await eval_(`(async () => {
    const cell = Alpine.$data(${cellOf(firstId, 'status')});
    await cell.commit('forged');
    return { value: cell.value, error: cell.error };
  })()`);
  check('a value that is not an option is refused and rolled back', refused.value === 'done' && !! refused.error, JSON.stringify(refused));
  check('…and the database still holds the real one', sql(`select status from tasks where id = ${firstId}`) === 'done');
  await sleep(200);
  const laid = await eval_(`(() => {
    const cell = ${cellOf(firstId, 'status')};
    const msg = cell.querySelector('[data-cell-error]').getBoundingClientRect();
    const own = ${triggerOf(firstId, 'status')}.getBoundingClientRect();
    const next = ${cellOf(firstId, 'owner_name')}.querySelector('[aria-haspopup=listbox]').getBoundingClientRect();
    return { top: Math.round(own.top), neighbour: Math.round(next.top), msgLeft: Math.round(msg.left), msgRight: Math.round(msg.right), left: Math.round(own.left), right: Math.round(own.right), below: msg.top >= own.bottom };
  })()`);
  check('the message sits under the select, no wider than it, and the select stays level with its row',
    laid.below && laid.msgLeft >= laid.left - 1 && laid.msgRight <= laid.right + 1 && Math.abs(laid.top - laid.neighbour) <= 1, JSON.stringify(laid));

  // ── Multiple: one write, as the list closes ───────────────────────────
  await eval_(`${writes}.length = 0`);
  await eval_(`${triggerOf(firstId, 'labels')}.click()`);
  await waitFor(`!! (${openPanel})`);
  await eval_(`(${openPanel}).querySelector('[data-testid=select-option-bug]').click()`);
  await eval_(`(${openPanel}).querySelector('[data-testid=select-option-docs]').click()`);
  await sleep(200);
  check('ticking options of a multiple cell writes nothing yet', (await eval_(`${writes}.length`)) === 0);
  await shot('02-multiple');
  await eval_(`${triggerOf(firstId, 'labels')}.click()`);
  await waitFor(`${writes}.length === 1`).catch(() => {});
  await sleep(400);
  check('closing the list writes the list once', JSON.stringify(await eval_(writes)) === JSON.stringify([{ column: 'labels', value: ['bug', 'docs'] }]), JSON.stringify(await eval_(writes)));
  check('…and the database holds it', sql(`select labels from tasks where id = ${firstId}`) === '["bug","docs"]', sql(`select labels from tasks where id = ${firstId}`));
  check('the cell does not read as unsaved afterwards', (await eval_(`Alpine.$data(${cellOf(firstId, 'labels')}).dirty`)) === false);

  // ── Native ─────────────────────────────────────────────────────────────
  await eval_(`${writes}.length = 0`);
  const nativeWant = sql(`select priority from tasks where id = ${firstId}`) === 'low' ? 'high' : 'low';
  check('the native column is the browser select', (await eval_(`${cellOf(firstId, 'priority')}.querySelector('select')?.value`)) === sql(`select priority from tasks where id = ${firstId}`));
  await eval_(`(() => { const s = ${cellOf(firstId, 'priority')}.querySelector('select'); s.value = '${nativeWant}'; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  await waitFor(`${writes}.length === 1`).catch(() => {});
  await sleep(400);
  check('…and writes on change', sql(`select priority from tasks where id = ${firstId}`) === nativeWant, JSON.stringify(await eval_(writes)));

  // ── Create an option ──────────────────────────────────────────────────
  const owner = `Owner ${Date.now() % 100000}`;
  await eval_(`${triggerOf(secondId, 'owner_name')}.click()`);
  await waitFor(`!! (${openPanel})?.querySelector('[data-testid=table-select-owner_name-create-option]')`);
  await eval_(`(${openPanel}).querySelector('[data-testid=table-select-owner_name-create-option]').click()`);
  const modalInput = `document.querySelector('input[wire\\\\:model*="modal.actions"], input[wire\\\\:model\\\\.live*="modal.actions"]')`;
  await waitFor(`!! ${modalInput}`);
  await shot('03-create-modal');
  await eval_(`(() => { const i = ${modalInput}; i.value = ${JSON.stringify(owner)}; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  await sleep(300);
  await eval_(`[...document.querySelectorAll('button')].find((b) => b.getClientRects().length > 0 && b.textContent.includes('Create') && ! b.textContent.includes('option'))?.click()`);
  await waitFor(`${triggerOf(secondId, 'owner_name')}.textContent.includes(${JSON.stringify(owner)})`).catch(() => {});
  check('a created option lands in the row it came from', sql(`select owner_name from tasks where id = ${secondId}`) === owner, sql(`select owner_name from tasks where id = ${secondId}`));
  check('…and its trigger shows it', (await eval_(`${triggerOf(secondId, 'owner_name')}.textContent.trim()`)) === owner);
  await eval_(`${triggerOf(firstId, 'owner_name')}.click()`);
  await waitFor(`!! (${openPanel})`);
  check('…and every other row of the column can pick it', (await eval_(`(${openPanel}).textContent.includes(${JSON.stringify(owner)})`)) === true);
  await eval_(`document.body.click()`);
  await sleep(200);
  await shot('04-created');

  // ── Phone: the searchable column is a touch sheet ─────────────────────
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await sleep(400);
  await eval_(`${triggerOf(secondId, 'status')}.scrollIntoView(); ${triggerOf(secondId, 'status')}.click()`);
  const sheetWidth = `Math.max(0, ...[...document.querySelectorAll('[data-testid=select-touch-sheet]')].filter((el) => getComputedStyle(el).display !== 'none').map((el) => Math.round(el.getBoundingClientRect().width)))`;
  await waitFor(`${sheetWidth} >= 380`).catch(() => {});
  await sleep(300);
  check('on a phone the list opens as a full-width touch sheet', (await eval_(sheetWidth)) >= 380, String(await eval_(sheetWidth)));
  await shot('05-phone-sheet');
  sql(`update tasks set owner_name = 'Sofia Bennett' where owner_name like 'Owner %'`);
} catch (error) {
  check('driver ran to completion', false, error.message);
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
