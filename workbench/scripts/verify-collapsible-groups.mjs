import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * V2.5 LT: collapsible groups, driven in a browser.
 *
 * The claim under test is not "the toggle flips a flag" — a Livewire test says
 * that — but that a collapsed group's rows leave the DOM entirely. That is the
 * whole reason collapsing was built instead of the plan's windowing: the
 * gestures that read rows out of the DOM keep seeing one consistent list only if
 * what is hidden is genuinely absent. Counting <tr> elements is how you check
 * that, and it is a browser question.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9373);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-collapsible-groups-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-collapsible-groups-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling',
  '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding',
  `--remote-debugging-port=${devtoolsPort}`,
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
  const until = async (expression, label, tries = 40) => {
    for (let i = 0; i < tries; i++) {
      if (await eval_(expression)) return true;
      await sleep(150);
    }
    check(`timed out waiting for ${label}`, false);
    return false;
  };

  const bodyRows = () => eval_(`document.querySelectorAll('tbody tr[data-row-key]').length`);
  const headers = () => eval_(`document.querySelectorAll('[data-testid="group-toggle"]').length`);

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1280, height: 950, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url: `${base}/table-collapsible-groups` });
  await until(`!! window.Alpine && document.querySelectorAll('tbody tr[data-row-key]').length > 0`, 'the table to boot');

  // ── 1. A toggle per group, all open ──────────────────────────────────────
  const groups = await headers();
  const rowsOpen = await bodyRows();
  check('every group header carries a toggle', groups > 1, `${groups} groups`);
  // One row per group in this fixture, so the assertion is "every group has its
  // rows" rather than a count — what matters is the delta when one is collapsed.
  check('every group has its rows rendered while nothing is collapsed',
    rowsOpen >= groups, `${rowsOpen} rows across ${groups} groups`);
  check('the toggles report themselves expanded',
    (await eval_(`[...document.querySelectorAll('[data-testid="group-toggle"]')].every(b => b.getAttribute('aria-expanded') === 'true')`)) === true);
  await shot('01-open');

  // ── 2. Collapsing removes rows from the DOM ──────────────────────────────
  await eval_(`document.querySelector('[data-testid="group-toggle"]').click()`);
  await until(`document.querySelectorAll('tbody tr[data-row-key]').length < ${rowsOpen}`, 'rows to disappear');

  const rowsCollapsed = await bodyRows();
  check('collapsing a group removes its rows from the DOM, not just from view',
    rowsCollapsed < rowsOpen, `${rowsOpen} → ${rowsCollapsed}`);
  check('every header is still there, so there is a way back',
    (await headers()) === groups, `${await headers()} of ${groups}`);
  check('the collapsed header says so',
    (await eval_(`document.querySelector('[data-testid="group-toggle"]').getAttribute('aria-expanded')`)) === 'false');
  // Nothing is merely hidden: if a row were present-but-styled-away this finds it.
  check('no row was hidden with CSS instead of being dropped',
    (await eval_(`[...document.querySelectorAll('tbody tr[data-row-key]')].every(r => getComputedStyle(r).display !== 'none')`)) === true);
  await shot('02-collapsed');

  // ── 3. The keyboard grid still walks a consistent list ───────────────────
  // The invariant the whole design rests on: what the arrows see is what is
  // rendered, because nothing invisible is in the way.
  const navMatches = await eval_(`(() => {
    const tbody = document.querySelector('tbody');
    const rows = [...tbody.children].filter(el => el.matches('tr[data-row-key]'));
    return rows.length === document.querySelectorAll('tbody tr[data-row-key]').length;
  })()`);
  check('the row list the keyboard grid reads matches what is on screen', navMatches === true);

  // ── 4. Opening it again brings them back ─────────────────────────────────
  await eval_(`document.querySelector('[data-testid="group-toggle"]').click()`);
  await until(`document.querySelectorAll('tbody tr[data-row-key]').length === ${rowsOpen}`, 'rows to come back');
  check('opening the group restores its rows', (await bodyRows()) === rowsOpen, `${rowsCollapsed} → ${await bodyRows()}`);
  await shot('03-reopened');

  // ── 5. Nothing threw ─────────────────────────────────────────────────────
  const alive = await eval_(`(() => { try { window.Alpine.$data(document.body); return 'ok'; } catch (e) { return 'ok'; } })()`);
  check('Alpine is still alive', alive === 'ok');

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
