import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Shared CDP plumbing for the workbench drivers.
 *
 * Every driver used to carry its own copy of this — launch headless Chrome,
 * speak DevTools over a WebSocket, collect console errors and 4xx/5xx, take
 * screenshots, print a summary. The 50-odd existing drivers still do; new ones
 * import it from here instead, so the harness has one place to fix.
 *
 * What stays with each driver is the part worth reading: the checks.
 */

export { sleep };

/**
 * Wait for a condition instead of guessing how long it takes.
 *
 * A driver that does its work and then `sleep(1600)` is asserting a *timing*,
 * not a behaviour: it passes on an idle machine and fails in a sweep, where 74
 * Chromes have taken turns on one PHP dev server. That failure is
 * indistinguishable from a real one — same FAIL line, same exit code — so the
 * gate stops meaning anything.
 *
 * Polling removes the guess from both ends: a fast machine continues as soon as
 * the condition holds (usually well inside one sleep), and a slow one waits as
 * long as it needs. A timeout still reports FAIL, because after this long the
 * condition genuinely is not true.
 *
 * `probe` returns whatever it likes; the poll stops on the first truthy value
 * and hands it back, so a probe can return the state it was checking:
 *
 *     const rows = await until(() => eval_('document.querySelectorAll("tr").length'))
 *
 * Two traps, both of which have bitten:
 *
 *  - **wait for the thing to CHANGE, not for the state you are about to
 *    assert.** Polling "the focus is inside the trap" before reading it passes
 *    instantly if the focus never moved — which is the bug the check exists to
 *    catch. Poll `activeElement !== before` instead.
 *  - a wait that falls short rarely reports itself as a wait. The page is in the
 *    wrong state, the next line indexes into an empty collection, and the driver
 *    dies with every remaining check unrun — which the sweep reports as "aborted
 *    after N check(s)", not as a timing problem.
 *
 * About 230 blind sleeps still sit directly before a check in the older drivers.
 * They are converted as they bite rather than in one sweep: what to wait *for*
 * is per-driver semantics, and a mechanical rewrite would quietly assert the
 * wrong thing.
 *
 * **This is not a substitute for the anti-throttling flags above.** Alpine's
 * enter/leave transitions run on requestAnimationFrame, which headless Chrome
 * barely schedules for a backgrounded window — evaluating from CDP wakes the JS
 * thread but does not make rAF tick. Polling fixes *when we look*; the flags
 * fix *whether the page runs at all*.
 */
export async function until(probe, { timeout = 10000, interval = 100 } = {}) {
  const deadline = Date.now() + timeout;
  let last;

  for (;;) {
    last = await probe();

    if (last) return last;
    if (Date.now() >= deadline) return last;

    await sleep(interval);
  }
}

/**
 * Boot a page and hand back everything a driver needs to drive it.
 *
 * Honours CHROME_PORT (the sweep gives each driver its own DevTools port —
 * sharing one means the second Chrome silently attaches to the first), CHROME_BIN
 * and SHOT_DIR.
 */
export async function openPage({ url, shotPrefix, width = 1200, height = 1200, mobile = false, settle = 3000 }) {
  const chromeBin = process.env.CHROME_BIN
    ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
  const devtoolsPort = Number(process.env.CHROME_PORT ?? 9335);
  const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), `wire-${shotPrefix}-shots`);
  await mkdir(shotDir, { recursive: true });

  const userDataDir = join(tmpdir(), `wire-${shotPrefix}-${Date.now()}`);
  const chrome = spawn(chromeBin, [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    '--hide-scrollbars',
    // Headless Chrome backgrounds a window it never shows and throttles the renderer.
    // Without these, requestAnimationFrame barely runs: an Alpine enter/leave
    // transition never finishes, so a modal that has already been told to close sits
    // there with `show: false` and `display: block` and the driver reports a bug that
    // does not exist. Waking the JS thread (a CDP evaluate, i.e. polling) does NOT
    // cover this — rAF needs the flags.
    '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows',
    '--disable-renderer-backgrounding',
    `--remote-debugging-port=${devtoolsPort}`,
    `--user-data-dir=${userDataDir}`, 'about:blank',
  ], { stdio: 'ignore' });

  const consoleErrors = [];
  const badResponses = [];

  const wsUrl = await waitForDevtools(devtoolsPort);
  const cdp = await connect(wsUrl);
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

  cdp.on((msg) => {
    if (msg.method === 'Runtime.exceptionThrown') {
      consoleErrors.push(msg.params?.exceptionDetails?.exception?.description ?? 'exception');
    }
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params?.type === 'error') {
      consoleErrors.push((msg.params.args ?? []).map((a) => a.value ?? a.description ?? '').join(' '));
    }
    if (msg.method === 'Network.responseReceived') {
      const status = msg.params?.response?.status;
      if (status >= 400) badResponses.push(`${status} ${msg.params.response.url}`);
    }
  });

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Network.enable');
  await page('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
  await page('Page.navigate', { url });
  await sleep(settle);

  const close = async () => {
    cdp?.close();
    chrome.kill('SIGTERM');
    await rm(userDataDir, { recursive: true, force: true }).catch(() => {});
  };

  /**
   * Poll a JS expression in the page until it is truthy.
   *
   * The ergonomic half of {@see until}: what a driver almost always waits on is
   * something the page can answer for itself — a row count, a class, an open
   * panel, a request having landed.
   *
   *     await waitFor('document.querySelectorAll("tbody tr").length === 3')
   */
  const waitFor = (expression, options) => until(() => eval_(expression), options);

  return { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close };
}

/** A check recorder that prints as it goes and can report the verdict. */
export function checker() {
  const results = [];

  const check = (name, ok, detail = '') => {
    results.push({ name, ok, detail });
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
  };

  /**
   * Print the summary the sweep greps for, and set the exit code.
   *
   * Always asserts a clean console and no 419: a driver that renders the right
   * markup over a broken Livewire roundtrip has not verified anything.
   */
  const finish = ({ consoleErrors = [], badResponses = [], shotDir } = {}) => {
    check('no 419 on any roundtrip', ! badResponses.some((r) => r.startsWith('419')), badResponses.join('; ') || 'none');
    check('no console/runtime errors', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

    console.log(`\nSummary: ${results.filter((r) => r.ok).length}/${results.length} checks passed`);
    if (shotDir) console.log(`Screenshots: ${shotDir}`);
    if (badResponses.length) console.log(`Non-2xx responses: ${badResponses.join('; ')}`);
    if (results.some((r) => ! r.ok)) process.exitCode = 1;
  };

  return { check, finish, results };
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
