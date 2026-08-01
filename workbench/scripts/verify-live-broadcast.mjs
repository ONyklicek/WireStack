import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for the push half of `Table::live(broadcast: true)`
 * (/previews/table-editable-live).
 *
 * The socket itself is the app's — Reverb, Pusher, whatever it configured — so
 * what this repo owns, and all this checks, is the bridge: does the table
 * subscribe to the right channel, does an event on it make the table re-read,
 * and does it hold off while somebody is mid-write. `window.Echo` is therefore
 * stubbed before the page loads, which is also the honest shape of the contract:
 * anything Echo-like will do, and a page with no Echo at all must degrade to the
 * poll rather than break.
 *
 * The multi-user claim is the interesting one and is checked end to end: a
 * second session writes, the first is nudged, and its editable cell shows the
 * new value — without waiting for the 2s tick that would eventually do it
 * anyway.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-live-broadcast.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-editable-live';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9359);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-live-broadcast-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-live-broadcast-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars',
  // This is the only driver here that keeps TWO pages open at once, and the one
  // under test stops being the front tab the moment the second session opens.
  // Chrome then clamps a background tab's timers to roughly one second, which is
  // long enough to swallow the 250ms settle window this checks and to make the
  // re-read land past the 2s poll tick — so the broadcast looked broken and the
  // poll looked like what saved it, in a run where both were working. Timers
  // have to be honest for the timing itself to be the assertion.
  '--disable-background-timer-throttling',
  '--disable-backgrounding-occluded-windows',
  '--disable-renderer-backgrounding',
  `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};

// A minimal Echo: records what was subscribed to and hands back a way to fire.
const echoStub = `
  window.__echoLog = { channels: [], listeners: [], left: [] };
  window.Echo = {
    private(name) {
      window.__echoLog.channels.push(name);
      return {
        listen(event, cb) {
          window.__echoLog.listeners.push({ channel: name, event, cb });
          return this;
        },
      };
    },
    leave(name) { window.__echoLog.left.push(name); },
    // Livewire reads this on every request to stamp X-Socket-Id, so a stub
    // without it breaks every round trip on the page — including the one this
    // driver is here to observe.
    socketId() { return 'driver-stub-socket'; },
  };
  window.__fireBroadcast = () => {
    window.__echoLog.listeners.forEach((l) => l.cb({}));
    return window.__echoLog.listeners.length;
  };
`;

const consoleErrors = [];

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);

  const open = async (withEcho) => {
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
    await page('Page.enable');
    await page('Runtime.enable');
    if (withEcho) await page('Page.addScriptToEvaluateOnNewDocument', { source: echoStub });
    await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1100, deviceScaleFactor: 1, mobile: false });
    await page('Page.navigate', { url });
    await sleep(3200);
    await eval_(helpers);
    return { page, eval_ };
  };

  const helpers = `
    window.cell = (col, row = 0) =>
      document.querySelectorAll('[data-testid="table-editable-'+col+'"]')[row];
    window.state = (col, row = 0) => Alpine.$data(cell(col, row));
    window.type = async (col, text, row = 0) => {
      const input = cell(col, row).querySelector('input');
      input.dispatchEvent(new Event('focus', { bubbles: true }));
      await new Promise((r) => setTimeout(r, 120));
      input.value = text;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      await new Promise((r) => setTimeout(r, 250));
      input.dispatchEvent(new Event('blur', { bubbles: true }));
    };
    true;
  `;

  cdp.on((msg) => {
    if (msg.method === 'Runtime.exceptionThrown') {
      consoleErrors.push(msg.params?.exceptionDetails?.exception?.description ?? 'exception');
    }
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params?.type === 'error') {
      consoleErrors.push((msg.params.args ?? []).map((a) => a.value ?? a.description ?? '').join(' '));
    }
  });

  const us = await open(true);
  const shot = async (name) => {
    const { data } = await us.page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  // ── 1. It subscribed, to a channel named after the model ──────────────
  const channels = await us.eval_(`window.__echoLog.channels`);
  check('the live table subscribed to exactly one channel',
    Array.isArray(channels) && channels.length === 1, JSON.stringify(channels));
  check('the channel is named after the model it lists',
    (channels ?? [])[0] === 'wire-table.Workbench-App-Models-User', (channels ?? [])[0]);

  const listeners = await us.eval_(`window.__echoLog.listeners.map(l => l.event)`);
  check('it listens for the broadcast event name the server sends',
    JSON.stringify(listeners) === JSON.stringify(['.wire-table.changed']), JSON.stringify(listeners));
  await shot('01-subscribed');

  // ── 2. A broadcast makes it re-read another session's write ───────────
  const theirs = `broadcast-${Date.now()}@example.test`;
  const them = await open(false);
  await them.eval_(`(async () => await type('email', ${JSON.stringify(theirs)}))()`);
  await sleep(1600);
  check('the second session committed its write',
    (await them.eval_(`state('email').serverValue`)) === theirs);

  // Back to the front before anything is timed: see the throttling note above.
  await us.page('Page.bringToFront');
  await sleep(200);

  const firedAt = Date.now();
  const fired = await us.eval_(`window.__fireBroadcast()`);
  check('the nudge reached a listener', fired === 1, `listeners=${fired}`);

  // The component coalesces a burst before re-reading (settle window), so this
  // allows for that plus the round trip — and gives up well inside the 2s poll
  // tick, which must NOT be what makes this pass. Sampled rather than slept
  // through so the elapsed time is reported: a pass that took ~1.9s would mean
  // the tick beat the nudge to it and the check proved nothing.
  let weSee = null;
  while (Date.now() - firedAt < 1200) {
    weSee = await us.eval_(`state('email').value`);
    if (weSee === theirs) break;
    await sleep(50);
  }
  const took = Date.now() - firedAt;
  check('a broadcast brings the other session\'s write onto our screen',
    weSee === theirs, `after ${took}ms — us=${weSee} expected=${theirs}`);
  check('and it was the broadcast that did it, not the next poll tick',
    weSee === theirs && took < 1200, `${took}ms (poll interval is 2000ms)`);
  await shot('02-after-broadcast');

  // ── 3. It holds off while one of our own cells is mid-write ───────────
  // A refresh here would be answered with the pre-write value, and the cell
  // would rightly ignore it — a wasted round trip in the worst moment.
  await us.eval_(`state('email').saving = true`);
  const beforeHold = await us.eval_(`state('email').value`);
  await us.eval_(`window.__fireBroadcast()`);
  await sleep(700);
  const duringHold = await us.eval_(`state('email').value`);
  check('a broadcast during our own save does not disturb the cell',
    duringHold === beforeHold, `${beforeHold} -> ${duringHold}`);
  await us.eval_(`state('email').saving = false`);
  await shot('03-held-off');

  // ── 4. No Echo on the page must not break the table ───────────────────
  const bare = await open(false);
  const bareOk = await bare.eval_(`typeof Alpine !== 'undefined' && !!cell('email') && !window.Echo`);
  check('a page with no Echo still boots the table (it falls back to the poll)', bareOk);

  check('no console/runtime errors', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  console.log('\nSummary: ' + results.filter((r) => r.ok).length + '/' + results.length + ' checks passed');
  console.log('Screenshots: ' + shotDir);
  if (results.some((r) => !r.ok)) process.exitCode = 1;
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
