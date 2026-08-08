import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver for /previews/forms-enum-defaults, covering what Pest
 * cannot see: whether a field's ->default() actually reaches the rendered
 * control, and whether clearing an enum-sourced combobox (which writes null,
 * not '') still passes validation.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-enum-defaults.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/forms-enum-defaults';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9336);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-enum-defaults-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-enum-defaults-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (method, params) => cdp.send(method, params, sessionId);
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
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  await eval_(`
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.qtyInput = () => $q('#data\\\\.qty');
    window.wire = () => Livewire.all()[0].$wire;
    window.state = () => JSON.parse(JSON.stringify(wire().get('data')));
    // The combobox trigger for a given statePath, and its Alpine scope.
    window.combo = (path) => $qa('[x-data]').find(el => (el.getAttribute('x-data')||'').includes("'data." + path + "'"));
    window.comboData = (path) => Alpine.$data(combo(path));
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !!qtyInput()`);
  check('preview boots with Alpine + Livewire and renders the qty input', booted);
  await shot('01-initial');

  // ── 1. Server state carries the scalarised enum default ────────────────
  const state = await eval_(`JSON.stringify(state())`);
  const s = JSON.parse(state);
  check('enum ->default(Status::Draft) reaches state as the scalar key', s.status === 'draft', `status=${JSON.stringify(s.status)}`);
  check('numeric ->default(1) reaches state', s.qty === 1, `qty=${JSON.stringify(s.qty)}`);
  check('placeholder select with no default starts null', s.priority === null, `priority=${JSON.stringify(s.priority)}`);

  // ── 2. The rendered controls actually SHOW those defaults ──────────────
  const qtyShown = await eval_(`qtyInput().value`);
  check('qty input displays the default in the browser', qtyShown === '1', `input.value=${JSON.stringify(qtyShown)}`);

  const statusLabel = await eval_(`combo('status') ? comboData('status').selectedLabel : '<no combobox>'`);
  check('status combobox displays the default label', statusLabel === 'Draft', `selectedLabel=${JSON.stringify(statusLabel)}`);

  const priorityLabel = await eval_(`combo('priority') ? comboData('priority').selectedLabel : '<no combobox>'`);
  check('priority combobox shows the placeholder, not a value', !priorityLabel, `selectedLabel=${JSON.stringify(priorityLabel)}`);

  // ── 3. Validation passes with the untouched defaults ───────────────────
  await eval_(`wire().call('submitPreview')`);
  await sleep(1800);
  const errs1 = await eval_(`JSON.stringify($qa('[data-testid=form-error], .text-red-600, .text-red-500').map(e => e.innerText.trim()).filter(Boolean))`);
  check('defaults validate cleanly', JSON.parse(errs1).length === 0, `errors=${errs1}`);
  await shot('02-validated-defaults');

  // ── 4. Clearing an enum select writes null — must still validate ───────
  await eval_(`comboData('status').clear()`);
  await sleep(1800);
  const cleared = await eval_(`JSON.stringify(state().status)`);
  check('clearing the enum combobox writes null (not empty string)', JSON.parse(cleared) === null, `status=${cleared}`);
  await shot('03-cleared');

  await eval_(`wire().call('submitPreview')`);
  await sleep(1800);
  const errs2 = await eval_(`JSON.stringify($qa('[data-testid=form-error], .text-red-600, .text-red-500').map(e => e.innerText.trim()).filter(Boolean))`);
  check('a cleared optional enum select passes validation (the nullable fix)', JSON.parse(errs2).length === 0, `errors=${errs2}`);
  await shot('04-validated-cleared');

  // ── 5. A real value still round-trips, and a bogus one is still rejected ─
  await eval_(`comboData('status').select('published')`);
  await sleep(1800);
  const picked = await eval_(`JSON.stringify(state().status)`);
  check('picking an option writes its scalar key', JSON.parse(picked) === 'published', `status=${picked}`);

  await eval_(`wire().set('data.status', 'bogus')`);
  await sleep(800);
  await eval_(`wire().call('submitPreview')`);
  await sleep(1800);
  const errs3 = await eval_(`JSON.stringify($qa('[data-testid=form-error], .text-red-600, .text-red-500').map(e => e.innerText.trim()).filter(Boolean))`);
  check('an off-list value is still rejected by the implicit in: rule', JSON.parse(errs3).length > 0, `errors=${errs3}`);
  await shot('05-bogus-rejected');

  console.log('\nSummary: ' + results.filter(r => r.ok).length + '/' + results.length + ' checks passed');
  console.log('Screenshots: ' + shotDir);
  if (results.some(r => !r.ok)) process.exitCode = 1;
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  cdp?.close();
  chrome.kill('SIGTERM');
  await rm(userDataDir, { recursive: true, force: true }).catch(() => {});
}

async function waitForDevtools(port) {
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      const json = await res.json();
      if (json.webSocketDebuggerUrl) return json.webSocketDebuggerUrl;
    } catch {}
    await sleep(250);
  }
  throw new Error('DevTools endpoint never came up');
}

function connect(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl);
    const pending = new Map();
    const listeners = [];
    let nextId = 1;
    ws.addEventListener('open', () => resolve({
      send(method, params = {}, sessionId) {
        const id = nextId++;
        return new Promise((res, rej) => {
          pending.set(id, { res, rej });
          ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
        });
      },
      on(fn) { listeners.push(fn); },
      close() { ws.close(); },
    }));
    ws.addEventListener('error', (err) => reject(err));
    ws.addEventListener('message', (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && pending.has(msg.id)) {
        const { res, rej } = pending.get(msg.id);
        pending.delete(msg.id);
        msg.error ? rej(new Error(msg.error.message)) : res(msg.result);
      } else {
        listeners.forEach((fn) => fn(msg));
      }
    });
  });
}
