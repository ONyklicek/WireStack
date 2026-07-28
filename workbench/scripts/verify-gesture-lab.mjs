import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * End-to-end driver for the gesture lab — every capability the selection-gesture
 * work added, switched on together on one table.
 *
 * The per-feature drivers each isolate one thing, which is the right way to pin
 * a behaviour down but says nothing about what happens when the features share a
 * table. This one goes after the seams between them:
 *
 *   - a sweep, then a keyboard range, then "select all matching", in sequence
 *     against one selection
 *   - a record action running against the row a gesture marked
 *   - column reordering while rows are selected: the checkbox column must not
 *     move and the selection must survive it
 *   - the shortcut help listing the actions THIS table binds
 *   - the live region and aria-selected keeping step through all of it
 *
 * Assertions read the lab's rendered state panel wherever possible, so they
 * describe what a person watching the page would see rather than Alpine
 * internals.
 *
 * Exit 0 = all passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE_URL ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9343);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-gesture-lab-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-gesture-lab-${Date.now()}`);
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

  const consoleErrors = [];
  cdp.on((msg) => {
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params.sessionId === sessionId && msg.params.type === 'error') {
      consoleErrors.push(msg.params.args.map((a) => a.value ?? a.description ?? '').join(' '));
    }
    if (msg.method === 'Runtime.exceptionThrown' && msg.params.sessionId === sessionId) {
      consoleErrors.push(msg.params.exceptionDetails.exception?.description ?? msg.params.exceptionDetails.text);
    }
  });

  const helpers = `
    window.$q = (s) => document.querySelector(s);
    window.$qa = (s) => [...document.querySelectorAll(s)];
    window.tbody = () => $q('tbody[x-data^="wireRecordActions"]');
    window.ctrl = () => Alpine.$data(tbody());
    window.sel = () => Alpine.$data($q('[data-selection-root]'));
    window.rows = () => [...tbody().children].filter((el) => el.matches('tr[data-row-key]'));
    window.key = (i) => rows()[i].dataset.rowKey;
    window.cell = (i) => rows()[i].querySelector('[data-testid="table-cell-name"]');
    window.selectCell = (i) => rows()[i].querySelector('[data-select-cell]');
    window.fireKey = (el, k, o) => el.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...(o || {}) }));
    window.vis = (el) => !!el && el.getClientRects().length > 0;
    // Everything the lab panel reports, in one read.
    window.lab = () => Object.fromEntries(
      ['mode', 'selected-raw', 'selected-count', 'anchor', 'base', 'active', 'announced', 'columns']
        .map((k) => [k, $q('[data-testid="lab-' + k + '"]')?.textContent?.trim() ?? null])
    );
    window.gestureReadout = () => Object.fromEntries(
      ['keyboard', 'ranges', 'sweep', 'menu', 'help', 'fill']
        .map((k) => [k, $q('[data-testid="lab-gesture-' + k + '"]')?.textContent?.trim() ?? null])
    );
    window.help = () => $q('[data-testid="shortcut-help"]')?.closest('[role="dialog"]');
    window.dialogOpen = () => $qa('[role=dialog]').some((d) => vis(d) && getComputedStyle(d).display !== 'none');
    true;
  `;

  const realClick = async (expr, modifiers = 0) => {
    const box = JSON.parse(await eval_(`(() => {
      const el = ${expr};
      el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await sleep(140);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: box.x, y: box.y, button: 'left', clickCount: 1, modifiers });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: box.x, y: box.y, button: 'left', clickCount: 1, modifiers });
    await sleep(320);
  };

  // Press, move through the given elements, release — re-measuring each step,
  // because the bulk bar appears mid-drag and shifts the rows under the pointer.
  const drag = async (exprs) => {
    const first = JSON.parse(await eval_(`(() => {
      const el = ${exprs[0]}; el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: first.x, y: first.y, button: 'left', clickCount: 1 });

    for (const expr of exprs.slice(1)) {
      const p = JSON.parse(await eval_(`(() => {
        const r = ${expr}.getBoundingClientRect();
        return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
      })()`));
      await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x: p.x, y: p.y, button: 'left', buttons: 1 });
      await sleep(90);
    }

    const last = JSON.parse(await eval_(`(() => {
      const r = ${exprs[exprs.length - 1]}.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: last.x, y: last.y, button: 'left', clickCount: 1 });
    await sleep(500);
  };

  const MOD = { shift: 8, ctrl: 2, meta: 4 };
  const isMac = process.platform === 'darwin';
  const modBit = isMac ? MOD.meta : MOD.ctrl;

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1500, height: 1200, deviceScaleFactor: 1, mobile: false });

  // ════ the lab boots with everything switched on ═════════════════════════
  await page('Page.navigate', { url: `${base}/gesture-lab` });
  await sleep(3500);
  await eval_(helpers);

  const boot = JSON.parse(await eval_(`JSON.stringify({
    rows: rows().length,
    grid: !!$q('table[role=grid]'),
    selectable: !!$q('[data-selection-root]'),
    controller: !!tbody(),
    reorderable: !!$q('thead th[data-sortable-column]'),
    liveRegion: !!$q('[data-testid=selection-live]'),
    panel: !!$q('[data-testid=lab-inspector]'),
  })`));
  check('the lab boots with grid, selection, record actions, reorderable columns and a live region',
    boot.rows === 40 && boot.grid && boot.selectable && boot.controller && boot.reorderable && boot.liveRegion && boot.panel,
    JSON.stringify(boot));

  // The read-out names the layer this table asked for. It is worth asserting
  // rather than eyeballing: the gestures are opt-in, so "it works" and "this
  // fixture happens to have them on" are two different statements.
  const readout = JSON.parse(await eval_(`JSON.stringify(gestureReadout())`));
  check('the panel reports the gestures this table offers',
    readout.keyboard === 'on' && readout.ranges === 'on' && readout.sweep === 'on'
      && readout.menu === 'on' && readout.help === 'on' && readout.fill === 'off',
    JSON.stringify(readout));

  const idle = JSON.parse(await eval_(`JSON.stringify(lab())`));
  check('the state panel starts idle: keys mode, nothing selected, no anchor, nothing announced',
    idle.mode === 'keys' && idle['selected-count'] === '0' && idle.anchor === '—' && idle.announced === '—',
    JSON.stringify(idle));
  await shot('01-idle');

  // ════ 1. sweep, then extend by keyboard, on one selection ═══════════════
  // The sweep and the keyboard ranges are separate features that write the same
  // state; a range after a sweep has no anchor of its own to grow from.
  await drag([`selectCell(2)`, `selectCell(4)`, `selectCell(6)`]);
  const swept = JSON.parse(await eval_(`JSON.stringify({ ...lab(), count: sel().selected.length })`));
  check('a sweep down the checkbox column selects the run it covers',
    swept.count === 5 && swept['selected-count'] === '5', JSON.stringify(swept));

  await eval_(`rows()[6].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[6], 'ArrowDown', { shiftKey: true })`);
  await sleep(350);
  const afterRange = JSON.parse(await eval_(`JSON.stringify({ ...lab(), count: sel().selected.length })`));
  check('a Shift+arrow after a sweep grows the block instead of replacing the selection',
    afterRange.count === 6, JSON.stringify(afterRange));
  check('the live region reports the running count',
    afterRange.announced === '6 of 40 selected', afterRange.announced);
  await shot('02-sweep-then-range');

  // ════ 2. a record action runs against the row a gesture marked ══════════
  const activeKey = await eval_(`ctrl().activeKey`);
  await eval_(`fireKey(rows()[6], 'Enter')`);
  await sleep(1200);
  const opened = await eval_(`(() => {
    const d = $qa('[role=dialog]').find((el) => vis(el) && getComputedStyle(el).display !== 'none');
    return d ? d.textContent.replace(/\\s+/g, ' ').trim().slice(0, 90) : null;
  })()`);
  check('Enter runs the primary record action against the marked row',
    !!opened && opened.includes('Opened'), `${opened} (active=${activeKey})`);
  await shot('03-enter-action');

  await eval_(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`);
  await sleep(700);
  const kept = JSON.parse(await eval_(`JSON.stringify({ ...lab(), open: dialogOpen() })`));
  check('closing the action leaves the selection and the active row intact',
    !kept.open && kept['selected-count'] === '6' && kept.active === activeKey, JSON.stringify(kept));

  // ════ 2b. a modal takes the keyboard, and gives it back ═════════════════
  // Reported bug: a single click opening a modal left the keyboard unusable —
  // during (the focus stayed on the row behind the dialog, so Tab walked the
  // page behind it and the dialog's own buttons were unreachable) and after
  // (the focus was left wherever tabbing had put it, and the grid only answers
  // when a row itself has the focus).
  await page('Page.navigate', { url: `${base}/gesture-lab-click` });
  await sleep(3000);
  await eval_(helpers);
  await eval_(`window.where = () => {
    const a = document.activeElement;
    return { inDialog: !!a?.closest?.('[role=dialog]'), tag: a?.tagName, testid: a?.getAttribute?.('data-testid') ?? null };
  }; true`);

  await realClick(`cell(3)`);
  await sleep(1600);
  const inModal = JSON.parse(await eval_(`JSON.stringify({ ...where(), open: dialogOpen() })`));
  check('a modal opened by a single click takes the focus into the dialog',
    inModal.open && inModal.inDialog, JSON.stringify(inModal));

  // Tab has to stay inside; it used to walk the table behind the dialog.
  const tabbed = [];
  for (let i = 0; i < 3; i++) {
    await page('Input.dispatchKeyEvent', { type: 'rawKeyDown', windowsVirtualKeyCode: 9, key: 'Tab', code: 'Tab' });
    await page('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 9, key: 'Tab', code: 'Tab' });
    await sleep(220);
    tabbed.push(JSON.parse(await eval_(`JSON.stringify(where())`)));
  }
  check('Tab cycles inside the dialog instead of walking the table behind it',
    tabbed.every((t) => t.inDialog), JSON.stringify(tabbed.map((t) => t.testid ?? t.tag)));

  const rowBefore = await eval_(`ctrl().activeKey`);
  await eval_(`(() => { const b = [...document.querySelectorAll('[data-testid=confirmation-cancel]')].find((x) => x.getClientRects().length); b && b.click(); })()`);
  await sleep(1500);
  const returned = JSON.parse(await eval_(`JSON.stringify({ ...where(), open: dialogOpen(), active: ctrl().activeKey })`));
  check('closing it hands the focus back to the row it was opened from',
    !returned.open && returned.tag === 'TR' && returned.active === rowBefore, JSON.stringify(returned));

  await eval_(`fireKey(document.activeElement, 'ArrowDown')`);
  await sleep(400);
  check('and the arrow keys work again straight away, with no click needed',
    await eval_(`ctrl().activeKey`) !== rowBefore,
    `${rowBefore} → ${await eval_(`ctrl().activeKey`)}`);
  await shot('03b-modal-focus');

  await page('Page.navigate', { url: `${base}/gesture-lab` });
  await sleep(3000);
  await eval_(helpers);
  await eval_(`rows()[6].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[6], ' ')`);
  await sleep(300);

  // ════ 3. the shortcut help lists what THIS table binds ══════════════════
  await eval_(`rows()[6].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[6], '?')`);
  await sleep(800);
  const helpBody = JSON.parse(await eval_(`(() => {
    const el = help();
    if (!el) return 'null';
    const text = el.textContent.replace(/\\s+/g, ' ');
    return JSON.stringify({
      open: getComputedStyle(el).display !== 'none',
      sections: [...el.querySelectorAll('section h4')].map((h) => h.textContent.trim()),
      lists: {
        // The keyboard-reachable actions, by the label each was given.
        open: text.includes('Open'),
        peek: text.includes('Peek'),
        archive: text.includes('Archive'),
        // The context menu is one gesture, so the legend lists the gesture —
        // not every action sitting inside the menu it opens.
        menu: text.includes('Open the row menu'),
        duplicateListedSeparately: /Run .Duplicate/.test(text),
      },
    });
  })()`) ?? 'null');
  check('? lists the navigation, selection and action sections',
    helpBody.open && ['Navigation', 'Selection', 'Actions', 'Help'].every((s) => helpBody.sections.includes(s)),
    JSON.stringify(helpBody.sections));
  check('the actions section names the keyboard-reachable actions and the menu gesture',
    helpBody.lists.open && helpBody.lists.peek && helpBody.lists.archive && helpBody.lists.menu,
    JSON.stringify(helpBody.lists));
  check('a context-menu-only action is not listed as a shortcut of its own',
    !helpBody.lists.duplicateListedSeparately,
    'the menu is one gesture; its contents are not shortcuts');
  await shot('04-shortcut-help');

  await eval_(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`);
  await sleep(600);

  // ════ 4. reorder a column while rows are selected ═══════════════════════
  // Column reordering rewrites every row's cells; the selection cell must stay
  // where it is and the selection itself must survive.
  const beforeReorder = JSON.parse(await eval_(`JSON.stringify({
    ...lab(),
    cells: [...rows()[0].children].map((el) => el.tagName === 'TEMPLATE' ? 'TEMPLATE' : (el.getAttribute('data-column') ?? (el.hasAttribute('data-select-cell') ? 'SELECT' : 'FIXED'))),
  })`));

  await eval_(`(() => {
    const root = $q('[x-data*="wireSortable"]');
    const d = Alpine.$data(root);
    const ths = [...document.querySelectorAll('thead th')];
    const moved = ths.find((t) => t.getAttribute('data-sortable-column') === 'name');
    const oldIndex = Sortable.utils.index(moved);
    ths[ths.length - 1].after(moved);
    d.columnSortableInstance.options.onEnd({ oldIndex, newIndex: Sortable.utils.index(moved) });
  })()`);
  await sleep(1500);

  const afterReorder = JSON.parse(await eval_(`JSON.stringify({
    ...lab(),
    cells: [...rows()[0].children].map((el) => el.tagName === 'TEMPLATE' ? 'TEMPLATE' : (el.getAttribute('data-column') ?? (el.hasAttribute('data-select-cell') ? 'SELECT' : 'FIXED'))),
    headerCols: [...document.querySelectorAll('thead th[data-column]')].map((th) => th.getAttribute('data-column')),
  })`));
  check('dragging a column header moves that column, in the header and the body alike',
    afterReorder.headerCols[afterReorder.headerCols.length - 1] === 'name'
      && afterReorder.cells.filter((c) => !['TEMPLATE', 'SELECT', 'FIXED'].includes(c)).join() === afterReorder.headerCols.join(),
    `header=${JSON.stringify(afterReorder.headerCols)} body=${JSON.stringify(afterReorder.cells)}`);
  check('the checkbox column stays where it was through the reorder',
    afterReorder.cells.indexOf('SELECT') === beforeReorder.cells.indexOf('SELECT'),
    JSON.stringify(afterReorder.cells));
  check('the selection survives the reorder',
    afterReorder['selected-count'] === beforeReorder['selected-count'],
    `${beforeReorder['selected-count']} → ${afterReorder['selected-count']}`);
  await shot('05-after-reorder');

  // ════ 5. aria-selected tracks the live selection ════════════════════════
  const aria = JSON.parse(await eval_(`(() => {
    const wrong = rows().filter((r) => r.getAttribute('aria-selected') !== String(sel().isSelected(r.dataset.rowKey)));
    // This table has a filterable column, so the header is TWO rows — the count
    // and the body's first index have to account for it.
    const headerRows = $qa('thead tr').length;
    return JSON.stringify({
      wrong: wrong.length,
      rowcount: Number($q('table[role=grid]').getAttribute('aria-rowcount')),
      headerRows,
      firstBodyIndex: Number(rows()[0].getAttribute('aria-rowindex')),
      lastBodyIndex: Number(rows()[39].getAttribute('aria-rowindex')),
    });
  })()`));
  check('every row reports aria-selected matching the live selection',
    aria.wrong === 0, `${aria.wrong} row(s) out of step`);
  check('the row count and indices account for the column-filter header row',
    aria.headerRows === 2 && aria.rowcount === 40 + aria.headerRows
      && aria.firstBodyIndex === aria.headerRows + 1
      && aria.lastBodyIndex === aria.rowcount,
    JSON.stringify(aria));

  // ════ 6. "select all matching", where a range means the opposite ════════
  await page('Page.navigate', { url: `${base}/gesture-lab-paged` });
  await sleep(3500);
  await eval_(helpers);

  await eval_(`$q('[data-testid=table-select-all]').click()`);
  await sleep(700);
  await eval_(`$q('[data-testid=table-select-all-matching]').click()`);
  await sleep(1800);
  const allMode = JSON.parse(await eval_(`JSON.stringify(lab())`));
  check('escalating to "all matching" covers the whole filtered set, not the page',
    allMode.mode === 'all' && allMode['selected-count'] === '40', JSON.stringify(allMode));
  check('and it is announced as a shape, not as a count of ticks',
    allMode.announced === 'All 40 selected', allMode.announced);

  await eval_(`rows()[3].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[3], ' ')`);
  await sleep(300);
  await eval_(`fireKey(rows()[3], 'ArrowDown', { shiftKey: true })`);
  await sleep(400);
  const rangeInAll = JSON.parse(await eval_(`JSON.stringify({ ...lab(), raw: sel().selected.length })`));
  check('a range in that mode DESELECTS, because the stored list is the exclusions',
    rangeInAll.mode === 'all' && Number(rangeInAll['selected-count']) < 40 && rangeInAll.raw > 0,
    JSON.stringify(rangeInAll));
  await shot('06-all-mode-range');

  // mod+A is not a range gesture: everything is selected already.
  const beforeModA = await eval_(`lab()['selected-count']`);
  await eval_(`fireKey(rows()[3], 'a', { ctrlKey: true, metaKey: ${isMac} })`);
  await sleep(400);
  check('mod+A stands down in that mode instead of inverting the selection',
    await eval_(`lab()['selected-count']`) === beforeModA, `stayed at ${beforeModA}`);

  // ════ 7. a modified click is a selection gesture, never an action ═══════
  await eval_(`sel().deselectAll()`);
  await sleep(500);
  await realClick(`cell(1)`, modBit);
  const modClick = JSON.parse(await eval_(`JSON.stringify({ ...lab(), open: dialogOpen() })`));
  check('mod+click toggles the row and never runs the click-bound record action',
    modClick['selected-count'] === '1' && !modClick.open, JSON.stringify(modClick));

  await realClick(`cell(4)`, MOD.shift);
  const shiftClick = JSON.parse(await eval_(`JSON.stringify({ ...lab(), open: dialogOpen() })`));
  check('Shift+click ranges from that anchor, still without running an action',
    shiftClick['selected-count'] === '4' && !shiftClick.open, JSON.stringify(shiftClick));
  await shot('07-modified-clicks');

  const alpineErrors = consoleErrors.filter((e) => /Alpine|Expression Error|SyntaxError|TypeError/i.test(e));
  check('no Alpine/JS errors across the whole session', alpineErrors.length === 0,
    alpineErrors.slice(0, 3).join(' | '));

  // Column order is persisted per user, so without this the next run starts
  // from the order this one left behind — and the reorder check would be
  // dragging a column that is already where it is being dragged to.
  await eval_(`(() => {
    const root = document.querySelector('[wire\\\\:id]');
    if (root && window.Livewire) window.Livewire.find(root.getAttribute('wire:id'))?.call('resetColumnOrder');
  })()`);
  await sleep(1200);
  const restored = await eval_(`[...document.querySelectorAll('thead th[data-column]')].map((th) => th.getAttribute('data-column')).join(',')`);
  check('the driver leaves the persisted column order back at its default',
    restored === 'name,status,amount', restored);
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
console.log(`\nSummary: ${results.length - failed.length}/${results.length} checks passed`);
if (failed.length) process.exitCode = process.exitCode ?? 1;

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
