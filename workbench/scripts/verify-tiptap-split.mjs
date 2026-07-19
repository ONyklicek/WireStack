import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * CDP driver for the ESM code-split TipTap editor (B1). Verifies the module-script
 * delivery actually boots the editor and that the opt-in addon chunk is loaded
 * only when a field enables tables/images:
 *  - /previews/field-tiptap: core editor boots (.ProseMirror contenteditable),
 *    window.WireTiptapAddons is UNDEFINED (addon not loaded).
 *  - /previews/field-tiptap-tables: window.WireTiptapAddons DEFINED (addon loaded
 *    + shares the core chunk), and clicking the Table toolbar button inserts a
 *    <table> — proving the addon extension registered before new Editor().
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # background
 *   node workbench/scripts/verify-tiptap-split.mjs
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-tiptap-split-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-tiptap-split-${Date.now()}`);
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
  await page('Emulation.setDeviceMetricsOverride', { width: 1200, height: 1000, deviceScaleFactor: 1, mobile: false });

  // ─────────────── CORE editor (no addon) ───────────────
  await page('Page.navigate', { url: `${base}/field-tiptap` });
  await sleep(4000);
  const coreBooted = await eval_(`!!document.querySelector('.ProseMirror[contenteditable="true"]')`);
  check('core editor boots (.ProseMirror contenteditable) via ESM module script', coreBooted);
  const noAddonOnCore = await eval_(`typeof window.WireTiptapAddons === 'undefined'`);
  check('addon chunk NOT loaded on the core editor', noAddonOnCore);
  await shot('01-core-editor');

  // ─────────────── editor WITH tables (addon) ───────────────
  await page('Page.navigate', { url: `${base}/field-tiptap-tables` });
  await sleep(4000);
  const tablesBooted = await eval_(`!!document.querySelector('.ProseMirror[contenteditable="true"]')`);
  check('tables editor boots', tablesBooted);
  const addonLoaded = await eval_(`typeof window.WireTiptapAddons === 'object' && !!window.WireTiptapAddons.Table`);
  check('addon chunk loaded + registry has Table', addonLoaded);
  await shot('02-tables-editor');

  // Click the Table toolbar button → a <table> node must appear in the doc.
  const tableClicked = await eval_(`(() => {
    const btn = [...document.querySelectorAll('button[title]')].find(b => b.getAttribute('title') === 'Table');
    if (!btn) return 'no-button';
    btn.click();
    return 'clicked';
  })()`);
  check('Table toolbar button present + clicked', tableClicked === 'clicked', tableClicked);
  await sleep(1200);
  const tableInserted = await eval_(`!!document.querySelector('.ProseMirror table')`);
  check('insertTable() renders a <table> (addon extension is active)', tableInserted);
  await shot('03-table-inserted');

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
