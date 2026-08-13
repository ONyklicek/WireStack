import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Phase 2 mobile checks: multi-column form fields reflow down on a phone, and
 * icon-only action buttons get a larger tap target on mobile (canonical HasSize
 * `p-2.5 sm:p-1.5`). Measures computed styles at 390px vs 1400px + screenshots.
 * See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-phase2-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-phase2-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};
const trackCount = (tpl) => (tpl && tpl !== 'none' ? tpl.trim().split(/\s+/).length : 0);

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
  const load = async (slug, w, h) => {
    await page('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 2, mobile: w < 700 });
    await page('Page.navigate', { url: `${base}/${slug}` });
    await sleep(2600);
  };

  await page('Page.enable');
  await page('Runtime.enable');

  // ── checkbox-list columns(2): 1 track on mobile, 2 on desktop ──────────
  const gridTpl = `getComputedStyle([...document.querySelectorAll('.grid')].find(el => el.className.includes('grid-cols'))).gridTemplateColumns`;
  await load('field-checkbox-list', 390, 844);
  const cbMobile = trackCount(await eval_(gridTpl));
  await shot('01-checkbox-list-390');
  await load('field-checkbox-list', 1400, 900);
  const cbDesktop = trackCount(await eval_(gridTpl));
  check('checkbox-list reflows to 1 column on mobile', cbMobile === 1, `mobile tracks=${cbMobile}`);
  check('checkbox-list keeps 2 columns on desktop', cbDesktop === 2, `desktop tracks=${cbDesktop}`);

  // ── radio cards inline: stacked (1 track) on mobile, multi on desktop ──
  await load('field-radio-color', 390, 844);
  const rMobile = trackCount(await eval_(gridTpl));
  await shot('02-radio-inline-cards-390');
  await load('field-radio-color', 1400, 900);
  const rDesktop = trackCount(await eval_(gridTpl));
  check('inline radio cards stack on mobile', rMobile === 1, `mobile tracks=${rMobile}`);
  check('inline radio cards flow horizontally on desktop', rDesktop >= 2, `desktop tracks=${rDesktop}`);

  // ── icon-only action buttons: larger padding on mobile than desktop ────
  // Target the canonical HasSize icon button by its responsive-padding signature
  // (class contains `sm:p-1.5`), not the hardcoded `p-2` toolbar triggers.
  const iconBtnPad = `(() => {
    const btn = [...document.querySelectorAll('button')].find(b => /(^|\\s|:)sm:p-1\\.5(\\s|$)/.test(b.className) || b.className.includes('sm:p-1.5'));
    return btn ? parseFloat(getComputedStyle(btn).paddingTop) : null;
  })()`;
  await load('table-actions-group', 390, 844);
  const padMobile = await eval_(iconBtnPad);
  await shot('03-table-icon-buttons-390');
  await load('table-actions-group', 1400, 900);
  const padDesktop = await eval_(iconBtnPad);
  check('icon action button has a larger tap target on mobile', padMobile !== null && padDesktop !== null && padMobile > padDesktop, `mobile=${padMobile}px desktop=${padDesktop}px`);

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
