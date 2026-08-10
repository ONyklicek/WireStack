import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver for selectable() + fillHandle() on ONE table
 * (/previews/table-editable-fill-selectable).
 *
 * Two things make this combination its own gate, and neither is observable from
 * PHP:
 *
 *   1. Scope. The fill handle wraps the table in an x-data INSIDE the selection
 *      root, so every selection expression under it — the header select-all, the
 *      row checkboxes, the bulk bar — evaluates in a stack whose INNERMOST
 *      component is the fill handle. Alpine resolves magics against the element
 *      an expression runs on, not against the component that owns the method, so
 *      a selection getter reaching for its root through `$root` reads the fill
 *      root and finds no `data-page-keys` — select-all then selects nothing, in
 *      silence. Pest sees the markup; only a browser sees the resolution.
 *
 *   2. Pointer. Both gestures drag down the same rows with the same button. The
 *      fill must not sweep the selection, the sweep must not paint a fill range,
 *      and neither may swallow the other's release.
 *
 * The pointer stream is synthesized rather than driven through Input.*, matching
 * verify-fill-handle.mjs: real pointer capture would need a genuine device
 * pointer, and neither controller depends on it.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-fill-selection.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-editable-fill-selectable';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9381);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-fill-selection-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-fill-selection-${Date.now()}`);
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

const consoleErrors = [];
const badResponses = [];

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

  cdp.on((msg) => {
    if (msg.method === 'Runtime.exceptionThrown') {
      consoleErrors.push(msg.params?.exceptionDetails?.exception?.description ?? 'exception');
    }
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params?.type === 'error') {
      consoleErrors.push((msg.params.args ?? []).map((a) => a.value ?? a.description ?? '').join(' '));
    }
    if (msg.method === 'Network.responseReceived') {
      const s = msg.params?.response?.status;
      if (s >= 400 && ! String(msg.params.response.url).endsWith('/favicon.ico')) {
        badResponses.push(`${s} ${msg.params.response.url}`);
      }
    }
  });

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Network.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1280, height: 1000, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  const helpers = `
    window.selRoot = () => document.querySelector('[data-selection-root]');
    window.fillRoot = () => document.querySelector('[data-fill-root]');
    window.handle = () => document.querySelector('[data-fill-handle]');
    window.overlay = () => document.querySelector('[data-fill-overlay]');
    window.sel = () => Alpine.\$data(selRoot());
    window.rows = () => Array.from(fillRoot().querySelector('table').tBodies[0].children)
      .filter((el) => el.matches('tr[data-row-key]'));
    window.cell = (i, col) => rows()[i].querySelector(':scope > td[data-column="' + col + '"]');
    window.selectCell = (i) => rows()[i].querySelector(':scope > td[data-select-cell]');
    window.roleOf = (i) => cell(i, 'role').querySelector('select').value;

    /* The selection as the USER can see it: what each row's checkbox reports,
       not what the component thinks. A getter reading the wrong root returns a
       plausible-looking empty array; the boxes cannot lie. */
    window.checked = () => rows().map((r) =>
      r.querySelector('[data-testid="table-row-select"]').getAttribute('aria-checked') === 'true');
    window.painted = (col) => rows().map((_, i) => cell(i, col).classList.contains('wire-fill-target'));

    window.reset = async () => {
      sel().deselectAll();
      await new Promise((r) => setTimeout(r, 700));
    };

    window.pointer = (type, x, y, target, extra = {}) => (target ?? window).dispatchEvent(
      new PointerEvent(type, {
        pointerId: 1, pointerType: 'mouse', isPrimary: true, button: 0,
        bubbles: true, cancelable: true, clientX: x, clientY: y, ...extra,
      })
    );
    window.centre = (el) => { const r = el.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; };
    window.hover = (el) => { const p = centre(el); pointer('pointerover', p.x, p.y, el); };
    window.grab = () => { const r = handle().getBoundingClientRect(); pointer('pointerdown', r.left + 4, r.top + 4, handle()); };
    window.dragOver = (el) => { const p = centre(el); pointer('pointermove', p.x, p.y); };
    window.release = (el) => { const p = centre(el); pointer('pointerup', p.x, p.y); };

    /* The checkbox sweep: press in the select cell, move down, release. buttons:1
       is load-bearing — the sweep abandons itself the moment the button is up. */
    window.sweep = (fromIdx, toIdx) => {
      const from = centre(selectCell(fromIdx));
      pointer('pointerdown', from.x, from.y, selectCell(fromIdx), { buttons: 1 });
      const to = centre(selectCell(toIdx));
      pointer('pointermove', to.x, to.y, document, { buttons: 1 });
      pointer('pointerup', to.x, to.y, document);
    };

    window.__calls = 0;
    window.__fills = [];
    const __fetch = window.fetch;
    window.fetch = function (...args) {
      window.__calls++;
      const body = String(args[1]?.body ?? '');
      const promise = __fetch.apply(this, args);
      if (body.includes('fillTableCells')) {
        promise.then((res) => res.clone().text().then((text) => {
          const at = text.indexOf('"returns"');
          window.__fills.push({
            sent: body.slice(body.indexOf('fillTableCells'), body.indexOf('fillTableCells') + 400),
            got: at === -1 ? ('NO RETURNS: ' + text.slice(0, 300)) : text.slice(at, at + 900),
          });
        }).catch(() => {}));
      }
      return promise;
    };

    true;
  `;
  await eval_(helpers);

  // ── 0. The shape that causes the shadowing ────────────────────────────
  const shape = await eval_(`(() => {
    if (typeof Alpine === 'undefined' || ! selRoot() || ! fillRoot()) return 'not booted';
    return {
      nested: selRoot().contains(fillRoot()) && selRoot() !== fillRoot(),
      selectAllUnderFill: fillRoot().contains(document.querySelector('[data-testid="table-select-all"]')),
      pageKeysOnSelectionRoot: !! selRoot().dataset.pageKeys && ! fillRoot().dataset.pageKeys,
      fillColumns: fillRoot().dataset.fillColumns,
      rows: rows().length,
      checkboxes: rows().filter((r) => r.querySelector('[data-select-cell]')).length,
    };
  })()`);
  check('the preview renders both roots, with the fill root nested inside the selection root',
    shape !== 'not booted' && shape.nested === true && shape.selectAllUnderFill === true,
    JSON.stringify(shape));
  check('the selection datasets stay on the selection root only', shape.pageKeysOnSelectionRoot === true);
  check('every row carries a checkbox cell', shape.checkboxes === shape.rows && shape.rows >= 4, `${shape.checkboxes}/${shape.rows}`);
  check('the fillable column allowlist is unaffected by the checkbox column',
    shape.fillColumns === '["role","is_active"]', `data-fill-columns=${shape.fillColumns}`);
  await shot('00-booted');

  // ── 1. The selection getters resolve through the fill handle's scope ───
  // Read through the FILL root's merged scope: that is the stack every selection
  // expression under it actually runs in. `$root` there is the fill root, so a
  // getter asking a magic for its element reads nothing.
  const throughFill = await eval_(`(() => {
    const scoped = Alpine.\$data(fillRoot());
    return {
      pageKeys: (scoped.pageKeys || []).length,
      matching: scoped.matching,
      ownRoot: JSON.parse(selRoot().dataset.pageKeys).length,
    };
  })()`);
  check('pageKeys still resolves when read through the fill handle\'s scope',
    throughFill.pageKeys === throughFill.ownRoot && throughFill.pageKeys >= 4,
    `through fill=${throughFill.pageKeys}, on root=${throughFill.ownRoot}`);
  check('matching still resolves through the fill handle\'s scope',
    throughFill.matching === shape.rows, `matching=${throughFill.matching}`);

  // ── 2. The header select-all — the gesture the shadowing broke ─────────
  const selectAll = await eval_(`(async () => {
    await reset();
    document.querySelector('[data-testid="table-select-all"]').click();
    await new Promise((r) => setTimeout(r, 800));
    return {
      checked: checked(),
      count: sel().selectedCount,
      allSelected: sel().allSelected,
      bulkBar: !! document.querySelector('[data-testid="table-bulk-bar"]'),
    };
  })()`);
  check('the header select-all checks every row on the page',
    selectAll.checked.every(Boolean) && selectAll.checked.length === shape.rows,
    JSON.stringify(selectAll.checked));
  check('and the selection count agrees with the boxes',
    selectAll.count === shape.rows && selectAll.allSelected === true,
    `count=${selectAll.count}`);
  await shot('01-select-all');

  const selectNone = await eval_(`(async () => {
    document.querySelector('[data-testid="table-select-all"]').click();
    await new Promise((r) => setTimeout(r, 800));
    return { checked: checked(), count: sel().selectedCount };
  })()`);
  check('a second header click clears the page again',
    selectNone.checked.every((c) => c === false) && selectNone.count === 0,
    JSON.stringify(selectNone.checked));

  // ── 3. A row checkbox, inside the fill root ───────────────────────────
  const rowBox = await eval_(`(async () => {
    await reset();
    selectCell(1).click();
    await new Promise((r) => setTimeout(r, 700));
    return { checked: checked(), count: sel().selectedCount };
  })()`);
  check('a row checkbox selects exactly its own row',
    JSON.stringify(rowBox.checked) === JSON.stringify([false, true, false, false]) && rowBox.count === 1,
    JSON.stringify(rowBox.checked));

  // ── 4. The fill handle still finds its cells past the checkbox column ──
  // The grid locates cells by data-column, never by position — the checkbox <td>
  // shifts every index by one, and an index-based grid would fill the wrong one.
  const handlePlaced = await eval_(`(() => {
    cell(0, 'role').querySelector('select').focus();
    if (handle().hidden) return 'hidden';
    const h = handle().getBoundingClientRect(), c = cell(0, 'role').getBoundingClientRect();
    const hx = h.left + h.width / 2, hy = h.top + h.height / 2;
    return hx > c.left && hx < c.right && hy > c.top && hy < c.bottom;
  })()`);
  check('the handle parks inside the focused cell despite the checkbox column',
    handlePlaced === true, String(handlePlaced));

  const notOnCheckbox = await eval_(`(() => {
    document.activeElement?.blur();
    hover(cell(2, 'role'));
    const parked = handle().getBoundingClientRect().top;
    hover(selectCell(2));
    // The checkbox column is not fillable, so the handle holds still rather than
    // flickering away on every pass across it.
    return ! handle().hidden && Math.abs(handle().getBoundingClientRect().top - parked) < 1;
  })()`);
  check('hovering the checkbox column neither hides nor moves the handle', notOnCheckbox === true);

  // ── 5. A fill drag must not touch the selection ───────────────────────
  const dragKeepsSelection = await eval_(`(async () => {
    await reset();
    selectCell(3).click();                       // one row selected, away from the drag
    await new Promise((r) => setTimeout(r, 700));
    const before = checked();

    cell(0, 'role').querySelector('select').focus();
    grab();
    dragOver(cell(1, 'role'));
    dragOver(cell(2, 'role'));

    const during = {
      checked: checked(),
      painted: painted('role'),
      overlayShown: ! overlay().hidden,
      filling: document.body.classList.contains('wire-filling'),
    };

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    await new Promise((r) => setTimeout(r, 500));

    return { before, during, after: checked(), count: sel().selectedCount };
  })()`);
  check('dragging the fill handle paints a range and selects nothing',
    JSON.stringify(dragKeepsSelection.during.checked) === JSON.stringify(dragKeepsSelection.before)
    && JSON.stringify(dragKeepsSelection.during.painted) === JSON.stringify([false, true, true, false]),
    `checked=${JSON.stringify(dragKeepsSelection.during.checked)} painted=${JSON.stringify(dragKeepsSelection.during.painted)}`);
  check('the range overlay and the drag body class are on during the fill drag',
    dragKeepsSelection.during.overlayShown && dragKeepsSelection.during.filling);
  check('Escaping the fill drag leaves the selection exactly as it was',
    JSON.stringify(dragKeepsSelection.after) === JSON.stringify(dragKeepsSelection.before)
    && dragKeepsSelection.count === 1,
    JSON.stringify(dragKeepsSelection.after));
  await shot('02-fill-drag-with-selection');

  // ── 6. A checkbox sweep must not paint a fill range ───────────────────
  const swept = await eval_(`(async () => {
    await reset();
    const calls = __calls;
    sweep(0, 2);
    await new Promise((r) => setTimeout(r, 800));
    return {
      checked: checked(),
      count: sel().selectedCount,
      painted: painted('role').some(Boolean),
      overlayHidden: overlay().hidden,
      filling: document.body.classList.contains('wire-filling'),
      calls: __calls - calls,
    };
  })()`);
  check('sweeping the checkbox column selects the swept block',
    JSON.stringify(swept.checked) === JSON.stringify([true, true, true, false]) && swept.count === 3,
    JSON.stringify(swept.checked));
  check('and paints no fill range, opens no overlay, sends no fill',
    ! swept.painted && swept.overlayHidden && ! swept.filling,
    `painted=${swept.painted} overlay=${swept.overlayHidden} filling=${swept.filling}`);
  await shot('03-swept');

  // ── 7. Shift+click ranges, under the fill root ────────────────────────
  const ranged = await eval_(`(async () => {
    await reset();
    // Anchor on row 0 with a plain checkbox click, then extend to row 2.
    selectCell(0).click();
    await new Promise((r) => setTimeout(r, 500));
    const target = cell(2, 'name');
    const p = centre(target);
    target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, shiftKey: true, clientX: p.x, clientY: p.y }));
    await new Promise((r) => setTimeout(r, 700));
    return { checked: checked(), count: sel().selectedCount };
  })()`);
  check('Shift+click extends the range from the checkbox anchor',
    JSON.stringify(ranged.checked) === JSON.stringify([true, true, true, false]) && ranged.count === 3,
    JSON.stringify(ranged.checked));

  // ── 8. Keyboard selection, and the editable cells it must keep out of ──
  const keyboard = await eval_(`(async () => {
    await reset();
    // Space on a focused ROW toggles it.
    rows()[1].focus();
    rows()[1].dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 600));
    const afterRowSpace = checked();

    // A space typed INTO an editable cell belongs to the input, not the grid.
    const input = cell(2, 'email').querySelector('input');
    input.focus();
    input.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 500));
    const afterCellSpace = checked();

    // mod+A selects the whole page.
    rows()[0].focus();
    rows()[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'a', ctrlKey: true, bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 700));

    return { afterRowSpace, afterCellSpace, afterModA: checked() };
  })()`);
  check('Space on a focused row toggles that row',
    JSON.stringify(keyboard.afterRowSpace) === JSON.stringify([false, true, false, false]),
    JSON.stringify(keyboard.afterRowSpace));
  check('a space typed into an editable cell changes no selection',
    JSON.stringify(keyboard.afterCellSpace) === JSON.stringify(keyboard.afterRowSpace),
    JSON.stringify(keyboard.afterCellSpace));
  check('mod+A selects the whole page',
    keyboard.afterModA.every(Boolean), JSON.stringify(keyboard.afterModA));
  await shot('04-keyboard');

  // ── 9. The lock version of a cell edited inline ───────────────────────
  // The cell root carries `wire:ignore.self` so a morph cannot overwrite the
  // optimistic state it holds — and the cost is that Livewire never refreshes
  // that element's attributes either. The server keeps sending a fresh version;
  // the DOM is told to ignore it. So nothing but the cell itself can keep
  // `data-record-version` current, and until it did, the fill read a version the
  // server had long passed: the whole range came back a conflict and the drag
  // was rolled back without a word.
  //
  // The companion check is the edit STICKING. The tidy-looking fix — having the
  // cell write the fresh version back to `data-record-version` — quietly breaks
  // that: the root is the element the cell's own MutationObserver watches, so the
  // write wakes it, it re-reads the equally frozen `data-server-value`, and syncs
  // the cell back to what the page loaded with. The row is saved and the screen
  // shows the old value a second later. Only a browser sees it.
  const version = await eval_(`(async () => {
    // Force a REAL commit: setting a select to the value it already holds is
    // not dirty, so nothing is sent and the version never moves — which is
    // exactly what made this reproduce only on some runs.
    const box = () => cell(1, 'role').querySelector('select');
    const want = box().value === 'viewer' ? 'editor' : 'viewer';
    box().value = want;
    box().dispatchEvent(new Event('change', { bubbles: true }));
    await new Promise((r) => setTimeout(r, 1400));

    const el = cell(1, 'role').querySelector('[data-record-key]');

    return {
      want,
      shown: box().value,
      state: String(Alpine.\$data(el).recordVersion),
      // The read path the fill actually takes, through its own grid.
      asFillReadsIt: String(Alpine.\$data(fillRoot()).grid.describe(1, 0).version),
    };
  })()`);
  check('a cell edited inline hands the fill its CURRENT lock version',
    version.asFillReadsIt === version.state && version.state !== '',
    `component=${version.state} fill reads=${version.asFillReadsIt}`);
  check('and the inline edit stays on screen instead of being synced back',
    version.shown === version.want, `picked ${version.want}, cell shows ${version.shown}`);

  // ── 10. A real fill onto that row, with a selection standing ──────────
  // Three claims in one drag: the fill lands on a row that was edited inline,
  // it writes the DRAGGED range and nothing else (a selection is not a fill
  // target), and the selection survives the round-trip the fill sends.
  const filled = await eval_(`(async () => {
    await reset();

    // A distinct baseline FIRST, or the claim is unfalsifiable: a previous run
    // (this driver's own, or verify-fill-handle's) can leave every row already
    // holding the value about to be filled, and "the other rows did not change"
    // then passes without the fill having been constrained to anything.
    const want = 'admin';
    const baseline = 'editor';
    const setRole = async (i, value) => {
      const box = cell(i, 'role').querySelector('select');
      if (box.value === value) return;   // not dirty, nothing would be sent
      box.value = value;
      box.dispatchEvent(new Event('change', { bubbles: true }));
      await new Promise((r) => setTimeout(r, 1000));
    };
    // Through a third value, so every row really commits at least once no
    // matter what the last run left in the database.
    for (const i of [1, 2, 3]) { await setRole(i, 'viewer'); await setRole(i, baseline); }
    await setRole(0, 'viewer');
    await setRole(0, want);

    selectCell(2).click();
    selectCell(3).click();
    await new Promise((r) => setTimeout(r, 800));
    const selectionBefore = checked();

    const rolesBefore = [0, 1, 2, 3].map(roleOf);
    const src = cell(0, 'role').querySelector('select');
    src.focus();
    const calls = __calls;
    const ctrl = Alpine.\$data(fillRoot());
    grab();
    const grabbed = { hidden: handle().hidden, active: JSON.stringify(ctrl.active), dragging: ctrl.dragging };
    dragOver(cell(1, 'role'));
    const dragged = { range: JSON.stringify(ctrl.range), painted: painted('role') };
    release(cell(1, 'role'));
    await new Promise((r) => setTimeout(r, 1800));

    return {
      want,
      calls: __calls - calls,
      grabbed,
      dragged,
      rolesBefore,
      roles: [0, 1, 2, 3].map(roleOf),
      selectionBefore,
      selectionAfter: checked(),
      count: sel().selectedCount,
      fills: window.__fills,
    };
  })()`);
  check('the baseline the fill has to overwrite is really distinct from it',
    filled.rolesBefore[0] === filled.want
    && [1, 2, 3].every((i) => filled.rolesBefore[i] !== filled.want),
    JSON.stringify(filled.rolesBefore));
  const wroteRange = filled.roles[1] === filled.want
    && filled.roles[2] === filled.rolesBefore[2]
    && filled.roles[3] === filled.rolesBefore[3];
  check('the fill writes only the dragged row, not the selected ones', wroteRange,
    `want=${filled.want} before=${JSON.stringify(filled.rolesBefore)} after=${JSON.stringify(filled.roles)}`
    + ` grabbed=${JSON.stringify(filled.grabbed)} dragged=${JSON.stringify(filled.dragged)}`);
  // A fill that painted the right range and still wrote nothing was refused by
  // the server, and the reason is only in the response — print it, or the next
  // reader re-derives it from scratch.
  if (! wroteRange) console.log('  fill traffic: ' + JSON.stringify(filled.fills, null, 2));
  check('it is still exactly one request', filled.calls === 1, `calls=${filled.calls}`);
  check('the selection survives the fill round-trip',
    JSON.stringify(filled.selectionAfter) === JSON.stringify(filled.selectionBefore) && filled.count === 2,
    `${JSON.stringify(filled.selectionBefore)} -> ${JSON.stringify(filled.selectionAfter)}`);
  await shot('05-filled-with-selection');

  // A reload is the only honest proof the write landed — and that the fill did
  // not quietly take the selected rows with it.
  await page('Page.navigate', { url });
  await sleep(2500);
  await eval_(helpers);
  const persisted = await eval_(`[0, 1, 2, 3].map(roleOf)`);
  check('the stored rows match what the drag claimed',
    persisted[1] === filled.want
    && persisted[2] === filled.rolesBefore[2]
    && persisted[3] === filled.rolesBefore[3],
    `server=${JSON.stringify(persisted)} want=${filled.want}`);
  await shot('06-reloaded');

  // ── 10. Nothing broke on the way ──────────────────────────────────────
  check('no 419 on any request', ! badResponses.some((r) => r.startsWith('419')), badResponses.join('; ') || 'none');
  check('no console/runtime errors', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  console.log('\nSummary: ' + results.filter((r) => r.ok).length + '/' + results.length + ' checks passed');
  console.log('Screenshots: ' + shotDir);
  if (badResponses.length) console.log('Non-2xx responses: ' + badResponses.join('; '));
  if (results.some((r) => ! r.ok)) process.exitCode = 1;
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
