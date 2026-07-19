import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying Table::collapseActionsOnMobile() in the workbench preview
 * (/previews/table-stacked-actions-collapse). Emulates a mobile viewport so the
 * stacked-card layout renders, then asserts the three inline row actions collapse
 * into one dropdown group ("⋮") whose menu opens with all three actions.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-collapse-mobile-actions.mjs
 *
 * Exit 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-stacked-actions-collapse';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-collapse-actions-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-collapse-verify-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
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

  await page('Page.enable');
  await page('Runtime.enable');
  // Mobile viewport: below the md (768px) stacked breakpoint so cards render.
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url });
  await sleep(3000);

  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.firstCard = () => $q('[data-testid="table-card"]');
    window.isVisible = (el) => !!el && el.getClientRects().length > 0;
    true;
  `;
  await eval_(helpers);

  // ── 0. Page booted + mobile cards rendered ─────────────────────────────
  const booted = await eval_(`typeof Alpine !== 'undefined' && $qa('[data-testid="table-card"]').length > 0`);
  const cardCount = await eval_(`$qa('[data-testid="table-card"]').length`);
  check('mobile stacked cards render with Alpine booted', booted, `cards=${cardCount}`);
  await shot('01-mobile-cards');

  // ── 1. Each card collapses its actions into ONE group trigger ──────────
  const collapse = await eval_(`JSON.stringify({
    triggers: $qa('[data-testid="table-card"] [data-testid="action-group-trigger"]').length,
    cards: $qa('[data-testid="table-card"]').length,
    // Inline row-action buttons carry data-testid="action-<name>"; collapsed away.
    inlineActionBtns: $qa('[data-testid="table-card"] [data-testid^="action-"]:not([data-testid="action-group-trigger"])').length,
  })`);
  const s1 = JSON.parse(collapse);
  check('every card header shows one action-group trigger', s1.triggers === s1.cards && s1.cards > 0, collapse);
  check('no inline per-action buttons remain in the cards', s1.inlineActionBtns === 0, `inline=${s1.inlineActionBtns}`);

  // ── 2. Menu is closed until the trigger is clicked ─────────────────────
  const menuClosedBefore = await eval_(`!$qa('[role="menu"]').some(isVisible)`);
  check('dropdown menu is closed before interaction', menuClosedBefore);

  // ── 3. Open the first card's group → all three actions appear ──────────
  const clicked = await eval_(`
    (() => {
      const t = firstCard().querySelector('[data-testid="action-group-trigger"]');
      if (!t) return false; t.click(); return true;
    })()
  `);
  check('first card group trigger clicked', clicked === true);
  await sleep(1200);

  const opened = await eval_(`JSON.stringify({
    menuVisible: $qa('[role="menu"]').some(isVisible),
    items: $qa('[role="menu"] [role="menuitem"]').filter(isVisible).map(i => i.innerText.trim()).filter(Boolean),
  })`);
  const s3 = JSON.parse(opened);
  const labels = s3.items.join(' | ').toLowerCase();
  const hasAll = ['view', 'edit', 'delete'].every(l => labels.includes(l));
  check('opening the group reveals a visible menu', s3.menuVisible, opened);
  check('menu contains all three collapsed actions (View/Edit/Delete)', hasAll, `items=[${s3.items.join(', ')}]`);
  await shot('02-menu-open');

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
