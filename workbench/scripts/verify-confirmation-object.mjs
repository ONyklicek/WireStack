import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying the Htmlable Confirmation object (Rule 5 framework-wide,
 * Phase 1). The framework renders confirmations as `{{ new Confirmation(...) }}`
 * — no <x-wire-modals::confirmation> component. This opens a real DeleteAction
 * confirmation in the browser and checks it behaves identically:
 *   - the confirmation dialog opens (role=dialog + confirmation-confirm button),
 *   - the confirm button carries the forwarded wire:click,
 *   - clicking Cancel closes it, clicking Confirm fires the action (dialog closes),
 *   - no Alpine console errors and no literal component/blade markup leaks.
 *
 * Exit 0 = all passed; 1 = a check failed; 2 = driver error.
 */

// table-actions-quiet has direct DeleteAction row actions wired to the action
// runtime, so clicking one opens the (Htmlable-object) confirmation for real.
const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-actions-quiet`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9336);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-confirmation-object-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-confirmation-object-${Date.now()}`);
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

  const consoleErrors = [];
  cdp.on((msg) => {
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params.sessionId === sessionId && msg.params.type === 'error') {
      consoleErrors.push(msg.params.args.map((a) => a.value ?? a.description ?? '').join(' '));
    }
    if (msg.method === 'Runtime.exceptionThrown' && msg.params.sessionId === sessionId) {
      consoleErrors.push(msg.params.exceptionDetails.exception?.description ?? msg.params.exceptionDetails.text);
    }
  });

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  await eval_(`
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    // The overlay is position:fixed (offsetParent is null), so gate on Alpine's
    // x-show display toggle, not offsetParent.
    window.dialogShown = () => $qa('[role=dialog]').some(d => getComputedStyle(d).display !== 'none');
    window.confirmBtn = () => document.querySelector('[data-testid=confirmation-confirm]');
    window.cancelBtn = () => document.querySelector('[data-testid=confirmation-cancel]');
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined'
    && !document.body.innerHTML.includes('x-wire-modals::confirmation')
    && !document.body.innerHTML.includes('{{ new')`);
  check('page booted, no leaked component/blade markup for the confirmation', booted);

  // Open the delete confirmation.
  const opened = await eval_(`(function(){
    const b = document.querySelector('[data-testid=action-delete]')
      || $qa('button,[role=button]').find(el => /delete/i.test(el.innerText));
    if (!b) return false;
    b.click();
    return true;
  })()`);
  check('found + clicked the Delete action', opened === true);
  await sleep(1500);
  await shot('01-confirmation-open');

  const dialogOpen = await eval_(`dialogShown() && !!confirmBtn() && !!cancelBtn()`);
  check('confirmation dialog opened with confirm + cancel buttons', dialogOpen);

  const confirmWired = await eval_(`(function(){
    const b = confirmBtn();
    return b ? b.getAttribute('wire:click') : null;
  })()`);
  check('confirm button carries the forwarded wire:click', !!confirmWired, `wire:click=${confirmWired}`);

  // Cancel closes it.
  await eval_(`cancelBtn().click()`);
  await sleep(1200);
  const closedAfterCancel = await eval_(`!dialogShown()`);
  check('Cancel closes the confirmation dialog', closedAfterCancel);
  await shot('02-after-cancel');

  // Re-open and confirm — the action fires and the dialog closes.
  await eval_(`(function(){
    const b = document.querySelector('[data-testid=action-delete]')
      || $qa('button,[role=button]').find(el => /delete/i.test(el.innerText));
    b && b.click();
  })()`);
  await sleep(1500);
  const reopened = await eval_(`dialogShown()`);
  check('confirmation re-opens', reopened);

  await eval_(`confirmBtn() && confirmBtn().click()`);
  await sleep(1800);
  const closedAfterConfirm = await eval_(`!dialogShown()`);
  check('Confirm fires the action and closes the dialog', closedAfterConfirm);
  await shot('03-after-confirm');

  const alpineErrors = consoleErrors.filter((e) => /Alpine|Expression Error|SyntaxError|Unexpected/i.test(e));
  check('no Alpine/JS errors during the confirmation lifecycle', alpineErrors.length === 0,
    alpineErrors.slice(0, 3).join(' | '));
} catch (err) {
  console.error('DRIVER ERROR:', err.message);
  process.exitCode = 2;
} finally {
  cdp?.close();
  chrome.kill('SIGTERM');
  await rm(userDataDir, { recursive: true, force: true }).catch(() => {});
}

console.log(`\nScreenshots: ${shotDir}`);
const failed = results.filter((r) => !r.ok);
if (failed.length) {
  console.log(`\n${failed.length} check(s) FAILED.`);
  process.exitCode = process.exitCode ?? 1;
} else {
  console.log(`\nAll ${results.length} checks passed.`);
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
