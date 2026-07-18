import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * CDP driver for two morph/runtime fixes verified in the workbench:
 *  - Chart widget (/previews/widgets-chart): destroy() + wire:ignore so an
 *    Alpine re-init tears the old Chart.js down instead of leaking / throwing
 *    "Canvas is already in use".
 *  - Repeater (/previews/forms-repeater): byte-stable x-data so adding a row
 *    does NOT re-init Alpine and reset every row's collapse state.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # background
 *   node workbench/scripts/verify-optim-morph.mjs
 * Exit 0 = all pass, 1 = a check failed, 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9336);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-optim-morph-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-optim-morph-${Date.now()}`);
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

  const common = `
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
  `;

  // ─────────────────────────── CHART ───────────────────────────
  await page('Page.navigate', { url: `${base}/widgets-chart` });
  await sleep(3500);
  await eval_(common + `
    window.chartRoot = () => $qa('[x-data]').find(el => (el.getAttribute('x-data')||'').includes('wireChart'));
    true;
  `);
  const chartBooted = await eval_(`!!chartRoot() && typeof Alpine !== 'undefined'`);
  check('chart preview renders with Alpine booted', chartBooted);
  const chartLoaded = await eval_(`typeof Chart !== 'undefined'`);
  check('Chart.js loaded (CDN reachable)', chartLoaded);
  await shot('01-chart-initial');

  if (chartLoaded) {
    const hasInstance = await eval_(`!!Alpine.$data(chartRoot()).chart`);
    check('chart instance created on init', hasInstance);

    // Directly exercise the fix: destroy() must free the canvas, and a following
    // init() must build a fresh chart WITHOUT "Canvas is already in use".
    const reinit = await eval_(`(() => {
      try {
        const d = Alpine.$data(chartRoot());
        d.destroy();
        const destroyed = d.chart === null;
        d.init();
        const rebuilt = !!d.chart;
        return JSON.stringify({ destroyed, rebuilt, err: null });
      } catch (e) { return JSON.stringify({ destroyed: null, rebuilt: null, err: String(e) }); }
    })()`);
    const r = JSON.parse(reinit);
    check('destroy() frees canvas and re-init rebuilds without "canvas in use"',
      r.destroyed === true && r.rebuilt === true && r.err === null, reinit);
    await shot('02-chart-reinit');
  }

  // ────────────────────────── REPEATER ──────────────────────────
  await page('Page.navigate', { url: `${base}/forms-repeater` });
  await sleep(3500);
  await eval_(common + `
    window.repRoot = () => $qa('[x-data]').find(el => /collapsed\\s*:/.test(el.getAttribute('x-data')||''));
    window.repXData = () => (repRoot()?.getAttribute('x-data') || '');
    window.collapseToggles = () => $qa('button').filter(b => b.querySelector('svg') && /rotate-180|chevron/.test(b.outerHTML));
    true;
  `);
  const repBooted = await eval_(`!!repRoot()`);
  check('repeater preview renders with a collapse-capable root', repBooted);
  await shot('03-repeater-initial');

  // The x-data must NOT contain a per-item collapsed array (the morph-reset cause).
  const xdataBefore = await eval_(`repXData()`);
  const noArray = !/collapsed\s*:\s*\[/.test(xdataBefore);
  check('x-data uses an object, not a per-item collapsed array', noArray);

  // Collapse the first item.
  const collapsed0 = await eval_(`(() => {
    const root = repRoot();
    const data = Alpine.$data(root);
    if (typeof data.toggleCollapse !== 'function') return 'no-toggle';
    data.toggleCollapse(0);
    return String(data.isCollapsed(0));
  })()`);
  check('first row collapses', collapsed0 === 'true', collapsed0);
  await sleep(400);
  await shot('04-repeater-collapsed');

  // Add a row → Livewire roundtrip + morph. Then the first row must STAY collapsed.
  const addClicked = await eval_(`(() => {
    const add = $qa('[data-testid$="-add"]')[0] || $qa('button').find(b => /add contact/i.test(b.innerText));
    if (!add) return false; add.click(); return true;
  })()`);
  check('add-row button found and clicked', addClicked === true);
  await sleep(2500);

  const xdataAfter = await eval_(`repXData()`);
  check('x-data is byte-identical after adding a row (no morph re-init)',
    xdataBefore === xdataAfter);

  const stillCollapsed = await eval_(`(() => {
    const d = Alpine.$data(repRoot());
    return d && typeof d.isCollapsed === 'function' ? String(d.isCollapsed(0)) : 'no-data';
  })()`);
  check('first row STAYS collapsed after adding a row (morph-reset fixed)',
    stillCollapsed === 'true', stillCollapsed);
  await shot('05-repeater-after-add');

  check('no console errors during the run', consoleErrors.length === 0,
    consoleErrors.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${shotDir}`);
  const failed = results.filter((r) => !r.ok);
  chrome.kill();
  process.exit(failed.length ? 1 : 0);
} catch (err) {
  console.error('DRIVER ERROR:', err);
  chrome.kill();
  process.exit(2);
}

// ─── CDP scaffolding (copied from verify-wizard-live.mjs) ───
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
