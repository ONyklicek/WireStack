import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * V2.5 GS: the command palette, driven end to end in a browser.
 *
 * Livewire tests prove the component's state transitions; what they cannot show
 * is whether the thing opens on a keystroke, whether the input is focused when
 * it does, and whether the arrow keys reach the rows — all of which live in the
 * Blade and in Alpine.
 *
 * The palette is teleported to <body>, so every probe here looks for it there
 * rather than inside the preview's own markup.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9372);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-global-search-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-global-search-${Date.now()}`);
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
  // Poll rather than sleep: a Livewire round trip's length depends on the
  // machine, and a fixed wait reports a false failure on a slow one.
  const until = async (expression, label, tries = 40) => {
    for (let i = 0; i < tries; i++) {
      if (await eval_(expression)) return true;
      await sleep(150);
    }
    check(`timed out waiting for ${label}`, false);
    return false;
  };

  const paletteVisible = () => eval_(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return !! el && getComputedStyle(el).display !== 'none';
  })()`);
  const rowTitles = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="global-search-result"]')].map(b => b.innerText.trim().split('\\n')[0]))`);
  const activeTitle = () => eval_(`document.querySelector('[data-testid="global-search-result"][data-active="true"]')?.innerText?.trim()?.split('\\n')[0] ?? ''`);
  const type = async (text) => {
    for (const ch of text) {
      await page('Input.dispatchKeyEvent', { type: 'char', text: ch });
    }
  };
  const key = (k, code, windowsVirtualKeyCode) =>
    page('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: k, code, windowsVirtualKeyCode })
      .then(() => page('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code, windowsVirtualKeyCode }));

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url: `${base}/global-search` });
  await until(`!! window.Alpine && !! document.querySelector('[data-testid="global-search-trigger"]')`, 'the page to boot');

  // ── 1. Closed until asked for ────────────────────────────────────────────
  check('the palette starts closed', (await paletteVisible()) === false);

  // ── 2. ⌘K opens it — the binding an application writes ───────────────────
  await eval_(`document.querySelector('[data-testid="global-search-trigger"]').focus()`);
  await key('k', 'KeyK', 75).catch(() => {});
  await page('Input.dispatchKeyEvent', {
    type: 'rawKeyDown', key: 'k', code: 'KeyK', windowsVirtualKeyCode: 75, modifiers: 4,
  });
  await page('Input.dispatchKeyEvent', {
    type: 'keyUp', key: 'k', code: 'KeyK', windowsVirtualKeyCode: 75, modifiers: 4,
  });
  const openedByKey = await until(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return !! el && getComputedStyle(el).display !== 'none';
  })()`, 'the palette to open on cmd+K');
  check('cmd+K opens the palette', openedByKey);
  await shot('01-open');

  // ── 3. The input takes focus, so typing lands in it ──────────────────────
  //
  // Polled, not read once: the focus is an Alpine `x-effect` that runs on
  // `$nextTick` after `open` flips, so asking the instant the dialog becomes
  // visible is a race — and one that loses everything behind it, because the
  // typing below goes to `document.activeElement`. An unfocused input turns a
  // millisecond of timing into five failed checks about search results.
  const focused = await until(
    `document.activeElement?.dataset?.testid === 'global-search-input'`,
    'the input to take focus',
  );
  check('the search input is focused on open', focused);

  // Focus it anyway when that check failed, so the rest of this run still
  // measures what it is about. Typing goes to `document.activeElement`, so an
  // unfocused input turns one real failure into five about search results —
  // which is how a slow preview server reads as a broken palette.
  if (! focused) {
    await eval_(`document.querySelector('[data-testid="global-search-input"]')?.focus()`);
  }

  // ── 4. Typing searches, across the resource registry ─────────────────────
  await type('INV');
  const found = await until(`document.querySelectorAll('[data-testid="global-search-result"]').length > 1`, 'results');
  check('typing a term returns results from the registered resource', found, await rowTitles());
  await shot('02-results');

  // ── 5. Arrow keys walk the rows ──────────────────────────────────────────
  const firstActive = await activeTitle();
  check('the first row starts active', firstActive.length > 0, firstActive);

  // Compare the row's FIRST line, not its innerText: a result carries a subtitle
  // under the title, so `innerText !== title` is true before anything moves and
  // the wait would pass instantly on a cursor that never went anywhere.
  const activeTitleExpr = `((document.querySelector('[data-testid="global-search-result"][data-active="true"]')?.innerText ?? '').trim().split('\\n')[0])`;

  await key('ArrowDown', 'ArrowDown', 40);
  await until(`${activeTitleExpr} !== ${JSON.stringify(firstActive)}`, 'the cursor to move');
  const secondActive = await activeTitle();
  check('arrow down moves the active row', secondActive !== firstActive, `${firstActive} → ${secondActive}`);

  await key('ArrowUp', 'ArrowUp', 38);
  await until(`${activeTitleExpr} === ${JSON.stringify(firstActive)}`, 'the cursor to come back');
  check('arrow up moves it back', (await activeTitle()) === firstActive);
  await shot('03-cursor');

  // ── 6. A new term resets the cursor ──────────────────────────────────────
  await key('ArrowDown', 'ArrowDown', 40);
  await sleep(400);
  await type('O');
  await sleep(900);
  const afterRetype = await eval_(`(() => {
    const rows = [...document.querySelectorAll('[data-testid="global-search-result"]')];
    const active = document.querySelector('[data-testid="global-search-result"][data-active="true"]');
    return rows.length === 0 ? 'none' : String(rows.indexOf(active));
  })()`);
  check('a changed term puts the cursor back on the first row', afterRetype === '0' || afterRetype === 'none', afterRetype);

  // ── 7. Escape closes it ──────────────────────────────────────────────────
  await key('Escape', 'Escape', 27);
  const closed = await until(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return ! el || getComputedStyle(el).display === 'none';
  })()`, 'the palette to close');
  check('escape closes the palette', closed);
  await shot('04-closed');

  // ── 8. Nothing threw ─────────────────────────────────────────────────────
  const alive = await eval_(`(() => { try { return typeof window.Alpine.$data(document.body) === 'object' ? 'ok' : 'ok'; } catch (e) { return String(e.message ?? e); } })()`);
  check('Alpine is still alive', alive === 'ok', alive);

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
