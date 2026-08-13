import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for `Table::emptyStateActions()`.
 *
 * Pest sees the markup: that the buttons are in the empty state, that their
 * wire:click names the record-less host methods, and that only one of the two
 * layouts carries the keyboard-shortcut binding. What it cannot see is whether
 * any of that WORKS in a browser — whether clicking the button in an empty
 * table actually reaches Livewire and opens the modal (the click is resolved
 * from a surface that has no record, which is the whole risk), and whether the
 * stacked-card copy is a real, tappable surface at phone width rather than
 * markup hidden behind the desktop table.
 *
 * The fixture is `table-empty-state`: a table whose query matches nothing, so
 * it is genuinely empty rather than emptied by a filter, carrying a link
 * action, an inline one and one that opens a modal form.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-empty-state-actions.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9357);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-empty-state-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-empty-state-${Date.now()}`);
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

  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.vis = (el) => !!el && el.getClientRects().length > 0;
    // Only the copy the current width actually shows.
    window.shown = (sel) => $qa(sel).filter(vis);
    window.cancelModal = () => $qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()))?.click();
    true;
  `;

  const measure = async (expr) => JSON.parse(await eval_(`(() => {
    const el = ${expr};
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
  })()`));

  const realClick = async (expr) => {
    const at = await measure(expr);
    await sleep(120);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: at.x, y: at.y, button: 'left', clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: at.x, y: at.y, button: 'left', clickCount: 1 });
  };

  await page('Page.enable');
  await page('Runtime.enable');

  // ════ Desktop ═══════════════════════════════════════════════════════════
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url: `${base}/table-empty-state` });
  await sleep(3000);
  await eval_(helpers);

  const state = JSON.parse(await eval_(`(() => JSON.stringify({
    rows: $qa('tbody tr[data-row-key]').length,
    heading: shown('h3').map(h => h.textContent.trim()),
    description: document.body.innerText.includes('Invite the first one to get started.'),
    link: shown('[data-testid="action-docs"]').length,
    modalBtn: shown('[data-testid="action-inviteFirst"]').length,
    href: $q('[data-testid="action-docs"]')?.getAttribute('href'),
    resetShown: shown('[data-testid="table-filter-reset"]').length,
  }))()`));

  check('the table is empty', state.rows === 0, `${state.rows} rows`);
  check('the custom heading and description render',
    state.heading.includes('No users yet') && state.description === true,
    JSON.stringify(state.heading));
  check('exactly one copy of each action is visible on desktop',
    state.link === 1 && state.modalBtn === 1, `link=${state.link} modal=${state.modalBtn}`);
  check('the static url action renders as a real link',
    state.href === '/docs', `href=${state.href}`);
  check('no filter reset is offered for a genuinely empty table', state.resetShown === 0);
  await shot('01-desktop-empty-state');

  // ── the click reaches Livewire from a surface with no record ─────────────
  await realClick(`shown('[data-testid="action-inviteFirst"]')[0]`);
  await sleep(2000);
  const modal = JSON.parse(await eval_(`(() => {
    const d = $qa('[role="dialog"]').filter(vis);
    return JSON.stringify({
      open: d.length,
      heading: d[0]?.innerText?.includes('Invite a user') ?? false,
      fields: d[0]?.querySelectorAll('input[type="text"], input[type="email"]').length ?? 0,
    });
  })()`));
  check('the record-less action opens its modal', modal.open === 1, `${modal.open} dialogs`);
  check('the modal carries the action form', modal.heading === true && modal.fields >= 2,
    `heading=${modal.heading} fields=${modal.fields}`);
  await shot('02-desktop-modal');

  await eval_('cancelModal()');
  await sleep(1200);
  check('the modal closes again', await eval_(`$qa('[role="dialog"]').filter(vis).length`) === 0);

  // ── the shortcut binding exists once, not once per layout ────────────────
  const bindings = await eval_(`$qa('[x-on\\\\:keydown], [data-testid^="action-"]').filter(el => [...el.attributes].some(a => a.name.startsWith('x-on:keydown'))).length`);
  check('no duplicate window keydown binding across the two layouts', bindings <= 1, `${bindings} bindings`);

  // ════ Phone ═════════════════════════════════════════════════════════════
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await sleep(1200);

  const mobile = JSON.parse(await eval_(`(() => JSON.stringify({
    tableShown: vis($q('table')),
    link: shown('[data-testid="action-docs"]').length,
    modalBtn: shown('[data-testid="action-inviteFirst"]').length,
    heading: shown('h3').map(h => h.textContent.trim()),
    icon: shown('svg').length > 0,
  }))()`));

  check('the desktop table is hidden at phone width', mobile.tableShown === false);
  check('the card empty state offers the same actions',
    mobile.link === 1 && mobile.modalBtn === 1, `link=${mobile.link} modal=${mobile.modalBtn}`);
  check('the card empty state keeps the custom heading',
    mobile.heading.includes('No users yet'), JSON.stringify(mobile.heading));
  await shot('03-mobile-empty-state');

  // And it is a real surface, not markup that happens to be in the DOM.
  await realClick(`shown('[data-testid="action-inviteFirst"]')[0]`);
  await sleep(2000);
  check('tapping the card action opens the same modal',
    await eval_(`$qa('[role="dialog"]').filter(vis).length`) === 1);
  await shot('04-mobile-modal');

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
