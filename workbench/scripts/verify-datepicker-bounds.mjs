import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * minDate()/maxDate() on the custom picker. The preview is bound to
 * 2030-03-10 08:30 … 2030-03-20 17:00, so this covers all four halves of the
 * constraint: the days outside the range, the clock on each boundary day, the
 * month arrows that would walk out of the range, and the month an unset picker
 * opens on. See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9351);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-datepicker-bounds-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-datepicker-bounds-${Date.now()}`);
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
    const day = (n) => document.querySelector('[data-testid="form-datetime-data.slot_at-day-' + n + '"]');
    const enabled = (n) => { const b = day(n); return b ? !b.disabled : null; };
    return JSON.stringify({
      value: d.value,
      month: d.currentMonth, year: d.currentYear,
      hours: d.hours, minutes: d.minutes,
      canGoPrev: d.canGoPrev, canGoNext: d.canGoNext,
      prevDisabled: document.querySelector('[data-testid="form-datetime-data.slot_at-prev-month"]')?.disabled,
      nextDisabled: document.querySelector('[data-testid="form-datetime-data.slot_at-next-month"]')?.disabled,
      day09: enabled(9), day10: enabled(10), day15: enabled(15), day20: enabled(20), day21: enabled(21),
    });
  })()`));
  const click = async (testid) => {
    await eval_(`document.querySelector('[data-testid="${testid}"]')?.click()`);
    await sleep(250);
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 2, mobile: false });
  await page('Page.navigate', { url: `${base}/field-date-time-picker-bounds` });
  await sleep(2800);
  await eval_(`document.querySelector('[data-testid="form-datetime-data.slot_at-trigger"]').click()`);
  await sleep(700);

  const opened = await state();
  console.log('opened:', JSON.stringify(opened));
  await shot('01-bounds-opened');

  // Today is nowhere near 2030, so an unclamped picker would open on a month
  // where every single day is greyed out.
  check('an unset picker opens on the first month the bounds allow',
    opened.year === 2030 && opened.month === 2, `${opened.year}-${opened.month + 1}`);
  check('days before minDate are not selectable', opened.day09 === false);
  check('the boundary days themselves are selectable', opened.day10 === true && opened.day20 === true,
    `10=${opened.day10} 20=${opened.day20}`);
  check('days inside the range are selectable', opened.day15 === true);
  check('days after maxDate are not selectable', opened.day21 === false);
  check('the arrows stop at the bounds', opened.prevDisabled === true && opened.nextDisabled === true,
    `prev=${opened.prevDisabled} next=${opened.nextDisabled}`);

  // The clock: 00:00 on the lower boundary day is before 08:30.
  await click('form-datetime-data.slot_at-day-10');
  const atMin = await state();
  console.log('min day:', JSON.stringify(atMin));
  check('picking the lower boundary day pulls the clock up to its time',
    atMin.value === '2030-03-10 08:30', atMin.value);

  await click('form-datetime-data.slot_at-hours-down');
  const belowMin = await state();
  check('the clock cannot be wound back past the lower bound',
    belowMin.value === '2030-03-10 08:30', belowMin.value);

  await click('form-datetime-data.slot_at-hours-up');
  const aboveMin = await state();
  check('the clock still moves freely inside the bounds',
    aboveMin.value === '2030-03-10 09:30', aboveMin.value);

  // The upper boundary day: 09:30 is fine, 18:30 is not.
  await click('form-datetime-data.slot_at-day-20');
  await click('form-datetime-data.slot_at-hours-up');
  await click('form-datetime-data.slot_at-hours-up');
  await click('form-datetime-data.slot_at-hours-up');
  await click('form-datetime-data.slot_at-hours-up');
  await click('form-datetime-data.slot_at-hours-up');
  await click('form-datetime-data.slot_at-hours-up');
  await click('form-datetime-data.slot_at-hours-up');
  await click('form-datetime-data.slot_at-hours-up');
  const atMax = await state();
  console.log('max day:', JSON.stringify(atMax));
  await shot('02-bounds-clamped-clock');
  check('the clock cannot be wound past the upper bound on its day',
    atMax.value === '2030-03-20 17:00', atMax.value);

  // A day in the middle carries no clock bound at all.
  await click('form-datetime-data.slot_at-day-15');
  await click('form-datetime-data.slot_at-hours-up');
  const midday = await state();
  check('a day between the bounds leaves the clock alone',
    midday.value === '2030-03-15 18:00', midday.value);

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
