import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * ->nativeOnMobile(): below the mobile breakpoint the browser's own <select> /
 * date / time input is the visible control, from the breakpoint up the custom
 * one is. Both are in the markup and CSS picks — which is exactly what Pest
 * cannot see, so this driver measures what is displayed at 390px and 1400px,
 * that the two halves share one state, and that the combobox keeps
 * integer-keyed options in the order given and refuses a disabled one.
 * Preview: /previews/field-native-on-mobile (FieldPreview).
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9371);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-native-on-mobile-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-native-on-mobile-${Date.now()}`);
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
    await page('Page.navigate', { url: `${base}/field-native-on-mobile` });
    return waitFor(`!! (window.Alpine && window.Livewire && document.querySelector('#urgency, [id$="urgency"]'))`);
  };
  // What is actually displayed: an element hidden by an ancestor has no box.
  const probe = () => eval_(`(() => {
    const shown = (el) => !! el && el.getClientRects().length > 0;
    const nativeSelect = document.querySelector('select[id$="urgency-native"]');
    const trigger = document.querySelector('button[aria-haspopup="listbox"]');
    const nativeDate = document.querySelector('input[type="date"]');
    const dateTrigger = document.querySelector('[data-testid$="due_on-trigger"]');
    const nativeTime = document.querySelector('select[id$="opens_at-native"]');
    const timeTrigger = document.querySelector('[data-testid$="opens_at-trigger"]');
    return {
      nativeSelect: shown(nativeSelect), combobox: shown(trigger),
      nativeDate: shown(nativeDate), dateTrigger: shown(dateTrigger),
      nativeTime: shown(nativeTime), timeTrigger: shown(timeTrigger),
      timeSlots: nativeTime ? [...nativeTime.options].map(o => o.value).filter(v => v !== '') : [],
      nativeOrder: nativeSelect ? [...nativeSelect.options].map(o => o.value) : [],
      nativeDisabled: nativeSelect ? [...nativeSelect.options].filter(o => o.disabled).map(o => o.value) : [],
    };
  })()`);

  await page('Page.enable');
  await page('Runtime.enable');

  // ─── Phone ───────────────────────────────────────────────────────────
  check('phone: the preview renders', await load(390, 844));
  const m = await probe();
  await shot('01-native-390');
  check('phone: the native <select> is the visible control', m.nativeSelect && ! m.combobox, JSON.stringify({ n: m.nativeSelect, c: m.combobox }));
  check('phone: the native date input is the visible control', m.nativeDate && ! m.dateTrigger);
  check('phone: the native slot select is the visible control', m.nativeTime && ! m.timeTrigger);
  // A <select>, not <input type="time">: iOS ignores step in its wheel.
  check('phone: the native time offers only the 30-minute slots', m.timeSlots.length === 48 && m.timeSlots.every(v => /:(00|30)$/.test(v)), `${m.timeSlots.length} slots`);
  check('phone: the native list keeps the given order', JSON.stringify(m.nativeOrder.filter(v => v !== '')) === '["10","2","7"]', m.nativeOrder.join(','));
  check('phone: the disabled option is disabled natively', JSON.stringify(m.nativeDisabled) === '["7"]', m.nativeDisabled.join(','));

  // The Livewire component that owns the field, found from the field itself.
  await eval_(`window.__startsAt = () => {
    let host = document.querySelector('[data-testid$="starts_at-native"]');
    while (host && ! host.hasAttribute('wire:id')) host = host.parentElement;
    return window.Livewire.find(host.getAttribute('wire:id')).$get('data.starts_at');
  }`);
  // A stepped datetime: a native date beside a native <select> of the slots
  // (iOS ignores a time input's step), joined into one state by Alpine.
  const joined = await eval_(`(() => {
    const wrap = document.querySelector('[data-testid$="starts_at-native"]');
    if (! wrap || ! wrap.getClientRects().length) return { shown: false };
    const date = wrap.querySelector('input[type="date"]');
    const time = wrap.querySelector('select');
    return { shown: true, date: !! date, slots: time ? [...time.options].map(o => o.value).filter(v => v !== '').length : 0 };
  })()`);
  check('phone: a stepped datetime is a native date plus a slot select', joined.shown && joined.date && joined.slots === 96, JSON.stringify(joined));
  await eval_(`(() => {
    const wrap = document.querySelector('[data-testid$="starts_at-native"]');
    const date = wrap.querySelector('input[type="date"]');
    date.value = '2026-10-05'; date.dispatchEvent(new Event('input', { bubbles: true })); date.dispatchEvent(new Event('change', { bubbles: true }));
  })()`);
  check('phone: a date alone writes nothing', await eval_(`window.__startsAt() === null`));
  await eval_(`(() => {
    const time = document.querySelector('[data-testid$="starts_at-native"] select');
    time.value = '14:45'; time.dispatchEvent(new Event('change', { bubbles: true }));
  })()`);
  check('phone: date and slot join into the datetime state', await waitFor(`window.__startsAt() === '2026-10-05T14:45'`),
    String(await eval_(`window.__startsAt()`)));

  // Picking on the native half writes the state the combobox reads.
  await eval_(`(() => {
    const s = document.querySelector('select[id$="urgency-native"]');
    s.value = '2';
    s.dispatchEvent(new Event('change', { bubbles: true }));
    s.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  check('phone: the native pick reaches the shared state', await waitFor(`(() => {
    const el = document.querySelector('button[aria-haspopup="listbox"]')?.closest('[x-data]');
    return !! el && String(window.Alpine.$data(el).selected) === '2';
  })()`));

  // ─── Desktop ─────────────────────────────────────────────────────────
  check('desktop: the preview renders', await load(1400, 900));
  const d = await probe();
  check('desktop: the combobox is the visible control', d.combobox && ! d.nativeSelect, JSON.stringify({ n: d.nativeSelect, c: d.combobox }));
  check('desktop: the calendar trigger is the visible control', d.dateTrigger && ! d.nativeDate);
  check('desktop: the slot list trigger is the visible control', d.timeTrigger && ! d.nativeTime);

  await eval_(`document.querySelector('button[aria-haspopup="listbox"]').click()`);
  check('desktop: the combobox opens', await waitFor(`!! document.querySelector('[data-testid="select-option-2"]')?.getClientRects().length`));
  await shot('02-combobox-1400');
  const order = await eval_(`[...document.querySelectorAll('[data-testid^="select-option-"]')].map(b => b.dataset.testid.replace('select-option-', ''))`);
  // A JS object would list these 2, 7, 10 — ascending integer keys.
  check('desktop: integer-keyed options keep the given order', JSON.stringify(order) === '["10","2","7"]', order.join(','));
  check('desktop: the disabled option is disabled', await eval_(`document.querySelector('[data-testid="select-option-7"]').disabled === true`));

  // Arrow keys step over the disabled option: 10 → 2, and a third press stays.
  for (let i = 0; i < 3; i++) {
    await eval_(`document.querySelector('button[aria-haspopup="listbox"]').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))`);
    await sleep(50);
  }
  const active = await eval_(`document.querySelector('button[aria-haspopup="listbox"]').getAttribute('aria-activedescendant')`);
  check('desktop: arrow keys never land on the disabled option', !! active && ! active.endsWith('-option-7'), `active=${active}`);

  await eval_(`document.querySelector('[data-testid="select-option-7"]').click()`);
  await eval_(`document.querySelector('[data-testid="select-option-10"]').click()`);
  check('desktop: a pick shows on the trigger', await waitFor(`document.querySelector('button[aria-haspopup="listbox"]').textContent.includes('Urgent')`));
  // The hidden native twin follows the same state.
  check('desktop: the hidden native twin follows the pick', await waitFor(`document.querySelector('select[id$="urgency-native"]').value === '10'`));

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
