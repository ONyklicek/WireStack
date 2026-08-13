import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * TimePicker's slot panel on a phone: a bottom sheet (fixed, bottom-pinned,
 * full-width, backdrop, grabber) rather than the trigger-anchored floating panel
 * it is from sm up. The sheet chrome is inherited from DateTimePicker, but the
 * thing inside it is not — the list carries its own desktop width (w-36) and
 * height cap, both of which have to lift on a phone or the sheet is a full-width
 * panel with a 144px column of times stranded in it. That is what this checks,
 * on top of the sheet geometry itself. See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9492);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-time-picker-sheet-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-time-picker-sheet-${Date.now()}`);
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
  const shot = async (name) => {
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };
  const probe = async () => JSON.parse(await eval_(`(() => {
    const panel = document.querySelector('[x-ref="panel"]');
    if (! panel || getComputedStyle(panel).display === 'none') return JSON.stringify({ open: false });
    const cs = getComputedStyle(panel);
    const r = panel.getBoundingClientRect();
    const list = document.querySelector('[data-testid="form-time-data.opens_at-list"]');
    const lr = list?.getBoundingClientRect();
    const grabber = panel.querySelector('[x-sheet-dismiss]');
    const backdrops = [...document.querySelectorAll('div')].filter((d) => {
      const c = getComputedStyle(d);
      return c.position === 'fixed' && c.inset === '0px' && c.display !== 'none' && parseFloat(c.opacity) > 0;
    });
    return JSON.stringify({
      open: true,
      position: cs.position,
      rect: { top: Math.round(r.top), bottom: Math.round(r.bottom), left: Math.round(r.left), right: Math.round(r.right), w: Math.round(r.width) },
      vw: window.innerWidth, vh: window.innerHeight,
      backdropVisible: backdrops.length > 0,
      grabberVisible: grabber ? getComputedStyle(grabber).display !== 'none' : false,
      list: lr ? {
        w: Math.round(lr.width), h: Math.round(lr.height), bottom: Math.round(lr.bottom),
        scrollTop: Math.round(list.scrollTop),
        // Whether the LIST is the scroll container. If a cap is removed the panel
        // takes over, and scrollToActive() — which sets list.scrollTop — dies.
        scrolls: list.scrollHeight > list.clientHeight,
      } : null,
      slots: document.querySelectorAll('[data-testid^="form-time-data.opens_at-slot-"]').length,
    });
  })()`));
  const openAt = async (w, h) => {
    await page('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 2, mobile: w < 700 });
    await page('Page.navigate', { url: `${base}/field-time-picker` });
    await sleep(2600);
    await eval_(`document.querySelector('[data-testid="form-time-data.opens_at-trigger"]').click()`);
    await sleep(800);
    return probe();
  };

  await page('Page.enable');
  await page('Runtime.enable');

  // ── Phone ──────────────────────────────────────────────────────────
  const m = await openAt(390, 844);
  console.log('mobile:', JSON.stringify(m));
  await shot('01-time-picker-sheet-390');

  check('the slot panel opens on mobile', m.open === true);
  check('it is a fixed bottom sheet',
    m.open && m.position === 'fixed' && Math.abs(m.rect.bottom - m.vh) <= 2,
    `pos=${m.position} bottom=${m.rect?.bottom} vh=${m.vh}`);
  check('the sheet is full-width',
    m.open && m.rect.left <= 1 && Math.abs(m.rect.right - m.vw) <= 2,
    `left=${m.rect?.left} right=${m.rect?.right} vw=${m.vw}`);
  check('mobile shows a dimming backdrop', m.open && m.backdropVisible === true);
  check('the sheet carries a drag grabber', m.open && m.grabberVisible === true);

  // The list must widen with the sheet — otherwise the times sit in a 144px
  // column inside a 390px panel.
  check('the slot list widens to the sheet',
    m.open && m.list && m.list.w > 300, `list=${m.list?.w}px of ${m.vw}`);
  // …and its desktop 7-row cap grows, without running past the viewport.
  check('the list uses the taller mobile cap and stays inside the viewport',
    m.open && m.list && m.list.h > 224 && m.list.bottom <= m.vh,
    `h=${m.list?.h} bottom=${m.list?.bottom} vh=${m.vh}`);
  // The cap must grow, not vanish: with max-h-none the panel becomes the scroll
  // container and scrollToActive() — which sets list.scrollTop — stops working,
  // so the sheet would open at 00:00 with every selectable slot below the fold.
  check('the list stays the scroll container in the sheet',
    m.open && m.list && m.list.scrolls === true, `scrolls=${m.list?.scrolls}`);
  check('the sheet opens scrolled to the first slot the bounds allow',
    m.open && m.list && m.list.scrollTop > 0, `scrollTop=${m.list?.scrollTop}`);
  check('the whole day is still offered in the sheet', m.slots === 96, String(m.slots));

  // Picking inside the sheet has to behave like picking anywhere else.
  await eval_(`document.querySelector('[data-testid="form-time-data.opens_at-slot-09:30"]')?.click()`);
  await sleep(600);
  const picked = await probe();
  const value = await eval_(`Alpine.$data(document.querySelector('[x-ref="trigger"]').closest('[x-data]')).value`);
  await shot('02-time-picker-sheet-picked');
  check('picking a slot in the sheet commits it and closes', value === '09:30' && picked.open === false,
    `value=${value} open=${picked.open}`);

  // ── Desktop ────────────────────────────────────────────────────────
  const d = await openAt(1400, 900);
  console.log('desktop:', JSON.stringify(d));
  await shot('03-time-picker-floating-1400');

  check('the slot panel opens on desktop', d.open === true);
  check('it is an absolute floating panel', d.open && d.position === 'absolute', `pos=${d.position}`);
  check('desktop anchors it to the trigger rather than the viewport',
    d.open && d.rect.left > 1 && d.rect.w < d.vw - 50, `left=${d.rect?.left} w=${d.rect?.w} vw=${d.vw}`);
  check('desktop shows no backdrop', d.open && d.backdropVisible === false);
  check('desktop hides the grabber', d.open && d.grabberVisible === false);
  check('desktop keeps the narrow list and its 7-row cap',
    d.open && d.list && d.list.w < 200 && d.list.h <= 224, `w=${d.list?.w} h=${d.list?.h}`);

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
