import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * `layout('list')` in a real browser (/previews/table-list).
 *
 * It used to drive the notifications resource, which was the only screen that
 * asked for this layout — and which has since been rewritten as a bespoke list
 * that does not use a table at all. So the layout kept its capability and lost
 * its page, and this drove a screen that could no longer fail the way it was
 * written to catch. `table-list` is a preview of the layout itself: cards as the
 * only rendering, selection off, row verbs behind one trigger.
 *
 * Pest sees the markup: no `<table>`, cards present, the sort control there.
 * What only a browser can answer is whether the list is still a *table* under
 * the surface — the search, the filters, the per-row menu — because all of that
 * is Alpine reading a region the card rendering has to carry as faithfully as
 * the row rendering does. (Selection is a capability of the layout and is
 * covered in Pest; this page deliberately does not use it — see below.)
 *
 * The failure this guards is the quiet one: the cards were written to be the
 * second half of a stacked table, so anything they only got by sitting beside a
 * `<table>` is now missing, and nothing says so.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-list-layout.mjs
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const { eval_, waitFor, shot, shotDir, consoleErrors, close } = await openPage({
  url: `${base}/table-list`,
  shotPrefix: 'list-layout',
  width: 1400,
  height: 950,
});

try {
  await waitFor(`!! window.Alpine && document.querySelectorAll('[data-testid="table-card"]').length > 0`);

  // ── 1. It is a list, and only a list ─────────────────────────────────────
  const cards = await eval_(`document.querySelectorAll('[data-testid="table-card"]').length`);
  check('the records render as cards', cards > 0, `${cards} cards`);
  // The payload claim: one rendering per record, not two chosen by CSS.
  check('and no table is in the document at all',
    (await eval_(`document.querySelectorAll('table').length`)) === 0);
  check('so there is no header row to hide', await eval_(`! document.querySelector('thead')`));

  // A list has no header row, so this is the only way to reorder it.
  check('sorting has a control of its own', await eval_(`
    !! document.querySelector('[data-testid="table-mobile-sort"]')
  `));
  await shot('01-list');

  // ── 2. It is still a table underneath ────────────────────────────────────
  // The whole argument for a layout rather than a hand-written page.
  check('the search survived', await eval_(`!! document.querySelector('[data-testid="table-search"]')`));
  check('the filters survived', await eval_(`!! document.querySelector('[data-testid="table-filters-trigger"], [data-testid="table-filters"]')`));

  // ── 2b. And nothing left on it announces a table ─────────────────────────
  // Each of these was really on the page that first asked for this layout, and
  // each was the wrong thing there: a checkbox on every row, a grey select-all
  // bar above them, and a control offering to read an inbox in tens and fifties.
  // What replaces the selection is a header action over the whole filtered set —
  // stronger than ticking the rows on one page, and quieter.
  check('no checkbox on any card', (await eval_(`
    document.querySelectorAll('[data-testid="table-card-select"]').length
  `)) === 0);
  check('no select-all bar above them', await eval_(`
    ! document.querySelector('[data-testid="table-card-select-all"]')
  `));
  check('no page-size control in the footer', await eval_(`
    ! document.querySelector('[data-testid="table-per-page"]')
  `));
  // Structural rather than by its words: the verb belongs to whichever screen
  // uses the layout, and a driver that matches one screen's wording is a driver
  // that breaks when that screen is rewritten — which is how this one came to be
  // pointed at a page that had stopped being a table.
  check('but a header action over the whole set', await eval_(`
    !! document.querySelector('[data-testid^="header-action-"]')
  `));
  await shot('02-no-table-furniture');

  // ── 3. The per-row menu opens ────────────────────────────────────────────
  // Folded into the card header by collapseActionsOnMobile(threshold: 1); a
  // dropdown inside a card is a different stacking context from one inside a
  // row, which is exactly the kind of thing markup tests cannot see.
  const trigger = await eval_(`(() => {
    const el = document.querySelector('[data-testid="table-card"] [data-testid="action-group-trigger"], [data-testid="table-card"] button[aria-haspopup]');
    if (! el) return false;
    el.click();
    return true;
  })()`);
  check('a card offers its verbs behind one trigger', trigger === true);

  if (trigger) {
    await sleep(700);
    check('and the menu opens over the list', await eval_(`
      [...document.querySelectorAll('[role="menu"], [data-testid="dropdown-panel"]')]
        .some(el => el.offsetParent !== null)
    `));
    await shot('03-menu');
  }

  check('no console errors', consoleErrors.length === 0, consoleErrors.join(' | '));
  console.log(`\nScreenshots: ${shotDir}`);
} finally {
  await close();
}

finish();
