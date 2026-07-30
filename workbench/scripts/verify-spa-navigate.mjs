import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for a `wire:navigate` hop onto a table page.
 *
 * Every client-side controller in this repository registers its factory from
 * inside a single `document.addEventListener('alpine:init', …)` listener:
 *
 *   packages/core/resources/js/dropdown.js          wireDropdown, wireContextMenu,
 *                                                   wireTabs, wireWizard,
 *                                                   wireEditableCell, wireFillHandle
 *   packages/table/resources/js/record-selection.js wireRecordSelection
 *   packages/table/resources/js/record-actions.js   wireRecordActions
 *   packages/sortable/…/partials/scripts.blade.php  wireSortable
 *
 * `alpine:init` fires exactly once per document, guarded by Livewire's own
 * `started` flag. So the bundles only ever hear it if they are in the document
 * BEFORE Alpine starts. A `wire:navigate` from a page carrying none of them onto
 * a page that needs all of them delivers them (Livewire injects the `@assets`
 * head blocks) into a document where Alpine started minutes ago — the listener
 * is added to an event that will never fire again, `Alpine.data()` is never
 * called, and every `x-data="wireRecordSelection(…)"` on the new page is an
 * expression referring to a name nothing defined.
 *
 * The fixture is the pair of previews the driver walks between:
 *   /previews/spa-navigate        page A — nothing that ships a controller
 *   /previews/spa-navigate-table  page B — selection, record actions, the fill
 *                                 handle, the context menu, column reordering
 *
 * Section 3 loads page B cold as a control: the same exercises on the same
 * markup, reached the way every other driver reaches it. It exists so a failure
 * in section 2 can only be read one way — the fixture works, the hop does not.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-spa-navigate.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const pageA = `${base}/spa-navigate`;
const pageB = `${base}/spa-navigate-table`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9377);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-spa-navigate-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-spa-navigate-${Date.now()}`);
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

// Everything the console said, in order, with the section it said it in. Alpine
// reports a broken x-data twice — a console.warn ("Alpine Expression Error…")
// and an uncaught ReferenceError — so warnings count here, not just errors.
const consoleLog = [];
let phase = 'boot';

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
  // A broken page is the expected outcome of section 2, so probes must not take
  // the driver down with them: this returns the thrown message as a value.
  const probe = async (expression) => {
    const raw = await eval_(`(() => { try { return JSON.stringify({ ok: true, v: (${expression}) }); }
      catch (e) { return JSON.stringify({ ok: false, error: String(e && e.message || e) }); } })()`);
    return JSON.parse(raw);
  };
  const shot = async (name) => {
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  cdp.on((msg) => {
    if (msg.method === 'Runtime.consoleAPICalled' && ['error', 'warning'].includes(msg.params?.type)) {
      consoleLog.push({
        phase,
        kind: msg.params.type,
        text: (msg.params.args ?? []).map((a) => a.value ?? a.description ?? '').join(' '),
      });
    }
    if (msg.method === 'Runtime.exceptionThrown') {
      consoleLog.push({
        phase,
        kind: 'exception',
        text: msg.params?.exceptionDetails?.exception?.description
          ?? msg.params?.exceptionDetails?.text ?? 'exception',
      });
    }
  });

  const since = (name) => consoleLog.filter((e) => e.phase === name);
  const undefinedIn = (name) => since(name).filter((e) => /is not defined/.test(e.text));

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Network.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });

  // Helpers, re-installed after every document swap — a wire:navigate keeps the
  // window, but a full load does not, and the driver does both.
  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.vis = (el) => !!el && el.getClientRects().length > 0;
    window.rows = () => $qa('tbody tr[data-row-key]');
    window.key = (i) => rows()[i].dataset.rowKey;
    window.cell = (i, col) => rows()[i].querySelector(':scope > td[data-column="' + col + '"]');
    window.nameCell = (i) => rows()[i].querySelector('[data-testid="table-cell-name"]');
    window.sel = () => Alpine.$data($q('[data-selection-root]'));
    window.ctrl = () => Alpine.$data($q('tbody[x-data^="wireRecordActions"]'));
    window.fireKey = (el, k, o) => el.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...(o||{}) }));
    window.cancelModal = () => $qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()))?.click();
    window.statusOf = (i) => cell(i, 'status').querySelector('select').value;

    window.pointer = (type, x, y, target) => (target ?? window).dispatchEvent(new PointerEvent(type, {
      pointerId: 1, pointerType: 'mouse', isPrimary: true,
      bubbles: true, cancelable: true, clientX: x, clientY: y,
    }));
    window.centre = (el) => { const r = el.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; };
    window.handle = () => $q('[data-fill-handle]');
    window.grab = () => { const r = handle().getBoundingClientRect(); pointer('pointerdown', r.left + 4, r.top + 4, handle()); };
    window.dragOver = (el) => { const p = centre(el); pointer('pointermove', p.x, p.y); };
    window.release = (el) => { const p = centre(el); pointer('pointerup', p.x, p.y); };

    // Drive the production onEnd the way Sortable does — Sortable runs with
    // forceFallback and ignores synthesised pointer input (see
    // verify-column-reorder.mjs, which established this).
    window.headerCols = () => $qa('thead th[data-sortable-column]').map(th => th.getAttribute('data-sortable-column'));
    window.bodyCols = (i = 0) => [...rows()[i].children]
      .map(el => el.tagName === 'TEMPLATE' ? 'TEMPLATE' : (el.getAttribute('data-column') ?? (el.hasAttribute('data-select-cell') ? 'SELECT' : 'FIXED')))
      .filter(c => !['TEMPLATE', 'SELECT', 'FIXED'].includes(c));
    // Sibling index, computed from the DOM rather than Sortable.utils.index:
    // SortableJS is bundled inside wire-sortable.js and is no longer a global.
    window.thIndex = (el) => [...el.parentElement.children].indexOf(el);
    window.dragHeader = (name, toEnd = true) => {
      const sortRoot = $q('[x-data*="wireSortable"]');
      const d = Alpine.$data(sortRoot);
      const ths = $qa('thead th');
      const moved = ths.find(t => t.getAttribute('data-sortable-column') === name);
      const target = toEnd ? ths[ths.length - 1] : ths.find(t => t.hasAttribute('data-sortable-column'));
      const oldIndex = thIndex(moved);
      toEnd ? target.after(moved) : target.before(moved);
      const newIndex = thIndex(moved);
      d.columnSortableInstance.options.onEnd({ oldIndex, newIndex });
      return { oldIndex, newIndex };
    };
    true;
  `;

  // A real click, so wire:navigate's own click interception is what handles it.
  const realClick = async (expr) => {
    const box = JSON.parse(await eval_(`(() => {
      const el = ${expr};
      el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await sleep(150);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: box.x, y: box.y, button: 'left', clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: box.x, y: box.y, button: 'left', clickCount: 1 });
  };

  // ════ Section 1 — page A, cold ══════════════════════════════════════════
  phase = 'page-a';
  await page('Page.navigate', { url: pageA });
  await sleep(3000);
  await eval_(helpers);

  const aBoot = JSON.parse(await eval_(`JSON.stringify({
    page: document.querySelector('[data-spa-page]')?.dataset.spaPage,
    alpine: typeof Alpine !== 'undefined',
    livewire: typeof Livewire !== 'undefined',
    link: !! $q('[data-testid="spa-navigate-link"]'),
    navAttr: $q('[data-testid="spa-navigate-link"]')?.hasAttribute('wire:navigate'),
  })`));
  check('page A boots Alpine and Livewire and carries a wire:navigate link',
    aBoot.page === 'a' && aBoot.alpine && aBoot.livewire && aBoot.link && aBoot.navAttr,
    JSON.stringify(aBoot));

  // The premise: none of the package bundles are in this document, so none of
  // them can have heard this document's alpine:init.
  const aBundles = JSON.parse(await eval_(`JSON.stringify(
    $qa('script[src]').map(s => s.src).filter(s => /wire-table|wire-core|sortable/i.test(s))
  )`));
  check('page A ships none of the package bundles', aBundles.length === 0, JSON.stringify(aBundles));

  const aFactories = JSON.parse(await eval_(`JSON.stringify(
    ['wireRecordSelection','wireRecordActions','wireDropdown','wireContextMenu','wireFillHandle','wireSortable']
      .filter(n => typeof window[n] !== 'undefined' || !! Alpine.__data?.[n])
  )`));
  check('and none of their Alpine factories exist yet', aFactories.length === 0, JSON.stringify(aFactories));

  // Livewire is genuinely live here — otherwise a dead page B would prove nothing.
  await realClick(`$q('[data-testid="spa-ping"]')`);
  await sleep(1200);
  check('Livewire round-trips on page A before we navigate',
    await eval_(`$q('[data-testid="spa-ping-count"]').innerText.trim()`) === '1');
  await shot('01-page-a');

  // ════ Section 2 — the wire:navigate hop ═════════════════════════════════
  // A marker on the window: a wire:navigate keeps it, a full page load does not.
  // That is what makes this document the one Alpine already started in.
  await eval_(`window.__spaMarker = 'set-on-page-a'; true`);

  phase = 'navigate';
  await realClick(`$q('[data-testid="spa-navigate-link"]')`);

  // Wait for the swap rather than a fixed sleep: navigate does not fire a load.
  let arrived = false;
  for (let i = 0; i < 40; i++) {
    await sleep(250);
    if (await eval_(`location.pathname.endsWith('/spa-navigate-table')
      && !! document.querySelector('[data-spa-page="b"]')`)) { arrived = true; break; }
  }
  await sleep(2500);
  check('the wire:navigate link lands on page B without a full page load', arrived,
    await eval_(`location.pathname`));

  // It really was an SPA swap: the window survived, so this is the same document
  // Alpine started in on page A.
  const sameWindow = await eval_(`window.__spaMarker === 'set-on-page-a'`);
  check('the navigation kept the window (an SPA swap, not a reload)', sameWindow === true);

  await eval_(helpers);
  await shot('02-after-navigate');

  const bBundles = JSON.parse(await eval_(`JSON.stringify(
    $qa('script[src]').map(s => s.src).filter(s => /wire-table|wire-core|sortable/i.test(s))
  )`));
  check('page B\'s bundles were injected by the navigation', bBundles.length >= 3,
    JSON.stringify(bBundles.map(u => u.replace(/^https?:\/\/[^/]+/, ''))));

  // ── The assertion this driver exists for ─────────────────────────────────
  const notDefined = undefinedIn('navigate');
  check('no "is not defined" console error after the navigate', notDefined.length === 0,
    [...new Set(notDefined.map(e => e.text.split('\n')[0]))].slice(0, 6).join(' | '));

  // `Alpine.data(name)` is a setter only, so "is it registered" is asked the way
  // it actually matters: did the element declaring that x-data end up with a
  // scope of its own? A factory that never registered leaves the expression
  // throwing, and Alpine.$data() falls through to an ancestor's scope.
  const mounted = JSON.parse(await eval_(`(() => {
    const probes = {
      wireRecordSelection: ['[data-selection-root]', 'selected'],
      wireRecordActions: ['tbody[x-data^="wireRecordActions"]', 'activeKey'],
      wireFillHandle: ['[data-fill-root]', 'write'],
      wireSortable: ['[x-data*="wireSortable"]', 'columnSortableInstance'],
    };
    const out = {};
    for (const [name, [sel, key]] of Object.entries(probes)) {
      const el = document.querySelector(sel);
      if (! el) { out[name] = 'no element'; continue; }
      try { out[name] = key in Alpine.\$data(el); } catch (e) { out[name] = String(e.message ?? e); }
    }
    return JSON.stringify(out);
  })()`));
  check('every controller the page uses actually mounted',
    Object.values(mounted).every((v) => v === true), JSON.stringify(mounted));

  // Diagnostic, not a check: WHY it did not mount. Registering from
  // `alpine:init` alone and losing the race to Alpine's initTree are two
  // different defects with two different fixes, and they look identical from
  // the console. A throwaway element initialised now answers it — if the
  // factory works here, the bundle did register, just after Alpine had already
  // walked the swapped-in page.
  const lateness = JSON.parse(await eval_(`(() => {
    const probes = {
      wireRecordSelection: 'selected', wireRecordActions: 'activeKey',
      wireFillHandle: 'write', wireDropdown: 'open', wireSortable: 'config',
    };
    const out = {};
    for (const [name, key] of Object.entries(probes)) {
      const el = document.createElement('div');
      el.setAttribute('x-data', name + '({})');
      document.body.appendChild(el);
      try { Alpine.initTree(el); out[name] = key in Alpine.\$data(el) ? 'registered' : 'missing'; }
      catch (e) { out[name] = 'missing'; }
      el.remove();
    }
    return JSON.stringify(out);
  })()`));
  console.log(`      ↳ factory registry once the navigation settled: ${JSON.stringify(lateness)}`);
  console.log('        ("registered" here with a failed mount above means the bundle won the delivery'
    + ' race and lost the ORDERING one — Alpine had already initialised the new tree.)');

  // ── Exercise the four surfaces ───────────────────────────────────────────
  const rowCount = await probe(`rows().length`);
  check('page B rendered its rows', rowCount.ok && rowCount.v === 40,
    rowCount.ok ? String(rowCount.v) : rowCount.error);

  // 1. select all — the controller method, and the header gesture that calls it.
  //
  // They are separate checks because they fail for separate reasons. The
  // controller is what the navigate breaks. The header checkbox is broken on
  // this fixture EITHER WAY, by a second, unrelated defect this combination
  // turned up: `fillHandle()` wraps the table in its own `x-data`, so `$root`
  // inside an expression evaluated on the header button resolves to the fill
  // root instead of the selection root, and `get pageKeys()` reads an empty
  // list off the wrong element. See the control section — it fails there too.
  const toggled = await probe(`(() => { sel().toggleAll(); return sel().selected.length; })()`);
  await sleep(900);
  const selectedAll = await probe(`sel().selected.length`);
  check('the selection controller selects the page (toggleAll) after a navigate',
    toggled.ok && selectedAll.ok && selectedAll.v === 40,
    toggled.ok ? `selected=${selectedAll.ok ? selectedAll.v : selectedAll.error}` : toggled.error);
  await shot('03-select-all');

  await eval_(`try { sel().deselectAll(); } catch (e) {}`);
  await sleep(500);
  await eval_(`try { $q('[data-testid="table-select-all"]')?.click(); } catch (e) {}`);
  await sleep(900);
  const headerGesture = await probe(`sel().selected.length`);
  check('the header select-all checkbox selects the page after a navigate',
    headerGesture.ok && headerGesture.v === 40,
    headerGesture.ok ? `selected=${headerGesture.v}` : headerGesture.error);

  await eval_(`try { sel().deselectAll(); } catch (e) {}`);
  await sleep(400);

  // 2. the fill handle
  const filled = await probe(`(() => {
    const src = cell(0, 'status').querySelector('select');
    src.value = src.value === 'active' ? 'paused' : 'active';
    src.dispatchEvent(new Event('change', { bubbles: true }));
    src.focus();
    if (! handle() || handle().hidden) return 'no handle';
    grab();
    dragOver(cell(1, 'status'));
    dragOver(cell(2, 'status'));
    release(cell(2, 'status'));
    return JSON.stringify({ want: src.value, painted: !! $q('.wire-fill-target') });
  })()`);
  await sleep(1800);
  const fillLanded = await probe(`JSON.stringify([0,1,2].map(statusOf))`);
  check('the fill handle drags and writes after a navigate',
    filled.ok && filled.v !== 'no handle' && fillLanded.ok
      && (() => { try { const v = JSON.parse(fillLanded.v); return v[1] === v[0] && v[2] === v[0]; } catch { return false; } })(),
    filled.ok ? `${filled.v} → ${fillLanded.ok ? fillLanded.v : fillLanded.error}` : filled.error);
  await shot('04-fill-handle');

  // 3. column reorder
  const beforeCols = await probe(`JSON.stringify({ header: headerCols(), body: bodyCols() })`);
  const dragged = await probe(`JSON.stringify(dragHeader('name', true))`);
  await sleep(900);
  const afterCols = await probe(`JSON.stringify({ header: headerCols(), body: bodyCols() })`);
  check('dragging a header reorders the columns after a navigate',
    dragged.ok && afterCols.ok
      && (() => { try { const a = JSON.parse(afterCols.v); return a.header[a.header.length - 1] === 'name' && JSON.stringify(a.header) === JSON.stringify(a.body); } catch { return false; } })(),
    dragged.ok ? `${beforeCols.ok ? beforeCols.v : '?'} → ${afterCols.ok ? afterCols.v : afterCols.error}` : dragged.error);
  await shot('05-column-reorder');

  // 4. the record action, by double-click and by Enter
  await eval_(`try { nameCell(3).dispatchEvent(new MouseEvent('dblclick', { bubbles: true, cancelable: true })); } catch (e) {}`);
  await sleep(2500);
  const dbl = await eval_(`(document.body.innerText.match(/Opened Record \\d+/) || [null])[0]`);
  check('a double-click runs the primary record action after a navigate', !! dbl, String(dbl));
  await eval_(`try { cancelModal(); } catch (e) {}`);
  await sleep(1500);

  await eval_(`try { rows()[5].focus(); } catch (e) {}`);
  await sleep(250);
  await eval_(`try { fireKey(rows()[5], 'Enter'); } catch (e) {}`);
  await sleep(2500);
  const viaEnter = await eval_(`(document.body.innerText.match(/Opened Record \\d+/) || [null])[0]`);
  check('Enter runs the primary record action after a navigate', !! viaEnter, String(viaEnter));
  await eval_(`try { cancelModal(); } catch (e) {}`);
  await sleep(1200);
  await shot('06-record-action');

  // ════ Section 3 — control: page B loaded cold ═══════════════════════════
  // Same markup, same exercises, reached the way every other driver reaches it.
  // If these pass while section 2 fails, the difference is the navigation.
  phase = 'cold';
  await page('Page.navigate', { url: pageB });
  await sleep(3500);
  await eval_(helpers);

  check('control: a cold load of page B raises no "is not defined"',
    undefinedIn('cold').length === 0,
    [...new Set(undefinedIn('cold').map(e => e.text.split('\n')[0]))].slice(0, 4).join(' | '));

  const coldToggle = await probe(`(() => { sel().toggleAll(); return true; })()`);
  await sleep(900);
  const coldSelected = await probe(`sel().selected.length`);
  check('control: the selection controller selects the page on a cold load',
    coldToggle.ok && coldSelected.ok && coldSelected.v === 40,
    coldSelected.ok ? `selected=${coldSelected.v}` : coldSelected.error);
  await eval_(`try { sel().deselectAll(); } catch (e) {}`);
  await sleep(500);

  // The second defect, stated where it can be seen: this one is NOT the
  // navigation's doing — it fails on a cold load too.
  await eval_(`try { $q('[data-testid="table-select-all"]')?.click(); } catch (e) {}`);
  await sleep(900);
  const coldHeader = await probe(`sel().selected.length`);
  const rootShadow = await probe(`JSON.stringify({
    rootFromHeader: Alpine.evaluate($q('[data-testid="table-select-all"]'), '$root').getAttribute('x-data').split('(')[0],
    pageKeysFromHeader: Alpine.evaluate($q('[data-testid="table-select-all"]'), 'pageKeys').length,
    pageKeysFromRoot: Alpine.evaluate($q('[data-selection-root]'), 'pageKeys').length,
  })`);
  check('control: the header select-all checkbox selects the page on a cold load',
    coldHeader.ok && coldHeader.v === 40,
    `selected=${coldHeader.ok ? coldHeader.v : coldHeader.error} · ${rootShadow.ok ? rootShadow.v : rootShadow.error}`);
  await eval_(`try { sel().deselectAll(); } catch (e) {}`);
  await sleep(400);

  const coldFill = await probe(`(() => {
    const src = cell(0, 'status').querySelector('select');
    src.value = src.value === 'active' ? 'paused' : 'active';
    src.dispatchEvent(new Event('change', { bubbles: true }));
    src.focus();
    if (! handle() || handle().hidden) return 'no handle';
    grab(); dragOver(cell(1, 'status')); dragOver(cell(2, 'status')); release(cell(2, 'status'));
    return src.value;
  })()`);
  await sleep(1800);
  const coldFilled = await probe(`JSON.stringify([0,1,2].map(statusOf))`);
  check('control: the fill handle works on a cold load',
    coldFill.ok && coldFill.v !== 'no handle' && coldFilled.ok
      && (() => { try { const v = JSON.parse(coldFilled.v); return v[1] === v[0] && v[2] === v[0]; } catch { return false; } })(),
    coldFill.ok ? `${coldFill.v} → ${coldFilled.ok ? coldFilled.v : coldFilled.error}` : coldFill.error);

  const coldDrag = await probe(`(() => { dragHeader('name', true); return JSON.stringify({ header: headerCols(), body: bodyCols() }); })()`);
  await sleep(900);
  check('control: a header drag reorders columns on a cold load',
    coldDrag.ok && (() => { try { const a = JSON.parse(coldDrag.v); return a.header[a.header.length - 1] === 'name' && JSON.stringify(a.header) === JSON.stringify(a.body); } catch { return false; } })(),
    coldDrag.ok ? coldDrag.v : coldDrag.error);
  // Put the column order back — it is persisted per user and would leak into
  // the next run of this driver.
  await probe(`dragHeader('name', false)`);
  await sleep(900);

  await eval_(`try { nameCell(3).dispatchEvent(new MouseEvent('dblclick', { bubbles: true, cancelable: true })); } catch (e) {}`);
  await sleep(2500);
  const coldDbl = await eval_(`(document.body.innerText.match(/Opened Record \\d+/) || [null])[0]`);
  check('control: a double-click opens the record action on a cold load', !! coldDbl, String(coldDbl));
  await eval_(`try { cancelModal(); } catch (e) {}`);
  await sleep(1200);
  await shot('07-cold-control');

  // ── The console, verbatim, so the report does not have to guess ──────────
  const navErrors = since('navigate');
  if (navErrors.length) {
    console.log('\nConsole during the wire:navigate hop:');
    for (const e of navErrors.slice(0, 25)) {
      console.log(`  [${e.kind}] ${e.text.split('\n').slice(0, 2).join(' ⏎ ')}`);
    }
    if (navErrors.length > 25) console.log(`  … ${navErrors.length - 25} more`);
  }

  console.log('\nSummary: ' + results.filter((r) => r.ok).length + '/' + results.length + ' checks passed');
  console.log('Screenshots: ' + shotDir);
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
