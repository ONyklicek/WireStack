import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * The row's actions on a tablet.
 *
 * A tablet is wide enough for the desktop table, so it gets the desktop's
 * gestures and none of the phone card's buttons. Before this, a record action
 * that was only a gesture — a double click, a right click, a key — could not be
 * reached with a finger at all: no right click, no key, and Safari's double tap
 * zooms. The row now keeps each gesture's meaning for a finger:
 *
 *   tap        → click         double tap → double click
 *   long press → right click   ⋯ in the actions column → the same menu, visibly
 *
 * and the menu a finger opens also lists what a mouse reaches by a double click
 * or a key. A mouse is checked last: its right-click menu must not grow the
 * touch items, and the ⋯ must not show.
 *
 * Touch is Chrome's own emulation — Emulation.setTouchEmulationEnabled makes
 * (pointer: coarse) match and turns Input.dispatchTouchEvent into the pointer
 * events and clicks a real finger produces, pointerType 'touch' included.
 *
 *   Previews: /previews/table-record-actions       (open → double click, view/edit/… → right click)
 *             /previews/table-record-actions-dual  (view → click, edit → double click)
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const tablet = await openPage({ url: `${origin}/previews/table-record-actions`, shotPrefix: 'touch-gestures', width: 1024, height: 768, settle: 500 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = tablet;

const recordCalls = `(() => {
  window.__calls = [];
  window.Livewire.hook('commit', ({ commit }) => {
    (commit.calls || []).forEach((c) => {
      if (c.method === 'openActionModal') window.__calls.push(c.params?.[1]);
    });
  });
  return true;
})()`;

// A finger on the middle of the first row's name cell — not a button, not the checkbox.
const cellPoint = (i = 0) => eval_(`(() => {
  const row = document.querySelectorAll('tbody tr[data-row-key]')[${i}];
  const cell = [...row.children].find((td) => td.tagName === 'TD' && ! td.querySelector('button, input, [role="checkbox"]') && ! td.hasAttribute('data-select-cell'));
  cell.scrollIntoView({ block: 'center', inline: 'nearest' });
  const r = cell.getBoundingClientRect();
  const x = Math.round(r.left + 12);
  const y = Math.round(r.top + r.height / 2);
  // What a finger there would actually land on — the point is no use if it is not this row.
  return { x, y, onRow: document.elementFromPoint(x, y)?.closest('tr') === row };
})()`);

// A row a finger can actually reach: a closed modal's backdrop takes its leave
// transition to get out of the way, and a tap on it is a tap on nothing.
const reachableCell = async (i = 0) => {
  let point = await cellPoint(i);
  for (let n = 0; n < 30 && ! point.onRow; n++) {
    await sleep(100);
    point = await cellPoint(i);
  }
  return point;
};

const touch = async (type, x, y) => page('Input.dispatchTouchEvent', {
  type,
  touchPoints: type === 'touchEnd' ? [] : [{ x, y, id: 1, radiusX: 4, radiusY: 4, force: 1 }],
});
const tap = async ({ x, y }) => { await touch('touchStart', x, y); await touch('touchEnd', x, y); };
const press = async ({ x, y }, ms = 700) => { await touch('touchStart', x, y); await sleep(ms); await touch('touchEnd', x, y); };

// The confirmation an action opens, closed with its own Cancel — a synthetic
// Escape is not a key press Alpine's modal always answers.
const closeModal = async () => {
  await eval_(`(() => {
    const visible = (el) => !! el && el.getClientRects().length > 0;
    const cancel = [...document.querySelectorAll('[data-testid="confirmation-cancel"], [data-testid="modal-cancel"], [data-testid="modal-close"]')].find(visible);
    cancel?.click();
    return true;
  })()`);
  await waitFor(`! [...document.querySelectorAll('[role="dialog"]')].some((d) => d.getClientRects().length > 0)`, { timeout: 3000 }).catch(() => {});
  await sleep(300);
};

const openMenu = `(() => {
  const panel = [...document.querySelectorAll('[data-record-menu]')].find((p) => p.style.display !== 'none');
  if (! panel) return null;
  const items = panel.querySelector('[data-touch-menu]');
  return {
    key: panel.dataset.recordMenu,
    text: panel.innerText.replace(/\\s+/g, ' ').trim(),
    touchItems: !! items && items.style.display !== 'none',
  };
})()`;

try {
  await page('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 });
  await page('Page.reload');
  await waitFor(`!! window.Livewire && !! document.querySelector('tbody tr[data-row-key]') && !! window.Alpine`, { timeout: 15000 });
  await sleep(300);
  await eval_(recordCalls);

  check('the tablet is a coarse pointer', await eval_(`matchMedia('(pointer: coarse)').matches`));
  check('and gets the desktop table, not the stacked cards',
    await eval_(`!! document.querySelector('tbody tr[data-row-key]')?.getClientRects().length`));

  // ── The visible way in: ⋯ ──
  const trigger = await eval_(`(() => {
    const b = document.querySelector('tbody tr[data-row-key] [data-testid="row-touch-menu"]');
    if (! b) return null;
    // The actions column can sit past the edge of a scrolling table on a tablet.
    b.scrollIntoView({ block: 'center', inline: 'center' });
    const r = b.getBoundingClientRect();
    const x = Math.round(r.left + r.width / 2);
    const y = Math.round(r.top + r.height / 2);
    return { visible: getComputedStyle(b).display !== 'none' && r.width > 0, x, y, hit: b.contains(document.elementFromPoint(x, y)) };
  })()`);
  check('every row carries a ⋯ a finger can see', trigger?.visible === true && trigger?.hit === true, JSON.stringify(trigger));

  await tap(trigger);
  await waitFor(`!! (${openMenu})`, { timeout: 3000 }).catch(() => {});
  const fromTrigger = await eval_(openMenu);
  check('tapping ⋯ opens the row menu', !! fromTrigger, JSON.stringify(fromTrigger));
  check('…with the actions a mouse reaches by a gesture', fromTrigger?.touchItems === true && /Open/.test(fromTrigger?.text ?? ''), fromTrigger?.text);
  check('…and the right-click ones', /Duplicate/.test(fromTrigger?.text ?? ''));
  check('…without running anything', (await eval_(`window.__calls.length`)) === 0, await eval_(`JSON.stringify(window.__calls)`));
  await shot('01-trigger-menu');

  // A tap elsewhere closes it, as a click does.
  await tap({ x: 900, y: 40 });
  await waitFor(`! (${openMenu})`, { timeout: 2000 }).catch(() => {});
  check('a tap elsewhere closes it', ! (await eval_(openMenu)));

  // ── Long press = right click ──
  const cell = await reachableCell(1);
  await press(cell, 800);
  await waitFor(`!! (${openMenu})`, { timeout: 2000 }).catch(() => {});
  const fromPress = await eval_(openMenu);
  const secondKey = await eval_(`document.querySelectorAll('tbody tr[data-row-key]')[1].dataset.rowKey`);
  check('a long press opens the menu of the row it rests on', fromPress?.key === secondKey, JSON.stringify(fromPress));
  check('…as a touch menu', fromPress?.touchItems === true);
  check('…and the lift that ends it runs nothing', (await eval_(`window.__calls.length`)) === 0, await eval_(`JSON.stringify(window.__calls)`));
  await shot('02-long-press');

  // Choosing an item runs it against that row.
  await eval_(`[...document.querySelectorAll('[data-record-menu]')].find((p) => p.style.display !== 'none').querySelector('[data-touch-menu] button, [data-touch-menu] [role="menuitem"]').click()`);
  await waitFor(`window.__calls.includes('open')`, { timeout: 5000 }).catch(() => {});
  check('choosing a touch item runs that action', (await eval_(`JSON.stringify(window.__calls)`)) === '["open"]', await eval_(`JSON.stringify(window.__calls)`));
  await closeModal();

  // A press that drifts is a scroll, not a right click.
  await eval_(`window.__calls = []`);
  await touch('touchStart', cell.x, cell.y);
  await sleep(150);
  await touch('touchMove', cell.x, cell.y + 40);
  await sleep(700);
  await touch('touchEnd', cell.x, cell.y + 40);
  await sleep(200);
  check('a press that moves is not a long press', ! (await eval_(openMenu)));

  // ── Double tap = double click ──
  // A fresh page: each section starts from a table with nothing open over it,
  // rather than from whatever the last one's modal left on its way out.
  await page('Page.reload');
  await waitFor(`!! window.Livewire && !! document.querySelector('tbody tr[data-row-key]') && !! window.Alpine`, { timeout: 15000 });
  await sleep(300);
  await eval_(recordCalls);
  const first = await reachableCell(0);
  await tap(first);
  await sleep(120);
  await tap(first);
  await waitFor(`window.__calls.length > 0`, { timeout: 3000 }).catch(() => {});
  await sleep(500);
  check('a double tap runs the double-click action, once', (await eval_(`JSON.stringify(window.__calls)`)) === '["open"]', await eval_(`JSON.stringify(window.__calls)`));
  check('…and the page did not zoom', (await eval_(`window.visualViewport ? visualViewport.scale : 1`)) === 1);
  await shot('03-double-tap');
  await closeModal();

  // ── Tap vs double tap, both bound ──
  await page('Page.navigate', { url: `${origin}/previews/table-record-actions-dual` });
  await waitFor(`!! window.Livewire && !! document.querySelector('tbody tr[data-row-key]') && !! window.Alpine`, { timeout: 15000 });
  await sleep(300);
  await eval_(recordCalls);

  const dual = await reachableCell(0);
  await tap(dual);
  await sleep(900);
  check('a single tap is a click', (await eval_(`JSON.stringify(window.__calls)`)) === '["view"]', await eval_(`JSON.stringify(window.__calls)`));
  await closeModal();

  await eval_(`window.__calls = []`);
  const dual2 = await reachableCell(1);
  await tap(dual2);
  await sleep(120);
  await tap(dual2);
  await sleep(900);
  check('a double tap runs only the double-click action, never the tap one', (await eval_(`JSON.stringify(window.__calls)`)) === '["edit"]', await eval_(`JSON.stringify(window.__calls)`));
  await closeModal();

  // ── A mouse is left as it was ──
  await page('Emulation.setTouchEmulationEnabled', { enabled: false });
  await page('Page.navigate', { url: `${origin}/previews/table-record-actions` });
  await waitFor(`!! window.Livewire && !! document.querySelector('tbody tr[data-row-key]') && !! window.Alpine`, { timeout: 15000 });
  await sleep(300);

  check('a mouse sees no ⋯', await eval_(`(() => {
    const b = document.querySelector('tbody tr[data-row-key] [data-testid="row-touch-menu"]');
    return !! b && getComputedStyle(b).display === 'none';
  })()`));

  const mouse = await reachableCell(0);
  await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x: mouse.x, y: mouse.y });
  await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: mouse.x, y: mouse.y, button: 'right', clickCount: 1 });
  await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: mouse.x, y: mouse.y, button: 'right', clickCount: 1 });
  await waitFor(`!! (${openMenu})`, { timeout: 2000 }).catch(() => {});
  const fromMouse = await eval_(openMenu);
  check('a right click still opens the menu', !! fromMouse, JSON.stringify(fromMouse));
  check('…without the touch items', fromMouse?.touchItems === false && ! /\bOpen\b/.test(fromMouse?.text ?? ''), fromMouse?.text);
  await shot('04-mouse');
} catch (error) {
  check('driver ran to the end', false, error.message);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
