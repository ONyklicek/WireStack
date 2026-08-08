import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * TimePicker's slot list (ADR 0008, amendment) — the Flux UI pattern: pick a time
 * from a list at a fixed interval, rather than winding the hour/minute steppers
 * DateTimePicker::asTime() still renders. What needs proving in a browser is the
 * list itself: that it is built at the field's interval, that the bounds disable
 * the slots outside them, that a click commits and closes, and that opening does
 * not dump the user at 00:00. The preview is bound to 08:00 … 17:00 at 15 minutes.
 * See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9491);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-time-picker-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-time-picker-${Date.now()}`);
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
  const state = async () => JSON.parse(await eval_(`(() => {
    const root = document.querySelector('[x-ref="trigger"]').closest('[x-data]');
    const d = Alpine.$data(root);
    const slots = [...document.querySelectorAll('[data-testid^="form-time-data.opens_at-slot-"]')];
    const list = document.querySelector('[data-testid="form-time-data.opens_at-list"]');
    const labels = slots.map((s) => s.textContent.trim());
    return JSON.stringify({
      value: d.value,
      interval: d.interval, minTime: d.minTime, maxTime: d.maxTime,
      open: d.open,
      display: document.querySelector('[data-testid="form-time-data.opens_at-trigger"]')?.value,
      slotCount: slots.length,
      firstLabels: labels.slice(0, 3),
      enabled: slots.filter((s) => ! s.disabled).map((s) => s.textContent.trim()),
      activeLabel: slots.filter((s) => s.dataset.active === 'true').map((s) => s.textContent.trim()),
      scrollTop: list ? Math.round(list.scrollTop) : null,
      scrollable: list ? list.scrollHeight > list.clientHeight : null,
      // The stepper picker's chrome must not be here at all.
      steppers: document.querySelectorAll('[data-testid*="-hours-up"], [data-testid*="-minutes-up"]').length,
    });
  })()`));
  const click = async (testid) => {
    await eval_(`document.querySelector('[data-testid="${testid}"]')?.click()`);
    await sleep(250);
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 2, mobile: false });
  await page('Page.navigate', { url: `${base}/field-time-picker` });
  await sleep(2800);
  await eval_(`document.querySelector('[data-testid="form-time-data.opens_at-trigger"]').click()`);
  await sleep(700);

  const opened = await state();
  console.log('opened:', JSON.stringify({ ...opened, enabled: opened.enabled.length }));
  await shot('01-time-picker-opened');

  // A list, not a pair of steppers.
  check('the picker offers a list of slots, not steppers',
    opened.slotCount > 0 && opened.steppers === 0,
    `slots=${opened.slotCount} steppers=${opened.steppers}`);

  // 24h at 15 minutes = 96 slots, starting at midnight.
  check('the whole day is offered at the field interval',
    opened.interval === 15 && opened.slotCount === 96, `interval=${opened.interval} slots=${opened.slotCount}`);
  check('slots are labelled as times from midnight',
    JSON.stringify(opened.firstLabels) === JSON.stringify(['00:00', '00:15', '00:30']),
    opened.firstLabels.join(','));

  // The bounds disable everything outside 08:00…17:00 — 37 slots inclusive.
  check('bounds reach the list as times, not days',
    opened.minTime === '08:00:00' && opened.maxTime === '17:00:00',
    `${opened.minTime}…${opened.maxTime}`);
  check('only the slots inside the bounds are selectable',
    opened.enabled.length === 37 && opened.enabled[0] === '08:00'
      && opened.enabled[opened.enabled.length - 1] === '17:00',
    `${opened.enabled.length} enabled, ${opened.enabled[0]}…${opened.enabled[opened.enabled.length - 1]}`);

  // Opening at 00:00 would put every selectable slot below the fold.
  check('the list scrolls, and opens on the first slot the bounds allow',
    opened.scrollable === true && opened.scrollTop > 0,
    `scrollable=${opened.scrollable} scrollTop=${opened.scrollTop}`);

  // A disabled slot must not commit anything.
  await click('form-time-data.opens_at-slot-07:00');
  const refused = await state();
  check('a slot outside the bounds cannot be picked',
    !refused.value && refused.open === true, `value=${refused.value} open=${refused.open}`);

  // A slot inside them commits and closes, the way a list does.
  await click('form-time-data.opens_at-slot-09:30');
  const picked = await state();
  console.log('picked:', JSON.stringify({ value: picked.value, display: picked.display, open: picked.open }));
  await shot('02-time-picker-picked');
  check('picking a slot commits it', picked.value === '09:30', String(picked.value));
  check('the trigger shows the picked time', picked.display === '09:30', String(picked.display));
  check('picking closes the panel', picked.open === false, String(picked.open));

  // Reopening marks the current value and lands on it.
  await eval_(`document.querySelector('[data-testid="form-time-data.opens_at-trigger"]').click()`);
  await sleep(700);
  const reopened = await state();
  check('the current value is marked as active on reopen',
    JSON.stringify(reopened.activeLabel) === JSON.stringify(['09:30']), reopened.activeLabel.join(','));

  await click('form-time-data.opens_at-clear');
  const cleared = await state();
  check('clear empties the field', !cleared.value, String(cleared.value));

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
