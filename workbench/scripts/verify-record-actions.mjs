import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver verifying record actions in the workbench preview
 * (/previews/table-record-actions): the delegated wireRecordActions controller,
 * double-click → the primary action's modal, right-click → the rebuilt context
 * menu, the interactive-element guard, and keyboard navigation (roving tabindex,
 * arrows, Enter → primary).
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-record-actions.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-record-actions';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9335);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-record-actions-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-record-actions-verify-${Date.now()}`);
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
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 900, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.tbody = () => $q('tbody[x-data^="wireRecordActions"]');
    window.ctrl = () => Alpine.$data(tbody());
    window.rows = () => [...tbody().children].filter(el => el.matches('tr[data-row-key]'));
    window.activeRow = () => rows().find(r => r.getAttribute('tabindex') === '0') || rows()[0];
    window.fireKey = (row, key, opts) => row.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...(opts||{}) }));
    true;
  `;
  await eval_(helpers);

  // ── 0. Boot: delegated controller, grid, roving tabindex, no per-row menu ──
  const boot = JSON.parse(await eval_(`JSON.stringify({
    alpine: typeof Alpine !== 'undefined',
    grid: !!$q('table[role="grid"]'),
    controller: !!tbody(),
    rowCount: rows().length,
    tabindexes: rows().map(r => r.getAttribute('tabindex')),
    panels: $qa('[data-record-menu]').length,
    perRowContextMenu: !!$q('[x-data*="wireContextMenu"]'),
    cursor: rows()[0] ? getComputedStyle(rows()[0]).cursor : null,
    kb: ctrl().kb ? { primary: ctrl().kb.primary, selectable: ctrl().kb.selectable } : null,
  })`));
  check('page boots with Alpine + delegated controller on tbody', boot.alpine && boot.controller);
  check('table announces role="grid" with focusable rows', boot.grid && boot.tabindexes[0] === '0' && boot.tabindexes.slice(1).every(t => t === '-1'), JSON.stringify(boot.tabindexes));
  check('one teleported menu panel per row, no per-row wireContextMenu', boot.panels === boot.rowCount && !boot.perRowContextMenu, `panels=${boot.panels} rows=${boot.rowCount}`);
  check('clickable rows show a pointer cursor', boot.cursor === 'pointer', boot.cursor);
  check('keyboard config resolved (primary=open, selectable)', boot.kb?.primary === 'open' && boot.kb?.selectable === true, JSON.stringify(boot.kb));
  await shot('01-boot');

  // ── 1. Guard: interactive elements inside the row are inert ────────────────
  const guard = JSON.parse(await eval_(`(() => {
    const row = rows()[0];
    const checkbox = row.querySelector('[role="checkbox"], [data-testid="table-row-select"]');
    const editBtn = [...row.querySelectorAll('button, a')].find(b => /Edit/.test(b.innerText));
    const cell = row.querySelector('[data-testid="table-cell-name"]');
    const mk = (t) => ({ target: t, clientX: 0, clientY: 0 });
    return JSON.stringify({
      checkbox: ctrl().blocked(mk(checkbox), row),
      editBtn: ctrl().blocked(mk(editBtn), row),
      cell: ctrl().blocked(mk(cell), row),
    });
  })()`));
  check('guard blocks checkbox + action button, allows the empty cell', guard.checkbox && guard.editBtn && !guard.cell, JSON.stringify(guard));

  // ── 2. Double-click a row → the primary action's confirmation modal ────────
  await eval_(`(() => { const c = rows()[0].querySelector('[data-testid="table-cell-name"]'); c.dispatchEvent(new MouseEvent('dblclick', { bubbles: true, cancelable: true })); })()`);
  await sleep(2000);
  const dbl = JSON.parse(await eval_(`JSON.stringify({ heading: (document.body.innerText.match(/Opened [\\w ]+/) || [null])[0], desc: document.body.innerText.includes('Double-clicking the row opened') })`));
  check('double-click opens the primary action modal', !!dbl.heading && dbl.desc, dbl.heading);
  await shot('02-dblclick-modal');

  // Close the modal.
  await eval_(`$qa('button').find(b => b.innerText.trim() === 'Cancel' || b.innerText.trim() === 'Zrušit')?.click()`);
  await sleep(1500);

  // ── 3. Right-click a row → the rebuilt context menu, positioned ────────────
  await eval_(`(() => { const c = rows()[1].querySelector('[data-testid="table-cell-name"]'); c.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 320, clientY: 260 })); })()`);
  await sleep(400);
  const menu = JSON.parse(await eval_(`(() => {
    const shown = $qa('[data-record-menu]').filter(p => p.style.display !== 'none');
    return JSON.stringify({ count: shown.length, key: shown[0]?.dataset.recordMenu, left: shown[0]?.style.left, items: shown[0] ? shown[0].innerText.replace(/\\s+/g,' ').trim() : null });
  })()`));
  check('right-click opens the context menu for that row, positioned', menu.count === 1 && !!menu.left, JSON.stringify({ count: menu.count, left: menu.left }));
  check('context menu carries the row actions', /View/.test(menu.items || '') && /Delete/.test(menu.items || ''), menu.items);
  await shot('03-contextmenu');

  // Close the menu (a document click).
  await eval_(`document.body.click()`);
  await sleep(300);

  // ── 4. Keyboard: arrows move the active row, Enter runs the primary ────────
  await eval_(`rows()[0].focus()`);
  await eval_(`fireKey(activeRow(), 'ArrowDown'); fireKey(activeRow(), 'ArrowDown');`);
  const nav = JSON.parse(await eval_(`JSON.stringify({ activeKey: ctrl().activeKey, activeIdx: rows().findIndex(r => r.getAttribute('tabindex') === '0'), highlighted: rows().findIndex(r => r.classList.contains('bg-primary-100')) })`));
  check('ArrowDown moves the active row (roving tabindex + highlight)', nav.activeIdx === 2 && nav.highlighted === 2, JSON.stringify(nav));
  await shot('04-keyboard-active');

  await eval_(`fireKey(activeRow(), 'Enter')`);
  await sleep(2000);
  const enter = JSON.parse(await eval_(`JSON.stringify({ heading: (document.body.innerText.match(/Opened [\\w ]+/) || [null])[0] })`));
  check('Enter runs the primary action on the active row', !!enter.heading, enter.heading);
  await shot('05-enter-modal');
  await eval_(`$qa('button').find(b => b.innerText.trim() === 'Cancel' || b.innerText.trim() === 'Zrušit')?.click()`);
  await sleep(1500);

  // ── 5. Space toggles the active row's selection ────────────────────────────
  await eval_(`rows()[0].focus(); ctrl().activeKey = rows()[0].dataset.rowKey;`);
  await eval_(`fireKey(rows()[0], ' ')`);
  await sleep(1200);
  const spaceSel = JSON.parse(await eval_(`(() => { const s = Alpine.$data($q('[data-selection-root]')); return JSON.stringify({ selected: [...s.selected], anchor: ctrl().anchorKey }); })()`));
  check('Space selects the active row and sets the anchor', spaceSel.selected.length === 1 && spaceSel.anchor === spaceSel.selected[0], JSON.stringify(spaceSel));

  // ── 6. Shift+ArrowDown extends a contiguous selection range from the anchor ─
  await eval_(`fireKey(activeRow(), 'ArrowDown', { shiftKey: true }); fireKey(activeRow(), 'ArrowDown', { shiftKey: true });`);
  await sleep(1200);
  const range = JSON.parse(await eval_(`(() => { const s = Alpine.$data($q('[data-selection-root]')); return JSON.stringify({ selected: [...s.selected], activeIdx: rows().findIndex(r => r.getAttribute('tabindex') === '0') }); })()`));
  check('Shift+ArrowDown extends the selection to a 3-row range', range.selected.length === 3 && range.activeIdx === 2, JSON.stringify(range));
  await shot('06-shift-range');

  // ── 7. Ctrl/⌘+A selects every row on the page ──────────────────────────────
  await eval_(`fireKey(activeRow(), 'a', { ctrlKey: true })`);
  await sleep(1200);
  const all = JSON.parse(await eval_(`(() => { const s = Alpine.$data($q('[data-selection-root]')); return JSON.stringify({ selected: s.selected.length, rows: rows().length }); })()`));
  check('Ctrl/Cmd+A selects every row on the page', all.selected === all.rows, JSON.stringify(all));
  await shot('07-select-all');

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
