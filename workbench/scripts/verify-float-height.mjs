import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Verifies the viewport-aware height cap added to the shared `$float` primitive
 * (packages/core/resources/js/dropdown.js): a tall floating panel (the date
 * picker calendar) must stay inside a short viewport and scroll internally,
 * while a normal viewport is unchanged. See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9336);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-float-height-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-float-height-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${devtoolsPort}`,
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

  // Opens the date picker and reports the teleported panel geometry vs viewport.
  const openAndProbe = async () => {
    await eval_(`(() => {
      const input = document.querySelector('[x-ref="trigger"] input') || document.querySelector('input');
      input && input.click();
      return true;
    })()`);
    await sleep(1000);
    return JSON.parse(await eval_(`(() => {
      const p = document.querySelector('[x-ref="panel"]');
      if (!p) return JSON.stringify({ open: false });
      const r = p.getBoundingClientRect();
      const cs = getComputedStyle(p);
      return JSON.stringify({
        open: true,
        rect: { top: Math.round(r.top), bottom: Math.round(r.bottom), height: Math.round(r.height) },
        maxHeight: cs.maxHeight,
        overflowY: cs.overflowY,
        scrollable: p.scrollHeight > p.clientHeight + 1,
        vh: window.innerHeight,
        withinViewport: r.top >= -1 && r.bottom <= window.innerHeight + 1,
      });
    })()`));
  };

  // ── Short landscape viewport: panel must cap + stay on-screen ──────────
  await page('Emulation.setDeviceMetricsOverride', { width: 760, height: 360, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url: `${base}/field-date-time-picker` });
  await sleep(2600);
  const short = await openAndProbe();
  console.log('short(760x360):', JSON.stringify(short));
  await shot('01-datepicker-landscape-760x360');
  check('calendar opens on short viewport', short.open === true);
  check('calendar stays within the short viewport', short.open && short.withinViewport, `bottom=${short.rect?.bottom} vh=${short.vh}`);
  check('calendar scrolls internally when capped', short.open && short.overflowY === 'auto' && short.scrollable, `overflowY=${short.overflowY} scrollable=${short.scrollable}`);

  // ── Normal tall viewport: no regression (fits, not clipped) ────────────
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url: `${base}/field-date-time-picker` });
  await sleep(2600);
  const tall = await openAndProbe();
  console.log('tall(390x844):', JSON.stringify(tall));
  await shot('02-datepicker-portrait-390x844');
  check('calendar opens on tall viewport', tall.open === true);
  check('calendar within viewport (portrait)', tall.open && tall.withinViewport, `bottom=${tall.rect?.bottom} vh=${tall.vh}`);

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
    let nextId = 1;
    ws.addEventListener('open', () => resolve({
      send(method, params = {}, sessionId) {
        const id = nextId++;
        return new Promise((res, rej) => {
          pending.set(id, { res, rej });
          ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
        });
      },
      close() { ws.close(); },
    }));
    ws.addEventListener('error', (err) => reject(err));
    ws.addEventListener('message', (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && pending.has(msg.id)) {
        const { res, rej } = pending.get(msg.id);
        pending.delete(msg.id);
        msg.error ? rej(new Error(msg.error.message)) : res(msg.result);
      }
    });
  });
}
