import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * `Table::live(broadcast: true)` over a REAL transport.
 *
 * verify-live-broadcast.mjs stubs `window.Echo` and proves the bridge: the table
 * subscribes, an event makes it re-read, a save in flight holds the re-read off.
 * What a stub cannot prove is everything between the two — that
 * `TableRecordsChanged` serialises, that `broadcastOn()` names a channel a real
 * server accepts, that `broadcastAs()` is the string the client ends up bound to,
 * and that private-channel authorization lets the subscription through at all. A
 * stub says yes to all four by construction.
 *
 * So this runs the whole path: Laravel Echo + pusher-js in the page, a Reverb
 * server on :8090, `/broadcasting/auth` doing a real authorization round trip,
 * and one browser session watching another one write.
 *
 * It also answers the question a stub is worst at: does an incoming update
 * disturb a write that is happening right now? Three ways it could —
 * overwriting a half-typed value, cancelling a commit in flight, or landing a
 * pre-write snapshot on top of a write that already succeeded — are checked
 * separately.
 *
 * Requires the workbench started for broadcasting:
 *   touch workbench/storage/wire-broadcast.enabled
 *   vendor/bin/testbench reverb:start --host=127.0.0.1 --port=8090 &
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085 &
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-editable-live';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9361);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-live-real-shots');
await mkdir(shotDir, { recursive: true });

const POLL_MS = 2000;   // the preview's live() interval — nothing here may rely on it

const userDataDir = join(tmpdir(), `wire-live-real-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars',
  // Two pages are open at once, so the one under test spends most of the run in
  // the background, where Chrome clamps timers to ~1s. That is long enough to
  // swallow the settle window and push a re-read past the poll tick, which reads
  // as "the broadcast did nothing" in a run where it did everything.
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

const consoleErrors = [];

const helpers = `
  window.cell = (col, row = 0) =>
    document.querySelectorAll('[data-testid="table-editable-'+col+'"]')[row];
  window.state = (col, row = 0) => Alpine.$data(cell(col, row));
  window.sync = (col, row = 0) => cell(col, row).querySelector('[data-cell-sync]');
  window.liveRoot = () => [...document.querySelectorAll('[x-data]')]
    .find(el => (el.getAttribute('x-data') || '').includes('wireTableLive'));
  window.type = async (col, text, row = 0) => {
    const input = cell(col, row).querySelector('input');
    input.dispatchEvent(new Event('focus', { bubbles: true }));
    await new Promise(r => setTimeout(r, 120));
    input.value = text;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    await new Promise(r => setTimeout(r, 250));
    input.dispatchEvent(new Event('blur', { bubbles: true }));
  };
  true;
`;

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);

  const open = async () => {
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
    await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1100, deviceScaleFactor: 1, mobile: false });
    await page('Page.navigate', { url });
    await sleep(3500);
    await eval_(helpers);
    return { page, eval_ };
  };

  cdp.on((msg) => {
    if (msg.method === 'Runtime.exceptionThrown') {
      consoleErrors.push(msg.params?.exceptionDetails?.exception?.description ?? 'exception');
    }
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params?.type === 'error') {
      consoleErrors.push((msg.params.args ?? []).map((a) => a.value ?? a.description ?? '').join(' '));
    }
  });

  const us = await open();
  const shot = async (name) => {
    const { data } = await us.page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  // This one needs infrastructure the default workbench does not start: a Reverb
  // on :8090 and the flag file that makes the preview ship Echo at all. Skipping
  // is the honest answer when they are absent — failing would say the feature is
  // broken when what is missing is the harness, and the sweep would then carry a
  // red line every developer learns to ignore.
  if (! (await us.eval_(`typeof window.Echo !== 'undefined'`))) {
    console.log('SKIP  the workbench is not running with broadcasting enabled.');
    console.log('      No broadcaster is a dependency of this repository, so this');
    console.log('      driver installs on demand and is never part of a sweep:');
    console.log('');
    console.log('        composer require --dev laravel/reverb');
    console.log('        npm i -D laravel-echo pusher-js && npm run build:workbench-echo');
    console.log('        touch workbench/storage/wire-broadcast.enabled');
    console.log('        vendor/bin/testbench reverb:start --host=127.0.0.1 --port=8090 &');
    console.log('        vendor/bin/testbench serve --host=127.0.0.1 --port=8085 &');
    chrome.kill('SIGTERM');
    cdp.close();
    await rm(userDataDir, { recursive: true, force: true }).catch(() => {});
    process.exit(0);
  }

  // ── 1. A real socket, and a real authorization round trip ─────────────
  const socketId = await us.eval_(`window.__echoReady`);
  check('the page opened a real WebSocket to Reverb',
    typeof socketId === 'string' && socketId.length > 0, `socket_id=${socketId}`);
  check('Echo is the real client, not a stub',
    (await us.eval_(`!!window.Echo?.connector?.pusher && window.__echoState() === 'connected'`)) === true);

  // pusher-js only fires this after /broadcasting/auth signed the subscription,
  // so it is the single check that the private channel was actually authorized.
  const subscribed = await us.eval_(`(async () => {
    const name = 'wire-table.Workbench-App-Models-User';
    const ch = window.Echo.connector.channels['private-' + name];
    if (! ch) return 'NO CHANNEL';
    if (ch.subscribed) return 'ok';
    return await new Promise((resolve) => {
      ch.bind('pusher:subscription_succeeded', () => resolve('ok'));
      ch.bind('pusher:subscription_error', (e) => resolve('ERROR ' + JSON.stringify(e)));
      setTimeout(() => resolve('TIMEOUT state=' + window.__echoState()), 8000);
    });
  })()`);
  check('the private channel was authorized and subscribed', subscribed === 'ok', String(subscribed));
  await shot('01-connected');

  // ── 2. Another session writes; this one is told over the socket ───────
  const them = await open();
  await them.eval_(`window.__echoReady`);

  const theirs = `real-${Date.now()}@example.test`;
  const before = await us.eval_(`state('email').value`);

  await us.page('Page.bringToFront');
  await sleep(150);
  // Watch for the change rather than sleeping through it, so the elapsed time is
  // the evidence: anything near the 2s tick would mean the poll did it.
  await us.eval_(`(() => {
    window.__seenAt = null;
    window.__t0 = performance.now();
    const want = ${JSON.stringify(theirs)};
    window.__iv = setInterval(() => {
      if (state('email').value === want && window.__seenAt === null) {
        window.__seenAt = Math.round(performance.now() - window.__t0);
      }
    }, 25);
  })()`);

  await them.eval_(`(async () => await type('email', ${JSON.stringify(theirs)}))()`);

  const deadline = Date.now() + 1500;
  let seenAt = null;
  while (Date.now() < deadline) {
    seenAt = await us.eval_(`window.__seenAt`);
    if (seenAt !== null) break;
    await sleep(50);
  }
  await us.eval_(`clearInterval(window.__iv)`);

  check("another session's write reaches this one over the real socket",
    seenAt !== null, `before=${before} expected=${theirs} seenAt=${seenAt}`);
  check('…and it was the broadcast that carried it, not the next poll tick',
    seenAt !== null && seenAt < POLL_MS - 300, `${seenAt}ms (poll interval ${POLL_MS}ms)`);
  await shot('02-received');

  // ── 3. An incoming update must not disturb a write being TYPED ────────
  // The cell is focused and dirty: the user is mid-sentence. A refresh landing
  // here that replaced the input would lose their keystrokes.
  const halfTyped = 'half-typed-' + Date.now() + '@example.test';
  await us.eval_(`(() => {
    const i = cell('email').querySelector('input');
    i.dispatchEvent(new Event('focus', { bubbles: true }));
    i.value = ${JSON.stringify(halfTyped)};
    i.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await sleep(150);

  const duringTyping = `typed-race-${Date.now()}@example.test`;
  await them.eval_(`(async () => await type('email', ${JSON.stringify(duringTyping)}))()`);
  await sleep(1500);   // well past the settle window and a poll tick

  const stillTyping = await us.eval_(`JSON.stringify({
    input: cell('email').querySelector('input').value,
    value: state('email').value,
    focused: state('email').focused,
  })`);
  const typing = JSON.parse(stillTyping);
  check('an update arriving mid-typing leaves the half-typed value alone',
    typing.input === halfTyped && typing.value === halfTyped, stillTyping);
  await shot('03-mid-typing');

  // Abandon that edit so it cannot leak into the next check.
  await us.eval_(`(() => {
    const d = state('email');
    d.value = d.serverValue;
    cell('email').querySelector('input').dispatchEvent(new Event('blur', { bubbles: true }));
  })()`);
  await sleep(400);

  // ── 4. An incoming update must not disturb a write IN FLIGHT ──────────
  // Our own commit is pending when the other session's broadcast lands. The
  // write must still complete, keep its value, and not come back as a conflict.
  const ourValue = `ours-${Date.now()}@example.test`;
  const race = await us.eval_(`(async () => {
    const d = state('email');
    const pending = d.commit(${JSON.stringify(ourValue)});   // not awaited yet
    const sawSavingAtFire = d.saving;
    window.__racePending = pending;
    return JSON.stringify({ sawSavingAtFire });
  })()`);
  check('our own write is genuinely in flight when the race starts',
    JSON.parse(race).sawSavingAtFire === true, race);

  // Fire the other session's write into that window.
  const theirRacer = `theirs-race-${Date.now()}@example.test`;
  await them.eval_(`(async () => await type('email', ${JSON.stringify(theirRacer)}))()`);

  const outcome = await us.eval_(`(async () => {
    await window.__racePending;
    await new Promise(r => setTimeout(r, 1800));
    const d = state('email');
    return JSON.stringify({ value: d.value, serverValue: d.serverValue, error: d.error, saving: d.saving });
  })()`);
  const o = JSON.parse(outcome);
  check('the in-flight write completed rather than being cancelled',
    o.saving === false, outcome);
  check("…and was not refused as somebody else's edit", !o.error, `error=${o.error ?? 'none'}`);
  check('…and the cell settled on a real value, not a half-applied one',
    typeof o.value === 'string' && o.value.length > 0 && o.value === o.serverValue, outcome);
  await shot('04-in-flight-race');

  // The database is the arbiter: whichever write landed last, the cell must be
  // showing exactly that — never a value neither session wrote.
  const server = await us.eval_(`(async () => {
    const html = await fetch(${JSON.stringify(url)}, { headers: { 'X-Requested-With': 'fetch' } }).then(r => r.text());
    const m = html.match(/data-testid="table-editable-email"[\\s\\S]{0,600}?data-server-value="([^"]*)"/);
    return m ? m[1] : null;
  })()`);
  check('the cell agrees with what the database actually holds',
    server === o.value, `server=${server} cell=${o.value}`);

  check('no console/runtime errors anywhere in the run', consoleErrors.length === 0,
    consoleErrors.slice(0, 3).join(' | '));

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
