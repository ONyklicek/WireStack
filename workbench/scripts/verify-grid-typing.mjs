import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * CDP driver for filling in a grid: the save of one cell comes back while the
 * user is already typing into the next (/previews/sortable-grid-partials and
 * /previews/sortable-grid-region).
 *
 * What was reported: on an order's line grid — twelve text inputs a row — every
 * cell had to be waited for. Leave a cell, start typing into another, and the
 * moment the first one's save answered the second lost the focus, saved what
 * was in it mid-word and locked itself.
 *
 * It was not the morph. The grid has draggable headers, and the drag controller
 * (wire-sortable) repairs the column order of whatever a row partial replaced:
 * `reorderBodyColumns()` re-inserted every column cell of EVERY row in header
 * order. A cell that already stood there was inserted where it was — which is
 * not a no-op to the browser. The node is taken out of the document and put
 * back, and an input inside it loses the focus on the way. So the cell being
 * typed into was blurred by any save anywhere on the page, in its own row or
 * not; blur is what saves a dirty cell, so it committed half a word, and a cell
 * whose write is out is readonly.
 *
 * `verify-row-partials` already typed into the next cell during a save and
 * passed all along: its table has no draggable headers. The fixture here is
 * that shape with them, and without them nothing below can fail.
 *
 * Checked on both answers a write can get — the row alone (`rowPartials()`,
 * through the `wire:partials-applied` announcement) and the data region
 * (Livewire's own morph) — with every `updateTableCell` answer held back
 * HOLD_MS, so the typing certainly straddles it:
 *
 *   1. the next input of the same row, an input of another row and one of the
 *      last row each keep the focus, as the same node, with no blur at all;
 *   2. what was typed before the answer and after it is all there, in order,
 *      with the caret behind it, and the cell is not locked;
 *   3. the row the write belonged to really was re-rendered, so "nothing
 *      happened" cannot pass for "nothing was disturbed";
 *   4. the repair the controller exists for still works: with the headers
 *      dragged into an order the server has not been told yet, a row partial is
 *      put back into it — and an input of another row keeps the focus meanwhile.
 *
 * Mutates tasks' titles and puts them back; run it against a preview database
 * you do not mind changing.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-grid-typing.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed.
 */

const HOLD_MS = 1200;

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const fixtures = [
  ['row partials', `${origin}/previews/sortable-grid-partials`, true],
  ['data region', `${origin}/previews/sortable-grid-region`, false],
];

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url: fixtures[0][1], shotPrefix: 'grid-typing', width: 1400, height: 1000, settle: 4000,
});
const { check, finish } = checker();

const install = () => eval_(`
  window.$rows = () => [...document.querySelectorAll('tbody tr[data-row-key]')];
  window.$input = (row, col) => window.$rows()[row].querySelector('[data-column-name="' + col + '"] input');
  window.$cell = (row, col) => Alpine.$data(window.$input(row, col).closest('[data-record-key]'));
  window.$order = (row) => [...window.$rows()[row].children].map((td) => td.getAttribute('data-column')).filter(Boolean);
  window.$sortable = () => Alpine.$data(document.querySelector('.wire-sortable-wrapper'));

  // Every cell write's answer is held HOLD_MS; what it carried is kept.
  window.__net = { answers: [], out: 0 };
  const original = window.fetch;
  window.fetch = async (input, options = {}) => {
    const body = typeof options.body === 'string' ? options.body : '';
    if (! body.includes('updateTableCell')) return original(input, options);

    window.__net.out++;
    const response = await original(input, options);
    try {
      const effects = (await response.clone().json()).components.map((c) => c.effects ?? {});
      window.__net.answers.push({
        html: effects.some((e) => e.html !== undefined),
        islands: effects.some((e) => (e.islandFragments ?? []).length > 0),
        partials: effects.flatMap((e) => Object.keys(e.wirePartials ?? {})),
      });
    } catch (e) {}
    await new Promise((r) => setTimeout(r, ${HOLD_MS}));
    window.__net.out--;
    return response;
  };

  // The input the user moved on to, watched from the moment it has the focus.
  window.$watch = (el) => {
    window.__next?.observer.disconnect();
    const next = window.__next = { el, blurs: 0, moved: 0 };
    el.addEventListener('blur', () => next.blurs++);
    next.observer = new MutationObserver((records) => records.forEach((r) => r.removedNodes.forEach((n) => {
      if (n.contains?.(el)) next.moved++;
    })));
    next.observer.observe(document.body, { childList: true, subtree: true });
  };
  window.$next = () => { const n = window.__next; return {
    focused: document.activeElement === n.el, connected: n.el.isConnected, blurs: n.blurs, moved: n.moved,
    shown: n.el.value, caret: n.el.selectionStart, readonly: n.el.readOnly,
    active: document.activeElement?.tagName ?? null,
  }; };
  true;
`);

const json = async (expression) => JSON.parse(await eval_(`JSON.stringify(${expression})`));
const type = (text) => page('Input.insertText', { text });
const settled = () => until(() => eval_(`window.__net.out === 0`), { timeout: HOLD_MS * 4 + 6000 });

try {
  for (const [name, url, partials] of fixtures) {
    if (url !== fixtures[0][1]) {
      await page('Page.navigate', { url });
      await sleep(4000);
    }
    await install();

    const premise = await json(`{
      rows: window.$rows().length,
      inputs: window.$rows()[0].querySelectorAll('[data-column-name] input').length,
      draggableHeaders: document.querySelectorAll('thead th[data-sortable-column]').length,
      anchored: document.querySelectorAll('tbody tr[wire\\\\:partial]').length,
    }`);
    check(`[${name}] a grid of rows with two inputs each, under draggable headers`,
      premise.rows >= 3 && premise.inputs === 2 && premise.draggableHeaders >= 2
      && (premise.anchored > 0) === partials, JSON.stringify(premise));

    const last = premise.rows - 1;

    // ── 1–3. Moving on while the save is out ───────────────────────────────
    for (const [label, from, to] of [
      ['the next input of the same row', [0, 'title'], [0, 'owner_name']],
      ['an input of another row', [1, 'title'], [2, 'owner_name']],
      ['an input of the last row', [last, 'title'], [last, 'owner_name']],
    ]) {
      const written = `typing-${Date.now()}`;
      const before = await json(`{ title: window.$cell(${from[0]}, 'title').serverValue,
        next: window.$cell(${to[0]}, '${to[1]}').serverValue,
        sync: window.$input(${from[0]}, 'title').closest('[data-record-key]').querySelector('[data-cell-sync]').dataset.serverValue }`);

      // Type into the first cell, then click on — which blurs it, and saves it.
      await eval_(`(() => { window.__net.answers = []; const a = window.$input(${from[0]}, '${from[1]}'); a.focus(); a.select(); return true; })()`);
      await type(written);
      await eval_(`(() => { const b = window.$input(${to[0]}, '${to[1]}'); b.focus(); b.select(); window.$watch(b); return true; })()`);

      await type('ab');
      // Livewire sends a call a few milliseconds after it is made.
      await until(() => eval_(`window.__net.out === 1`), { timeout: 4000 });
      const during = await json(`{ out: window.__net.out, ...window.$next() }`);
      await settled();
      await sleep(400);
      await type('cd');
      const after = await json(`{ ...window.$next(), answers: window.__net.answers,
        sync: window.$input(${from[0]}, 'title').closest('[data-record-key]').querySelector('[data-cell-sync]').dataset.serverValue }`);

      check(`[${name}] ${label}: typing started while the save was still out`,
        during.out === 1 && during.shown === 'ab', JSON.stringify(during));
      check(`[${name}] …the save came back as ${partials ? 'a row partial' : 'the data region'}`,
        partials
          ? after.answers.some((a) => a.partials.some((p) => p.startsWith('row-')) && ! a.html)
          : after.answers.some((a) => a.islands || a.html), JSON.stringify(after.answers));
      check(`[${name}] …and its row really was re-rendered`,
        after.sync === written && before.sync !== written, `${before.sync} -> ${after.sync}`);
      check(`[${name}] …${label} kept the focus through it, as the same node`,
        after.focused === true && after.connected === true, JSON.stringify(after));
      check(`[${name}] …it was never blurred, nor taken out of the document`,
        after.blurs === 0 && after.moved === 0, JSON.stringify(after));
      check(`[${name}] …what was typed before the answer and after it is all there, caret behind it`,
        after.shown === 'abcd' && after.caret === 4, JSON.stringify(after));
      check(`[${name}] …and it is not locked`, after.readonly === false, JSON.stringify(after));

      // Leave nothing behind: the second cell never saves — unless it was
      // blurred, which is the failure, and then it has — and the first goes back.
      await eval_(`(async () => {
        const next = window.$cell(${to[0]}, '${to[1]}');
        next.onEscape();
        await next._writing;
        if (next.serverValue !== ${JSON.stringify(before.next)}) await next.commit(${JSON.stringify(before.next)});
        await window.$cell(${from[0]}, 'title').commit(${JSON.stringify(before.title)});
        return true;
      })()`);
      await sleep(300);
    }
    await shot(`01-${partials ? 'partials' : 'region'}-typed`);

    if (! partials) continue;

    // ── 4. The repair itself: a partial arriving mid header drag ──────────
    // The headers are moved the way a drag leaves them and the body follows —
    // the state between a drop and the server hearing about it. The row the
    // partial brings is in the SERVER's order and has to be put back.
    const dragged = await json(`(() => {
      const head = document.querySelector('thead tr');
      const [first, second] = head.querySelectorAll('th[data-sortable-column]');
      head.insertBefore(second, first);
      const sortable = window.$sortable();
      const order = sortable.headerColumnOrder(document.querySelector('thead'));
      sortable.reorderBodyColumns(order);
      return { order, rows: window.$rows().map((_, i) => window.$order(i)) };
    })()`);
    check('[row partials] the body follows a header drag',
      dragged.rows.every((row) => JSON.stringify(row) === JSON.stringify(dragged.order)), JSON.stringify(dragged));

    const original = await json(`window.$cell(0, 'title').serverValue`);
    await eval_(`(() => { window.__net.answers = []; const b = window.$input(2, 'owner_name'); b.focus(); b.select(); window.$watch(b); return true; })()`);
    await type('ab');
    await eval_(`window.$cell(0, 'title').commit('repair-' + Date.now()); true`);
    await until(() => eval_(`window.__net.out === 1`), { timeout: 4000 });
    await settled();
    await sleep(400);
    await type('cd');
    const repaired = await json(`{ ...window.$next(), answers: window.__net.answers, rows: window.$rows().map((_, i) => window.$order(i)) }`);

    check('[row partials] a row partial arriving meanwhile is put back into the headers\' order',
      repaired.answers.some((a) => a.partials.some((p) => p.startsWith('row-')))
      && repaired.rows.every((row) => JSON.stringify(row) === JSON.stringify(dragged.order)), JSON.stringify(repaired.rows));
    check('[row partials] …and an input of another row keeps the focus and what was typed',
      repaired.focused === true && repaired.blurs === 0 && repaired.moved === 0 && repaired.shown === 'abcd',
      JSON.stringify(repaired));
    await shot('02-repaired');

    await eval_(`(async () => {
      window.$cell(2, 'owner_name').onEscape();
      await window.$cell(0, 'title').commit(${JSON.stringify(original)});
      return true;
    })()`);
  }
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
