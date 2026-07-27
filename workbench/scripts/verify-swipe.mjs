import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { rm } from 'node:fs/promises';

/* Confirms x-sheet-dismiss: a downward drag on the grabber closes the sheet. */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9360);
const userDataDir = join(tmpdir(), `wire-swipe-${Date.now()}`);
const chrome = spawn(chromeBin, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--remote-debugging-port=${devtoolsPort}`, `--user-data-dir=${userDataDir}`, 'about:blank'], { stdio: 'ignore' });

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (m, p) => cdp.send(m, p, sessionId);
  const eval_ = async (expression) => {
    const { result, exceptionDetails } = await page('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'JS error');
    return result?.value;
  };
  await page('Page.enable'); await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url: `${base}/table-actions-group` });
  await sleep(2600);
  await eval_(`document.querySelector('button[aria-haspopup="menu"]')?.click()`);
  await sleep(700);

  // The VISIBLE menu: every row renders one, so querySelector returns the first
  // in the DOM — another row's, still hidden — and this read "not opened" even
  // though the sheet was up.
  const opened = await eval_(`[...document.querySelectorAll('[role="menu"]')].some((m) => getComputedStyle(m).display !== 'none')`);

  // Synthesize a downward drag on the grabber: touchstart @y=760, move to 760+120, end.
  const closed = await eval_(`(async () => {
    const menuEl = [...document.querySelectorAll('[role="menu"]')].find(m => getComputedStyle(m).display !== 'none');
    const grab = menuEl?.querySelector('[x-sheet-dismiss]');
    if (!grab) return 'no-grabber';
    const mk = (y) => new TouchEvent('touchstart',{bubbles:true});
    const touch = (type, y) => {
      const t = new Touch({ identifier: 1, target: grab, clientX: 195, clientY: y });
      grab.dispatchEvent(new TouchEvent(type, { bubbles: true, cancelable: true, touches: type==='touchend'?[]:[t], changedTouches: [t] }));
    };
    touch('touchstart', 760);
    touch('touchmove', 820);
    touch('touchmove', 880);
    touch('touchend', 880);
    await new Promise(r => setTimeout(r, 600));
    return getComputedStyle(menuEl).display === 'none';
  })()`);

  console.log('menu opened:', opened);
  console.log('closed after swipe-down:', closed);
  const ok = opened === true && closed === true;
  console.log(ok ? 'PASS  swipe-to-dismiss closes the sheet' : 'FAIL  ' + JSON.stringify({ opened, closed }));
  if (!ok) process.exitCode = 1;
} catch (e) { console.error('DRIVER ERROR:', e.message); process.exitCode = 2; }
finally { cdp?.close(); chrome.kill('SIGTERM'); await rm(userDataDir, { recursive: true, force: true }).catch(() => {}); }

async function waitForDevtools(port) {
  for (let i = 0; i < 60; i++) { try { const r = await fetch(`http://127.0.0.1:${port}/json/version`); const j = await r.json(); if (j.webSocketDebuggerUrl) return j.webSocketDebuggerUrl; } catch {} await sleep(250); }
  throw new Error('DevTools endpoint never came up');
}
function connect(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl); const pending = new Map(); let nextId = 1;
    ws.addEventListener('open', () => resolve({ send(method, params = {}, sessionId) { const id = nextId++; return new Promise((res, rej) => { pending.set(id, { res, rej }); ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) })); }); }, close() { ws.close(); } }));
    ws.addEventListener('error', reject);
    ws.addEventListener('message', (e) => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { const { res, rej } = pending.get(m.id); pending.delete(m.id); m.error ? rej(new Error(m.error.message)) : res(m.result); } });
  });
}
