import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver for the Excel-style fill handle
 * (/previews/table-editable-fill).
 *
 * Everything here is something a PHP test cannot observe: that dragging paints
 * a preview WITHOUT touching data or the wire, that releasing sends exactly ONE
 * request for the whole range, that Escape abandons the drag with nothing
 * written, and that a filled toggle sends a real boolean rather than the '1'
 * its data attribute holds.
 *
 * The pointer stream is synthesized rather than driven through Input.* so the
 * run stays deterministic: real pointer capture would need a genuine device
 * pointer, and the controller deliberately does not depend on it.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-fill-handle.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-editable-fill';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9372);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-fill-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-fill-verify-${Date.now()}`);
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
      // The preview ships no favicon; a 404 for it is not this feature's problem.
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
    window.root = () => document.querySelector('[data-fill-root]');
    window.handle = () => document.querySelector('[data-fill-handle]');
    window.overlay = () => document.querySelector('[data-fill-overlay]');
    window.rows = () => Array.from(root().querySelector('table').tBodies[0].children)
      .filter((el) => el.matches('tr[data-row-key]'));
    window.cell = (i, col) => rows()[i].querySelector(':scope > td[data-column="' + col + '"]');
    window.painted = (col) => rows().map((_, i) => cell(i, col).classList.contains('wire-fill-target'));
    window.roleOf = (i) => cell(i, 'role').querySelector('select').value;
    window.activeOf = (i) => cell(i, 'is_active').querySelector('button').getAttribute('aria-checked');

    // Count Livewire round-trips so "no request while dragging" is measured, not assumed.
    window.__calls = 0;
    const __fetch = window.fetch;
    window.fetch = function (...args) { window.__calls++; return __fetch.apply(this, args); };

    window.pointer = (type, x, y, target) => (target ?? window).dispatchEvent(new PointerEvent(type, {
      pointerId: 1, pointerType: 'mouse', isPrimary: true,
      bubbles: true, cancelable: true, clientX: x, clientY: y,
    }));
    window.centre = (el) => { const r = el.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; };

    // The handle sits INSIDE the cell, tucked in from the bottom-right corner —
    // asserting an exact offset would just restate HANDLE_INSET, so this checks
    // the property that matters: wholly within the cell, and near that corner.
    window.anchoredIn = (el) => {
      const h = handle().getBoundingClientRect(), c = el.getBoundingClientRect();
      const hx = h.left + h.width / 2, hy = h.top + h.height / 2;
      const inside = hx > c.left && hx < c.right && hy > c.top && hy < c.bottom;
      const nearCorner = (c.right - hx) <= 24 && (c.bottom - hy) <= 24;
      return inside && nearCorner;
    };
    window.grab = () => { const r = handle().getBoundingClientRect(); pointer('pointerdown', r.left + 4, r.top + 4, handle()); };
    window.dragOver = (el) => { const p = centre(el); pointer('pointermove', p.x, p.y); };
    window.release = (el) => { const p = centre(el); pointer('pointerup', p.x, p.y); };

    true;
  `;
  await eval_(helpers);

  // ── 0. The page booted with a handle and a column allowlist ───────────
  const booted = await eval_(`typeof Alpine !== 'undefined' && !!root() && !!handle() && !!overlay()`);
  check('preview renders the fill root, handle and overlay with Alpine booted', booted);

  const columns = await eval_(`root().dataset.fillColumns`);
  check('only fillable columns are advertised', columns === '["role","is_active"]', `data-fill-columns=${columns}`);

  // ── 1. The handle follows the focused editable cell ───────────────────
  const hiddenAtRest = await eval_(`handle().hidden`);
  check('handle stays hidden until an editable cell is focused', hiddenAtRest === true);

  const placed = await eval_(`(() => {
    cell(0, 'role').querySelector('select').focus();
    if (handle().hidden) return 'still hidden';
    return anchoredIn(cell(0, 'role'));
  })()`);
  check('handle parks inside the focused cell, by its bottom-right corner', placed === true, String(placed));

  // A column excluded with ->fillable(false) must never offer the affordance.
  const onNonFillable = await eval_(`(() => { cell(0, 'email').querySelector('input').focus(); return handle().hidden; })()`);
  check('no handle on a column excluded with ->fillable(false)', onNonFillable === true);
  await shot('01-handle-placed');

  // Cloning must not require editing first: clicking into a text cell to reveal
  // the handle would start an edit the user never asked for.
  const hovered = await eval_(`(() => {
    document.activeElement?.blur();
    const target = cell(2, 'role');
    const p = centre(target);
    target.dispatchEvent(new PointerEvent('pointerover', { pointerId: 1, pointerType: 'mouse', bubbles: true, clientX: p.x, clientY: p.y }));
    if (handle().hidden) return 'hidden';
    return anchoredIn(target);
  })()`);
  check('hovering a fillable cell moves the handle onto it, with nothing focused', hovered === true, String(hovered));

  // The handle must not wander off a cell someone is typing in.
  const stayedOnFocused = await eval_(`(() => {
    cell(0, 'role').querySelector('select').focus();
    const other = cell(3, 'role');
    const p = centre(other);
    other.dispatchEvent(new PointerEvent('pointerover', { pointerId: 1, pointerType: 'mouse', bubbles: true, clientX: p.x, clientY: p.y }));
    return anchoredIn(cell(0, 'role'));
  })()`);
  check('a focused cell keeps the handle while the pointer moves elsewhere', stayedOnFocused === true);

  // The handle straddles a row boundary, so reaching for it means crossing the
  // row below. Without a grab radius the handle jumps down exactly then, and is
  // impossible to catch — which is what shipped before this check existed.
  const heldWhileApproaching = await eval_(`(() => {
    document.activeElement?.blur();
    // Park the handle on row 1 by hovering it.
    const src = cell(1, 'role');
    const sp = centre(src);
    src.dispatchEvent(new PointerEvent('pointerover', { pointerId: 1, pointerType: 'mouse', bubbles: true, clientX: sp.x, clientY: sp.y }));
    const parked = handle().getBoundingClientRect().top;

    // Now approach it: a point a few px BELOW the boundary, i.e. inside row 2's
    // cell, but within reach of the handle.
    const h = handle().getBoundingClientRect();
    const x = h.left + h.width / 2;
    const y = h.top + h.height / 2 + 15;   // mimo hit-area (12px), uvnitř grab radiusu (18px)
    const below = document.elementFromPoint(x, y);
    below?.dispatchEvent(new PointerEvent('pointerover', { pointerId: 1, pointerType: 'mouse', bubbles: true, clientX: x, clientY: y }));

    return Math.abs(handle().getBoundingClientRect().top - parked) < 1;
  })()`);
  check('the handle holds still while the pointer approaches it across the row below', heldWhileApproaching === true);

  // It must still follow a deliberate move to another row.
  const movedOnRealChange = await eval_(`(() => {
    const target = cell(3, 'role');
    const p = centre(target);
    target.dispatchEvent(new PointerEvent('pointerover', { pointerId: 1, pointerType: 'mouse', bubbles: true, clientX: p.x, clientY: p.y }));
    return anchoredIn(target);
  })()`);
  check('the handle still follows a deliberate move to another row', movedOnRealChange === true);
  await shot('02-hover');

  // ── 2. Dragging paints, and touches neither data nor the wire ─────────
  const drag = await eval_(`(() => {
    cell(0, 'role').querySelector('select').focus();
    const before = { calls: __calls, roles: [0,1,2,3].map(roleOf) };
    grab();
    dragOver(cell(1, 'role'));
    dragOver(cell(2, 'role'));
    return {
      callsDuring: __calls - before.calls,
      rolesUnchanged: JSON.stringify([0,1,2,3].map(roleOf)) === JSON.stringify(before.roles),
      painted: painted('role'),
      overlayShown: ! overlay().hidden,
      bodyFilling: document.body.classList.contains('wire-filling'),
    };
  })()`);
  check('no request is sent while dragging', drag.callsDuring === 0, `calls=${drag.callsDuring}`);
  check('no value changes while dragging', drag.rolesUnchanged === true);
  check('the covered rows are previewed, the source row is not',
    JSON.stringify(drag.painted) === JSON.stringify([false, true, true, false]),
    JSON.stringify(drag.painted));
  check('the range overlay and the drag body class are active', drag.overlayShown && drag.bodyFilling);
  await shot('03-dragging');

  // ── 3. Escape abandons the drag, writing nothing ──────────────────────
  const escaped = await eval_(`(async () => {
    const callsBefore = __calls, roles = [0,1,2,3].map(roleOf);
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    await new Promise((r) => setTimeout(r, 400));
    return {
      calls: __calls - callsBefore,
      painted: painted('role').some(Boolean),
      overlayHidden: overlay().hidden,
      bodyFilling: document.body.classList.contains('wire-filling'),
      rolesUnchanged: JSON.stringify([0,1,2,3].map(roleOf)) === JSON.stringify(roles),
    };
  })()`);
  check('Escape sends no request', escaped.calls === 0, `calls=${escaped.calls}`);
  check('Escape clears the preview, overlay and body class',
    ! escaped.painted && escaped.overlayHidden && ! escaped.bodyFilling);
  check('Escape leaves every value untouched', escaped.rolesUnchanged === true);
  await shot('04-escaped');

  // ── 4. Release sends exactly one request and writes the range ─────────
  const filled = await eval_(`(async () => {
    // Make the source distinct from the rows below, so the fill is observable.
    const src = cell(0, 'role').querySelector('select');
    src.value = src.value === 'admin' ? 'viewer' : 'admin';
    src.dispatchEvent(new Event('change', { bubbles: true }));
    await new Promise((r) => setTimeout(r, 1200));

    const want = src.value;
    src.focus();
    const callsBefore = __calls;
    grab();
    dragOver(cell(1, 'role'));
    dragOver(cell(2, 'role'));
    release(cell(2, 'role'));
    await new Promise((r) => setTimeout(r, 1500));

    return {
      calls: __calls - callsBefore,
      want,
      roles: [0,1,2,3].map(roleOf),
      painted: painted('role').some(Boolean),
    };
  })()`);
  check('releasing sends exactly one request for the whole range', filled.calls === 1, `calls=${filled.calls}`);
  check('the dragged rows take the source value, the rest do not',
    filled.roles[1] === filled.want && filled.roles[2] === filled.want,
    JSON.stringify(filled.roles));
  check('the preview is cleared after the write', filled.painted === false);
  await shot('05-filled');

  // A full reload is the only honest proof the write reached the database —
  // and it also catches the optimistic value disagreeing with the stored one.
  await page('Page.navigate', { url });
  await sleep(2500);
  await eval_(helpers);
  const persisted = await eval_(`[0,1,2,3].map(roleOf)`);
  check('the fill survives a reload, and matches what the cells showed',
    persisted[1] === filled.want && persisted[2] === filled.want && persisted[0] === filled.want,
    `server=${JSON.stringify(persisted)} want=${filled.want}`);
  await shot('06-reloaded');

  // ── 5. A toggle fill carries a real boolean ───────────────────────────
  // The cell's data attribute holds '1'/'0'; sending that string instead of the
  // boolean is the mistake this guards against.
  const toggled = await eval_(`(async () => {
    const el = cell(0, 'is_active').querySelector('[data-record-key]');
    const type = typeof Alpine.$data(el).value;
    cell(0, 'is_active').querySelector('button').focus();
    const source = activeOf(0);
    grab();
    dragOver(cell(3, 'is_active'));
    release(cell(3, 'is_active'));
    await new Promise((r) => setTimeout(r, 1500));
    return { type, source, last: activeOf(3), version: el.dataset.recordVersion };
  })()`);
  check('a toggle fill sends a boolean, not its serialized form', toggled.type === 'boolean', `typeof=${toggled.type}`);
  check('the toggle range adopts the source state', toggled.last === toggled.source, `${toggled.source} -> ${toggled.last}`);
  await shot('07-toggle-filled');

  // ── 6. Two fills in quick succession (last, it leaves the table churned) ─────────────────────────────────
  // The reported bug: a second fill sent before the first response landed
  // carried the versions the DOM held BEFORE the first write, so the server
  // refused the whole range and the user's last drag was silently rolled back.
  //
  // Driven through the controller rather than two synthesised drags: the race
  // is a sub-response timing window, and a drag pair cannot hit it reliably.
  // write() is the very method a drag ends in.
  const raced = await eval_(`(async () => {
    const ctrl = Alpine.$data(root());
    const editRoot = (i) => cell(i, 'role').querySelector('[data-record-key]');
    const describe = (i) => ({
      row: i, col: 0, cell: cell(i, 'role'), el: editRoot(i),
      recordKey: editRoot(i).dataset.recordKey,
      version: editRoot(i).dataset.recordVersion,
      serialized: editRoot(i).dataset.serverValue ?? '',
    });

    // Settle on a known value first.
    Alpine.\$data(editRoot(0)).value = 'admin';
    await ctrl.write('role', describe(0), [1, 2, 3].map(describe));
    await new Promise((r) => setTimeout(r, 400));

    const src = Alpine.\$data(editRoot(0));
    src.value = 'editor';
    ctrl.write('role', describe(0), [1, 2, 3].map(describe));
    await new Promise((r) => setTimeout(r, 30));   // first response not back yet
    src.value = 'viewer';
    ctrl.write('role', describe(0), [1, 2, 3].map(describe));
    await new Promise((r) => setTimeout(r, 3000));

    const html = await fetch(location.href, { headers: { 'X-Requested-With': 'fetch' } }).then((r) => r.text());
    const stored = [...html.matchAll(/data-column-name="role"[\\s\\S]{0,300}?data-server-value="([^"]*)"/g)].map((m) => m[1]);

    return { dom: [1, 2, 3].map(roleOf), stored: stored.slice(1, 4) };
  })()`);
  check('a second fill sent before the first returns still lands on every row',
    raced.dom.every((v) => v === 'viewer'), JSON.stringify(raced.dom));
  check('and the rows it wrote really hold the last value, not the first',
    raced.stored.every((v) => v === 'viewer'), JSON.stringify(raced.stored));
  await shot('06-raced-fills');

  // ── 7. Nothing broke on the way ───────────────────────────────────────
  const no419 = ! badResponses.some((r) => r.startsWith('419'));
  check('no 419 on any fill', no419, badResponses.join('; ') || 'none');
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
