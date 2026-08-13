import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying the infolist "order detail" preview
 * (/previews/infolists-order): a realistic Order #1042 screen exercising the
 * Flex layout, Badge/Boolean/List entries, and header/entry/per-row actions
 * that do real work — "Mark fulfilled" flips the status badge and notifies,
 * per-row "View" and "Resend receipt" raise toasts.
 *
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-infolist-order.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/infolists-order`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9336);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-infolist-order-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-infolist-order-${Date.now()}`);
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
  const setViewport = (w, h) => page('Emulation.setDeviceMetricsOverride',
    { width: w, height: h, deviceScaleFactor: 1, mobile: false });

  await page('Page.enable');
  await page('Runtime.enable');
  await setViewport(1400, 1200);
  await page('Page.navigate', { url });
  await sleep(3000);

  await eval_(`
    window.$q = (s) => document.querySelector(s);
    window.$qa = (s) => [...document.querySelectorAll(s)];
    window.focusText = () => ($q('[data-preview-focus]')?.innerText || '');
    window.bodyText = () => document.body.innerText;
    window.btn = (t) => $qa('button').find(b => b.innerText.trim() === t);
    window.flexEl = () => $qa('div').find(d => (d.className||'').includes('md:flex-row'));
    true;
  `);

  // ── 0. Renders with Alpine ────────────────────────────────────────────
  const booted = await eval_(`typeof Alpine !== 'undefined' && !!$q('[data-preview-root]')`);
  check('order detail renders with Alpine booted', booted);

  // ── 1. Realistic content + initial state ──────────────────────────────
  const content = JSON.parse(await eval_(`JSON.stringify({
    order: focusText().includes('Order #1042'),
    processing: focusText().includes('Processing') && !focusText().includes('Fulfilled'),
    item: focusText().includes('Wire Forms'),
    segments: $qa('.rounded-full').length >= 3,
    flex: !!flexEl(),
  })`));
  check('shows Order #1042 with items, segment chips and a Flex', content.order && content.item && content.segments && content.flex, JSON.stringify(content));
  check('status starts as "Processing"', content.processing);
  await shot('01-order-detail');

  // ── 2. Flex responsive ───────────────────────────────────────────────
  const deskDir = await eval_(`getComputedStyle(flexEl()).flexDirection`);
  check('Flex is a row on desktop', deskDir === 'row', deskDir);
  await setViewport(480, 1400);
  await sleep(400);
  const mobDir = await eval_(`getComputedStyle(flexEl()).flexDirection`);
  check('Flex stacks to a column on mobile', mobDir === 'column', mobDir);
  await shot('05-mobile');
  await setViewport(1400, 1200);
  await sleep(400);

  // ── 3. Header action: Mark fulfilled → badge flips + toast + button hides
  await eval_(`btn('Mark fulfilled').click()`);
  await sleep(1900);
  const afterFulfill = JSON.parse(await eval_(`JSON.stringify({
    fulfilled: focusText().includes('Fulfilled'),
    btnGone: !btn('Mark fulfilled'),
    toast: bodyText().toLowerCase().includes('marked as fulfilled'),
  })`));
  check('Mark fulfilled flips the status badge to "Fulfilled"', afterFulfill.fulfilled, JSON.stringify(afterFulfill));
  check('Mark fulfilled hides itself (visible=false) and raises a toast', afterFulfill.btnGone && afterFulfill.toast);
  await shot('02-fulfilled');

  // ── 4. Per-row action: View → toast for that line item ────────────────
  await eval_(`$qa('button').filter(b => b.innerText.trim() === 'View')[0].click()`);
  await sleep(1900);
  const rowToast = await eval_(`bodyText().includes('Opening') && bodyText().includes('Wire Forms')`);
  check('per-row "View" raises a toast for that row', rowToast);
  await shot('03-row-action');

  // ── 5. Entry action: Resend receipt → toast with the email ────────────
  await eval_(`btn('Resend receipt').click()`);
  await sleep(1900);
  const receiptToast = await eval_(`bodyText().toLowerCase().includes('receipt') && bodyText().includes('ada@analytical.co')`);
  check('entry "Resend receipt" raises a toast with the email ($state)', receiptToast);
  await shot('04-entry-action');

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
