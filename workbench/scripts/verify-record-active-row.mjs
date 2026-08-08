import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver for the active-row marker: the row the pointer last
 * landed on must be visibly marked, must stay marked while the pointer hovers
 * it, must survive the Livewire roundtrip a click triggers, and must be the row
 * the arrow keys continue from. Runs against /previews/table-record-actions
 * (double-click binding, so a single click only moves the marker) and
 * /previews/table-record-actions-keyboard (Enter / Shift+Enter / Delete).
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-record-active-row.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9337);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-active-row-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-active-row-verify-${Date.now()}`);
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
    window.tbody = () => $q('tbody[x-data^="wireRecordActions"]');
    window.ctrl = () => Alpine.$data(tbody());
    window.rows = () => [...tbody().children].filter(el => el.matches('tr[data-row-key]'));
    window.cell = (i) => rows()[i].querySelector('[data-testid="table-cell-name"]');
    window.clickRow = (i) => cell(i).dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    window.fireKey = (row, key, opts) => row.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...(opts||{}) }));
    window.sel = () => Alpine.$data($q('[data-selection-root]'));
    window.marked = () => rows().findIndex(r => r.classList.contains('bg-primary-100'));
    window.bg = (i) => getComputedStyle(rows()[i]).backgroundColor;
    true;
  `;
  // Move the real cursor over a row so :hover applies (a synthetic MouseEvent
  // never does) and report the row's painted background.
  const hoverRow = async (i) => {
    const box = JSON.parse(await eval_(`(() => { const r = rows()[${i}].getBoundingClientRect(); return JSON.stringify({ x: r.left + 40, y: r.top + r.height / 2 }); })()`));
    await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x: box.x, y: box.y, button: 'none' });
    await sleep(200);
    return eval_(`bg(${i})`);
  };
  const unhover = async () => {
    await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 5, y: 5, button: 'none' });
    await sleep(200);
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 900, deviceScaleFactor: 1, mobile: false });

  // ── A. Pointer marks the row (double-click-only binding: a click does
  //       nothing else, so the marker is all that may change) ────────────────
  await page('Page.navigate', { url: `${base}/table-record-actions` });
  await sleep(3000);
  await eval_(helpers);

  check('no row is marked before the first interaction', await eval_('marked()') === -1);

  await eval_('clickRow(2)');
  await sleep(600);
  const clicked = JSON.parse(await eval_(`JSON.stringify({ marked: marked(), activeKey: ctrl().activeKey, key: rows()[2].dataset.rowKey, tabindex: rows()[2].getAttribute('tabindex') })`));
  check('a click marks the clicked row and moves the roving tabindex',
    clicked.marked === 2 && clicked.activeKey === clicked.key && clicked.tabindex === '0',
    JSON.stringify(clicked));
  await shot('01-click-marked');

  // ── B. The marker survives hover (the row's hover tint is switched off) ────
  const activeHovered = await hoverRow(2);
  const otherHovered = await hoverRow(1);
  await unhover();
  const activeResting = await eval_('bg(2)');
  check('the marked row keeps its marker under the pointer',
    activeHovered === activeResting && activeHovered !== otherHovered,
    `active hovered=${activeHovered} resting=${activeResting} other hovered=${otherHovered}`);
  check('an unmarked row still gets the normal hover tint',
    otherHovered !== await eval_('bg(1)'),
    otherHovered);
  await shot('02-hover-marked');

  // ── C. The marker survives the Livewire roundtrip / DOM morph ─────────────
  await eval_('Livewire.find(document.querySelector("[wire\\\\:id]").getAttribute("wire:id")).$refresh()');
  await sleep(2000);
  const afterMorph = JSON.parse(await eval_(`JSON.stringify({ marked: marked(), activeKey: ctrl().activeKey })`));
  check('the marker survives a Livewire morph', afterMorph.marked === 2, JSON.stringify(afterMorph));

  // ── D. Arrow keys continue from the clicked row ───────────────────────────
  await eval_(`fireKey(rows()[2], 'ArrowDown')`);
  await sleep(300);
  const afterArrow = JSON.parse(await eval_(`JSON.stringify({ marked: marked(), tabindex: rows().map(r => r.getAttribute('tabindex')) })`));
  check('ArrowDown continues from the clicked row, not from the top',
    afterArrow.marked === 3, JSON.stringify(afterArrow));
  await shot('03-arrow-from-click');

  // ── E. The keyboard preview: Enter, Shift+Enter and an onKey() binding ────
  await page('Page.navigate', { url: `${base}/table-record-actions-keyboard` });
  await sleep(3000);
  await eval_(helpers);

  check('the keyboard legend documents the arrow keys',
    await eval_(`(() => { const l = $q('[data-testid="keyboard-legend"]'); return !!l && /↑ \\/ ↓/.test(l.innerText) && /Shift \\+ Enter/.test(l.innerText); })()`),
    'legend');
  await shot('04-keyboard-legend');

  await eval_(`rows()[0].focus(); fireKey(rows()[0], 'ArrowDown');`);
  await sleep(300);
  await eval_(`fireKey(rows()[1], 'Enter')`);
  await sleep(2500);
  const primary = await eval_(`(document.body.innerText.match(/Opened [\\w ]+/) || [null])[0]`);
  check('Enter runs the primary (double-click) action on the active row', !!primary, primary);
  await eval_(`$qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()))?.click()`);
  await sleep(1500);

  await eval_(`fireKey(rows()[1], 'Enter', { shiftKey: true })`);
  await sleep(2500);
  const secondary = await eval_(`(document.body.innerText.match(/Preview of [\\w ]+/) || [null])[0]`);
  check('Shift+Enter runs the secondary (single-click) action', !!secondary, secondary);
  await eval_(`$qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()))?.click()`);
  await sleep(1500);

  await eval_(`fireKey(rows()[1], 'Delete')`);
  await sleep(2500);
  const onKey = await eval_(`(document.body.innerText.match(/Archive [\\w ]+\\?/) || [null])[0]`);
  check('Delete fires the onKey() record action against the active row', !!onKey, onKey);
  await shot('05-onkey-delete');

  // ── F. Closing the modal with the mouse must not kill the keyboard ────────
  // A real click focuses the button; hiding the modal then drops the focus to
  // <body>, where the grid's keydown listener never sees it — the arrows would
  // stop working. The controller has to hand the focus back to the active row.
  const cancel = JSON.parse(await eval_(`(() => {
    const b = $qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()));
    const r = b.getBoundingClientRect();
    return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
  })()`));
  await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: cancel.x, y: cancel.y, button: 'left', clickCount: 1 });
  await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: cancel.x, y: cancel.y, button: 'left', clickCount: 1 });
  await sleep(2500);
  const focusBack = await eval_(`document.activeElement === document.body ? 'body' : (document.activeElement.dataset?.rowKey ?? document.activeElement.tagName)`);
  check('closing the modal with the mouse hands the focus back to the active row',
    focusBack !== 'body', `activeElement=${focusBack}`);

  const before = await eval_('ctrl().activeKey');
  await page('Input.dispatchKeyEvent', { type: 'rawKeyDown', windowsVirtualKeyCode: 40, code: 'ArrowDown', key: 'ArrowDown' });
  await page('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 40, code: 'ArrowDown', key: 'ArrowDown' });
  await sleep(400);
  const after = await eval_('ctrl().activeKey');
  check('the arrow keys still work after the modal was closed', before !== after, `${before} → ${after}`);
  await shot('06-focus-after-modal');

  // ── G. The grid is inert while a modal is up ─────────────────────────────
  // Enter opens the modal but leaves the focus on the row behind it: the arrows
  // must not move the marker out of sight, and a shortcut must not fire a second
  // action against a different record than the one the modal is asking about.
  await eval_(`rows()[1].focus(); fireKey(rows()[1], 'Enter');`);
  await sleep(2500);
  const behindBefore = await eval_('ctrl().activeKey');
  await page('Input.dispatchKeyEvent', { type: 'rawKeyDown', windowsVirtualKeyCode: 40, code: 'ArrowDown', key: 'ArrowDown' });
  await page('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 40, code: 'ArrowDown', key: 'ArrowDown' });
  await page('Input.dispatchKeyEvent', { type: 'rawKeyDown', windowsVirtualKeyCode: 46, code: 'Delete', key: 'Delete' });
  await page('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 46, code: 'Delete', key: 'Delete' });
  await sleep(2000);
  const behind = JSON.parse(await eval_(`JSON.stringify({ activeKey: ctrl().activeKey, archive: /Archive /.test(document.body.innerText) })`));
  check('an open modal makes the grid inert (no arrow move, no shortcut)',
    behind.activeKey === behindBefore && ! behind.archive, JSON.stringify(behind));
  await eval_(`$qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()))?.click()`);
  await sleep(1500);

  // ── H. A keystroke inside the row belongs to the element that has it ──────
  const onButton = await eval_(`(() => {
    const b = [...rows()[0].querySelectorAll('button')].find(b => /Edit|Delete/.test(b.innerText));
    if (! b) return 'no-button';
    b.focus();
    b.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    return 'fired';
  })()`);
  await sleep(1500);
  check('Enter on a row action button does not also run the record action',
    onButton === 'fired' && ! await eval_(`/Opened /.test(document.body.innerText)`), onButton);

  // ── I. A Shift+range must not throw away a selection it did not make ─────
  // mod+A (like a checkbox or the select-all strip) sets no anchor. Without a
  // fallback anchor the first Shift+arrow replaced the whole selection with a
  // two-row range — every other row silently deselected.
  await eval_(`rows()[0].focus(); fireKey(rows()[0], 'a', { ctrlKey: true });`);
  await sleep(1500);
  const selectedAll = await eval_(`sel().selected.length`);
  await eval_(`fireKey(rows()[0], 'ArrowDown', { shiftKey: true })`);
  await sleep(1500);
  const shrunk = await eval_(`sel().selected.length`);
  check('Shift+arrow after select-all shrinks the block by one instead of collapsing it',
    selectedAll === await eval_('rows().length') && shrunk === selectedAll - 1,
    `${selectedAll} → ${shrunk} of ${await eval_('rows().length')} rows`);

  // A checkbox click is a selection gesture: it decides where the next
  // Shift+arrow range grows from, like a click in a file explorer.
  const boxOf = JSON.parse(await eval_(`(() => {
    const b = rows()[2].querySelector('[role="checkbox"]');
    const r = b.getBoundingClientRect();
    return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2, key: rows()[2].dataset.rowKey });
  })()`));
  await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: boxOf.x, y: boxOf.y, button: 'left', clickCount: 1 });
  await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: boxOf.x, y: boxOf.y, button: 'left', clickCount: 1 });
  await sleep(1200);
  check('a checkbox click anchors the next keyboard range', await eval_(`Alpine.$data($q('[data-selection-root]')).anchorKey`) === boxOf.key,
    `anchor=${await eval_(`Alpine.$data($q('[data-selection-root]')).anchorKey`)} row=${boxOf.key}`);

  // ── J. The roving tabindex survives a table update ───────────────────────
  await eval_(`rows()[1].focus()`);
  const activeBeforeSort = await eval_('ctrl().activeKey');
  await eval_(`$qa('th button, th [wire\\\\:click]').find(b => /Name/.test(b.innerText))?.click()`);
  await sleep(2500);
  const sorted = JSON.parse(await eval_(`JSON.stringify({ tabstops: rows().filter(r => r.getAttribute('tabindex') === '0').map(r => r.dataset.rowKey), activeKey: ctrl().activeKey })`));
  check('the tabstop follows the active row through a re-sort',
    sorted.tabstops.length === 1 && sorted.tabstops[0] === activeBeforeSort && sorted.activeKey === activeBeforeSort,
    JSON.stringify(sorted));

  // The active row leaves the page: the tabstop must fall back to the first row,
  // or the grid drops out of the tab order entirely.
  await eval_(`(() => { const i = $q('[data-testid="table-search"], input[type=search], input[placeholder*="Search"]'); i.value = 'Sofia'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  await sleep(3000);
  const filtered = JSON.parse(await eval_(`JSON.stringify({ rows: rows().length, tabstops: rows().filter(r => r.getAttribute('tabindex') === '0').length, activeKey: ctrl().activeKey })`));
  check('the tabstop falls back to the first row when the active row is filtered away',
    filtered.tabstops === 1, JSON.stringify(filtered));
  await shot('07-tabstop-after-update');

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
