import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Characterization CDP driver for the table selection gestures (C1–C13 of the
 * rollout plan): what the checkboxes, Space/Shift+arrow/mod+A, the record
 * actions, the context menu, the bulk bar and the mobile cards do TODAY.
 *
 * Three checks describe behaviour that later steps deliberately flip — their
 * assertions are reversed there, consciously, never patched to stay green:
 *   C3  Shift+arrow after mod+A REPLACES the selection with the shrunk block
 *       (becomes base ∪ range in step 16)
 *   C9  in `all` mode Shift+arrow drops the selection to a page block and
 *       rewrites `mode` (step 15)
 *   C10 the header checkbox in `all` mode collapses the selection to the
 *       exceptions' complement (step 10)
 *
 * Environment facts this driver is built around (verified against real Chrome):
 *   - viewport pinned to 1400×1200 — pageStep derives from window.innerHeight
 *   - 40 rows make the document scroll; coordinates are measured right before
 *     every real Input.dispatch* call, never cached
 *   - real modifier clicks use the CDP bitmask { alt:1, ctrl:2, meta:4,
 *     shift:8 }; on macOS `mod` must be meta — a real ctrl+click is the
 *     secondary click there and Chrome swallows the `click` event entirely
 *   - a drag is mousePressed → N× mouseMoved with buttons:1 → mouseReleased
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-selection-gestures.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9338);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-selection-gestures-shots');
await mkdir(shotDir, { recursive: true });

// CDP Input.dispatch* modifier bitmask (additive).
const MOD = { alt: 1, ctrl: 2, meta: 4, shift: 8 };

const userDataDir = join(tmpdir(), `wire-selection-gestures-${Date.now()}`);
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

  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.tbody = () => $q('tbody[x-data^="wireRecordActions"]');
    window.ctrl = () => Alpine.$data(tbody());
    window.rows = () => [...($q('tbody[x-data]') ?? $q('table tbody')).children].filter(el => el.matches('tr[data-row-key]'));
    window.key = (i) => rows()[i].dataset.rowKey;
    window.cell = (i) => rows()[i].querySelector('[data-testid="table-cell-name"]');
    window.clickRow = (i) => cell(i).dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    window.fireKey = (el, key, opts) => el.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...(opts||{}) }));
    window.sel = () => Alpine.$data($q('[data-selection-root]'));
    window.vis = (el) => !!el && el.getClientRects().length > 0;
    window.cancelModal = () => $qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()))?.click();
    true;
  `;
  // Real mouse click at an element's current position — measured now, because
  // the document scrolls (40 rows) and focus() moves the viewport.
  const realClick = async (expr, modifiers = 0) => {
    const box = JSON.parse(await eval_(`(() => {
      const el = ${expr};
      el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await sleep(150);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: box.x, y: box.y, button: 'left', clickCount: 1, modifiers });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: box.x, y: box.y, button: 'left', clickCount: 1, modifiers });
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });

  // ════ Section 1 — one page of 40 rows ═══════════════════════════════════
  await page('Page.navigate', { url: `${base}/table-selection-gestures` });
  await sleep(3000);
  await eval_(helpers);

  check('the gesture fixture renders all 40 rows on one page', await eval_('rows().length') === 40);

  // ── C1: a checkbox click toggles the row and anchors the next range ──────
  await realClick(`rows()[2].querySelector('[data-testid="table-row-select"]')`);
  await sleep(500);
  const c1a = JSON.parse(await eval_(`JSON.stringify({ selected: sel().selected, anchor: ctrl().anchorKey, key: key(2) })`));
  check('C1: a checkbox click selects the row and sets the anchor',
    c1a.selected.length === 1 && c1a.selected[0] === c1a.key && c1a.anchor === c1a.key,
    JSON.stringify(c1a));

  await realClick(`rows()[2].querySelector('[data-testid="table-row-select"]')`);
  await sleep(500);
  check('C1: a second checkbox click deselects the row',
    await eval_('sel().selected.length') === 0);

  // ── C2: Shift+arrow ranges from the anchor Space set ─────────────────────
  await eval_(`rows()[4].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[4], ' ')`);
  await sleep(300);
  await eval_(`fireKey(rows()[4], 'ArrowDown', { shiftKey: true })`);
  await sleep(300);
  const c2 = JSON.parse(await eval_(`JSON.stringify({ selected: sel().selected, k4: key(4), k5: key(5), mode: sel().mode })`));
  check('C2: Space anchors, Shift+ArrowDown selects the [anchor…active] block',
    c2.selected.length === 2 && c2.selected.includes(c2.k4) && c2.selected.includes(c2.k5) && c2.mode === 'keys',
    JSON.stringify(c2));

  // ── C5: Space toggles the active row ──────────────────────────────────────
  await eval_(`fireKey(rows()[5], ' ')`);
  await sleep(300);
  const c5 = JSON.parse(await eval_(`JSON.stringify({ selected: sel().selected, k4: key(4) })`));
  check('C5: Space toggles the active row back out of the selection',
    c5.selected.length === 1 && c5.selected[0] === c5.k4,
    JSON.stringify(c5));

  // ── C6: a plain arrow moves the marker but never the selection ────────────
  await eval_(`fireKey(rows()[5], 'ArrowDown')`);
  await sleep(300);
  const c6 = JSON.parse(await eval_(`JSON.stringify({ selected: sel().selected, active: ctrl().activeKey, k6: key(6) })`));
  check('C6: a plain ArrowDown moves the active row and leaves the selection alone',
    c6.selected.length === 1 && c6.active === c6.k6,
    JSON.stringify(c6));

  // ── C4: mod+A selects the page ────────────────────────────────────────────
  await eval_(`fireKey(rows()[6], 'a', { ctrlKey: true })`);
  await sleep(400);
  const c4 = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, mode: sel().mode })`));
  check('C4: mod+A selects the whole page in keys mode',
    c4.count === 40 && c4.mode === 'keys',
    JSON.stringify(c4));

  // ── C3: Shift+arrow after mod+A shrinks the block by one ─────────────────
  // Characterizes REPLACE semantics: the selection becomes exactly the
  // [far-edge…active] block. Step 16 flips this to base ∪ range.
  await eval_(`rows()[0].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[0], 'ArrowDown', { shiftKey: true })`);
  await sleep(300);
  const c3 = await eval_('sel().selected.length');
  check('C3: Shift+ArrowDown after mod+A shrinks the block to 39 instead of collapsing it',
    c3 === 39, `40 → ${c3}`);
  await shot('01-desktop-range');

  // ── C13: keys inside the row belong to the focused element ───────────────
  // Focusing the checkbox adopts its row as active (onRowFocus) — that part is
  // by design. The guard under test is the keystroke: an arrow fired from the
  // focused checkbox must not advance the marker to the next row.
  const c13 = JSON.parse(await eval_(`(() => {
    const box = rows()[5].querySelector('[role="checkbox"]');
    box.focus();
    const before = ctrl().activeKey;
    box.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true }));
    return JSON.stringify({ before, after: ctrl().activeKey, row: key(5) });
  })()`));
  check('C13: an arrow key fired from a focused checkbox does not reach the grid',
    c13.before === c13.row && c13.after === c13.before, JSON.stringify(c13));

  await eval_(`sel().deselectAll()`);
  await sleep(300);

  // ── C7: Enter / double-click run the primary action, Delete the onKey() ──
  await eval_(`rows()[1].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[1], 'Enter')`);
  await sleep(2500);
  const enter = await eval_(`(document.body.innerText.match(/Opened Record \\d+/) || [null])[0]`);
  check('C7: Enter runs the primary (double-click) record action', !!enter, enter);
  await eval_('cancelModal()');
  await sleep(1500);

  await eval_(`cell(3).dispatchEvent(new MouseEvent('dblclick', { bubbles: true, cancelable: true }))`);
  await sleep(2500);
  const dbl = await eval_(`(document.body.innerText.match(/Opened Record \\d+/) || [null])[0]`);
  check('C7: a double-click runs the primary record action', !!dbl, dbl);
  await eval_('cancelModal()');
  await sleep(1500);

  await eval_(`fireKey(rows()[3], 'Delete')`);
  await sleep(2500);
  const del = await eval_(`(document.body.innerText.match(/Archive Record \\d+\\?/) || [null])[0]`);
  check('C7: Delete fires the onKey() record action against the active row', !!del, del);
  await eval_('cancelModal()');
  await sleep(1500);

  // ── C8: right-click opens the row context menu ───────────────────────────
  await eval_(`cell(5).dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, clientX: 400, clientY: 400 }))`);
  await sleep(500);
  const menuOpen = await eval_(`$qa('[data-record-menu]').some(m => vis(m))`);
  check('C8: right-click opens the row context menu', menuOpen);
  await shot('02-context-menu');
  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await sleep(300);
  check('C8: Escape closes the context menu', ! await eval_(`$qa('[data-record-menu]').some(m => vis(m))`));

  // ── C12: the bulk bar shows the count and all four bulk actions ──────────
  await eval_(`sel().toggle(key(10)); sel().toggle(key(11)); sel().toggle(key(12));`);
  await sleep(500);
  const c12 = JSON.parse(await eval_(`(() => {
    const bar = $q('[data-testid="table-bulk-bar"]');
    return JSON.stringify({
      visible: vis(bar),
      count: bar?.querySelector('.rounded-full span')?.innerText.trim(),
      labels: ['Activate', 'Archive', 'Export selected', 'Delete'].filter(l => bar?.innerText.includes(l)),
    });
  })()`));
  check('C12: the bulk bar shows the count and the four bulk actions',
    c12.visible && c12.count === '3' && c12.labels.length === 4,
    JSON.stringify(c12));
  await shot('03-bulk-bar');

  // ════ Section 1b — selection-only: the grid works without record actions ═
  await page('Page.navigate', { url: `${base}/table-selection-only` });
  await sleep(3000);
  await eval_(helpers);

  check('selection-only: the delegated controller mounts without record actions',
    await eval_('!!tbody() && rows().length === 40'));

  await eval_(`rows()[2].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[2], ' ')`);
  await sleep(300);
  await eval_(`fireKey(rows()[2], 'ArrowDown', { shiftKey: true })`);
  await sleep(300);
  const soSel = JSON.parse(await eval_(`JSON.stringify({ selected: sel().selected.length, active: ctrl().activeKey, k3: key(3) })`));
  check('selection-only: Space and Shift+arrow drive the selection',
    soSel.selected === 2 && soSel.active === soSel.k3, JSON.stringify(soSel));

  await eval_(`fireKey(rows()[3], 'Enter')`);
  await sleep(800);
  check('selection-only: Enter is a quiet no-op with no primary action',
    ! await eval_(`$qa('[role="dialog"]').some(d => vis(d))`));

  await eval_(`clickRow(7)`);
  await sleep(300);
  const soClick = JSON.parse(await eval_(`JSON.stringify({ active: ctrl().activeKey, k7: key(7), selected: sel().selected.length })`));
  check('selection-only: a row click marks the row and never ticks the checkbox',
    soClick.active === soClick.k7 && soClick.selected === 2, JSON.stringify(soClick));
  await shot('03b-selection-only');

  // ════ Section 2 — 20 a page, the `all` mode ═════════════════════════════
  await page('Page.navigate', { url: `${base}/table-selection-gestures-paged` });
  await sleep(3000);
  await eval_(helpers);

  await eval_(`$q('[data-testid="table-select-all"]').click()`);
  await sleep(600);
  const pageSel = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, escalate: vis($q('[data-testid="table-select-all-matching"]')) })`));
  check('the header checkbox selects the page and offers the all-matching escalation',
    pageSel.count === 20 && pageSel.escalate, JSON.stringify(pageSel));

  await eval_(`$q('[data-testid="table-select-all-matching"]').click()`);
  await sleep(2000);
  const allMode = JSON.parse(await eval_(`JSON.stringify({ mode: sel().mode, count: sel().selectedCount })`));
  check('the escalation enters all mode covering every matching record',
    allMode.mode === 'all' && allMode.count === 40, JSON.stringify(allMode));
  await shot('04-all-mode');

  // ── C9: in `all` mode Shift+arrow drops the selection to a page block ────
  // Characterizes the BUG step 15 fixes: selectRange rewrites `mode` to
  // 'keys', so 40 matching records collapse to the 17-row block between the
  // fallback far-edge anchor (row 20) and the moved active row (row 4).
  await eval_(`clickRow(2)`);
  await sleep(300);
  await eval_(`fireKey(rows()[2], 'ArrowDown', { shiftKey: true })`);
  await sleep(400);
  const c9 = JSON.parse(await eval_(`JSON.stringify({ mode: sel().mode, count: sel().selected.length, shown: sel().selectedCount })`));
  check('C9: in all mode Shift+ArrowDown rewrites mode and drops 40 → a 17-row page block (bug, flips in step 15)',
    c9.mode === 'keys' && c9.count === 17 && c9.shown === 17,
    JSON.stringify(c9));

  // ── C10: the header checkbox in `all` mode inverts the selection ─────────
  // Characterizes the BUG step 10 fixes: `clear` is true, mode is forced to
  // 'keys' and `selected` keeps only off-page exceptions — one click turns
  // "everything but Record 01" into nothing (or, with off-page exceptions,
  // into exactly the rows the user unchecked).
  await page('Page.navigate', { url: `${base}/table-selection-gestures-paged` });
  await sleep(3000);
  await eval_(helpers);
  await eval_(`$q('[data-testid="table-select-all"]').click()`);
  await sleep(600);
  await eval_(`$q('[data-testid="table-select-all-matching"]').click()`);
  await sleep(2000);
  await eval_(`rows()[0].querySelector('[data-testid="table-row-select"]').click()`);
  await sleep(500);
  const excluded = JSON.parse(await eval_(`JSON.stringify({ mode: sel().mode, exceptions: sel().selected, shown: sel().selectedCount })`));
  check('unchecking one row in all mode records an exception (39 of 40)',
    excluded.mode === 'all' && excluded.exceptions.length === 1 && excluded.shown === 39,
    JSON.stringify(excluded));

  await eval_(`$q('[data-testid="table-select-all"]').click()`);
  await sleep(600);
  const c10 = JSON.parse(await eval_(`JSON.stringify({ mode: sel().mode, count: sel().selected.length, shown: sel().selectedCount })`));
  check('C10: the header checkbox in all mode collapses 39 selected to none (bug, flips in step 10)',
    c10.mode === 'keys' && c10.count === 0 && c10.shown === 0,
    JSON.stringify(c10));
  await shot('05-toggleall-collapse');

  // ── matching follows the filter through the morph ────────────────────────
  // The selection root is morphed in place (wire:key="table-wrapper"), so a
  // count seeded into x-data would stay at the first render's value forever;
  // it must be read from the DOM the morph refreshes.
  await page('Page.navigate', { url: `${base}/table-selection-gestures-paged` });
  await sleep(3000);
  await eval_(helpers);
  await eval_(`(() => { const i = $q('[data-testid="table-search"]'); i.value = 'Record 0'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  await sleep(3000);
  await eval_(`$q('[data-testid="table-select-all"]').click()`);
  await sleep(600);
  const narrowed = JSON.parse(await eval_(`JSON.stringify({ rows: rows().length, matching: sel().matching, shown: sel().selectedCount })`));
  check('the matching count follows a filter change instead of staying baked in',
    narrowed.rows === 9 && narrowed.matching === 9 && narrowed.shown === 9,
    JSON.stringify(narrowed));

  // ════ Section 3 — mobile cards (C11) ════════════════════════════════════
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url: `${base}/table-selection-gestures` });
  await sleep(3000);
  await eval_(helpers);

  const cards = JSON.parse(await eval_(`JSON.stringify({
    cards: $qa('[data-testid="table-card"]').length,
    strip: vis($q('[data-testid="table-card-select-all"]')),
    countLine: vis($q('[data-testid="table-card-select-count"]')),
  })`));
  check('C11: the card view renders every record with the always-visible select-all strip',
    cards.cards === 40 && cards.strip && cards.countLine, JSON.stringify(cards));

  await eval_(`$qa('[data-testid="table-card-select"]')[0].click()`);
  await sleep(400);
  const cardToggle = JSON.parse(await eval_(`JSON.stringify({
    selected: sel().selected.length,
    checked: $qa('[data-testid="table-card-select"]')[0].checked,
    count: $q('[data-testid="table-card-select-count"]').innerText.trim(),
  })`));
  check('C11: tapping a card checkbox selects the card and updates the count',
    cardToggle.selected === 1 && cardToggle.checked && /1/.test(cardToggle.count),
    JSON.stringify(cardToggle));

  await eval_(`$q('[data-testid="table-card-select-all"]').click()`);
  await sleep(500);
  const stripAll = JSON.parse(await eval_(`JSON.stringify({
    shown: sel().selectedCount,
    aria: $q('[data-testid="table-card-select-all"]').getAttribute('aria-checked'),
    everyChecked: $qa('[data-testid="table-card-select"]').every(c => c.checked),
  })`));
  check('C11: the select-all strip selects every card on the page',
    stripAll.shown === 40 && stripAll.aria === 'true' && stripAll.everyChecked,
    JSON.stringify(stripAll));
  await shot('06-mobile-cards');

  await eval_(`$q('[data-testid="table-card-select-all"]').click()`);
  await sleep(500);
  check('C11: a second strip tap clears the page again',
    await eval_('sel().selectedCount') === 0);

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
