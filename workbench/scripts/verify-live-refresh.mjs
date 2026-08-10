import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for the live-refresh contract of an inline-editable table
 * (/previews/table-editable-fill).
 *
 * An editable cell mounts with `wire:ignore.self` so a morph cannot stomp the
 * optimistic state it holds — and Livewire then leaves the ROOT's attributes
 * alone for good. `data-server-value` / `data-record-version` therefore have to
 * live on a child node the morph still reaches, or the cell is a write-only
 * surface: no re-render, no poll and no modal write can ever put a newer value
 * on the screen, and the lock version it keeps sending is the one the page
 * loaded with.
 *
 * Nothing in the PHP suite can see any of this — it renders markup, and the
 * whole failure is about what the SECOND render does to the first one's DOM.
 *
 * Checks, in order:
 *   1. a cell edited here is reconciled, not reverted, by a re-render;
 *   2. a change made by somebody else (second tab, same record) reaches the
 *      cell on the next refresh — the multi-user case;
 *   3. the lock version the cell holds tracks the server, so the user's own
 *      next edit is not refused as somebody else's;
 *   4. a refresh landing mid-edit leaves the half-typed value alone.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-live-refresh.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-editable-fill';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9358);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-live-refresh-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-live-refresh-${Date.now()}`);
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

const consoleErrors = [];
const badResponses = [];

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);

  // Two independent pages against the same record: "us" and "somebody else".
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
    await page('Network.enable');
    await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1100, deviceScaleFactor: 1, mobile: false });
    await page('Page.navigate', { url });
    await sleep(3200);
    await eval_(helpers);
    return { page, eval_, sessionId };
  };

  // Everything the checks below need, defined once in the page.
  //
  // `cell()` is the Alpine root (it owns the state and the identity attributes);
  // `sync()` is whatever node currently carries data-server-value — the root
  // itself today, a morphable child once the channel is fixed. Written this way
  // so the driver reports on the BEHAVIOUR (does the value reach the cell)
  // rather than on which element happens to hold the attribute.
  const helpers = `
    window.cell = (col, row = 0) =>
      document.querySelectorAll('[data-testid="table-editable-'+col+'"]')[row];
    window.sync = (col, row = 0) => {
      const el = cell(col, row);
      return el?.matches('[data-server-value]') ? el : el?.querySelector('[data-server-value]');
    };
    window.state = (col, row = 0) => Alpine.$data(cell(col, row));
    window.serverValue = (col, row = 0) => sync(col, row)?.dataset.serverValue ?? null;
    window.recordKey = (col, row = 0) => cell(col, row)?.dataset.recordKey ?? null;
    window.type = async (col, text, row = 0) => {
      const input = cell(col, row).querySelector('input');
      input.dispatchEvent(new Event('focus', { bubbles: true }));
      await new Promise((r) => setTimeout(r, 120));
      input.value = text;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      await new Promise((r) => setTimeout(r, 250));
      input.dispatchEvent(new Event('blur', { bubbles: true }));
    };
    window.refresh = async () => {
      const c = Livewire.all().find((x) => x.$wire.getTableRecords !== undefined) ?? Livewire.all()[0];
      await c.$wire.$refresh();
      await new Promise((r) => setTimeout(r, 700));
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
    if (msg.method === 'Network.responseReceived') {
      const s = msg.params?.response?.status;
      if (s >= 400) badResponses.push(`${s} ${msg.params.response.url}`);
    }
  });

  const us = await open();
  const shot = async (name) => {
    const { data } = await us.page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  const booted = await us.eval_(`typeof Alpine !== 'undefined' && !!cell('email') && !!sync('email')`);
  check('editable table booted with a reachable sync node', booted);
  await shot('01-initial');

  // ── 1. Our own edit survives a re-render, and the DOM agrees with it ──
  const mine = `live-${Date.now()}@example.test`;
  await us.eval_(`(async () => await type('email', ${JSON.stringify(mine)}))()`);
  await sleep(1800);
  await us.eval_(`(async () => await refresh())()`);
  await sleep(600);

  const afterOwnRefresh = await us.eval_(`state('email').value`);
  check('an edited cell keeps its value across a re-render', afterOwnRefresh === mine,
    `cell=${afterOwnRefresh}`);

  const syncedAfterOwn = await us.eval_(`serverValue('email')`);
  check('the sync node carries the value the server now holds', syncedAfterOwn === mine,
    `data-server-value=${syncedAfterOwn} expected=${mine}`);
  await shot('02-after-own-edit');

  // ── 2. Somebody else's write reaches our cell ─────────────────────────
  const key = await us.eval_(`recordKey('email')`);
  const theirs = `other-${Date.now()}@example.test`;
  const them = await open();
  const theirKey = await them.eval_(`recordKey('email')`);
  check('both sessions are looking at the same record', String(key) === String(theirKey),
    `us=${key} them=${theirKey}`);

  await them.eval_(`(async () => await type('email', ${JSON.stringify(theirs)}))()`);
  await sleep(1800);
  const theirCommitted = await them.eval_(`state('email').serverValue`);
  check('the second session committed its write', theirCommitted === theirs, `them=${theirCommitted}`);

  await us.eval_(`(async () => await refresh())()`);
  await sleep(700);
  const weSee = await us.eval_(`state('email').value`);
  check("another user's write appears in our editable cell after a refresh",
    weSee === theirs, `us=${weSee} expected=${theirs}`);
  await shot('03-after-other-user');

  // ── 3. The lock version tracks the server, so our next edit is not
  //       refused as somebody else's ────────────────────────────────────
  const ours = `mine-again-${Date.now()}@example.test`;
  await us.eval_(`(async () => await type('email', ${JSON.stringify(ours)}))()`);
  await sleep(1800);
  const err = await us.eval_(`state('email').error`);
  const settled = await us.eval_(`state('email').value`);
  check('our next edit is not refused as a stale write', !err && settled === ours,
    `error=${err ?? 'none'} value=${settled}`);
  await shot('04-after-reedit');

  // ── 4. A refresh landing mid-edit must not stomp what is being typed ──
  await us.eval_(`(() => {
    const i = cell('email').querySelector('input');
    i.dispatchEvent(new Event('focus', { bubbles: true }));
    i.value = 'half-typed@example.test';
    i.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await sleep(250);
  await us.eval_(`(async () => await refresh())()`);
  await sleep(700);
  const midEdit = await us.eval_(`cell('email').querySelector('input').value`);
  check('a refresh in the middle of an edit leaves the typed value alone',
    midEdit === 'half-typed@example.test', `input=${midEdit}`);
  await shot('05-mid-edit-refresh');

  const no419 = !badResponses.some((r) => r.startsWith('419'));
  check('no 419 on any commit', no419, badResponses.join('; ') || 'none');
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
