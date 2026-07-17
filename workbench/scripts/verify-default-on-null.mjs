import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for /previews/forms-default-on-null: an edit-mode form filled from
 * an all-null "record". Verifies that only ->defaultOnNull() fields resurrect
 * their default while a plain-default field keeps the record's null — the thing
 * that only shows in a real browser once Livewire hydrates the controls.
 *
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-default-on-null.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/forms-default-on-null';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-default-on-null-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-default-on-null-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', `--remote-debugging-port=${devtoolsPort}`,
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
    window.combo = (path) => $qa('[x-data]').find(el => (el.getAttribute('x-data')||'').includes("'data." + path + "'"));
    window.comboData = (path) => Alpine.$data(combo(path));
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !!qtyInput()`);
  check('preview boots with Alpine + Livewire', booted);
  await shot('01-initial');

  // Record was all-null; the opted-in fields must now hold their default.
  const s = JSON.parse(await eval_(`JSON.stringify(state())`));
  check('defaultOnNull enum field resurrects its default from null', s.status === 'draft', `status=${JSON.stringify(s.status)}`);
  check('defaultOnNull numeric field resurrects its default from null', s.qty === 1, `qty=${JSON.stringify(s.qty)}`);
  check('plain-default field keeps the record null', s.kind === null, `kind=${JSON.stringify(s.kind)}`);

  // And the controls actually show it.
  const qtyShown = await eval_(`qtyInput().value`);
  check('qty input displays the resurrected default', qtyShown === '1', `input.value=${JSON.stringify(qtyShown)}`);

  const statusLabel = await eval_(`combo('status') ? comboData('status').selectedLabel : '<none>'`);
  check('status combobox displays the resurrected default label', statusLabel === 'Draft', `selectedLabel=${JSON.stringify(statusLabel)}`);

  const kindLabel = await eval_(`combo('kind') ? comboData('kind').selectedLabel : '<none>'`);
  check('kind combobox shows the placeholder, not a default', !kindLabel, `selectedLabel=${JSON.stringify(kindLabel)}`);

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
