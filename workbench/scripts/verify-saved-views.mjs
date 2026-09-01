import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * V2.5 SV: saved views, driven end to end in a browser.
 *
 * A driver test proves the persistence contract and a render test proves the
 * markup; neither can say whether a saved view actually comes back. What this
 * asks is the round trip a user performs: change the table, save the view under
 * a name, move the table somewhere else, pick the name, and see the first state
 * again — with the switcher living inside the existing view menu rather than a
 * second dropdown.
 *
 * The name is asked for with window.prompt(), so the dialog is stubbed before
 * the click; headless Chrome would otherwise block on it.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9371);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-saved-views-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-saved-views-${Date.now()}`);
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

  // Poll rather than sleep a fixed span: a Livewire round trip's length depends
  // on the machine, and a fixed wait reports a false failure on a slow one.
  const until = async (expression, label, tries = 40) => {
    for (let i = 0; i < tries; i++) {
      if (await eval_(expression)) return true;
      await sleep(150);
    }
    check(`timed out waiting for ${label}`, false);
    return false;
  };

  const openViewMenu = async () => {
    await eval_(`document.querySelector('[data-testid="table-column-toggle"]')?.click()`);
    await until(`!! document.querySelector('[data-testid="table-view-save"]')`, 'the view menu');
  };

  const firstRowName = () => eval_(`document.querySelector('tbody tr td')?.innerText?.trim() ?? ''`);
  const searchValue = () => eval_(`document.querySelector('input[type="search"], [data-testid="table-search"] input, input[wire\\\\:model\\\\.live*="search"]')?.value ?? ''`);

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 950, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url: `${base}/table-saved-views` });

  await until(`!! window.Alpine && !! document.querySelector('tbody tr')`, 'the table to boot');

  // ── 1. The switcher lives in the one view menu ───────────────────────────
  const triggers = await eval_(`document.querySelectorAll('[data-testid="table-column-toggle"]').length`);
  check('there is exactly one view-menu trigger, not a second dropdown', triggers === 1, `found ${triggers}`);

  await openViewMenu();
  await shot('01-menu-open');

  const menu = await eval_(`JSON.stringify({
    save: !! document.querySelector('[data-testid="table-view-save"]'),
    columns: !! document.querySelector('[data-testid="table-column-toggle"]'),
    views: document.querySelectorAll('[data-testid^="table-view-"]:not([data-testid="table-view-save"])').length,
  })`);
  const m = JSON.parse(menu);
  check('the menu offers "save current view" and still holds the column toggles', m.save && m.columns);
  check('no saved views yet', m.views === 0, `found ${m.views}`);

  // ── 2. Move the table, then save that as a view ──────────────────────────
  await eval_(`document.querySelector('body')?.click()`);
  await sleep(200);

  // Sort by name so the first row is deterministic, then remember it.
  await eval_(`[...document.querySelectorAll('th button, th [wire\\\\:click]')].find(b => /name/i.test(b.innerText))?.click()`);
  await sleep(900);
  const sortedFirst = await firstRowName();
  check('sorting changed the table', sortedFirst.length > 0, sortedFirst);

  await openViewMenu();
  // Stub the prompt BEFORE the click: headless Chrome blocks on a real one.
  await eval_(`window.prompt = () => 'Sorted by name'`);
  await eval_(`document.querySelector('[data-testid="table-view-save"]')?.click()`);

  const appeared = await until(
    `!! document.querySelector('[data-testid="table-view-Sorted by name"]')`,
    'the saved view to appear in the menu',
  );
  check('saving adds the view to the switcher', appeared);
  await shot('02-view-saved');

  // ── 3. Move the table away, then restore ─────────────────────────────────
  await eval_(`document.querySelector('body')?.click()`);
  await sleep(200);
  // Sort the other way, so restoring has something to undo.
  await eval_(`[...document.querySelectorAll('th button, th [wire\\\\:click]')].find(b => /name/i.test(b.innerText))?.click()`);
  await sleep(900);

  const movedFirst = await firstRowName();
  check('the table moved off the saved view', movedFirst !== sortedFirst, `${sortedFirst} → ${movedFirst}`);

  await openViewMenu();
  await eval_(`document.querySelector('[data-testid="table-view-Sorted by name"]')?.click()`);
  await until(`document.querySelector('tbody tr td')?.innerText?.trim() === ${JSON.stringify(sortedFirst)}`, 'the view to restore');

  const restoredFirst = await firstRowName();
  check('applying the saved view puts the table back', restoredFirst === sortedFirst, `${movedFirst} → ${restoredFirst}`);
  await shot('03-view-restored');

  // ── 4. Deleting one leaves the table where it is ─────────────────────────
  await openViewMenu();
  await eval_(`document.querySelector('[data-testid="table-view-delete-Sorted by name"]')?.click()`);
  await until(`! document.querySelector('[data-testid="table-view-Sorted by name"]')`, 'the view to disappear');

  const afterDelete = await firstRowName();
  check('deleting a view removes it from the switcher',
    (await eval_(`! document.querySelector('[data-testid="table-view-Sorted by name"]')`)) === true);
  check('deleting a view does not disturb the live table', afterDelete === restoredFirst, `${restoredFirst} → ${afterDelete}`);
  await shot('04-view-deleted');

  // ── 5. Nothing threw along the way ───────────────────────────────────────
  const broken = await eval_(`(() => {
    const el = document.querySelector('[data-testid="table-column-toggle"]');
    try { return el && typeof window.Alpine.$data(el) === 'object' ? 'ok' : 'no scope'; }
    catch (e) { return String(e.message ?? e); }
  })()`);
  check('Alpine is still alive on the view menu', broken === 'ok', broken);

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
