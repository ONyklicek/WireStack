import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * CDP driver for the per-page select on an EDITABLE table
 * (/previews/table-editable-fill-paged).
 *
 * Livewire merges everything queued for one component in the same tick into ONE
 * commit — updates applied first, calls after. On an editable table that means a
 * cell edit (committed on blur, as clicking the select does) rides along with
 * the select's `tableState.pagination.perPage` update. `updateTableCell()` calls
 * skipRender() on purpose, so the DOM is not morphed out from under the Alpine
 * cell state — and that skip used to take the per-page render with it: the new
 * size landed in the snapshot, the rows on screen did not change, and the table
 * only caught up on whatever the user did next.
 *
 * Both halves are asserted here, because the fix is a balance:
 *   - a cell edit ALONE must still skip the render (no morph, cell state intact)
 *   - a cell edit SHARING the request with a per-page change must render
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # background
 *   node workbench/scripts/verify-editable-per-page.mjs
 * Exit 0 = all pass, 1 = a check failed, 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9341);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-editable-per-page-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-editable-per-page-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (method, params) => cdp.send(method, params, sessionId);
  const consoleErrors = [];
  const eval_ = async (expression) => {
    const { result, exceptionDetails } = await page('Runtime.evaluate', {
      expression, returnByValue: true, awaitPromise: true,
    });
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'JS error');
    return result?.value;
  };
  const shot = async (name) => {
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Console.enable');
  cdp.on('Console.messageAdded', (p) => {
    const m = p.params?.message;
    if (m && m.level === 'error') consoleErrors.push(m.text);
  });
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });

  await page('Page.navigate', { url: `${base}/table-editable-fill-paged` });
  await sleep(3500);

  await eval_(`
    window.$rows = () => document.querySelectorAll('tbody tr[data-row-key]').length;
    window.$sel = () => document.querySelector('[data-testid="table-per-page"]');
    window.$wireHost = () => window.Livewire.find(
      document.querySelector('[wire\\\\:id]').getAttribute('wire:id')
    );
    // First editable cell: its Alpine root carries the record key / column name.
    window.$cell = () => document.querySelector('tbody [data-record-key][data-column-name]');
    window.$perPage = () => $wireHost().get('tableState.pagination.perPage');
    true;
  `);

  const booted = await eval_(`!!$sel() && !!$cell() && $rows() > 0`);
  check('editable paginated table renders with a per-page select', booted);
  await shot('01-initial');

  const initialRows = await eval_(`$rows()`);
  check('first page shows the configured 2 rows', initialRows === 2, `rows=${initialRows}`);

  // ── A cell edit ALONE must still leave the DOM alone ──────────────
  // The skip is what keeps a morph from resetting every cell's Alpine state
  // mid-edit; the fix must not have traded that away.
  const editOnly = await eval_(`(async () => {
    const cell = $cell();
    const key = cell.dataset.recordKey, col = cell.dataset.columnName;
    let rendered = false;
    const stop = window.Livewire.hook('morph', () => { rendered = true });
    await $wireHost().call('updateTableCell', key, col, 'solo-' + col + '@example.test', null);
    await new Promise(r => setTimeout(r, 600));
    stop && stop();
    return JSON.stringify({ rendered, rows: $rows() });
  })()`);
  const solo = JSON.parse(editOnly);
  check('a cell edit on its own still skips the table render',
    solo.rendered === false && solo.rows === 2, editOnly);

  // ── The merged commit: a cell edit AND the per-page change ────────
  // Queued in the same tick, which is exactly what blurring an edited cell by
  // clicking the select produces. Livewire folds both into one commit.
  const merged = await eval_(`(async () => {
    const cell = $cell();
    const key = cell.dataset.recordKey, col = cell.dataset.columnName;
    const before = $rows();

    const sel = $sel();
    sel.value = '4';
    sel.dispatchEvent(new Event('input', { bubbles: true }));
    sel.dispatchEvent(new Event('change', { bubbles: true }));
    // Same tick → same commit as the update above.
    const call = $wireHost().call('updateTableCell', key, col, 'merged-' + col + '@example.test', null);

    await call;
    await new Promise(r => setTimeout(r, 800));

    return JSON.stringify({ before, rows: $rows(), perPage: $perPage(), selValue: sel.value });
  })()`);
  const m = JSON.parse(merged);
  console.log('merged commit →', m);

  check('the server accepted the new page size', String(m.perPage) === '4', merged);
  check('the rows on screen follow it in the SAME request (no second action)',
    m.rows === 4, merged);
  check('the select still shows what the table is rendering',
    m.selValue === '4' && m.rows === 4, merged);
  await shot('02-after-merged-commit');

  // ── And the plain path is unchanged ───────────────────────────────
  const plain = await eval_(`(async () => {
    const sel = $sel();
    sel.value = '2';
    sel.dispatchEvent(new Event('input', { bubbles: true }));
    sel.dispatchEvent(new Event('change', { bubbles: true }));
    await new Promise(r => setTimeout(r, 1200));
    return JSON.stringify({ rows: $rows(), perPage: $perPage() });
  })()`);
  const p = JSON.parse(plain);
  check('a per-page change with no cell edit still applies at once',
    p.rows === 2 && String(p.perPage) === '2', plain);
  await shot('03-after-plain-change');

  check('no console errors during the run', consoleErrors.length === 0,
    consoleErrors.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${shotDir}`);
  const failed = results.filter((r) => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
  chrome.kill();
  process.exit(failed.length ? 1 : 0);
} catch (err) {
  console.error('DRIVER ERROR:', err);
  chrome.kill();
  process.exit(2);
}

// ─── CDP scaffolding (shared shape with the other drivers) ───
async function waitForDevtools(port) {
  for (let i = 0; i < 50; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      const j = await res.json();
      if (j.webSocketDebuggerUrl) return j.webSocketDebuggerUrl;
    } catch {}
    await sleep(200);
  }
  throw new Error('DevTools endpoint never came up');
}

async function connect(wsUrl) {
  const { WebSocket } = await import('ws').catch(() => ({ WebSocket: globalThis.WebSocket }));
  const ws = new WebSocket(wsUrl, { perMessageDeflate: false });
  const pending = new Map();
  const listeners = [];
  let id = 0;
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
  ws.onmessage = (ev) => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
    } else if (msg.method) {
      listeners.forEach((fn) => fn(msg));
    }
  };
  return {
    send(method, params = {}, sessionId) {
      return new Promise((resolve, reject) => {
        const mid = ++id;
        pending.set(mid, { resolve, reject });
        ws.send(JSON.stringify({ id: mid, method, params, sessionId }));
      });
    },
    on(_evt, fn) { listeners.push(fn); },
  };
}
