import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * ->touchOnMobile() on a select: the full-height touch list on a phone. Pest
 * sees the markup; this drives it — the touch sheet and not the floating panel
 * at 390px (and the reverse at 1400px), thumb-sized rows and 16px text, the
 * search filtering in place, the given option order, a single pick closing the
 * sheet, multiple picks toggling until "Done", and a disabled option refusing.
 * Preview: /previews/field-touch-select (FieldPreview).
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9373);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-touch-select-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-touch-select-${Date.now()}`);
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
  // Poll rather than sleep: a fixed wait reads as a failure on a throttled run.
  const waitFor = async (expression, timeout = 8000) => {
    const until = Date.now() + timeout;
    while (Date.now() < until) {
      if (await eval_(expression).catch(() => false)) return true;
      await sleep(100);
    }
    return false;
  };
  const shot = async (name) => {
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };
  const load = async (w, h) => {
    await page('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 2, mobile: w < 700 });
    await page('Page.navigate', { url: `${base}/field-touch-select` });
    return waitFor(`!! (window.Alpine && window.Livewire && document.querySelectorAll('[data-testid="select-trigger"]').length === 2)`);
  };
  // The combobox of the n-th select on the page, and its own touch sheet.
  const root = (n) => `document.querySelectorAll('[data-testid="select-trigger"]')[${n}].closest('[x-data]')`;
  const data = (n) => `Alpine.$data(${root(n)})`;
  const sheetOf = (n) => `[...document.querySelectorAll('[data-testid="select-touch-sheet"]')][${n}]`;
  const visible = (expr) => eval_(`(() => { const el = ${expr}; return !! el && el.getClientRects().length > 0; })()`);
  const stateOf = (name) => eval_(`(() => {
    let host = document.querySelector('[data-testid="select-trigger"]');
    while (host && ! host.hasAttribute('wire:id')) host = host.parentElement;
    return JSON.stringify(window.Livewire.find(host.getAttribute('wire:id')).$get('data.${name}'));
  })()`);
  const tap = (expr) => eval_(`(${expr}).click()`);

  await page('Page.enable');
  await page('Runtime.enable');

  // ─── Phone ───────────────────────────────────────────────────────────
  check('phone: the preview renders', await load(390, 844));
  await tap(`document.querySelectorAll('[data-testid="select-trigger"]')[0]`);
  check('phone: the trigger opens the touch sheet', await waitFor(`(() => { const el = ${sheetOf(0)}; return !! el && el.getClientRects().length > 0; })()`));
  await sleep(300);
  await shot('01-touch-select-390');
  check('phone: the floating panel stays hidden', ! await visible(`${root(0)}.querySelector('[x-ref="panel"]') ?? document.querySelectorAll('[x-ref="panel"]')[0]`));

  const metrics = await eval_(`(() => {
    const sheet = ${sheetOf(0)};
    const row = sheet.querySelector('[data-testid^="select-touch-option-"]');
    const search = sheet.querySelector('[data-testid="select-touch-search"]');
    const r = sheet.getBoundingClientRect();
    return { row: row.getBoundingClientRect().height, font: getComputedStyle(search).fontSize, top: Math.round(r.top), bottom: Math.round(r.bottom), vh: innerHeight };
  })()`);
  check('phone: rows are thumb-sized', metrics.row >= 44, `${metrics.row}px`);
  check('phone: the search is 16px, so iOS does not zoom', metrics.font === '16px', metrics.font);
  check('phone: the sheet fills the screen below a strip', metrics.top <= 60 && metrics.bottom === metrics.vh, JSON.stringify(metrics));

  const order = await eval_(`[...${sheetOf(0)}.querySelectorAll('[data-testid^="select-touch-option-"]')].slice(0, 3).map(b => b.textContent.trim())`);
  check('phone: options keep the given order', order.join('|') === 'Praha|Brno|Ostrava', order.join('|'));

  await eval_(`(() => { const i = ${sheetOf(0)}.querySelector('[data-testid="select-touch-search"]'); i.value = 'hrad'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  check('phone: the search filters the list', await waitFor(`[...${sheetOf(0)}.querySelectorAll('[data-testid^="select-touch-option-"]')].map(b => b.textContent.trim()).join('|') === 'Hradec Králové'`));
  await tap(`${sheetOf(0)}.querySelector('[data-testid^="select-touch-option-"]')`);
  check('phone: a single pick closes the sheet', await waitFor(`! ${sheetOf(0)}.getClientRects().length`));
  check('phone: a single pick writes the state', await waitFor(`${data(0)}.selected == 117`), await stateOf('touch_city'));
  check('phone: the trigger shows the pick', await eval_(`document.querySelectorAll('[data-testid="select-trigger"]')[0].textContent.includes('Hradec Králové')`));

  // Multiple: ticks toggle, the sheet stays, Done closes.
  await tap(`document.querySelectorAll('[data-testid="select-trigger"]')[1]`);
  await waitFor(`(() => { const el = ${sheetOf(1)}; return !! el && el.getClientRects().length > 0; })()`);
  await sleep(300);
  await tap(`${sheetOf(1)}.querySelector('[data-testid="select-touch-option-vip"]')`);
  await tap(`${sheetOf(1)}.querySelector('[data-testid="select-touch-option-gift"]')`);
  await tap(`${sheetOf(1)}.querySelector('[data-testid="select-touch-option-fragile"]')`);
  await sleep(200);
  await shot('02-touch-multi-390');
  check('phone: multiple picks leave the sheet open', await visible(sheetOf(1)));
  check('phone: multiple picks toggle, a disabled one refuses', await eval_(`JSON.stringify(${data(1)}.selected)`) === '["vip","gift"]', await eval_(`JSON.stringify(${data(1)}.selected)`));
  check('phone: the sheet counts the picks', await eval_(`${sheetOf(1)}.textContent.includes('2')`));
  await tap(`${sheetOf(1)}.querySelector('[data-testid="select-touch-option-vip"]')`);
  check('phone: a second tap unticks', await eval_(`JSON.stringify(${data(1)}.selected)`) === '["gift"]');
  const short = await eval_(`(() => { const r = ${sheetOf(1)}.getBoundingClientRect(); return { top: Math.round(r.top), vh: innerHeight }; })()`);
  check('phone: a short list sits at the height of its rows', short.top > short.vh / 3, JSON.stringify(short));
  await tap(`${sheetOf(1)}.querySelector('[data-testid="select-touch-done"]')`);
  check('phone: Done closes the sheet', await waitFor(`! ${sheetOf(1)}.getClientRects().length`));

  // ─── Desktop ─────────────────────────────────────────────────────────
  check('desktop: the preview renders', await load(1400, 900));
  await tap(`document.querySelectorAll('[data-testid="select-trigger"]')[0]`);
  await sleep(400);
  check('desktop: the floating panel opens', await visible(`document.querySelectorAll('[x-ref="panel"]')[0]`));
  check('desktop: the touch sheet stays hidden', ! await visible(sheetOf(0)));

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
