import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying stacked (nested) action modals in the workbench preview
 * (/previews/table-modal-nested). The invite modal auto-opens on boot; a footer
 * action ("Add a role first") stacks a second modal ("New role") on top of it.
 *
 * Checks: the parent stays behind (dimmed shell present), the active modal is
 * layered above (higher z-index), form data on the parent survives the stack,
 * and Escape pops only the top modal (back to the parent).
 *
 * Exit 0 = all passed, 1 = a check failed, 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-modal-nested';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-nested-modal-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-nested-verify-${Date.now()}`);
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
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3200);

  await eval_(`
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.setInput = (sel, value) => { const el = $q(sel); el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); };
    window.headings = () => $qa('h3').map(h => h.innerText.trim());
    true;
  `);

  // ── 0. Parent (invite) modal auto-opened ──────────────────────────────
  const parentOpen = await eval_(`headings().includes('Invite user')`);
  check('parent "Invite user" modal is open on boot', parentOpen, JSON.stringify(await eval_('headings()')));
  await shot('01-parent-open');

  // ── 1. Type into the parent form, then open the nested modal ───────────
  await eval_(`setInput('input[id*="name"]', 'Ada Lovelace')`);
  await sleep(1200);
  const footerClicked = await eval_(`
    (() => {
      const btn = $qa('button').find(b => b.innerText.includes('Add a role first'))
        || $q('[data-testid="modal-footer-action-quickRole"]');
      if (!btn) return false; btn.click(); return true;
    })()
  `);
  check('footer action "Add a role first" clicked', footerClicked === true);
  await sleep(2000);

  // ── 2. Both modals now present; nested on top, parent behind ───────────
  const stacked = await eval_(`JSON.stringify({ headings: headings() })`);
  const both = JSON.parse(stacked).headings;
  check('nested "New role" modal is stacked with the parent still present',
    both.includes('New role') && both.includes('Invite user'), stacked);
  await shot('02-stacked');

  // ── 3. Active modal layered above the base z-50 via inline z-index ─────
  const zTop = await eval_(`
    (() => {
      const els = $qa('[style*="z-index"]');
      const zs = els.map(e => parseInt(e.style.zIndex || '0', 10)).filter(n => n > 0);
      return Math.max(0, ...zs);
    })()
  `);
  check('active modal carries an elevated inline z-index (>50)', zTop > 50, `max z-index=${zTop}`);

  // A dimmed, inert suspended shell is behind the active modal.
  const suspendedShell = await eval_(`$qa('[aria-hidden="true"][inert]').length > 0 && $qa('[aria-hidden="true"][inert] .opacity-70').length > 0`);
  check('a dimmed, inert suspended parent shell is rendered behind', suspendedShell);

  // Exactly one full scrim is PAINTED — stacking parents must not compound the
  // backdrop. Counted by what the viewer sees, not by what sits in the DOM: a
  // closed modal on the same page (the table's `?` help teleports one into the
  // body too) keeps its backdrop in the document with display:none.
  const scrims = JSON.parse(await eval_(`(() => {
    const all = $qa('.bg-gray-500\\\\/75, [class*="bg-gray-500/75"]');
    const painted = all.filter(el => {
      const s = getComputedStyle(el);
      return s.display !== 'none' && s.visibility !== 'hidden' && parseFloat(s.opacity) > 0;
    });
    return JSON.stringify({ painted: painted.length, inDom: all.length });
  })()`));
  check('only one backdrop scrim is painted (no cumulative darkening)',
    scrims.painted === 1, `painted=${scrims.painted} of ${scrims.inDom} in the DOM`);

  // ── 4. Escape pops only the top modal → back to the parent ─────────────
  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await sleep(2000);
  const afterEscape = await eval_(`JSON.stringify({ headings: headings() })`);
  const back = JSON.parse(afterEscape).headings;
  check('Escape closes only the nested modal, parent remains',
    back.includes('Invite user') && !back.includes('New role'), afterEscape);
  await shot('03-back-to-parent');

  // ── 5. Parent form data survived the stack round-trip ──────────────────
  const nameVal = await eval_(`(($q('input[id*="name"]')||{}).value) || ''`);
  check('parent form value preserved through the stack', nameVal === 'Ada Lovelace', `name="${nameVal}"`);

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
