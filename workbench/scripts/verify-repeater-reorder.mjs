import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for the repeater's row controls (/previews/forms-repeater-controls).
 *
 * This one exists because of a specific failure. For months the repeater, its
 * table layout and the builder all shipped `x-sortable` / `x-sortable-item` /
 * `x-sortable-handle` against a directive **nothing in the repository
 * registered**. The handle rendered, the cursor said `grab`, and dragging did
 * nothing at all. Every Pest test reached `reorderRepeaterItems` through
 * `->call(...)`, and the one driver that looked at a handle asserted only that it
 * existed — so the whole stack was green over a feature that had never worked.
 *
 * The lesson is in the shape of the checks below: nothing here trusts markup.
 * The drag is a real pointer gesture through CDP, and what is asserted is the
 * *state after the Livewire roundtrip*, read out of the bound inputs.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-repeater-reorder.mjs
 */

const base = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${base}/previews/forms-repeater-controls`;

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'repeater-reorder' });

const { check, finish } = checker();

/** Drag `handle` by (dx, dy) with a real pointer gesture. */
async function drag(handleSelector, dx, dy) {
  // Scroll it into view before measuring. CDP dispatches mouse events at
  // viewport coordinates, so a handle below the fold gets a y past the viewport
  // and the gesture lands on nothing — which reads exactly like a drag that does
  // not work, and cost this driver a false failure on the table section.
  const box = await eval_(`(() => {
    const el = document.querySelector(${JSON.stringify(handleSelector)});
    if (! el) return null;
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
  })()`);

  if (! box) return false;

  await sleep(150);

  const { x, y } = JSON.parse(box);

  await page('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1, buttons: 1 });

  // In steps, not one jump: SortableJS decides what to swap from the pointer
  // crossing a row's midpoint, and a single teleporting move can land past every
  // midpoint at once without the library ever seeing the crossing.
  for (let step = 1; step <= 8; step++) {
    await page('Input.dispatchMouseEvent', {
      type: 'mouseMoved', x: x + (dx * step) / 8, y: y + (dy * step) / 8, button: 'left', buttons: 1,
    });
    await sleep(30);
  }

  await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: x + dx, y: y + dy, button: 'left', clickCount: 1, buttons: 0 });

  return true;
}

try {
  await eval_(`
    window.$qa = (s) => [...document.querySelectorAll(s)];
    // Read the order out of the bound inputs, never out of the DOM's own
    // arrangement: SortableJS moves nodes on its own, so asking the DOM whether
    // a drag "worked" would answer yes even when the server never heard about it.
    window.cardOrder = () => $qa('input[wire\\\\:model^="contacts."]')
      .filter((i) => /\\.label$/.test(i.getAttribute('wire:model')))
      .map((i) => i.value);
    window.rowOrder = () => $qa('tbody tr input[wire\\\\:model^="lines."]')
      .filter((i) => /\\.description$/.test(i.getAttribute('wire:model')))
      .map((i) => i.value);
    window.cards = () => $qa('[data-collapsible-item]');
    window.sortRoot = () => $qa('[x-data]').find((el) => /wireSortableList/.test(el.getAttribute('x-data') || ''));
    true;
  `);

  // Poll rather than trust the settle: on a busy machine — 70-odd Chromes taking
  // turns on one dev server during a sweep — Alpine has not booted and the bound
  // inputs are still empty at the three-second mark, and every check after this
  // fails for a reason that has nothing to do with the repeater.
  await waitFor(`typeof Alpine !== 'undefined' && cardOrder().filter(Boolean).length === 3`, { timeout: 20000 });

  const booted = await eval_(`typeof Alpine !== 'undefined' && cardOrder().length === 3`);
  check('the preview renders three named contact rows', booted, await eval_(`cardOrder().join('|')`));
  await shot('01-initial');

  // ── expandLast(), asserted on the first render ───────────────────────
  //
  // Deliberately before anything else touches the list. The policy decides how a
  // row *first* renders; it is not a rule re-imposed on every re-render, because
  // a row the user is looking at must not snap shut underneath them when they add
  // another one. (Alpine binds an `x-show` expression at init and does not
  // re-read it when a morph rewrites the attribute, so the implementation and the
  // contract agree here rather than fighting.)
  const bodyHeights = () => eval_(`JSON.stringify($qa('[data-collapsible-item] > div:last-child')
    .map((b) => b.getBoundingClientRect().height > 0))`);

  const initial = JSON.parse(await bodyHeights());
  check('expandLast() opens only the final row on first render',
    initial.length === 3 && initial[0] === false && initial[1] === false && initial[2] === true,
    JSON.stringify(initial));

  // ── The controller is actually there ─────────────────────────────────
  check('a wireSortableList root exists', await eval_(`!! sortRoot()`) === true);

  const bound = await eval_(`(() => {
    const root = sortRoot();
    if (! root) return 'no-root';

    const data = Alpine.$data(root);

    // The whole point: an unregistered factory leaves x-data evaluating to
    // nothing, and a declared drag that never bound is the failure this
    // controller was written to end.
    if (! data || typeof data.sortableConfig !== 'function') return 'no-controller';

    // SortableJS stamps itself onto the element it bound, under a key it builds
    // as 'Sortable' + a timestamp. Asked by prefix rather than by name because
    // the timestamp is not knowable — and asked of the element rather than of
    // the controller because Alpine's x-sort owns the instance now, so there is
    // nothing on our side to hold it. That makes this a stronger check than the
    // one it replaces: it proves the library bound, not merely that an object
    // existed.
    const isBound = (el) => !! el && Object.keys(el).some((key) => key.startsWith('Sortable'));

    return isBound(root) || Array.from(root.children).some(isBound) ? 'bound' : 'no-instance';
  })()`);
  check('SortableJS is bound to the list, not just declared on it', bound === 'bound', bound);

  // ── The drag itself ──────────────────────────────────────────────────
  const before = await eval_(`cardOrder().join('|')`);

  const cardHeight = await eval_(`Math.round(cards()[0].getBoundingClientRect().height)`);
  const dragged = await drag('[data-testid="form-repeater-contacts-reorder-0"]', 0, cardHeight + 20);
  check('the first row has a drag handle to grab', dragged === true);

  // Wait for the *server* to answer, not for the DOM to look different.
  await waitFor(`cardOrder().join('|') !== ${JSON.stringify(before)}`, { timeout: 8000 });
  const after = await eval_(`cardOrder().join('|')`);

  check('dragging a row past its neighbour reorders the bound state',
    after === 'Billing|Support|Slack', `before=${before} after=${after}`);
  await shot('02-after-drag');

  // Upwards too. Deliberately its own check: reverting the dropped node used to
  // be computed from the old index against the *current* children, which is only
  // correct when the item moved forwards — a backwards drag flashed a third
  // arrangement before the server's answer landed, and a driver that only ever
  // dragged downwards would never have seen it.
  const beforeUp = await eval_(`cardOrder().join('|')`);
  const upHeight = await eval_(`Math.round(cards()[0].getBoundingClientRect().height)`);
  await drag('[data-testid="form-repeater-contacts-reorder-2"]', 0, -(upHeight * 2 + 20));
  await waitFor(`cardOrder().join('|') !== ${JSON.stringify(beforeUp)}`, { timeout: 8000 });

  const afterUp = await eval_(`cardOrder().join('|')`);
  // Derived from what was on screen, not hardcoded: every check below moves the
  // list, and a literal expectation would have to be recomputed by hand each
  // time one is inserted — which is how a driver ends up asserting the wrong
  // thing rather than the right thing in the wrong order.
  const expectedUp = (() => {
    const rows = beforeUp.split('|');

    return [rows.at(-1), ...rows.slice(0, -1)].join('|');
  })();
  check('dragging a row up to the front reorders it the same way',
    afterUp === expectedUp, `before=${beforeUp} after=${afterUp}`);

  // ── The keyboard's half ──────────────────────────────────────────────
  const beforeMove = await eval_(`cardOrder().join('|')`);
  await eval_(`document.querySelector('[data-testid="form-repeater-contacts-move-up-2"]').click()`);
  await waitFor(`cardOrder().join('|') !== ${JSON.stringify(beforeMove)}`, { timeout: 8000 });

  const afterMove = await eval_(`cardOrder().join('|')`);
  const expectedMove = (() => {
    const rows = beforeMove.split('|');
    [rows[1], rows[2]] = [rows[2], rows[1]];

    return rows.join('|');
  })();
  check('the move-up button reorders without a pointer',
    afterMove === expectedMove, `before=${beforeMove} after=${afterMove}`);

  const endsDisabled = await eval_(`
    document.querySelector('[data-testid="form-repeater-contacts-move-up-0"]').disabled === true &&
    document.querySelector('[data-testid="form-repeater-contacts-move-down-2"]').disabled === true
  `);
  check('the move buttons are disabled at the ends', endsDisabled === true);

  // ── Duplication ──────────────────────────────────────────────────────
  const beforeClone = (await eval_(`cardOrder().join('|')`)).split('|');
  await eval_(`document.querySelector('[data-testid="form-repeater-contacts-clone-0"]').click()`);
  await waitFor(`cardOrder().length === 4`, { timeout: 8000 });

  const cloned = (await eval_(`cardOrder().join('|')`)).split('|');
  check('duplicating a row puts the copy directly below its original',
    cloned[0] === beforeClone[0] && cloned[1] === beforeClone[0] && cloned[2] === beforeClone[1],
    cloned.join('|'));
  await shot('03-after-clone');

  const afterClone = JSON.parse(await bodyHeights());
  check('the new row opens and the row already on screen is left alone',
    afterClone.length === 4 && afterClone.at(-1) === true && afterClone[0] === false,
    JSON.stringify(afterClone));

  // ── Collapse-all still reaches every row ─────────────────────────────
  // ── Collapse all / expand all ────────────────────────────────────────
  await eval_(`document.querySelector('[data-testid="form-repeater-contacts-toggle-all"]').click()`);
  await sleep(600);

  const allShut = await eval_(`$qa('[data-collapsible-item] > div:last-child')
    .every((b) => b.getBoundingClientRect().height === 0)`);
  check('the header toggle folds every row at once', allShut === true);
  await shot('04-all-collapsed');

  await eval_(`document.querySelector('[data-testid="form-repeater-contacts-toggle-all"]').click()`);
  await sleep(600);

  const allOpen = await eval_(`$qa('[data-collapsible-item] > div:last-child')
    .every((b) => b.getBoundingClientRect().height > 0)`);
  check('and unfolds every row on the second press', allOpen === true);

  // ── The table layout drags its rows too ──────────────────────────────
  const rowsBefore = await eval_(`rowOrder().join('|')`);
  const rowHeight = await eval_(`Math.round(document.querySelector('tbody tr').getBoundingClientRect().height)`);
  await drag('[data-testid="form-repeater-lines-reorder-0"]', 0, rowHeight + 10);
  await waitFor(`rowOrder().join('|') !== ${JSON.stringify(rowsBefore)}`, { timeout: 8000 });

  check('dragging a table row reorders the bound state as well',
    await eval_(`rowOrder().join('|')`) === 'Hosting|Consulting',
    `before=${rowsBefore} after=${await eval_(`rowOrder().join('|')`)}`);
  await shot('05-table-after-drag');

  finish({ consoleErrors, badResponses, shotDir });
} catch (err) {
  console.error('DRIVER ERROR:', err);
  process.exitCode = 2;
} finally {
  await close();
}
