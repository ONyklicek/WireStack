import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying Fix 1 (record-actions.js): when a table binds BOTH a
 * single-click and a double-click record action, a real double-click must run
 * ONLY the double-click action — never the deferred single-click one.
 *
 * The browser fires `click` (twice) before `dblclick`, so this drives a genuine
 * double-click through Input.dispatchMouseEvent (proper clickCount) rather than
 * a synthetic dblclick event, and records what actually reaches the server via
 * a Livewire `commit` hook on `openActionModal`.
 *
 *   Preview: /previews/table-record-actions-dual  (view→onClick, edit→onDoubleClick)
 *
 * Exit 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-record-actions-dual`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9336);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-record-actions-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-record-actions-verify-${Date.now()}`);
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
  // A genuine OS-style double-click: two press/release pairs with clickCount
  // 1 then 2, so Chrome synthesizes the real click, click, dblclick sequence.
  const mouse = (type, x, y, clickCount) => page('Input.dispatchMouseEvent', {
    type, x, y, button: 'left', clickCount, buttons: type === 'mousePressed' ? 1 : 0,
  });
  const singleClick = async (x, y) => {
    await mouse('mousePressed', x, y, 1);
    await mouse('mouseReleased', x, y, 1);
  };
  const doubleClick = async (x, y) => {
    await mouse('mousePressed', x, y, 1);
    await mouse('mouseReleased', x, y, 1);
    await mouse('mousePressed', x, y, 2);
    await mouse('mouseReleased', x, y, 2);
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1600, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  // ── 0. Page + controller booted, both gestures bound ──────────────────
  const setup = await eval_(`
    (() => {
      window.$qa = (s) => [...document.querySelectorAll(s)];
      const tbody = $qa('[x-data]').find(el => (el.getAttribute('x-data')||'').includes('wireRecordActions'));
      if (!tbody) return { ok: false };
      const data = Alpine.$data(tbody);

      // Ground-truth sink: record every openActionModal that actually commits.
      window.__calls = [];
      window.Livewire.hook('commit', ({ commit }) => {
        (commit.calls || []).forEach(c => {
          if (c.method === 'openActionModal') window.__calls.push({ name: c.params?.[1] });
        });
      });

      // Center of a non-interactive cell for the given main-body row index.
      window.__cellXY = (i) => {
        const row = $qa('tbody tr[data-row-key]')[i];
        if (!row) return null;
        const td = [...row.querySelectorAll('td')].find(td => !td.querySelector('a,button,input,select,textarea,label,[role],[x-data]'));
        if (!td) return null;
        const r = td.getBoundingClientRect();
        return { x: Math.round(r.left + r.width / 2), y: Math.round(r.top + r.height / 2) };
      };

      // Copy off the Alpine reactive proxy — a raw proxy serializes to {} over CDP.
      return { ok: true, bindings: { click: data.bindings.click, dblclick: data.bindings.dblclick }, rows: $qa('tbody tr[data-row-key]').length };
    })()
  `);
  check('preview booted with wireRecordActions controller', !!setup && setup.ok, JSON.stringify(setup?.bindings));
  check('both click and dblclick actions are bound',
    !!setup?.bindings && setup.bindings.click === 'view' && setup.bindings.dblclick === 'edit',
    JSON.stringify(setup?.bindings));
  await shot('01-initial');

  if (!setup?.ok) throw new Error('controller not found — cannot continue');

  // ── 1. Single click on a row → only the deferred "view" action runs ───
  await eval_(`window.__calls = []`);
  const cell0 = await eval_(`window.__cellXY(0)`);
  await singleClick(cell0.x, cell0.y);
  await sleep(1400); // > 250ms defer + Livewire roundtrip
  const afterSingle = await eval_(`JSON.stringify(window.__calls)`);
  const singleCalls = JSON.parse(afterSingle);
  check('single click runs exactly the click action (view)',
    singleCalls.length === 1 && singleCalls[0].name === 'view', afterSingle);
  await shot('02-after-single-click');

  // ── 2. Real double-click on a row → only "edit", never "view" ─────────
  await eval_(`window.__calls = []`);
  const cell1 = await eval_(`window.__cellXY(1)`);
  await doubleClick(cell1.x, cell1.y);
  await sleep(1400); // long enough that a leaked click timer (250ms) would have fired
  const afterDouble = await eval_(`JSON.stringify(window.__calls)`);
  const doubleCalls = JSON.parse(afterDouble);
  check('double-click runs exactly the dblclick action (edit)',
    doubleCalls.length === 1 && doubleCalls[0].name === 'edit', afterDouble);
  check('double-click does NOT also run the single-click action (view)',
    !doubleCalls.some(c => c.name === 'view'), afterDouble);
  await shot('03-after-double-click');

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
