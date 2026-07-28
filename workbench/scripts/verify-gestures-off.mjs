import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver for `Table::gestures(false)` and the mobile record-action
 * fallback.
 *
 * Pest can only see that the markup no longer carries a `role="grid"` or a
 * gesture flag. What it cannot see is the thing that matters: that the gestures
 * are actually DEAD in a browser — that an arrow key moves nothing, a Shift
 * click selects one row rather than a block, a drag down the checkbox column
 * paints nothing, a right-click gets the browser's own menu back and `?` opens
 * no help. A stale flag or a listener bound before the config is read would
 * pass every server-side test and fail here.
 *
 * The other half of the same feature is the phone: a behaviour-only record
 * action has no reachable trigger on touch, so the stacked cards render it as
 * an ordinary button. This drives the same preview at 390px and looks for them.
 *
 * The fixture is `gesture-lab-plain` — the full gesture lab with the layer
 * switched off, so anything that survives is a real leak rather than a table
 * that never had the feature. Its declared record actions stay bound on
 * purpose: `gestures(false)` governs the implicit layer, not a double-click
 * that was asked for by name.
 *
 * `gesture-lab-default` is checked too, and it is deliberately a different
 * state: a table that never called gestures() at all. That is what a consumer
 * gets out of the box, and it still answers a right click — the difference
 * between "did not ask" and "said no" is one people get wrong.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-gestures-off.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9351);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-gestures-off-shots');
await mkdir(shotDir, { recursive: true });

// CDP Input.dispatch* modifier bitmask (additive).
const MOD = { alt: 1, ctrl: 2, meta: 4, shift: 8 };

const userDataDir = join(tmpdir(), `wire-gestures-off-${Date.now()}`);
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
    window.rows = () => [...($q('tbody[x-data]') ?? $q('table tbody')).children].filter(el => el.matches('tr[data-row-key]'));
    window.key = (i) => rows()[i].dataset.rowKey;
    window.cell = (i) => rows()[i].querySelector('[data-testid="table-cell-name"]');
    window.box = (i) => rows()[i].querySelector('[data-select-cell]');
    window.sel = () => Alpine.$data($q('[data-selection-root]'));
    window.vis = (el) => !!el && el.getClientRects().length > 0;
    window.cancelModal = () => $qa('button').find(b => ['Cancel','Zrušit'].includes(b.innerText.trim()))?.click();
    true;
  `;

  const measure = async (expr) => JSON.parse(await eval_(`(() => {
    const el = ${expr};
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
  })()`));

  const realClick = async (expr, modifiers = 0, { button = 'left', clickCount = 1 } = {}) => {
    const at = await measure(expr);
    await sleep(120);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: at.x, y: at.y, button, clickCount, modifiers });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: at.x, y: at.y, button, clickCount, modifiers });
  };

  // mousePressed → N× mouseMoved(buttons:1) → mouseReleased, re-measuring at
  // each waypoint: the bulk bar appears mid-drag and shifts the rows down.
  const drag = async (exprs, steps = 6) => {
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
      await sleep(30);
    }
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: cur.x, y: cur.y, button: 'left', clickCount: 1 });
  };

  const pressKey = async (key, modifiers = 0, code = key) => {
    await page('Input.dispatchKeyEvent', { type: 'rawKeyDown', key, code, windowsVirtualKeyCode: 0, modifiers });
    await page('Input.dispatchKeyEvent', { type: 'keyUp', key, code, modifiers });
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });

  // ════ Desktop: the layer is off ═════════════════════════════════════════
  await page('Page.navigate', { url: `${base}/gesture-lab-plain` });
  await sleep(3000);
  await eval_(helpers);

  check('the fixture renders its rows', await eval_('rows().length') > 4, `${await eval_('rows().length')} rows`);

  // The lab's own read-out, which reports what the table offers rather than what
  // the controller happens to hold — the two disagreeing is exactly the bug this
  // driver exists to catch.
  const readout = JSON.parse(await eval_(`(() => JSON.stringify(Object.fromEntries(
    ['keyboard', 'ranges', 'sweep', 'menu', 'help', 'fill']
      .map((k) => [k, $q('[data-testid="lab-gesture-' + k + '"]')?.textContent?.trim() ?? null])
  )))()`));
  check('the read-out reports every gesture off',
    Object.values(readout).every((v) => v === 'off'), JSON.stringify(readout));

  const semantics = JSON.parse(await eval_(`(() => JSON.stringify({
    role: $q('table')?.getAttribute('role'),
    rowRole: rows()[0].getAttribute('role'),
    tabindex: rows()[0].getAttribute('tabindex'),
    menus: $qa('[data-record-menu]').length,
    help: !!$q('[data-testid="shortcut-help"]'),
    gutter: rows()[0].className.includes('first-of-type]:relative'),
    controller: !!$q('tbody[x-data^="wireRecordActions"]'),
  }))()`));

  check('the table is no longer an ARIA grid', semantics.role !== 'grid', `role=${semantics.role}`);
  check('the rows are out of the tab order', semantics.tabindex === null && semantics.rowRole === null,
    `tabindex=${semantics.tabindex} role=${semantics.rowRole}`);
  check('no context-menu panel is rendered at all', semantics.menus === 0, `${semantics.menus} panels`);
  check('no shortcut help is rendered', semantics.help === false);
  check('no active-row gutter is reserved', semantics.gutter === false);
  // The declared record actions still need their controller — it is only every
  // gesture inside it that is off.
  check('the controller is still mounted for the declared bindings', semantics.controller === true);

  const flags = JSON.parse(await eval_(`(() => {
    const c = Alpine.$data($q('tbody[x-data^="wireRecordActions"]'));
    return JSON.stringify({ sweep: c.gestures.sweep, ranges: c.gestures.ranges, kb: c.kb });
  })()`));
  check('the controller reports every gesture off',
    flags.sweep === false && flags.ranges === false && flags.kb === null, JSON.stringify(flags));

  await shot('01-plain-desktop');

  // ── the checkboxes still work: the selection is not a gesture ────────────
  await eval_(`sel().deselectAll?.() ?? (sel().selected = [])`);
  await sleep(300);
  await realClick(`box(1)`);
  await sleep(500);
  check('a plain click on the selection cell still selects the row',
    await eval_('sel().selected.length') === 1, `selected=${await eval_('sel().selected.length')}`);

  // ── Shift+click is not a range any more ─────────────────────────────────
  await realClick(`box(4)`, MOD.shift);
  await sleep(600);
  const afterShift = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length, first: sel().isSelected(key(1)), last: sel().isSelected(key(4)), middle: sel().isSelected(key(2)) })`));
  check('Shift+click toggles one row instead of a block',
    afterShift.count === 2 && afterShift.first && afterShift.last && !afterShift.middle,
    JSON.stringify(afterShift));

  // ── the sweep paints nothing ────────────────────────────────────────────
  await eval_(`sel().selected = []`);
  await sleep(300);
  await drag([`box(1)`, `box(5)`]);
  await sleep(700);
  const swept = JSON.parse(await eval_(`JSON.stringify({ count: sel().selected.length })`));
  // A drag that starts and ends on a cell still emits a click on release, so
  // the row it began on may toggle — what must not happen is a swept block.
  check('a drag down the checkbox column selects no block', swept.count <= 1, JSON.stringify(swept));
  await shot('02-after-drag');

  // ── the keyboard drives nothing ─────────────────────────────────────────
  await eval_(`sel().selected = []`);
  await eval_(`rows()[0].focus?.()`);
  await realClick(`cell(0)`);
  // The click is bound to a record action here (onClick → "peek"), which the
  // gesture switch does not touch — dismiss whatever it opened before typing.
  await sleep(900);
  await eval_(`cancelModal()`);
  await sleep(700);

  const beforeKeys = await eval_(`JSON.stringify({ active: $qa('tr[data-row-key]').findIndex(r => r.className.includes('bg-primary-100')), selected: sel().selected.length })`);
  await pressKey('ArrowDown');
  await pressKey('ArrowDown');
  await pressKey(' ', 0, 'Space');
  await pressKey('ArrowDown', MOD.shift);
  await sleep(600);
  const afterKeys = await eval_(`JSON.stringify({ active: $qa('tr[data-row-key]').findIndex(r => r.className.includes('bg-primary-100')), selected: sel().selected.length })`);
  check('arrows, Space and Shift+arrow do nothing',
    beforeKeys === afterKeys, `${beforeKeys} → ${afterKeys}`);

  // ── ? opens no help ─────────────────────────────────────────────────────
  await pressKey('?', MOD.shift, 'Slash');
  await sleep(600);
  check('? opens no shortcut help',
    await eval_(`!vis($q('[data-testid="shortcut-help"]'))`) === true);

  // ── right-click is the browser's again ──────────────────────────────────
  await realClick(`cell(2)`, 0, { button: 'right' });
  await sleep(600);
  check('right-click opens no row menu',
    await eval_(`$qa('[data-record-menu]').filter(vis).length`) === 0);
  await shot('03-after-keys');

  // ── the declared double-click still runs ────────────────────────────────
  // The point of the switch: it takes the implicit layer, not the binding the
  // table asked for by name.
  await realClick(`cell(3)`, 0, { clickCount: 2 });
  await sleep(1200);
  const opened = await eval_(`$qa('[role="dialog"]').filter(vis).length > 0`);
  check('a declared double-click record action still opens its modal', opened === true);
  await shot('04-record-action-modal');
  await eval_(`cancelModal()`);
  await sleep(500);

  // ════ Desktop: the shipped default (never asked) ═══════════════════════
  // Not the same page as above: gestures(false) refuses everything, while a
  // table that simply never asked keeps the capabilities it declared itself.
  await page('Page.navigate', { url: `${base}/gesture-lab-default` });
  await sleep(3000);
  await eval_(helpers);

  const shipped = JSON.parse(await eval_(`(() => JSON.stringify({
    readout: Object.fromEntries(
      ['keyboard', 'ranges', 'sweep', 'menu', 'help', 'fill']
        .map((k) => [k, $q('[data-testid="lab-gesture-' + k + '"]')?.textContent?.trim() ?? null])
    ),
    role: $q('table')?.getAttribute('role'),
    menus: $qa('[data-record-menu]').length,
    help: !!$q('[data-testid="shortcut-help"]'),
  }))()`));

  check('the shipped default leaves the three row gestures off',
    shipped.readout.keyboard === 'off' && shipped.readout.ranges === 'off' && shipped.readout.sweep === 'off'
      && shipped.role !== 'grid' && shipped.help === false,
    JSON.stringify(shipped.readout));
  check('…but keeps what the table declared for itself — the right-click menu',
    shipped.readout.menu === 'on' && shipped.menus > 0, `${shipped.menus} panels`);

  await realClick(`cell(2)`, 0, { button: 'right' });
  await sleep(600);
  check('and that menu really opens on a right click',
    await eval_(`$qa('[data-record-menu]').filter(vis).length`) === 1);
  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await sleep(300);
  await shot('04b-shipped-default');

  // ════ Desktop: the layer is on (the control) ════════════════════════════
  // Same table, gestures left alone: if these fail, the checks above prove
  // nothing — they would pass on a table where the gestures never worked.
  await page('Page.navigate', { url: `${base}/gesture-lab` });
  await sleep(3000);
  await eval_(helpers);

  const onSemantics = JSON.parse(await eval_(`(() => JSON.stringify({
    role: $q('table')?.getAttribute('role'),
    tabindex: rows()[0].getAttribute('tabindex'),
    help: !!$q('[data-testid="shortcut-help"]'),
  }))()`));
  check('control: the same table IS a grid with the gestures on',
    onSemantics.role === 'grid' && onSemantics.tabindex !== null && onSemantics.help,
    JSON.stringify(onSemantics));

  await eval_(`sel().selected = []`);
  await sleep(300);
  await realClick(`box(1)`);
  await sleep(400);
  await realClick(`box(4)`, MOD.shift);
  await sleep(700);
  check('control: Shift+click DOES range on the same table',
    await eval_('sel().selected.length') === 4, `selected=${await eval_('sel().selected.length')}`);

  // ════ Mobile: the record actions come back as buttons ═══════════════════
  await page('Emulation.setDeviceMetricsOverride', { width: 390, height: 900, deviceScaleFactor: 2, mobile: true });
  await page('Page.navigate', { url: `${base}/gesture-lab` });
  await sleep(3000);
  await eval_(helpers);

  const cards = JSON.parse(await eval_(`(() => {
    const first = $q('[data-testid="table-card-actions"]');
    return JSON.stringify({
      cards: $qa('[data-testid="table-card"]').length,
      actionRows: $qa('[data-testid="table-card-actions"]').length,
      labels: first ? [...first.querySelectorAll('button, a')].map(b => b.innerText.trim()).filter(Boolean) : [],
    });
  })()`));

  check('the stacked cards render', cards.cards > 0, `${cards.cards} cards`);
  check('every card offers the record actions as buttons',
    cards.actionRows === cards.cards, `${cards.actionRows} action rows for ${cards.cards} cards`);
  // The lab binds Open (double-click), Peek (click), Archive (Delete key) and
  // Duplicate (right-click) — every one of them unreachable by a finger.
  check('the behaviour-only record actions are all there',
    ['Open', 'Peek', 'Archive', 'Duplicate'].every((l) => cards.labels.some((x) => x.includes(l))),
    cards.labels.join(', '));
  await shot('05-mobile-cards');

  // And they run: a tap opens the action's modal, the same one the desktop
  // double-click opens.
  await eval_(`$q('[data-testid="table-card-actions"]').querySelectorAll('button')[0].click()`);
  await sleep(1500);
  check('tapping a fallback button runs the action',
    await eval_(`$qa('[role="dialog"]').filter(vis).length > 0`) === true);
  await shot('06-mobile-modal');

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
