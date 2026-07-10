import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Phase 3 rollout: the table filter panel opens as a bottom sheet on a phone
 * (fixed, bottom-pinned, full-width, backdrop) and as a trigger-anchored
 * floating panel on desktop. See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9339);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-filter-sheet-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-filter-sheet-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ ok });
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
  const openFilterAndProbe = async (w, h) => {
    await page('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 2, mobile: w < 700 });
    await page('Page.navigate', { url: `${base}/table-overview` });
    await sleep(2600);
    await eval_(`(() => {
      const btn = [...document.querySelectorAll('button')].find(b => /Filter/i.test(b.innerText) && b.getAttribute('x-ref') === 'trigger');
      (btn || [...document.querySelectorAll('button')].find(b => /Filter/i.test(b.innerText)))?.click();
    })()`);
    await sleep(700);
    return JSON.parse(await eval_(`(() => {
      // The open filter panel: a teleported x-ref="panel" that is display-visible
      // and holds the reset button.
      const panel = [...document.querySelectorAll('[x-ref="panel"]')].find(p =>
        getComputedStyle(p).display !== 'none' && [...p.querySelectorAll('button')].some(b => /reset/i.test(b.getAttribute('wire:click') || '')));
      if (!panel) return JSON.stringify({ open: false });
      const cs = getComputedStyle(panel);
      const r = panel.getBoundingClientRect();
      const backdrops = [...document.querySelectorAll('div')].filter(d => {
        const c = getComputedStyle(d);
        return c.position === 'fixed' && c.inset === '0px' && c.display !== 'none' && parseFloat(c.opacity) > 0;
      });
      return JSON.stringify({
        open: true,
        position: cs.position,
        rect: { top: Math.round(r.top), bottom: Math.round(r.bottom), left: Math.round(r.left), right: Math.round(r.right), w: Math.round(r.width) },
        vw: window.innerWidth, vh: window.innerHeight,
        backdropVisible: backdrops.length > 0,
      });
    })()`));
  };

  await page('Page.enable');
  await page('Runtime.enable');

  const m = await openFilterAndProbe(390, 844);
  console.log('mobile:', JSON.stringify(m));
  await shot('01-filter-sheet-390');
  check('filter panel opens on mobile', m.open === true);
  check('filter panel is a fixed bottom sheet', m.open && m.position === 'fixed' && Math.abs(m.rect.bottom - m.vh) <= 2, `pos=${m.position} bottom=${m.rect?.bottom} vh=${m.vh}`);
  check('filter sheet is full-width', m.open && m.rect.left <= 1 && Math.abs(m.rect.right - m.vw) <= 2, `left=${m.rect?.left} right=${m.rect?.right} vw=${m.vw}`);
  check('mobile shows a dimming backdrop', m.open && m.backdropVisible === true);

  const d = await openFilterAndProbe(1400, 900);
  console.log('desktop:', JSON.stringify(d));
  await shot('02-filter-floating-1400');
  check('filter panel opens on desktop', d.open === true);
  check('filter panel is an absolute floating panel', d.open && d.position === 'absolute', `pos=${d.position}`);
  check('desktop filter panel is not full-width', d.open && d.rect.w < 400, `w=${d.rect?.w}`);
  check('desktop shows no backdrop', d.open && d.backdropVisible === false);

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
