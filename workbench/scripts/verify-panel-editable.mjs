import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver verifying the editable panel preview
 * (/previews/panels-editable): a Model-backed record panel where a toggle,
 * a select, and a text input each commit directly to the User row through
 * $wire.updatePanelEntry with optimistic UI. Confirms optimistic flip in Alpine
 * AND server persistence via a fresh GET, plus no console errors / no 419.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-panel-editable.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/panels-editable';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9335);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-panel-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-panel-verify-${Date.now()}`);
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

const consoleErrors = [];
const badResponses = [];

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

  // Capture runtime errors + any 4xx/5xx (esp. 419) on the wire.
  cdp.on((msg) => {
    if (msg.method === 'Runtime.exceptionThrown') {
      consoleErrors.push(msg.params?.exceptionDetails?.exception?.description ?? 'exception');
    }
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params?.type === 'error') {
      consoleErrors.push((msg.params.args ?? []).map((a) => a.value ?? a.description ?? '').join(' '));
    }
    if (msg.method === 'Network.responseReceived') {
      const s = msg.params?.response?.status;
      if (s >= 400) badResponses.push(`${s} ${msg.params.response.url}`);
    }
  });

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Network.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1200, height: 1200, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.root = (name) => $q('[data-testid="panel-editable-'+name+'"]');
    window.data = (name) => Alpine.$data(root(name));
    window.freshValue = async (name) => {
      const html = await fetch('${url}', { headers: { 'X-Requested-With': 'fetch' } }).then(r => r.text());
      const re = new RegExp('data-testid="panel-editable-'+name+'"[\\\\s\\\\S]{0,400}?data-server-value="([^"]*)"');
      const m = html.match(re);
      return m ? m[1] : null;
    };
    true;
  `;
  await eval_(helpers);

  // ── 0. Panel booted with a real record + Alpine ───────────────────────
  const booted = await eval_(`typeof Alpine !== 'undefined' && !!root('is_active') && root('is_active').getAttribute('data-record-key') !== ''`);
  check('editable panel renders with Alpine booted and a bound record', booted);
  await shot('01-initial');

  // ── 1. Toggle commits optimistically + persists ───────────────────────
  const before = await eval_(`data('is_active').value`);
  await eval_(`root('is_active').querySelector('button[role="switch"]').click()`);
  await sleep(1800);
  const afterOptimistic = await eval_(`data('is_active').value`);
  check('toggle flips optimistically in Alpine', afterOptimistic === !before, `${before} -> ${afterOptimistic}`);
  const persistedToggle = await eval_(`(async () => await freshValue('is_active'))()`);
  const expected = afterOptimistic ? '1' : '0';
  check('toggle write persisted to the record (fresh GET)', persistedToggle === expected, `server=${persistedToggle} expected=${expected}`);
  await shot('02-toggle');

  // ── 2. Select commits + persists ──────────────────────────────────────
  await eval_(`(() => { const s = root('role').querySelector('select'); s.value = 'admin'; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  await sleep(1800);
  const persistedRole = await eval_(`(async () => await freshValue('role'))()`);
  check('select write persisted to the record', persistedRole === 'admin', `server=${persistedRole}`);
  await shot('03-select');

  // ── 3. Text input commits on blur + persists ──────────────────────────
  await eval_(`root('name').querySelector('input').dispatchEvent(new Event('focus', { bubbles: true }))`);
  await sleep(200);
  await eval_(`(() => {
    const i = root('name').querySelector('input');
    i.value = 'Amelia Renamed';
    i.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await sleep(300);
  // Blur commits the dirty value (saveOnBlur). Split from the input above so
  // Alpine's x-model catches the value before the blur handler reads dirty.
  await eval_(`root('name').querySelector('input').dispatchEvent(new Event('blur', { bubbles: true }))`);
  await sleep(1800);
  const nameError = await eval_(`data('name').error`);
  const persistedName = await eval_(`(async () => await freshValue('name'))()`);
  check('text input write persisted to the record on blur', persistedName === 'Amelia Renamed', `server=${persistedName}${nameError ? ' error=' + nameError : ''}`);
  await shot('04-text');

  // ── 4. No runtime errors, no 419 on any commit ────────────────────────
  const no419 = !badResponses.some((r) => r.startsWith('419'));
  check('no 419 on any commit', no419, badResponses.join('; ') || 'none');
  check('no console/runtime errors', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  console.log('\nSummary: ' + results.filter((r) => r.ok).length + '/' + results.length + ' checks passed');
  console.log('Screenshots: ' + shotDir);
  if (badResponses.length) console.log('Non-2xx responses: ' + badResponses.join('; '));
  if (results.some((r) => !r.ok)) process.exitCode = 1;
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
