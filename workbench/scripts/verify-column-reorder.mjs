import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for column reordering on a table that also has a selection
 * column, row drag handles and a row context menu — the combination where the
 * header cells and the body cells do NOT line up by position.
 *
 * A body row is not the header row with different tags. It leads with the
 * teleport <template> that carries its context menu, and the drag handle,
 * selection cell and actions column each sit on one side or the other. Mirroring
 * a header move onto the body by index therefore moved whichever cell happened
 * to occupy that offset: dragging a column header parked the checkbox column
 * between two data columns while the data itself never moved.
 *
 * The drag itself is driven the way Sortable drives it — move the header cell,
 * then hand the real onEnd handler the indices Sortable reports — because
 * Sortable runs with forceFallback and does not respond to synthesised pointer
 * input.
 *
 * Exit 0 = all passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/sortable-columns';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9342);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-column-reorder-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-column-reorder-${Date.now()}`);
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

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3500);

  await eval_(`
    window.root = document.querySelector('[x-data*="wireSortable"]');
    window.d = Alpine.$data(root);
    // Header order, and the body row's cells labelled by what they are.
    window.headerCols = () => [...document.querySelectorAll('thead th[data-sortable-column]')]
      .map((th) => th.getAttribute('data-sortable-column'));
    window.rowCells = (i = 0) => [...document.querySelectorAll('tbody tr[data-row-key]')[i].children]
      .map((el) => el.tagName === 'TEMPLATE'
        ? 'TEMPLATE'
        : (el.getAttribute('data-column') ?? (el.hasAttribute('data-select-cell') ? 'SELECT' : 'FIXED')));
    window.bodyCols = (i = 0) => rowCells(i).filter((c) => !['TEMPLATE', 'SELECT', 'FIXED'].includes(c));
    // Drive the production onEnd the way Sortable does: move the header cell,
    // then report the indices it would have reported for that move.
    //
    // detach=true silences the Livewire call, leaving only the client-side
    // mirroring. That is the half this driver is really about: with the server
    // in play its re-render rewrites the body wholesale a moment later, which
    // masks a wrong optimistic update — and does NOT mask it when the server
    // declines to save (no authenticated user, say), where the mangled DOM is
    // simply what the user is left with.
    // Sibling index, computed from the DOM rather than Sortable.utils.index:
    // SortableJS is bundled inside wire-sortable.js and is no longer a global.
    window.thIndex = (el) => [...el.parentElement.children].indexOf(el);
    window.dragHeader = (name, toEnd = true, detach = false) => {
      const ths = [...document.querySelectorAll('thead th')];
      const moved = ths.find((t) => t.getAttribute('data-sortable-column') === name);
      const target = toEnd ? ths[ths.length - 1] : ths.find((t) => t.hasAttribute('data-sortable-column'));
      const oldIndex = thIndex(moved);
      toEnd ? target.after(moved) : target.before(moved);
      const newIndex = thIndex(moved);

      const original = d.getLivewireComponent;
      if (detach) d.getLivewireComponent = () => null;
      try {
        d.columnSortableInstance.options.onEnd({ oldIndex, newIndex });
      } finally {
        d.getLivewireComponent = original;
      }

      return { oldIndex, newIndex };
    };
    true;
  `);

  const booted = await eval_(`!!root && !!d?.columnSortableInstance && headerCols().length >= 4`);
  check('the column-reorder fixture booted with a Sortable instance on the header', booted,
    JSON.stringify(await eval_(`headerCols()`)));

  const layout = await eval_(`JSON.stringify(rowCells())`);
  check('the body row really is offset from the header (template + selection cell)',
    JSON.parse(layout).includes('TEMPLATE') && JSON.parse(layout).includes('SELECT'), layout);

  const before = JSON.parse(await eval_(`JSON.stringify({ header: headerCols(), body: bodyCols(), cells: rowCells() })`));
  check('header and body start aligned',
    JSON.stringify(before.header) === JSON.stringify(before.body), JSON.stringify(before.body));
  await shot('01-before');

  // ── the client-side mirroring, on its own ────────────────────────────────
  // No server round trip, so what the body looks like here is exactly what the
  // reorder produced — the state a user sees until (and unless) a re-render
  // replaces it.
  const optimistic = JSON.parse(await eval_(`(() => {
    dragHeader('title', true, true);
    return JSON.stringify({ header: headerCols(), body: bodyCols(), cells: rowCells() });
  })()`));
  check('the body mirrors the header without a server round trip',
    JSON.stringify(optimistic.header) === JSON.stringify(optimistic.body),
    `header=${JSON.stringify(optimistic.header)} body=${JSON.stringify(optimistic.body)}`);
  check('mirroring moves column cells only, never the checkbox or the handle',
    optimistic.cells.indexOf('SELECT') === before.cells.indexOf('SELECT')
      && optimistic.cells.indexOf('TEMPLATE') === before.cells.indexOf('TEMPLATE')
      && optimistic.cells.indexOf('FIXED') === before.cells.indexOf('FIXED'),
    JSON.stringify(optimistic.cells));
  await shot('02-optimistic');

  // Put it back for the round-trip run below.
  await eval_(`dragHeader('title', false, true)`);
  await sleep(200);

  // ── drag the first column to the end, server included ────────────────────
  const moved = JSON.parse(await eval_(`JSON.stringify(dragHeader('title', true))`));
  await sleep(600);
  const after = JSON.parse(await eval_(`JSON.stringify({ header: headerCols(), body: bodyCols(), cells: rowCells() })`));

  check('the header moved the dragged column to the end',
    after.header[after.header.length - 1] === 'title',
    `${JSON.stringify(before.header)} → ${JSON.stringify(after.header)}`);
  check('the body columns follow the header exactly',
    JSON.stringify(after.header) === JSON.stringify(after.body),
    `header=${JSON.stringify(after.header)} body=${JSON.stringify(after.body)}`);
  // The regression this driver exists for: the checkbox used to be the cell
  // that moved, landing between two data columns.
  check('the selection cell, drag handle and menu template stay where they were',
    after.cells.indexOf('SELECT') === before.cells.indexOf('SELECT')
      && after.cells.indexOf('TEMPLATE') === before.cells.indexOf('TEMPLATE')
      && after.cells.indexOf('FIXED') === before.cells.indexOf('FIXED'),
    JSON.stringify(after.cells));
  await shot('03-after-move-to-end');

  // Every row, not just the first.
  const allRows = await eval_(`(() => {
    const want = JSON.stringify(headerCols());
    const rows = document.querySelectorAll('tbody tr[data-row-key]').length;
    let bad = 0;
    for (let i = 0; i < rows; i++) if (JSON.stringify(bodyCols(i)) !== want) bad++;
    return JSON.stringify({ rows, bad });
  })()`);
  check('every body row was reordered, not only the first', JSON.parse(allRows).bad === 0, allRows);

  // ── drag it back to the front ────────────────────────────────────────────
  await eval_(`dragHeader('title', false)`);
  await sleep(600);
  const back = JSON.parse(await eval_(`JSON.stringify({ header: headerCols(), body: bodyCols(), cells: rowCells() })`));
  check('dragging back to the front realigns just as well',
    back.header[0] === 'title' && JSON.stringify(back.header) === JSON.stringify(back.body),
    `header=${JSON.stringify(back.header)} body=${JSON.stringify(back.body)}`);
  check('the fixed cells are still untouched after the second move',
    back.cells.indexOf('SELECT') === before.cells.indexOf('SELECT')
      && back.cells.indexOf('TEMPLATE') === before.cells.indexOf('TEMPLATE'),
    JSON.stringify(back.cells));
  await shot('04-after-move-back');

  const alpineErrors = consoleErrors.filter((e) => /Alpine|Expression Error|SyntaxError|TypeError/i.test(e));
  check('no Alpine/JS errors during the reorders', alpineErrors.length === 0,
    alpineErrors.slice(0, 3).join(' | '));
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
if (failed.length) {
  console.log(`\n${failed.length} check(s) FAILED.`);
  process.exitCode = process.exitCode ?? 1;
} else {
  console.log(`\nAll ${results.length} checks passed.`);
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
