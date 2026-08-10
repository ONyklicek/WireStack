import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying Table::collapseHeaderActionsOnMobile() in the workbench
 * preview (/previews/table-header-actions-collapse). Unlike the row-action
 * collapse this is not tied to the stacked cards: both halves of the toolbar are
 * in the document at every width and CSS picks one, so the only honest check is
 * a real browser measuring what is visible at two viewport widths.
 *
 * Asserts, at 390px: one group trigger, no visible header-action buttons, the
 * menu opens with all three actions; at 1200px: the three buttons back, no
 * trigger. Plus the shortcut listener count — a keyboardShortcut() bound by both
 * halves would run the action twice per keypress.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-collapse-header-actions.mjs
 *
 * Exit 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-header-actions-collapse';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9338);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-collapse-header-actions-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-collapse-header-verify-${Date.now()}`);
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
  // Phone: below the default sm (640px) mobile breakpoint, where the fold applies.
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url });
  await sleep(3000);

  const helpers = `
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.isVisible = (el) => !!el && el.getClientRects().length > 0;
    // The toolbar's fold, not a row's: scope to the header-actions surfaces.
    window.headerBtns = () => $qa('[data-testid^="header-action-"]');
    window.mobileTrigger = () => $qa('[data-testid="table-header-actions-mobile"] [data-testid="action-group-trigger"]');
    true;
  `;
  await eval_(helpers);

  // ── 0. Page booted ─────────────────────────────────────────────────────
  const booted = await eval_(`typeof Alpine !== 'undefined' && $qa('[data-testid="table-row"], [data-testid="table-card"]').length > 0`);
  check('preview booted with Alpine and a rendered table', booted);
  await shot('01-phone-toolbar');

  // ── 1. On a phone: one trigger, no visible header buttons ──────────────
  const phone = await eval_(`JSON.stringify({
    triggers: mobileTrigger().filter(isVisible).length,
    // Both halves are in the DOM; only the desktop one must be hidden here.
    buttonsInDom: headerBtns().length,
    buttonsVisible: headerBtns().filter(isVisible).length,
  })`);
  const s1 = JSON.parse(phone);
  check('the toolbar shows exactly one collapsed trigger at 390px', s1.triggers === 1, phone);
  check('no header-action button is visible at 390px', s1.buttonsVisible === 0, `visible=${s1.buttonsVisible} of ${s1.buttonsInDom} in DOM`);

  // ── 2. Menu closed until asked ─────────────────────────────────────────
  const menuClosedBefore = await eval_(`!$qa('[role="menu"]').some(isVisible)`);
  check('dropdown menu is closed before interaction', menuClosedBefore);

  // ── 3. Opening it reveals all three header actions ─────────────────────
  const clicked = await eval_(`(() => { const t = mobileTrigger()[0]; if (!t) return false; t.click(); return true; })()`);
  check('collapsed trigger clicked', clicked === true);
  await sleep(1200);

  const opened = await eval_(`JSON.stringify({
    menuVisible: $qa('[role="menu"]').some(isVisible),
    items: $qa('[role="menu"] [role="menuitem"]').filter(isVisible).map(i => i.innerText.trim()).filter(Boolean),
  })`);
  const s3 = JSON.parse(opened);
  const labels = s3.items.join(' | ').toLowerCase();
  const hasAll = ['invite', 'import', 'export'].every(l => labels.includes(l));
  check('opening the group reveals a visible menu', s3.menuVisible, opened);
  check('menu contains all three header actions', hasAll, `items=[${s3.items.join(', ')}]`);
  await shot('02-menu-open');

  // ── 4. The shortcut is bound once, by the button half only ─────────────
  const shortcutBindings = await eval_(`
    $qa('[x-on\\\\:keydown\\\\.i\\\\.window\\\\.prevent], [data-testid="header-action-import"][x-on\\\\:keydown\\\\.i\\\\.window\\\\.prevent]').length
  `);
  check('the header action keyboard shortcut is bound exactly once', shortcutBindings === 1, `bindings=${shortcutBindings}`);

  // ── 5. Desktop width: the buttons are back, the trigger is gone ────────
  await eval_(`(() => { const t = mobileTrigger()[0]; t && document.body.click(); return true; })()`);
  await page('Emulation.setDeviceMetricsOverride', { width: 1200, height: 900, deviceScaleFactor: 1, mobile: false });
  await sleep(800);

  const desktop = await eval_(`JSON.stringify({
    triggers: mobileTrigger().filter(isVisible).length,
    buttonsVisible: headerBtns().filter(isVisible).length,
  })`);
  const s5 = JSON.parse(desktop);
  check('all three header buttons are visible at 1200px', s5.buttonsVisible === 3, desktop);
  check('the collapsed trigger is hidden at 1200px', s5.triggers === 0, desktop);
  await shot('03-desktop-toolbar');

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
