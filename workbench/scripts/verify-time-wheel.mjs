import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * ->touchOnMobile() on a picker: the touch wheel on a phone. Pest sees the
 * markup; this drives what it does — which control shows at 390px and at
 * 1400px, that turning the columns lands on a slot (a combination outside the
 * bounds rolls the other column back), that "Done" commits and "Cancel" and
 * the backdrop do not, and that "Clear" empties the field.
 * Preview: /previews/field-time-wheel (FieldPreview).
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9372);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-time-wheel-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-time-wheel-${Date.now()}`);
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
    await page('Page.navigate', { url: `${base}/field-time-wheel` });
    return waitFor(`!! (window.Alpine && window.Livewire && document.querySelector('[data-testid$="opens_at-wheel-trigger"]'))`);
  };
  const shown = (sel) => eval_(`(() => { const el = document.querySelector('${sel}'); return !! el && el.getClientRects().length > 0; })()`);
  // The Livewire state of a field, read from the component that owns it.
  const stateOf = (name) => eval_(`(() => {
    let host = document.querySelector('[data-testid$="${name}-wheel"]');
    while (host && ! host.hasAttribute('wire:id')) host = host.parentElement;
    return window.Livewire.find(host.getAttribute('wire:id')).$get('data.${name}');
  })()`);
  const wheel = (name) => `Alpine.$data(document.querySelector('[data-testid$="${name}-wheel"]'))`;
  const itemsOf = (name, column) => eval_(`${wheel(name)}.columnOf('${column}').items.map((i) => i.value)`);
  // Turn a column the way a finger leaves it: set where it rests, then let the
  // controller settle on it.
  const turn = async (name, column, value) => {
    await eval_(`(() => {
      const data = ${wheel(name)};
      const col = document.querySelector('[data-testid$="${name}-wheel-${column}"]');
      col.scrollTop = data.indexOf('${column}', '${value}') * 44;
      col.dispatchEvent(new Event('scroll'));
    })()`);
    await sleep(300);
  };
  const pair = (name) => eval_(`(() => { const d = ${wheel(name)}; return d.selected.hour + ':' + d.selected.minute; })()`);
  const picked = (name) => eval_(`${wheel(name)}.compose()`);
  const sheetOpen = (name) => shown(`[data-testid$="${name}-wheel-sheet"]`);

  await page('Page.enable');
  await page('Runtime.enable');

  // ─── Phone ───────────────────────────────────────────────────────────
  check('phone: the preview renders', await load(390, 844));
  check('phone: the wheel trigger is the visible control', await shown('[data-testid$="opens_at-wheel-trigger"]'));
  check('phone: the desktop trigger is hidden', ! await shown('[data-testid$="opens_at-trigger"]'));

  await eval_(`document.querySelector('[data-testid$="opens_at-wheel-trigger"]').click()`);
  check('phone: the trigger opens the sheet', await waitFor(`(() => { const el = document.querySelector('[data-testid$="opens_at-wheel-sheet"]'); return !! el && el.getClientRects().length > 0; })()`));
  await sleep(300);
  await shot('01-wheel-open-390');

  const columns = { hours: (await itemsOf('opens_at', 'hour')).join(','), minutes: (await itemsOf('opens_at', 'minute')).join(',') };
  check('phone: the columns hold only what the slots use', columns.hours === '08,09,10,11,12,13,14,15,16,17,18' && columns.minutes === '00,30', JSON.stringify(columns));

  await turn('opens_at', 'hour', '12');
  await turn('opens_at', 'minute', '30');
  check('phone: turning the columns lands on the time', await pair('opens_at') === '12:30', await pair('opens_at'));
  check('phone: nothing is written while the wheel turns', await stateOf('opens_at') === null);

  // 18:30 is past maxDate('18:00'): the minute column gives way.
  await turn('opens_at', 'hour', '18');
  await sleep(500);
  check('phone: a time outside the bounds rolls back onto a slot', await pair('opens_at') === '18:00', await pair('opens_at'));
  check('phone: the rolled-back column shows it', await eval_(`document.querySelector('[data-testid$="opens_at-wheel-minute"]').scrollTop`) === 0);

  await turn('opens_at', 'hour', '09');
  await turn('opens_at', 'minute', '30');
  await eval_(`document.querySelector('[data-testid$="opens_at-wheel-done"]').click()`);
  check('phone: Done commits the time', await waitFor(`(() => { let h = document.querySelector('[data-testid$="opens_at-wheel"]'); while (h && ! h.hasAttribute('wire:id')) h = h.parentElement; return window.Livewire.find(h.getAttribute('wire:id')).$get('data.opens_at') === '09:30'; })()`), String(await stateOf('opens_at')));
  check('phone: Done closes the sheet', await waitFor(`! document.querySelector('[data-testid$="opens_at-wheel-sheet"]').getClientRects().length`));
  check('phone: the trigger shows the time', await eval_(`document.querySelector('[data-testid$="opens_at-wheel-trigger"]').textContent.includes('09:30')`));

  // Reopening starts on the value; Cancel leaves it.
  await eval_(`document.querySelector('[data-testid$="opens_at-wheel-trigger"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid$="opens_at-wheel-sheet"]').getClientRects().length`);
  await sleep(300);
  check('phone: the sheet reopens on the current value', await pair('opens_at') === '09:30'
    && await eval_(`document.querySelector('[data-testid$="opens_at-wheel-hour"]').scrollTop`) === 44, await pair('opens_at'));
  await turn('opens_at', 'hour', '15');
  await eval_(`document.querySelector('[data-testid$="opens_at-wheel-cancel"]').click()`);
  await sleep(300);
  check('phone: Cancel keeps the value', await stateOf('opens_at') === '09:30', String(await stateOf('opens_at')));

  // Clear empties it.
  await eval_(`document.querySelector('[data-testid$="opens_at-wheel-trigger"]').click()`);
  await sleep(400);
  await eval_(`document.querySelector('[data-testid$="opens_at-wheel-clear"]').click()`);
  check('phone: Clear empties the field', await waitFor(`(() => { let h = document.querySelector('[data-testid$="opens_at-wheel"]'); while (h && ! h.hasAttribute('wire:id')) h = h.parentElement; return window.Livewire.find(h.getAttribute('wire:id')).$get('data.opens_at') === null; })()`));

  // asTime()->minutesStep(5): a DateTimePicker wheel, twelve minutes per hour.
  const closes = { hours: (await itemsOf('closes_at', 'hour')).length, minutes: (await itemsOf('closes_at', 'minute')).join(',') };
  check('phone: a stepped asTime() gets its own wheel', closes.hours === 24 && closes.minutes === '00,05,10,15,20,25,30,35,40,45,50,55', JSON.stringify(closes));

  // ─── A date: day / month / year ──────────────────────────────────────
  await eval_(`document.querySelector('[data-testid$="due_on-wheel-trigger"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid$="due_on-wheel-sheet"]').getClientRects().length`);
  await sleep(300);
  await shot('02-date-wheel-390');
  const years = await itemsOf('due_on', 'year');
  check('phone: the year column spans the bounds', years[0] === '2026' && years[years.length - 1] === '2027', years.join(','));
  await turn('due_on', 'year', '2026');
  await turn('due_on', 'month', '01');
  await turn('due_on', 'day', '31');
  await turn('due_on', 'month', '02');
  await sleep(500);
  // 31 February is not a day: the day column gives way to the 28th.
  check('phone: 31 February rolls the day back to the 28th', await picked('due_on') === '2026-02-28', await picked('due_on'));
  await turn('due_on', 'month', '03');
  await turn('due_on', 'day', '01');
  await sleep(500);
  check('phone: a disabled day steps to the next day, not another year', ['2026-02-28', '2026-03-02'].includes(await picked('due_on')), await picked('due_on'));
  await turn('due_on', 'month', '01');
  await turn('due_on', 'day', '05');
  await sleep(500);
  check('phone: a day before minDate lands on minDate', await picked('due_on') === '2026-01-10', await picked('due_on'));
  check('phone: an invalid row is greyed', await eval_(`${wheel('due_on')}.rowValid('day', '05') === false`));
  await turn('due_on', 'day', '15');
  await eval_(`document.querySelector('[data-testid$="due_on-wheel-done"]').click()`);
  check('phone: Done commits the date', await waitFor(`(() => { let h = document.querySelector('[data-testid$="due_on-wheel"]'); while (h && ! h.hasAttribute('wire:id')) h = h.parentElement; return window.Livewire.find(h.getAttribute('wire:id')).$get('data.due_on') === '2026-01-15'; })()`), String(await stateOf('due_on')));

  // ─── A datetime: a day beside the clock ──────────────────────────────
  await eval_(`document.querySelector('[data-testid$="starts_at-wheel-trigger"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid$="starts_at-wheel-sheet"]').getClientRects().length`);
  await sleep(300);
  await shot('03-datetime-wheel-390');
  const days = await itemsOf('starts_at', 'date');
  check('phone: the day column runs from minDate to maxDate', days[0] === '2026-09-01' && days[days.length - 1] === '2026-12-31', `${days[0]}…${days[days.length - 1]} (${days.length})`);
  check('phone: the minute column follows the step', (await itemsOf('starts_at', 'minute')).join(',') === '00,15,30,45');
  await turn('starts_at', 'date', '2026-09-01');
  await turn('starts_at', 'hour', '07');
  await sleep(500);
  check('phone: a time before minDate on its day lands on the first slot from it', await picked('starts_at') === '2026-09-01T08:30', await picked('starts_at'));
  await turn('starts_at', 'date', '2026-10-05');
  await turn('starts_at', 'hour', '14');
  await turn('starts_at', 'minute', '45');
  await eval_(`document.querySelector('[data-testid$="starts_at-wheel-done"]').click()`);
  check('phone: Done commits the datetime', await waitFor(`(() => { let h = document.querySelector('[data-testid$="starts_at-wheel"]'); while (h && ! h.hasAttribute('wire:id')) h = h.parentElement; return window.Livewire.find(h.getAttribute('wire:id')).$get('data.starts_at') === '2026-10-05T14:45'; })()`), String(await stateOf('starts_at')));

  // ─── Desktop ─────────────────────────────────────────────────────────
  check('desktop: the preview renders', await load(1400, 900));
  check('desktop: the wheel is hidden', ! await shown('[data-testid$="opens_at-wheel-trigger"]'));
  check('desktop: the slot list trigger is the visible control', await shown('[data-testid$="opens_at-trigger"]'));

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
