import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * CDP check for B3: the searchable-select combobox seeds `options` from
 * `initialOptions` in init() (the options JSON is embedded once, not twice), so
 * it must still open, list its options, and apply a selection.
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # background
 *   node workbench/scripts/verify-select-dedup.mjs
 */
const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/field-select';
const chromeBin = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9338);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-select-dedup-shots');
await mkdir(shotDir, { recursive: true });
const userDataDir = join(tmpdir(), `wire-select-dedup-${Date.now()}`);
const chrome = spawn(chromeBin, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${devtoolsPort}`, `--user-data-dir=${userDataDir}`, 'about:blank'], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => { results.push({ ok }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`); };

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (m, p) => cdp.send(m, p, sessionId);
  const errors = [];
  const eval_ = async (expression) => {
    const { result, exceptionDetails } = await page('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'JS error');
    return result?.value;
  };
  const shot = async (n) => { const { data } = await page('Page.captureScreenshot', { format: 'png' }); await writeFile(join(shotDir, `${n}.png`), Buffer.from(data, 'base64')); };
  await page('Page.enable'); await page('Runtime.enable'); await page('Console.enable');
  cdp.on('Console.messageAdded', (p) => { const m = p.params?.message; if (m?.level === 'error') errors.push(m.text); });
  await page('Emulation.setDeviceMetricsOverride', { width: 1200, height: 1000, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3500);

  const booted = await eval_(`!!document.querySelector('[x-data]') && typeof Alpine !== 'undefined'`);
  check('field-select preview boots', booted);

  // Open the first searchable-select combobox.
  const opened = await eval_(`(() => {
    const t = document.querySelector('[aria-haspopup="listbox"]') || document.querySelector('[x-ref="trigger"]');
    if (!t) return 'no-trigger'; t.click(); return 'clicked';
  })()`);
  check('combobox trigger clicked', opened === 'clicked', opened);
  await sleep(700);

  const optionCount = await eval_(`document.querySelectorAll('[role="option"]').length`);
  check('options are listed (seeded from initialOptions, not the removed 2nd embed)', optionCount > 0, `count=${optionCount}`);
  await shot('01-open');

  // Click the second option and confirm the trigger reflects a selection.
  const picked = await eval_(`(() => {
    const opts = [...document.querySelectorAll('[role="option"]')];
    if (opts.length < 1) return 'no-options';
    const target = opts[Math.min(1, opts.length - 1)];
    const label = target.innerText.trim();
    (target.querySelector('button') || target).click();
    return label;
  })()`);
  await sleep(1200);
  const triggerText = await eval_(`(document.querySelector('[x-ref="trigger"]')?.innerText || '').trim()`);
  check('selecting an option updates the trigger', !!picked && picked !== 'no-options' && triggerText.includes(picked), `picked="${picked}" trigger="${triggerText}"`);
  await shot('02-selected');

  check('no console errors', errors.length === 0, errors.slice(0, 3).join(' | '));
  console.log(`\nScreenshots: ${shotDir}`);
  const failed = results.filter((r) => !r.ok);
  chrome.kill();
  process.exit(failed.length ? 1 : 0);
} catch (err) { console.error('DRIVER ERROR:', err); chrome.kill(); process.exit(2); }

async function waitForDevtools(port) {
  for (let i = 0; i < 50; i++) { try { const r = await fetch(`http://127.0.0.1:${port}/json/version`); const j = await r.json(); if (j.webSocketDebuggerUrl) return j.webSocketDebuggerUrl; } catch {} await sleep(200); }
  throw new Error('DevTools endpoint never came up');
}
async function connect(wsUrl) {
  const { WebSocket } = await import('ws').catch(() => ({ WebSocket: globalThis.WebSocket }));
  const ws = new WebSocket(wsUrl, { perMessageDeflate: false });
  const pending = new Map(); const listeners = []; let id = 0;
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
  ws.onmessage = (ev) => { const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) { const { resolve, reject } = pending.get(msg.id); pending.delete(msg.id); msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result); }
    else if (msg.method) listeners.forEach((fn) => fn(msg)); };
  return { send(method, params = {}, sessionId) { return new Promise((resolve, reject) => { const mid = ++id; pending.set(mid, { resolve, reject }); ws.send(JSON.stringify({ id: mid, method, params, sessionId })); }); }, on(_e, fn) { listeners.push(fn); } };
}
