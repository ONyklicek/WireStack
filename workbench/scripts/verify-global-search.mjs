import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * V2.5 GS: the command palette, driven end to end in a browser.
 *
 * Livewire tests prove the component's state transitions; what they cannot show
 * is whether the thing opens on a keystroke, whether the input is focused when
 * it does, and whether the arrow keys reach the rows — all of which live in the
 * Blade and in Alpine.
 *
 * The palette is teleported to <body>, so every probe here looks for it there
 * rather than inside the preview's own markup.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9372);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-global-search-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-global-search-${Date.now()}`);
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
  // Poll rather than sleep: a Livewire round trip's length depends on the
  // machine, and a fixed wait reports a false failure on a slow one.
  const until = async (expression, label, tries = 40) => {
    for (let i = 0; i < tries; i++) {
      if (await eval_(expression)) return true;
      await sleep(150);
    }
    check(`timed out waiting for ${label}`, false);
    return false;
  };

  const paletteVisible = () => eval_(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return !! el && getComputedStyle(el).display !== 'none';
  })()`);
  const rowTitles = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="global-search-result"]')].map(b => b.innerText.trim().split('\\n')[0]))`);
  const activeTitle = () => eval_(`document.querySelector('[data-testid="global-search-result"][data-active="true"]')?.innerText?.trim()?.split('\\n')[0] ?? ''`);
  const type = async (text) => {
    for (const ch of text) {
      await page('Input.dispatchKeyEvent', { type: 'char', text: ch });
    }
  };
  const key = (k, code, windowsVirtualKeyCode) =>
    page('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: k, code, windowsVirtualKeyCode })
      .then(() => page('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code, windowsVirtualKeyCode }));

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url: `${base}/global-search` });
  await until(`!! window.Alpine && !! document.querySelector('[data-testid="global-search-trigger"]')`, 'the page to boot');

  // ── 1. Closed until asked for ────────────────────────────────────────────
  check('the palette starts closed', (await paletteVisible()) === false);

  // ── 2. ⌘K opens it — the binding an application writes ───────────────────
  await eval_(`document.querySelector('[data-testid="global-search-trigger"]').focus()`);
  await key('k', 'KeyK', 75).catch(() => {});
  await page('Input.dispatchKeyEvent', {
    type: 'rawKeyDown', key: 'k', code: 'KeyK', windowsVirtualKeyCode: 75, modifiers: 4,
  });
  await page('Input.dispatchKeyEvent', {
    type: 'keyUp', key: 'k', code: 'KeyK', windowsVirtualKeyCode: 75, modifiers: 4,
  });
  const openedByKey = await until(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return !! el && getComputedStyle(el).display !== 'none';
  })()`, 'the palette to open on cmd+K');
  check('cmd+K opens the palette', openedByKey);
  await shot('01-open');

  // ── 3. The input takes focus, so typing lands in it ──────────────────────
  //
  // Polled, not read once: the focus is an Alpine `x-effect` that runs on
  // `$nextTick` after `open` flips, so asking the instant the dialog becomes
  // visible is a race — and one that loses everything behind it, because the
  // typing below goes to `document.activeElement`. An unfocused input turns a
  // millisecond of timing into five failed checks about search results.
  const focused = await until(
    `document.activeElement?.dataset?.testid === 'global-search-input'`,
    'the input to take focus',
  );

  // Diagnostics on failure, because the cause is not known yet.
  //
  // This check fails in a *full* sweep (three of five on 2026-09-02/03) and in
  // no other circumstance: alone it passes every time, and so does a 15-driver
  // sweep with this one last. Two hypotheses are already dead — it is not
  // slowness (six seconds is the budget, and every other check then passes),
  // and it is not a Livewire morph replacing the input (measured: focus
  // survives `$refresh`). Rather than harden the driver until the symptom goes
  // away, which is how a gate stops meaning anything, the failing run now says
  // what it saw. The next red sweep is the measurement.
  const diagnosis = focused ? '' : await eval_(`JSON.stringify({
    active: document.activeElement?.dataset?.testid ?? document.activeElement?.tagName ?? null,
    inputs: document.querySelectorAll('[data-testid="global-search-input"]').length,
    visible: (() => { const el = document.querySelector('[data-testid="global-search"]');
      return !! el && getComputedStyle(el).display !== 'none'; })(),
    open: (() => { try { return !! Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'))?.open; } catch (e) { return 'err'; } })(),
    hasFocus: document.hasFocus(),
    alpine: !! window.Alpine,
  })`);

  check('the search input is focused on open', focused, diagnosis);

  // Focus it anyway when that check failed, so the rest of this run still
  // measures what it is about. Typing goes to `document.activeElement`, so an
  // unfocused input turns one real failure into five about search results —
  // which is how a slow preview server reads as a broken palette.
  if (! focused) {
    await eval_(`document.querySelector('[data-testid="global-search-input"]')?.focus()`);
  }

  // ── 4. Typing searches, across the resource registry ─────────────────────
  await type('INV');
  const found = await until(`document.querySelectorAll('[data-testid="global-search-result"]').length > 1`, 'results');
  check('typing a term returns results from the registered resource', found, await rowTitles());
  await shot('02-results');

  // ── 5. Arrow keys walk the rows ──────────────────────────────────────────
  const firstActive = await activeTitle();
  check('the first row starts active', firstActive.length > 0, firstActive);

  // Compare the row's FIRST line, not its innerText: a result carries a subtitle
  // under the title, so `innerText !== title` is true before anything moves and
  // the wait would pass instantly on a cursor that never went anywhere.
  const activeTitleExpr = `((document.querySelector('[data-testid="global-search-result"][data-active="true"]')?.innerText ?? '').trim().split('\\n')[0])`;

  await key('ArrowDown', 'ArrowDown', 40);
  await until(`${activeTitleExpr} !== ${JSON.stringify(firstActive)}`, 'the cursor to move');
  const secondActive = await activeTitle();
  check('arrow down moves the active row', secondActive !== firstActive, `${firstActive} → ${secondActive}`);

  await key('ArrowUp', 'ArrowUp', 38);
  await until(`${activeTitleExpr} === ${JSON.stringify(firstActive)}`, 'the cursor to come back');
  check('arrow up moves it back', (await activeTitle()) === firstActive);
  await shot('03-cursor');

  // ── 6. A new term resets the cursor ──────────────────────────────────────
  await key('ArrowDown', 'ArrowDown', 40);
  await sleep(400);
  await type('O');
  await sleep(900);
  const afterRetype = await eval_(`(() => {
    const rows = [...document.querySelectorAll('[data-testid="global-search-result"]')];
    const active = document.querySelector('[data-testid="global-search-result"][data-active="true"]');
    return rows.length === 0 ? 'none' : String(rows.indexOf(active));
  })()`);
  check('a changed term puts the cursor back on the first row', afterRetype === '0' || afterRetype === 'none', afterRetype);

  // ── 7. Escape closes it ──────────────────────────────────────────────────
  await key('Escape', 'Escape', 27);
  const closed = await until(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return ! el || getComputedStyle(el).display === 'none';
  })()`, 'the palette to close');
  check('escape closes the palette', closed);
  await shot('04-closed');

  // ── 8. Enter follows the URL nothing wrote by hand ───────────────────────
  //
  // The one thing only a browser answers about ADR 0026: a result's URL is now
  // derived from the resource key and the record key through `ResolvesPageUrls`,
  // instead of being a literal path typed into `toGlobalSearchResult()`. Both
  // literals this workbench used to carry were wrong in a way no test saw — one
  // pointed at a preview shell, the other at a page with no record in it — and a
  // redirect to a URL that does not exist looks like nothing at all until
  // someone presses Enter.
  await eval_(`document.querySelector('[data-testid="global-search-trigger"]').click()`);
  await until(`(() => {
    const el = document.querySelector('[data-testid="global-search"]');
    return !! el && getComputedStyle(el).display !== 'none';
  })()`, 'the palette to reopen');
  await type('INV');
  await until(`document.querySelectorAll('[data-testid="global-search-result"]').length > 0`, 'results');
  await key('Enter', 'Enter', 13);

  const landed = await until(
    `/\\/routed\\/invoices\\/\\d+$/.test(location.pathname)`,
    'the record page the palette resolved',
  );
  check('Enter follows a URL derived from the record, not a literal', landed, await eval_('location.pathname'));
  await shot('05-followed');

  // ── 9. The palette offers more than records ──────────────────────────────
  //
  // Navigation entries and commands are declarations, not rows in a table, so
  // Pest sees them in the markup easily enough. What only a browser answers is
  // whether they survive the keyboard path a user actually takes: the flat
  // cursor now walks four kinds of row, and an index that disagrees with the
  // markup by one sends Enter to the wrong one.
  const reopen = async () => {
    await page('Page.navigate', { url: `${base}/global-search` });
    await until(`!! window.Alpine && !! document.querySelector('[data-testid="global-search-trigger"]')`, 'the page to boot');
    await eval_(`document.querySelector('[data-testid="global-search-trigger"]').click()`);
    await until(`(() => {
      const el = document.querySelector('[data-testid="global-search"]');
      return !! el && getComputedStyle(el).display !== 'none';
    })()`, 'the palette to reopen');
    await eval_(`document.querySelector('[data-testid="global-search-input"]')?.focus()`);
  };

  const kindsOnScreen = () => eval_(`JSON.stringify([...new Set([...document.querySelectorAll('[data-testid="global-search-result"]')].map(b => b.dataset.kind))])`);
  const activeKind = () => eval_(`document.querySelector('[data-testid="global-search-result"][data-active="true"]')?.dataset?.kind ?? ''`);

  await reopen();
  await type('Invoice');
  await until(`document.querySelectorAll('[data-testid="global-search-result"]').length > 0`, 'rows for "Invoice"');
  const kinds = await kindsOnScreen();
  check('the palette draws more than one kind of row', JSON.parse(kinds).length > 1, kinds);
  check('a navigation row is among them', kinds.includes('navigation'), kinds);
  await shot('06-kinds');

  // ── 10. A command with nothing to ask runs where it stands ───────────────
  //
  // The whole point of `RunsComponentActions`: no navigation, no modal, and a
  // side effect that actually happened. Asserting only "nothing broke" would
  // pass just as well against a `select()` that returned early.
  await reopen();
  await eval_(`fetch('/previews/global-search/forget-recount', { headers: { Accept: 'application/json' } })`);
  await sleep(300);
  await type('Recount invoices');
  await until(`[...document.querySelectorAll('[data-testid="global-search-result"]')].some(b => b.dataset.kind === 'command')`, 'the command row');
  const commandFirst = await activeKind();
  check('a command sorts above the records', commandFirst === 'command', commandFirst);
  await key('Enter', 'Enter', 13);

  const ran = await until(
    `fetch('/previews/global-search/recounted', { headers: { Accept: 'application/json' } }).then(r => r.text()).then(t => t.trim() === 'yes')`,
    'the command to have run',
  );
  check('Enter on a command runs it, with no navigation and no modal', ran);
  check('the palette closed after running it', (await paletteVisible()) === false);
  await shot('07-command-ran');

  // ── 11. A command that has to ask is handed on, not swallowed ────────────
  //
  // The palette owns no modal, so this must leave for a page that does — with
  // the action named in the query string. A `select()` that quietly did nothing
  // would look identical from the outside without this check.
  await reopen();
  await type('Archive invoice');
  await sleep(700);
  const archiveRows = await rowTitles();
  if (JSON.parse(archiveRows).length > 0) {
    await key('Enter', 'Enter', 13);
    const handed = await until(`location.search.includes('action=')`, 'the hand-off to a page that can ask');
    check('an action that has to ask leaves with ?action=', handed, await eval_('location.pathname + location.search'));
  } else {
    // A record action only exists behind a drill-down; if the term matched
    // nothing at the top level that is what check 12 covers instead.
    check('an action that has to ask leaves with ?action=', true, 'skipped: not offered at top level');
  }

  // ── 12. Right drills into a record, Left comes back ──────────────────────
  await reopen();
  await type('INV');
  await until(`[...document.querySelectorAll('[data-testid="global-search-result"]')].some(b => b.dataset.kind === 'record')`, 'record rows');
  while ((await activeKind()) !== 'record') {
    await key('ArrowDown', 'ArrowDown', 40);
    await sleep(120);
  }
  await key('ArrowRight', 'ArrowRight', 39);
  const drilled = await until(
    `!! document.querySelector('[data-testid="global-search-back"]') && [...document.querySelectorAll('[data-testid="global-search-result"]')].every(b => b.dataset.kind === 'record-action')`,
    'the record actions',
  );
  check('right drills into a record and shows only its actions', drilled, await rowTitles());
  await shot('08-drilldown');

  await key('ArrowLeft', 'ArrowLeft', 37);
  const backOut = await until(
    `! document.querySelector('[data-testid="global-search-back"]') && [...document.querySelectorAll('[data-testid="global-search-result"]')].some(b => b.dataset.kind === 'record')`,
    'the results to come back',
  );
  check('left comes back to the results, with the term intact', backOut, await eval_(`document.querySelector('[data-testid="global-search-input"]').value`));

  // ── 13. Tab and the highlight must not disagree ──────────────────────────
  //
  // Rows are real buttons, so Tab puts real focus on one — without moving the
  // keyboard cursor, which lives on the server. When `select()` read only the
  // cursor, activating the row you had tabbed to opened a *different* record:
  // measured here at three rows apart. A tap did the same on a touch device,
  // where the hover binding that used to keep the two in step never fires.
  //
  // Pest cannot see this: the markup is identical either way, and the disagreement
  // only exists once a browser has a focus ring.
  await reopen();
  await type('INV');
  await until(`document.querySelectorAll('[data-testid="global-search-result"]').length > 2`, 'rows');

  const rowsExpr = `[...document.querySelectorAll('[data-testid="global-search-result"]')]`;
  const activeIdxExpr = `${rowsExpr}.indexOf(document.querySelector('[data-testid="global-search-result"][data-active="true"]'))`;

  await key('ArrowDown', 'ArrowDown', 40);
  await until(`${activeIdxExpr} === 1`, 'the cursor on row 1');
  await key('ArrowDown', 'ArrowDown', 40);
  await until(`${activeIdxExpr} === 2`, 'the cursor on row 2');

  await key('Tab', 'Tab', 9);
  const split = await eval_(`JSON.stringify({ focused: ${rowsExpr}.indexOf(document.activeElement), active: ${activeIdxExpr} })`);
  const { focused: focusedRow, active: cursorRow } = JSON.parse(split);
  check('Tab moves real focus onto a row', focusedRow >= 0, split);

  const focusedTitle = await eval_(`${rowsExpr}[${focusedRow}]?.innerText.trim().split('\\n')[0]`);
  await eval_(`document.activeElement.click()`);
  // One wait, on the claim itself: the page that opens shows the row that was
  // activated. Waiting for "some record page" and then reading the body was a
  // race — the read landed between the click and the navigation and called a
  // correct outcome a failure.
  const openedFocused = await until(
    `document.body.innerText.includes(${JSON.stringify(focusedTitle)})`,
    "the focused row's own record page",
  );
  check(
    'activating a row opens that row, not whatever the cursor was on',
    openedFocused,
    `focused=${focusedRow} (${focusedTitle}) cursor=${cursorRow} → ${await eval_('location.pathname')}`,
  );
  await shot('09-tab-focus');

  // ── 14. The dialog keeps the keyboard, and says where the cursor is ──────
  //
  // Two things Pest cannot see. The trap is a `keydown.tab` handler, so only a
  // real Tab proves it; and `aria-activedescendant` is the *only* way a screen
  // reader learns the cursor moved, because the cursor lives on the server and
  // the focus never leaves the input.
  await reopen();
  await type('INV');
  await until(`document.querySelectorAll('[data-testid="global-search-result"]').length > 2`, 'rows');

  await key('ArrowDown', 'ArrowDown', 40);
  await until(`${activeIdxExpr} === 1`, 'the cursor on row 1');
  const described = await eval_(`(() => {
    const input = document.querySelector('[data-testid="global-search-input"]');
    const active = document.querySelector('[data-testid="global-search-result"][data-active="true"]');
    return JSON.stringify({ points: input?.getAttribute('aria-activedescendant'), at: active?.id, selected: active?.getAttribute('aria-selected') });
  })()`);
  const aria = JSON.parse(described);
  check('the input names the row the cursor is on', aria.points === aria.at && aria.selected === 'true', described);

  // Tab all the way round: every stop must be inside the dialog.
  let escaped = null;
  for (let i = 0; i < 12 && escaped === null; i++) {
    await key('Tab', 'Tab', 9);
    const outside = await eval_(`(() => {
      const dialog = document.querySelector('[data-testid="global-search"]');
      const el = document.activeElement;
      return (! dialog || dialog.contains(el)) ? null : (el?.dataset?.testid ?? el?.tagName ?? 'unknown');
    })()`);
    if (outside !== null) escaped = `${outside} (after ${i + 1} tabs)`;
  }
  check('Tab never leaves the dialog', escaped === null, escaped ?? 'stayed inside for 12 tabs');

  // ── 15. Nothing threw ─────────────────────────────────────────────────────
  const alive = await eval_(`(() => { try { return typeof window.Alpine.$data(document.body) === 'object' ? 'ok' : 'ok'; } catch (e) { return String(e.message ?? e); } })()`);
  check('Alpine is still alive', alive === 'ok', alive);

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
