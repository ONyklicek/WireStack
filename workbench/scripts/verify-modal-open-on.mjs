import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying the openOn seam of the canonical modal shells (selection
 * gestures rollout, step 23). All three shells — modal, confirmation, slide-over
 * — render with no wire:model binding and a window event opens them purely
 * client-side:
 *   - every shell is hidden on load,
 *   - dispatching the openOn event shows the matching shell,
 *   - a Livewire roundtrip with the modal open does NOT close it (morph keeps
 *     the local Alpine `show` state) and morphs the teleported body content,
 *   - Escape closes it, and the event opens it again after the morph,
 *   - the confirmation closes via its cancel button, the slide-over via its
 *     close button,
 *   - no Alpine console errors.
 *
 * Exit 0 = all passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/core-open-on`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9341);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-modal-open-on-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-modal-open-on-${Date.now()}`);
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
    // The overlays are position:fixed, so gate visibility on Alpine's x-show
    // display toggle, not offsetParent.
    window.shown = (id) => {
      const el = document.getElementById(id);
      return !!el && getComputedStyle(el).display !== 'none';
    };
    window.trigger = (id) => document.querySelector('[data-testid=' + id + ']').click();
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined'
    && !!document.querySelector('[data-testid=open-on-modal-trigger]')
    && !document.body.innerHTML.includes('{{ new')`);
  check('page booted with the trigger buttons, no leaked blade markup', booted);

  const allHidden = await eval_(`!shown('open-on-modal') && !shown('open-on-confirmation') && !shown('open-on-slideover')`);
  check('all three event-opened shells start hidden', allHidden);

  // ── Modal: open by event, survive a Livewire roundtrip, close on Escape ──
  await eval_(`trigger('open-on-modal-trigger')`);
  await sleep(600);
  check('dispatching the openOn event shows the modal', await eval_(`shown('open-on-modal')`));
  await shot('01-modal-open');

  const ticksBefore = await eval_(`document.querySelector('[data-testid=open-on-modal-ticks]')?.textContent`);
  check('modal body renders the server state', ticksBefore === '0', `ticks=${ticksBefore}`);

  await eval_(`trigger('open-on-server-tick')`);
  await sleep(1500);
  const tickCount = await eval_(`document.querySelector('[data-testid=open-on-tick-count]')?.textContent`);
  check('Livewire roundtrip completed', tickCount === '1', `tick-count=${tickCount}`);
  check('modal stays open across the Livewire update', await eval_(`shown('open-on-modal')`));
  const ticksAfter = await eval_(`document.querySelector('[data-testid=open-on-modal-ticks]')?.textContent`);
  check('teleported modal body morphs with the update', ticksAfter === '1', `ticks=${ticksAfter}`);
  await shot('02-modal-after-roundtrip');

  await eval_(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`);
  await sleep(600);
  check('Escape closes the modal', await eval_(`!shown('open-on-modal')`));

  await eval_(`trigger('open-on-modal-trigger')`);
  await sleep(600);
  check('the openOn listener survives the morph — the modal opens again', await eval_(`shown('open-on-modal')`));
  await eval_(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`);
  await sleep(600);

  // ── Confirmation: open by event, close via cancel ──
  await eval_(`trigger('open-on-confirm-trigger')`);
  await sleep(600);
  check('dispatching the openOn event shows the confirmation', await eval_(`shown('open-on-confirmation')`));
  await shot('03-confirmation-open');
  await eval_(`document.querySelector('#open-on-confirmation [data-testid=confirmation-cancel]').click()`);
  await sleep(600);
  check('cancel closes the event-opened confirmation', await eval_(`!shown('open-on-confirmation')`));

  // ── Slide-over: open by event, close via the close button ──
  await eval_(`trigger('open-on-panel-trigger')`);
  await sleep(700);
  check('dispatching the openOn event shows the slide-over', await eval_(`shown('open-on-slideover')`));
  await shot('04-slideover-open');
  await eval_(`document.querySelector('#open-on-slideover [data-testid=slide-over-close]').click()`);
  await sleep(700);
  check('the close button closes the event-opened slide-over', await eval_(`!shown('open-on-slideover')`));

  const alpineErrors = consoleErrors.filter((e) => /Alpine|Expression Error|SyntaxError|Unexpected/i.test(e));
  check('no Alpine/JS errors during the openOn lifecycle', alpineErrors.length === 0,
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
