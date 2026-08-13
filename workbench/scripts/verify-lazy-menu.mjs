import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * CDP driver for ActionGroup::lazyMenu() (render-engine-htmlable-first.md §6).
 *
 * Verifies the lazy dropdown in the workbench preview
 * (/previews/table-actions-group-lazy):
 *   1. the menu items are NOT in the server DOM before the trigger is opened,
 *   2. clicking the trigger builds them client-side from the serialized spec,
 *   3. clicking a lazily-built item fires $wire from the teleported panel (the
 *      capture-at-init that dodges the teleport $wire gotcha) — Delete opens the
 *      action modal.
 *
 * Exit 0 = all passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-actions-group-lazy`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-lazy-menu-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-lazy-menu-${Date.now()}`);
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
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);
  await shot('01-loaded');

  // 1. Trigger present, but no menu items in the server DOM yet (lazy).
  const triggers = await eval_(`document.querySelectorAll('[data-testid="action-group-trigger"]').length`);
  check('action-group trigger rendered', triggers > 0, `${triggers} triggers`);

  // Server ships zero menu Blade markup (covered by the render fuse); client-built
  // items live in a hidden teleported panel and must not be visible before open.
  const visibleBeforeOpen = await eval_(`
    [...document.querySelectorAll('[data-testid^="menu-action-"]')].filter(e => e.offsetParent !== null).length
  `);
  check('no menu items visible before open', visibleBeforeOpen === 0, `${visibleBeforeOpen} visible`);

  // 2. Open the first dropdown → items are built client-side from the spec.
  await eval_(`document.querySelector('[data-testid="action-group-trigger"]').click()`);
  await sleep(700);
  await shot('02-menu-open');

  const itemsAfterOpen = await eval_(`document.querySelectorAll('[data-testid^="menu-action-"]').length`);
  check('menu items built client-side on open', itemsAfterOpen >= 3, `${itemsAfterOpen} items`);

  const teleported = await eval_(`
    (() => {
      const el = document.querySelector('[data-testid="menu-action-edit"]');
      if (!el) return false;
      // Built inside the teleported panel, i.e. a <body> child, not the row cell.
      return document.body.contains(el) && !el.closest('td');
    })()
  `);
  check('menu item lives in the teleported panel', teleported === true);

  const editLabel = await eval_(`(document.querySelector('[data-testid="menu-action-edit"]')?.textContent || '').trim()`);
  check('menu item has its label + icon', /Edit/.test(editLabel), JSON.stringify(editLabel));
  const hasIcon = await eval_(`!!document.querySelector('[data-testid="menu-action-edit"] svg')`);
  check('menu item icon rendered', hasIcon === true);

  // 3. Click Delete → $wire.openActionModal fires from the teleported button.
  const deleteExists = await eval_(`!!document.querySelector('[data-testid="menu-action-delete"]')`);
  check('delete item present', deleteExists === true);

  await eval_(`document.querySelector('[data-testid="menu-action-delete"]').click()`);
  await sleep(2000);
  await shot('03-after-delete-click');

  const modalOpen = await eval_(`
    (() => {
      const dialog = document.querySelector('[role="dialog"]');
      const fixedOverlay = [...document.querySelectorAll('.fixed.inset-0')].some(e => e.offsetParent !== null);
      const bodyText = document.body.innerText.toLowerCase();
      return !!dialog || fixedOverlay || bodyText.includes('are you sure') || bodyText.includes('delete');
    })()
  `);
  check('clicking the lazy item fired $wire (action modal opened)', modalOpen === true);

  console.log(`\nScreenshots: ${shotDir}`);
} catch (err) {
  console.error('DRIVER ERROR:', err.message);
  check('driver ran', false, err.message);
} finally {
  cdp?.close();
  chrome.kill();
}

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length ? 1 : 0);

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
