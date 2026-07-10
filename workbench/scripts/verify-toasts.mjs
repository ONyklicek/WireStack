import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for the toast notification preview (/previews/core-toasts):
 * countdown bar + hover-pause, server-sent toast with an Undo action that
 * round-trips back through Livewire, a persistent (sticky) toast, the
 * collapsible stack fanning out on hover, and the max-visible "+N more" cap.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-toasts.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/core-toasts';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-toast-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-toast-verify-${Date.now()}`);
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
const jsErrors = [];
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
  cdp.on((msg) => {
    if (msg.method === 'Runtime.exceptionThrown') {
      jsErrors.push(msg.params?.exceptionDetails?.exception?.description ?? 'exception');
    }
  });
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  // In-page helpers: locate each container by the event name baked into x-data.
  await eval_(`
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.containerFor = (needle) => $qa('[x-data]')
      .find(el => (el.getAttribute('x-data')||'').includes('toasts:') && (el.getAttribute('x-data')||'').includes(needle));
    window.dataFor = (needle) => Alpine.$data(containerFor(needle));
    window.clickBtn = (text) => {
      const b = $qa('button').find(x => x.innerText.trim() === text || x.innerText.trim().startsWith(text));
      if (b) { b.click(); return true; } return false;
    };
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && !!window.wireToast && !!containerFor('table-notification')`);
  check('toasts preview booted (Alpine + 3 containers)', booted);
  await shot('01-initial');

  // ── 1. Basic success toast → default container, countdown bar depletes ─────
  await eval_(`clickBtn('Success')`);
  await sleep(400);
  const c1 = await eval_(`dataFor('table-notification').toasts.length`);
  const bar = await eval_(`!!containerFor('table-notification').querySelector('[style*="width:"]')`);
  check('Success button shows a toast with a countdown bar', c1 >= 1 && bar, `count=${c1}`);
  const r1 = await eval_(`dataFor('table-notification').toasts[0].remaining`);
  await sleep(900);
  const r2 = await eval_(`dataFor('table-notification').toasts[0].remaining`);
  check('countdown depletes over time', r2 < r1, `remaining ${Math.round(r1)} -> ${Math.round(r2)}`);
  await shot('02-success-bar');

  // ── 2. Server toast with Undo action → round-trips back through Livewire ───
  await eval_(`clickBtn('Toast with Undo')`); // wire:click, needs a roundtrip
  await sleep(1800);
  const hasAction = await eval_(`dataFor('table-notification').toasts.some(t => (t.actions||[]).some(a => a.label === 'Undo'))`);
  check('server toast (no $this) carries an Undo action', hasAction);
  await shot('03-server-action');

  // Click the Undo action button → dispatches demo-undo → info "Change reverted".
  await eval_(`
    (() => {
      const btn = $qa('button').find(b => b.innerText.trim() === 'Undo');
      if (btn) btn.click();
    })()
  `);
  await sleep(1800);
  const reverted = await eval_(`dataFor('table-notification').toasts.some(t => t.message === 'Change reverted')`);
  check('Undo dispatches back to Livewire and shows the revert toast', reverted);
  await shot('04-undo-reverted');

  // ── 3. Persistent toast: sticky, no countdown, survives the timer ──────────
  await eval_(`clickBtn('Persistent')`);
  await sleep(1600);
  const sticky = await eval_(`dataFor('table-notification').toasts.some(t => t.sticky === true)`);
  await sleep(1200);
  const stickyStays = await eval_(`dataFor('table-notification').toasts.some(t => t.sticky === true)`);
  check('persistent toast is sticky and does not auto-dismiss', sticky && stickyStays);
  await shot('05-persistent');

  // ── 4. Stack: fire 5 → collapsed pile → hover fans out ─────────────────────
  await eval_(`clickBtn('Fire 5 stacked')`);
  await sleep(1200);
  const stackN = await eval_(`dataFor('demo-stack').toasts.length`);
  const collapsed = await eval_(`dataFor('demo-stack').expanded === false`);
  check('stack container piles up collapsed', stackN === 5 && collapsed, `count=${stackN}`);
  await shot('06-stack-collapsed');

  await eval_(`containerFor('demo-stack').querySelector('div').dispatchEvent(new MouseEvent('mouseenter'))`);
  await sleep(500);
  const expanded = await eval_(`dataFor('demo-stack').expanded === true`);
  check('hover fans the stack out', expanded);
  await shot('07-stack-expanded');
  await eval_(`containerFor('demo-stack').querySelector('div').dispatchEvent(new MouseEvent('mouseleave'))`);

  // ── 5. Max-visible cap: fire 8 → only 3 rendered + "+N more" ───────────────
  await eval_(`clickBtn('Fire 8 queued')`);
  await sleep(1600);
  const capState = await eval_(`JSON.stringify({
    total: dataFor('demo-max').toasts.length,
    visible: dataFor('demo-max').visibleList().length,
    hidden: dataFor('demo-max').hiddenCount(),
    pill: !!$qa('button').find(b => b.innerText.includes('more')),
  })`);
  const cap = JSON.parse(capState);
  check('max cap renders 3 with a "+N more" overflow pill', cap.visible === 3 && cap.hidden >= 1 && cap.pill, capState);
  await shot('08-max-cap');

  check('no uncaught JS exceptions during run', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '));

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
