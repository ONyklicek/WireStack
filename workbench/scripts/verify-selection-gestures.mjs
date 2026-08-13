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
 * The characterized bugs are all fixed and asserted in their final form:
 * ranges write base ∪ [anchor…active] and never rewrite the mode (C3, C9,
 * the base checks), and the header checkbox edits the exclusions in all
 * mode (C10).
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

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
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
  '--hide-scrollbars',
  // Headless Chrome backgrounds a window it never shows and throttles the renderer.
  // Polling (see waitFor) wakes the JS thread, but NOT requestAnimationFrame — so an
  // Alpine leave transition never finishes and a closed modal stays on screen with
  // `show: false` and `display: block`. That is what made "Escape closes the shortcut
  // help" fail on unmodified code. Both halves are needed: these flags for anything
  // animated, waitFor for anything awaited.
  '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows',
  '--disable-renderer-backgrounding',
  `--remote-debugging-port=${devtoolsPort}`,
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
  /*
   * Wait for a condition instead of sleeping at it.
   *
   * Headless Chrome backgrounds a window it never shows and throttles the renderer, so
   * a driver sitting in a dead `sleep()` watches a page that is barely running — the
   * work it is waiting for lands late and the check reads "nothing happened". Any CDP
   * evaluation wakes the renderer, so polling both waits and keeps the page moving.
   * (Diagnosed on verify-gesture-lab, which this driver's sleeps had the same disease.)
   *
   * The second win is time: this driver slept 80 s against a 180 s cap, so any extra
   * slowness pushed the whole run over and it was killed mid-sweep. Most of these waits
   * resolve in a fraction of their budget.
   *
   * Returns whether the condition ever held, so a real failure still fails.
   */
  const waitFor = async (expression, timeout = 6000) => {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
      if (await eval_(`!!(${expression})`)) return true;
      await sleep(100);
    }

    return false;
  };

  /* A page is ready when its rows are in the DOM and Alpine has wired the table. */
  const waitForTable = async (minRows = 1) => waitFor(
    `(() => { try { return rows().length >= ${minRows} && !!sel(); } catch { return false; } })()`,
    8000,
  );

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

  // Same, but offset from the centre — for hitting an element's padding rather
  // than whatever sits in the middle of it.
  const realClickAt = async (expr, dx, dy) => {
    const box = JSON.parse(await eval_(`(() => {
      const el = ${expr};
      el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2 + ${dx}, y: r.top + r.height / 2 + ${dy} });
    })()`));
    await sleep(150);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: box.x, y: box.y, button: 'left', clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: box.x, y: box.y, button: 'left', clickCount: 1 });
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });

  // ════ Section 1 — one page of 40 rows ═══════════════════════════════════
  await page('Page.navigate', { url: `${base}/table-selection-gestures` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();

  check('the gesture fixture renders all 40 rows on one page', await eval_('rows().length') === 40);

  // ── ARIA: grid semantics and a live region that starts silent ────────────
  // Watch the region the way a screen reader does, from before the first
  // change: a live region speaks on MUTATION, so a value that merely reads
  // correctly afterwards proves nothing about anything having been announced.
  await eval_(`
    window.live = () => $q('[data-testid="selection-live"]');
    window.__liveMutations = [];
    new MutationObserver(() => { window.__liveMutations.push(live().textContent); })
      .observe(live(), { childList: true, characterData: true, subtree: true });
    true;
  `);
  const ariaStatic = JSON.parse(await eval_(`(() => {
    const t = $q('table[role=grid]');
    return JSON.stringify({
      role: t?.getAttribute('role'),
      rowcount: t?.getAttribute('aria-rowcount'),
      multi: t?.getAttribute('aria-multiselectable'),
      headerIndex: t?.querySelector('thead tr')?.getAttribute('aria-rowindex'),
      firstBody: rows()[0].getAttribute('aria-rowindex'),
      lastBody: rows()[39].getAttribute('aria-rowindex'),
      liveText: live()?.textContent,
      liveMode: live()?.getAttribute('aria-live'),
    });
  })()`));
  check('the grid announces its shape: role, total row count, multiselectable',
    ariaStatic.role === 'grid' && ariaStatic.rowcount === '41' && ariaStatic.multi === 'true',
    JSON.stringify(ariaStatic));
  check('row indices run through the whole grid, header first',
    ariaStatic.headerIndex === '1' && ariaStatic.firstBody === '2' && ariaStatic.lastBody === '41',
    `header=${ariaStatic.headerIndex} first=${ariaStatic.firstBody} last=${ariaStatic.lastBody}`);
  // A live region has to be in the DOM and empty before it can announce: filled
  // at boot, a screen reader would read out a selection nobody made.
  check('the live region is present and silent before anything is selected',
    ariaStatic.liveMode === 'polite' && ariaStatic.liveText === '',
    `text=${JSON.stringify(ariaStatic.liveText)}`);

  // ── the active-row marker carries a non-color signal at 3:1 ─────────────
  // Colour alone fails anyone who cannot separate the two hues, and the tint
  // itself is far under the 3:1 non-text contrast floor — so the marker also
  // draws a stripe down the row's leading cell. Measured through a canvas,
  // because Tailwind 4 emits oklch() and no regex parses that.
  await eval_(`
    window.rgb = (css) => { const c = document.createElement('canvas').getContext('2d'); c.fillStyle = '#000'; c.fillStyle = css; c.fillRect(0,0,1,1); const d = c.getImageData(0,0,1,1).data; return [d[0],d[1],d[2],d[3]/255]; };
    window.lum = (css) => { const [r,g,b] = rgb(css).map(v => { v/=255; return v <= .03928 ? v/12.92 : Math.pow((v+.055)/1.055, 2.4); }); return .2126*r+.7152*g+.0722*b; };
    window.contrast = (a, b) => { const la = lum(a), lb = lum(b); return Math.round(((Math.max(la,lb)+.05)/(Math.min(la,lb)+.05))*100)/100; };
    true;
  `);
  const restingStripe = await eval_(`getComputedStyle(rows()[5].querySelector('td'), '::before').content`);
  check('a resting row draws no marker stripe', restingStripe === 'none', `content=${restingStripe}`);

  const cellBefore = await eval_(`Math.round(rows()[5].querySelector('td').getBoundingClientRect().width)`);
  await realClick(`rows()[5].querySelector('[data-testid="table-cell-name"]')`);
  await sleep(400);
  const marker = JSON.parse(await eval_(`(() => {
    const tr = rows()[5], td = tr.querySelector('td');
    const b = getComputedStyle(td, '::before');
    const plain = getComputedStyle(rows()[9]).backgroundColor;
    const behind = rgb(plain)[3] === 0 ? getComputedStyle(document.body).backgroundColor : plain;
    return JSON.stringify({
      drawn: b.content !== 'none',
      width: b.width,
      stripeVsTint: contrast(b.backgroundColor, getComputedStyle(tr).backgroundColor),
      stripeVsPlain: contrast(b.backgroundColor, behind),
      tintVsPlain: contrast(getComputedStyle(tr).backgroundColor, behind),
      cellWidth: Math.round(td.getBoundingClientRect().width),
    });
  })()`));
  check('the marked row draws a stripe clearing 3:1 against both the tint and a plain row',
    marker.drawn && marker.stripeVsTint >= 3 && marker.stripeVsPlain >= 3,
    JSON.stringify(marker));
  // The tint on its own is the reason the stripe exists — assert it really is
  // as weak as claimed, so this check keeps meaning something.
  check('the background tint alone would not have cleared 3:1',
    marker.tintVsPlain < 3, `tint contrast=${marker.tintVsPlain}`);
  check('marking a row does not move its content sideways',
    marker.cellWidth === cellBefore, `${cellBefore} → ${marker.cellWidth}`);

  // ── the selection cell is a real target, and it is not the row's ─────────
  const target = JSON.parse(await eval_(`(() => {
    const cell = rows()[7].querySelector('[data-select-cell]');
    const r = cell.getBoundingClientRect();
    const corners = [[r.left+3, r.top+3], [r.right-3, r.top+3], [r.left+3, r.bottom-3], [r.right-3, r.bottom-3]];
    return JSON.stringify({
      w: Math.round(r.width), h: Math.round(r.height),
      corners: corners.map(([x,y]) => !! document.elementFromPoint(x,y)?.closest('[data-select-cell]')),
    });
  })()`));
  check('the selection cell clears the 24x24 minimum target size on every corner',
    target.w >= 24 && target.h >= 24 && target.corners.every(Boolean), JSON.stringify(target));

  // A click in the cell's dead space must select — and must NOT fall through
  // into the row's record action (this fixture binds dblclick + a context menu).
  await eval_(`sel().selected = []; sel().clearAnchor()`);
  await sleep(200);
  await realClickAt(`rows()[7].querySelector('[data-select-cell]')`, -22, -18);
  await sleep(500);
  const cornerClick = JSON.parse(await eval_(`JSON.stringify({
    selected: sel().selected.length,
    key: sel().selected[0] ?? null,
    expected: key(7),
    dialog: $qa('[role="dialog"]').some(d => vis(d)),
  })`));
  check('a click in the cell padding selects the row instead of running its action',
    cornerClick.selected === 1 && cornerClick.key === cornerClick.expected && ! cornerClick.dialog,
    JSON.stringify(cornerClick));

  await eval_(`sel().selected = []; sel().clearAnchor()`);
  await sleep(200);

  // ── C1: a checkbox click toggles the row and anchors the next range ──────
  await realClick(`rows()[2].querySelector('[data-testid="table-row-select"]')`);
  await sleep(500);
  const c1a = JSON.parse(await eval_(`JSON.stringify({ selected: sel().selected, anchor: sel().anchorKey, key: key(2) })`));
  check('C1: a checkbox click selects the row and sets the anchor',
    c1a.selected.length === 1 && c1a.selected[0] === c1a.key && c1a.anchor === c1a.key,
    JSON.stringify(c1a));

  // aria-selected and the announcement both follow the click, live.
  const afterSelect = JSON.parse(await eval_(`JSON.stringify({
    selectedRow: rows()[2].getAttribute('aria-selected'),
    otherRow: rows()[3].getAttribute('aria-selected'),
    live: live().textContent,
  })`));
  check('the selected row reports aria-selected and the live region announces the count',
    afterSelect.selectedRow === 'true' && afterSelect.otherRow === 'false'
      && afterSelect.live === '1 of 40 selected',
    JSON.stringify(afterSelect));

  // The row state must survive a server round trip: a printed aria-selected
  // would be morphed back to the server's truth and start lying.
  await eval_(`window.Livewire.first().$commit()`);
  await sleep(900);
  check('aria-selected survives a Livewire morph',
    await eval_(`rows()[2].getAttribute('aria-selected')`) === 'true');

  await realClick(`rows()[2].querySelector('[data-testid="table-row-select"]')`);
  await sleep(500);
  check('C1: a second checkbox click deselects the row',
    await eval_('sel().selected.length') === 0);

  check('clearing the selection is announced too, instead of falling silent',
    await eval_(`live().textContent`) === 'Selection cleared');

  const mutations = await eval_(`JSON.stringify(window.__liveMutations)`);
  const spoken = JSON.parse(mutations);
  check('the live region actually mutated for each change, which is what makes it speak',
    spoken.includes('1 of 40 selected') && spoken.includes('Selection cleared'),
    JSON.stringify(spoken).slice(0, 160));

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
  // base ∪ range: the whole page is one block around the far-edge anchor, so
  // the base is empty and the range owns (and shrinks) the block.
  await eval_(`rows()[0].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[0], 'ArrowDown', { shiftKey: true })`);
  await sleep(300);
  const c3 = await eval_('sel().selected.length');
  check('C3: Shift+ArrowDown after mod+A shrinks the block to 39 instead of collapsing it',
    c3 === 39, `40 → ${c3}`);
  await shot('01-desktop-range');

  // ── base ∪ range: a range owns only the block around its anchor ──────────
  // A block selected earlier survives a disjoint range untouched, and the
  // snapshot makes the range shrinkable — a union alone is monotone.
  await eval_(`sel().deselectAll()`);
  await sleep(300);
  await eval_(`rows()[2].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[2], ' ')`);
  await sleep(200);
  await eval_(`(() => { for (let i = 2; i < 6; i++) fireKey(rows()[i], 'ArrowDown', { shiftKey: true }); })()`);
  await sleep(300);
  await eval_(`rows()[8].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[8], ' ')`);
  await sleep(200);
  await eval_(`(() => { for (let i = 8; i < 10; i++) fireKey(rows()[i], 'ArrowDown', { shiftKey: true }); })()`);
  await sleep(300);
  const union = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, hasBase: sel().isSelected(key(2)) && sel().isSelected(key(6)) })`));
  check('a disjoint Shift+range unions with the block selected before it',
    union.count === 8 && union.hasBase, JSON.stringify(union));

  await eval_(`fireKey(rows()[10], 'ArrowUp', { shiftKey: true })`);
  await sleep(300);
  const shrunkBack = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, hasBase: sel().isSelected(key(2)), rangeTip: sel().isSelected(key(10)) })`));
  check('shrinking the range back keeps the base and drops only the range tip',
    shrunkBack.count === 7 && shrunkBack.hasBase && ! shrunkBack.rangeTip, JSON.stringify(shrunkBack));

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

  // ── Backspace aliases Delete, Shift+F10 opens the menu with focus in it ──
  await eval_(`rows()[2].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[2], 'Backspace')`);
  await sleep(2500);
  const backspace = await eval_(`(document.body.innerText.match(/Archive Record \\d+\\?/) || [null])[0]`);
  check('Backspace fires the Delete-bound action (platform alias, JS-side)', !! backspace, backspace);
  await eval_('cancelModal()');
  await sleep(1800);

  await eval_(`rows()[4].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[4], 'F10')`);
  await sleep(300);
  check('plain F10 stays the browser’s — no menu',
    ! await eval_(`$qa('[data-record-menu]').some(m => vis(m))`));

  await eval_(`fireKey(rows()[4], 'F10', { shiftKey: true })`);
  await sleep(400);
  const f10 = JSON.parse(await eval_(`JSON.stringify({
    open: $qa('[data-record-menu]').some(m => vis(m)),
    focusInMenu: !! document.activeElement?.closest('[data-record-menu]'),
  })`));
  check('Shift+F10 opens the row menu and moves the focus into it',
    f10.open && f10.focusInMenu, JSON.stringify(f10));

  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await eval_(`document.activeElement?.blur()`);
  await sleep(1800);
  const escBack = await eval_(`document.activeElement === document.body ? 'body' : (document.activeElement.dataset?.rowKey ?? document.activeElement.tagName)`);
  check('closing the menu hands the focus back to the active row',
    escBack === await eval_('key(4)'), `focus=${escBack}`);

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

  // ── keyboard: Home/End, PageUp/PageDown, mod+Shift+arrows ────────────────
  await eval_(`sel().deselectAll()`);
  await sleep(300);
  await eval_(`rows()[5].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[5], 'End')`);
  await sleep(300);
  check('End moves the marker to the last row without selecting',
    await eval_(`ctrl().activeKey === key(39) && sel().selected.length === 0`));

  await eval_(`fireKey(rows()[39], 'Home')`);
  await sleep(300);
  check('Home moves the marker to the first row',
    await eval_(`ctrl().activeKey === key(0)`));

  await eval_(`rows()[35].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[35], ' ')`);
  await sleep(200);
  await eval_(`fireKey(rows()[35], 'End', { shiftKey: true })`);
  await sleep(300);
  check('Shift+End ranges from the anchor to the last row',
    await eval_(`sel().selected.length === 5 && ctrl().activeKey === key(39)`));

  await eval_(`fireKey(rows()[39], 'ArrowUp', { shiftKey: true, ctrlKey: true })`);
  await sleep(300);
  const modShift = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, active: ctrl().activeKey, k0: key(0) })`));
  check('mod+Shift+ArrowUp extends the range to the top edge',
    modShift.count === 36 && modShift.active === modShift.k0, JSON.stringify(modShift));

  // PageUp/PageDown derive the jump from the row pitch and the viewport the
  // driver pins to 1400×1200 — compute the expectation from the same DOM.
  await eval_(`sel().deselectAll()`);
  await sleep(300);
  const step = await eval_(`(() => {
    const pitch = rows()[1].getBoundingClientRect().top - rows()[0].getBoundingClientRect().top;
    return Math.max(1, Math.floor(window.innerHeight / pitch));
  })()`);
  await eval_(`rows()[0].focus()`);
  await sleep(150);
  await eval_(`fireKey(rows()[0], 'PageDown')`);
  await sleep(300);
  check('PageDown jumps one viewport of rows',
    await eval_(`ctrl().activeKey`) === String(await eval_(`key(${Math.min(step, 39)})`)),
    `step=${step}`);

  await eval_(`fireKey(rows()[${Math.min(step, 39)}], 'PageUp', { shiftKey: true })`);
  await sleep(300);
  check('Shift+PageUp selects the jumped range',
    await eval_(`sel().selected.length`) === Math.min(step, 39) + 1,
    `expected ${Math.min(step, 39) + 1}`);

  // mod+Shift+A is not the select-page gesture, and mod+PageDown belongs to
  // the browser — neither may touch the selection or the marker.
  const beforeUnbound = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, active: ctrl().activeKey })`));
  await eval_(`fireKey(rows()[0], 'a', { ctrlKey: true, shiftKey: true })`);
  await eval_(`fireKey(rows()[0], 'PageDown', { ctrlKey: true })`);
  await sleep(300);
  const afterUnbound = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, active: ctrl().activeKey })`));
  check('mod+Shift+A and mod+PageDown stay unbound',
    afterUnbound.count === beforeUnbound.count && afterUnbound.active === beforeUnbound.active,
    JSON.stringify(afterUnbound));

  // ── mouse: Shift+click / mod+click / mod+Shift+click ─────────────────────
  // Real clicks with real CDP modifiers; on macOS `mod` must be meta — a real
  // ctrl+click is the secondary click there and Chrome swallows `click`.
  const modBit = process.platform === 'darwin' ? MOD.meta : MOD.ctrl;

  await eval_(`sel().deselectAll()`);
  await sleep(300);

  await realClick(`rows()[3]`, modBit);
  await sleep(400);
  const modClick = JSON.parse(await eval_(`JSON.stringify({ selected: sel().selected, anchor: sel().anchorKey, k3: key(3), active: ctrl().activeKey })`));
  check('mod+click toggles the row outside the checkbox and anchors it',
    modClick.selected.length === 1 && modClick.selected[0] === modClick.k3
      && modClick.anchor === modClick.k3 && modClick.active === modClick.k3,
    JSON.stringify(modClick));

  await realClick(`rows()[7]`, MOD.shift);
  await sleep(400);
  const shiftClick = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, edge: sel().isSelected(key(3)) && sel().isSelected(key(7)) })`));
  check('Shift+click selects the [anchor…clicked] block',
    shiftClick.count === 5 && shiftClick.edge, JSON.stringify(shiftClick));

  await realClick(`rows()[9]`, modBit);
  await sleep(400);
  await realClick(`rows()[11]`, modBit | MOD.shift);
  await sleep(400);
  const addBlock = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, base: sel().isSelected(key(4)), block: sel().isSelected(key(10)) && sel().isSelected(key(11)) })`));
  check('mod+Shift+click adds a whole block and keeps the earlier one',
    addBlock.count === 8 && addBlock.base && addBlock.block, JSON.stringify(addBlock));

  await realClick(`rows()[9]`, modBit);
  await sleep(400);
  check('a second mod+click toggles the row back out',
    await eval_(`sel().selected.length`) === 7 && ! await eval_(`sel().isSelected(key(9))`));

  // A modifier click must never fall through into a bound click action: the
  // gesture table binds Delete/dblclick/context menu, so no dialog may open.
  check('modifier clicks never trigger a record action',
    ! await eval_(`$qa('[role="dialog"]').some(d => vis(d))`));
  await shot('03a-mouse-gestures');

  // ── sweep: drag down the checkbox column ─────────────────────────────────
  const measure = async (expr) => JSON.parse(await eval_(`(() => {
    const r = (${expr}).getBoundingClientRect();
    return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
  })()`));
  // Drag through a path of elements, re-measuring each waypoint after moving
  // toward it: the bulk bar appears the moment the first rows get selected and
  // shifts the whole table down, so coordinates cached before the press point
  // one row off by the time the pointer arrives.
  const drag = async (exprs, { release = true, steps = 6 } = {}) => {
    let cur = await measure(exprs[0]);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: cur.x, y: cur.y, button: 'left', clickCount: 1 });
    for (let s = 1; s < exprs.length; s++) {
      const target = await measure(exprs[s]);
      for (let i = 1; i <= steps; i++) {
        await page('Input.dispatchMouseEvent', {
          type: 'mouseMoved', button: 'left', buttons: 1,
          x: cur.x + ((target.x - cur.x) * i) / steps,
          y: cur.y + ((target.y - cur.y) * i) / steps,
        });
        await sleep(30);
      }
      cur = await measure(exprs[s]);
      await page('Input.dispatchMouseEvent', { type: 'mouseMoved', button: 'left', buttons: 1, x: cur.x, y: cur.y });
      await sleep(30);
    }
    if (release) await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: cur.x, y: cur.y, button: 'left', clickCount: 1 });
    return cur;
  };

  await eval_(`sel().deselectAll()`);
  await eval_(`window.scrollTo(0, 0)`);
  await sleep(400);

  await drag([`rows()[2].querySelector('[data-select-cell]')`, `rows()[6].querySelector('[data-select-cell]')`]);
  await sleep(500);
  const swept = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, span: sel().isSelected(key(2)) && sel().isSelected(key(6)) })`));
  check('a sweep down the checkbox column selects the swept span',
    swept.count === 5 && swept.span, JSON.stringify(swept));

  await sleep(400);
  check('the trailing click after a sweep toggles nothing',
    await eval_(`sel().selected.length`) === 5);

  // Backing up never deselects: sweep 10 → 14, then back to 12.
  await drag([
    `rows()[10].querySelector('[data-select-cell]')`,
    `rows()[14].querySelector('[data-select-cell]')`,
    `rows()[12].querySelector('[data-select-cell]')`,
  ]);
  await sleep(500);
  check('a sweep is additive — backing up keeps everything swept',
    await eval_(`sel().selected.length`) === 10 && await eval_(`sel().isSelected(key(14))`));

  // Outside the checkbox column the drag belongs to native text selection.
  await drag([`cell(2)`, `cell(5)`]);
  await sleep(400);
  const textDrag = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, text: window.getSelection().toString().length > 0 })`));
  check('a drag outside the checkbox column selects text, not rows',
    textDrag.count === 10 && textDrag.text, JSON.stringify(textDrag));
  await eval_(`window.getSelection().removeAllRanges()`);

  // Reduced motion: no transition animates while the sweep runs. Scroll the
  // target rows into view first — a press dispatched past the viewport edge
  // hits nothing and the gesture never arms.
  await page('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
  await eval_(`rows()[21].scrollIntoView({ block: 'center' })`);
  await sleep(300);
  const rmEnd = await drag([
    `rows()[20].querySelector('[data-select-cell]')`,
    `rows()[23].querySelector('[data-select-cell]')`,
  ], { release: false });
  await sleep(200);
  const rmDuration = await eval_(`getComputedStyle(rows()[21].querySelector('[role="checkbox"]')).transitionDuration`);
  await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: rmEnd.x, y: rmEnd.y, button: 'left', clickCount: 1 });
  await page('Emulation.setEmulatedMedia', { features: [] });
  await sleep(300);
  check('prefers-reduced-motion switches the swept transitions off',
    rmDuration === '0s', `transitionDuration=${rmDuration}`);
  await shot('03c-sweep');

  // ── sweep coexists with sortable's row drag handles ──────────────────────
  await page('Page.navigate', { url: `${base}/sortable-overview` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();
  const sortableState = JSON.parse(await eval_(`JSON.stringify({
    rows: rows().length,
    handles: $qa('.wire-sortable-handle').length > 0,
    selectable: !! $q('[data-selection-root]'),
  })`));
  await drag([`rows()[1].querySelector('[data-select-cell]')`, `rows()[4].querySelector('[data-select-cell]')`]);
  await sleep(500);
  const sortSwept = await eval_(`Alpine.$data($q('[data-selection-root]')).selected.length`);
  check('the sweep coexists with sortable: checkbox-column drag selects, handles stay',
    sortableState.handles && sortableState.selectable && sortSwept === 4,
    JSON.stringify({ ...sortableState, swept: sortSwept }));

  await page('Page.navigate', { url: `${base}/table-selection-gestures` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();

  // ── the help documents this table's own record actions ───────────────────
  // Same key, different legend: this table has record actions, so the actions
  // section names them and the onKey('Delete') binding shows its Backspace
  // alias next to it.
  await eval_(`window.help = () => $q('[data-testid="shortcut-help"]')?.closest('[role="dialog"]'); true`);
  await eval_(`rows()[1].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[1], '?')`);
  await sleep(700);
  const actionHelp = JSON.parse(await eval_(`(() => {
    const el = help();
    if (! el) return 'null';
    const row = [...el.querySelectorAll('[data-testid=shortcut-help] div.flex')]
      .find(d => /Archive/.test(d.textContent));
    return JSON.stringify({
      open: !! el && getComputedStyle(el).display !== 'none',
      sections: [...el.querySelectorAll('[data-testid=shortcut-help] section h4')].map(h => h.textContent.trim()),
      archiveKeys: row ? [...row.querySelectorAll('kbd')].map(k => k.textContent.trim()) : [],
    });
  })()`) ?? 'null') ?? {};
  check('the help names this table\'s record actions and pairs Delete with Backspace',
    actionHelp.open && actionHelp.sections.includes('Actions')
      && actionHelp.archiveKeys.includes('Del') && actionHelp.archiveKeys.includes('⌫'),
    JSON.stringify(actionHelp).slice(0, 240));
  await shot('03d-shortcut-help-actions');

  await eval_(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`);
  await sleep(500);

  // ════ Section 1b — selection-only: the grid works without record actions ═
  await page('Page.navigate', { url: `${base}/table-selection-only` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();

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

  // ── `?` opens the shortcut help, client-side and row-scoped ──────────────
  // A grid with no record actions still documents its gestures. The modal is
  // opened by a window event the controller dispatches — no Livewire roundtrip
  // — and the focus guard keeps a ? typed inside a row out of it.
  await eval_(`window.help = () => $q('[data-testid="shortcut-help"]')?.closest('[role="dialog"]'); true`);

  await eval_(`fireKey(rows()[2].querySelector('td'), '?')`);
  await sleep(600);
  check('the ? help stays shut when the key comes from inside a row, not the row itself',
    ! await eval_(`vis(help())`));

  await eval_(`rows()[2].focus()`);
  await sleep(200);
  await eval_(`fireKey(rows()[2], '?')`);
  await sleep(700);
  check('? on a focused row opens the shortcut help', await eval_(`vis(help())`));
  await shot('03c-shortcut-help');

  const helpBody = await eval_(`(() => {
    const el = help();
    if (! el) return null;
    return JSON.stringify({
      mac: Alpine.$data(el.querySelector('[data-testid=shortcut-help]')).mac,
      sections: [...el.querySelectorAll('[data-testid=shortcut-help] section h4')].map(h => h.textContent.trim()),
      keys: [...el.querySelectorAll('[data-testid=shortcut-help] kbd')].map(k => k.textContent.trim()),
    });
  })()`);
  const helpText = JSON.parse(helpBody ?? 'null') ?? { sections: [], keys: [] };
  // The keys render their non-Mac label server-side and swap through x-text, so
  // the expected glyphs follow the platform this browser reports.
  const selectPage = helpText.mac ? '⌘A' : 'Ctrl+A';
  check('the help lists the navigation and selection sections with their keys',
    helpText.sections.includes('Navigation') && helpText.sections.includes('Selection')
      && helpText.keys.includes('↑') && helpText.keys.includes(selectPage) && helpText.keys.includes('?'),
    JSON.stringify(helpText).slice(0, 240));

  // The Mac swap is a client fact: PHP renders the Ctrl labels, Alpine replaces
  // them where the platform calls for it. Whichever way this browser reports,
  // the two vocabularies must never mix in the rendered legend.
  check('the key labels follow one platform vocabulary, not a mixture',
    helpText.mac
      ? helpText.keys.includes('⇧↑') && ! helpText.keys.some(k => k.startsWith('Ctrl'))
      : helpText.keys.includes('Shift+↑') && ! helpText.keys.some(k => k.includes('⌘')),
    `mac=${helpText.mac}`);

  // No record actions on this table, so the help must not invent an actions
  // section — the legend describes what this table actually answers to.
  check('the help omits the actions section on a table without record actions',
    ! helpText.sections.includes('Actions'));

  await eval_(`window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`);
  await waitFor(`!vis(help())`);
  check('Escape closes the shortcut help', ! await eval_(`vis(help())`));

  // ════ Section 2 — 20 a page, the `all` mode ═════════════════════════════
  await page('Page.navigate', { url: `${base}/table-selection-gestures-paged` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();

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

  // The announcement follows the SHAPE of the selection, not just its size:
  // "all matching" is a different fact from "40 of 40 ticked".
  await eval_(`window.live = () => $q('[data-testid="selection-live"]'); true`);
  check('all-matching mode is announced as such, not as a count',
    await eval_(`live().textContent`) === 'All 40 selected',
    await eval_(`live().textContent`));

  // Paging must not restart the row numbering.
  const pagedAria = JSON.parse(await eval_(`(() => {
    const t = $q('table[role=grid]');
    return JSON.stringify({ rowcount: t?.getAttribute('aria-rowcount'), first: rows()[0].getAttribute('aria-rowindex') });
  })()`));
  check('page 1 of a paged grid still counts the whole result set',
    pagedAria.rowcount === '41' && pagedAria.first === '2', JSON.stringify(pagedAria));

  // ── C9 (fixed): a range gesture never rewrites the mode ──────────────────
  // In all mode `selected` holds the exclusions, so the same block write means
  // the range DESELECTS: the 17-row block between the far-edge anchor and the
  // moved active row becomes the exclusions and the other 23 matching records
  // stay selected — before the fix this collapsed 40 selected to a 17-row page
  // block in keys mode.
  await eval_(`clickRow(2)`);
  await sleep(300);
  await eval_(`fireKey(rows()[2], 'ArrowDown', { shiftKey: true })`);
  await sleep(400);
  const c9 = JSON.parse(await eval_(`JSON.stringify({ mode: sel().mode, count: sel().selected.length, shown: sel().selectedCount })`));
  check('C9: in all mode Shift+ArrowDown deselects the range and keeps the mode',
    c9.mode === 'all' && c9.count === 17 && c9.shown === 23,
    JSON.stringify(c9));

  // mod+A is not a range gesture: in all mode everything is already selected
  // and unioning page keys into the exclusions would deselect the page — the
  // gesture stands down instead.
  await eval_(`rows()[0].focus()`);
  await sleep(200);
  const beforeModA = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, shown: sel().selectedCount })`));
  await eval_(`fireKey(rows()[0], 'a', { ctrlKey: true })`);
  await sleep(400);
  const afterModA = JSON.parse(await eval_(`JSON.stringify({ mode: sel().mode, count: sel().selected.length, shown: sel().selectedCount })`));
  check('mod+A stands down in all mode instead of deselecting the page',
    afterModA.mode === 'all' && afterModA.count === beforeModA.count && afterModA.shown === beforeModA.shown,
    JSON.stringify(afterModA));

  // ── C10 (fixed): the header checkbox in `all` mode edits the exclusions ──
  // Before the fix the keys-mode arithmetic ran over the EXCLUSIONS list, so
  // one click turned the selection into its own complement. The canonical page
  // gesture never leaves all mode: a mixed page re-includes its excluded rows
  // (selectAllRecords), a fully selected page becomes the exclusions
  // (deselectPageRecords) — everything off-page stays selected either way.
  await page('Page.navigate', { url: `${base}/table-selection-gestures-paged` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();
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
  check('C10: a mixed page in all mode re-includes its excluded rows and stays in all mode',
    c10.mode === 'all' && c10.count === 0 && c10.shown === 40,
    JSON.stringify(c10));

  await eval_(`$q('[data-testid="table-select-all"]').click()`);
  await sleep(600);
  const c10b = JSON.parse(await eval_(`JSON.stringify({ mode: sel().mode, count: sel().selected.length, shown: sel().selectedCount })`));
  check('C10: deselecting a fully selected page in all mode keeps everything off-page selected',
    c10b.mode === 'all' && c10b.count === 20 && c10b.shown === 20,
    JSON.stringify(c10b));
  await shot('05-toggleall-fixed');

  // ── matching follows the filter through the morph ────────────────────────
  // The selection root is morphed in place (wire:key="table-wrapper"), so a
  // count seeded into x-data would stay at the first render's value forever;
  // it must be read from the DOM the morph refreshes.
  await page('Page.navigate', { url: `${base}/table-selection-gestures-paged` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();
  await eval_(`(() => { const i = $q('[data-testid="table-search"]'); i.value = 'Record 0'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  // The search is debounced and then re-renders: wait for the narrowed page rather
  // than for a stopwatch, or the select-all below runs against the unfiltered table.
  await waitFor(`rows().length < 20`, 8000);
  await eval_(`$q('[data-testid="table-select-all"]').click()`);
  await waitFor(`sel().selectedCount > 0`);
  const narrowed = JSON.parse(await eval_(`JSON.stringify({ rows: rows().length, matching: sel().matching, shown: sel().selectedCount })`));
  check('the matching count follows a filter change instead of staying baked in',
    narrowed.rows === 9 && narrowed.matching === 9 && narrowed.shown === 9,
    JSON.stringify(narrowed));

  // ════ Section 3 — mobile cards (C11) ════════════════════════════════════
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url: `${base}/table-selection-gestures` });
  await sleep(900);
  await eval_(helpers);
  await waitForTable();

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
