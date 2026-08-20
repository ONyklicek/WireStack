import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * CDP driver for what a poll tick is allowed to do to client-side state.
 *
 * A polling table re-renders on a timer, and every re-render is a morph over
 * DOM the user is currently working in. Three things live there that the server
 * render knows nothing about:
 *
 *   1. the search box, mid-word — `wire:model.live.debounce` means the server
 *      still holds the PREVIOUS value while the user types, so a tick landing
 *      in the debounce window renders the old string back over the new one;
 *   2. the row context menu, which is open purely as inline `style.display` on
 *      a teleported panel — a morph re-applies the server's `display: none`;
 *   3. everything at once, after the Stop control: pausing drops the
 *      `wire:poll` wrapper element itself, which changes the element tree the
 *      next morph has to line up against.
 *
 * None of this is visible to Pest: the markup is correct on every one of these
 * renders. The bug is only ever what the SECOND render does to the first one's
 * DOM, which is a browser question.
 *
 * The fixture polls every 1s (`poll()`, not `live()` — change detection would
 * skip the renders this is here to drive) and carries a search box, a context
 * menu and the Stop control on one page.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-poll-client-state.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL
  ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-gestures-live`;

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'poll-client-state', width: 1400, height: 1100, settle: 3500,
});
const { check, finish } = checker();

try {
  await eval_(`
    window.$q = (s) => document.querySelector(s);
    window.$qa = (s) => Array.from(document.querySelectorAll(s));
    window.vis = (el) => !! el && !! el.getClientRects().length;
    window.search = () => $q('[data-testid="table-search"]');
    window.rows = () => $qa('tbody tr[data-row-key]');
    window.menus = () => $qa('[data-record-menu]').filter(vis);
    window.pollAttr = () => !! $q('[wire\\\\:poll], [wire\\\\:poll\\\\.1s]')
      || $qa('*').some((el) => Array.from(el.attributes).some((a) => a.name.startsWith('wire:poll')));
    true;
  `);

  const rowCount = () => eval_('rows().length');
  const searchValue = () => eval_('search().value');

  const measure = async (expr) => JSON.parse(await eval_(
    `(() => { const r = (${expr}).getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 }); })()`
  ));

  const clickAt = async (expr, button = 'left') => {
    const at = await measure(expr);
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: at.x, y: at.y, button, clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: at.x, y: at.y, button, clickCount: 1 });
    return at;
  };

  // Enough rows that a search visibly narrows them; the paginated fixtures
  // show a page, the unpaginated ones all 40.
  const baseline = await rowCount();
  check('fixture renders its rows', baseline >= 10, `${baseline} rows`);
  check('the table is polling', await eval_('pollAttr()') === true);

  // ── 1. a search typed across poll ticks is not rolled back ──────────────
  // Typed one character at a time at human speed. The term is long enough
  // (9 chars ≈ 1.8s) that ticks land in the middle of it, and each keystroke
  // restarts the 300ms debounce, so the server is holding a stale value for
  // most of the typing. Every keystroke is checked, because a rollback is a
  // single frame — sampling only at the end would miss it.
  await eval_(`search().focus()`);
  const term = 'Record 07';
  const stomps = [];
  for (let i = 0; i < term.length; i++) {
    await page('Input.insertText', { text: term[i] });
    await sleep(200);
    const expected = term.slice(0, i + 1);
    const actual = await searchValue();
    if (actual !== expected) stomps.push(`after "${expected}" → "${actual}"`);
  }
  check('typing into search survives the poll ticks it spans',
    stomps.length === 0, stomps.slice(0, 3).join('; ') || 'no rollback');

  // The 300ms debounce, a roundtrip and a morph. Waiting for the filtered row
  // set rather than guessing at the sum: on a loaded machine the guess falls
  // short, and everything downstream then asserts against an unfiltered table.
  await until(async () => await rowCount() === 1);
  const settled = await searchValue();
  const filtered = await rowCount();
  check('the finished search term is still intact', settled === term, `"${settled}"`);
  check('the search actually filtered', filtered === 1 && filtered < baseline,
    `${filtered} of ${baseline} rows`);
  await shot('01-search-typed');

  // The tick AFTER the search landed must not undo it either.
  await sleep(1800);
  check('a later tick does not clear the applied search',
    await searchValue() === term && await rowCount() === 1,
    `"${await searchValue()}", ${await rowCount()} rows`);

  // Back to the full set for the rest of the run.
  await eval_(`search().focus(); search().select();`);
  await page('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Delete', code: 'Delete', windowsVirtualKeyCode: 46 });
  await page('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Delete', code: 'Delete', windowsVirtualKeyCode: 46 });
  await until(async () => await rowCount() === baseline);
  check('clearing the search restores every row', await rowCount() === baseline,
    `${await rowCount()} rows`);

  // ── 2. an open context menu survives a tick ─────────────────────────────
  // Opened through the real delegated handler. A right-click dispatched over
  // CDP does not synthesise `contextmenu` in headless Chrome, so the event is
  // dispatched at the row with real coordinates instead — what is under test
  // is what the morph does to the open panel, not how it opened.
  const at = await measure(`rows()[2].querySelector('td:nth-child(3)')`);
  await eval_(`
    rows()[2].querySelector('td:nth-child(3)').dispatchEvent(new MouseEvent('contextmenu', {
      bubbles: true, cancelable: true, clientX: ${Math.round(at.x)}, clientY: ${Math.round(at.y)},
    }));
    true;
  `);
  await until(async () => await eval_('menus().length') === 1);
  const openedCount = await eval_('menus().length');
  check('right-click opens the row context menu', openedCount === 1, `${openedCount} visible`);

  const before = await eval_(`JSON.stringify((menus()[0] || {}).style ? { left: menus()[0].style.left, top: menus()[0].style.top } : null)`);
  await shot('02-menu-open');

  // Two full ticks with the menu open.
  await sleep(2400);
  const stillOpen = await eval_('menus().length');
  const after = await eval_(`JSON.stringify((menus()[0] || {}).style ? { left: menus()[0].style.left, top: menus()[0].style.top } : null)`);
  check('the context menu stays open across poll ticks', stillOpen === 1, `${stillOpen} visible`);
  check('the context menu keeps its position', stillOpen === 1 && after === before,
    `${before} → ${after}`);
  await shot('03-menu-after-ticks');

  // …but only while its row is still there. Keeping a menu open over a record
  // that has been filtered off the page would aim its actions at something
  // nobody can see — the restore has to know when to give up.
  const goneKey = await eval_(`menus()[0].dataset.recordMenu`);
  await eval_(`search().focus()`);
  await page('Input.insertText', { text: 'Record 33' });
  await sleep(1800);
  const survivor = await eval_('menus().length');
  check('a context menu whose row is filtered away closes',
    survivor === 0, `${survivor} still visible (row ${goneKey})`);
  await shot('03b-menu-row-filtered');

  await eval_(`search().focus(); search().value = ''; search().dispatchEvent(new Event('input', { bubbles: true }));`);
  await sleep(1600);
  check('the row set is back after that search', await rowCount() === baseline,
    `${await rowCount()} rows`);

  await eval_(`document.body.click()`);
  await sleep(400);

  // ── 3. pausing the poll leaves the table working ────────────────────────
  await clickAt(`$q('[data-testid="polling-toggle"]')`);
  await sleep(1200);
  check('the Stop control pauses the poll', await eval_('pollAttr()') === false);
  await shot('04-paused');

  // The whole point: a paused table is a table, not a corpse.
  check('the search box is still on the page while paused',
    await eval_('vis(search())') === true);

  await eval_(`search().focus()`);
  await page('Input.insertText', { text: 'Record 12' });
  await sleep(1600);
  const pausedValue = await searchValue();
  const pausedRows = await rowCount();
  check('search still works with the poll paused',
    pausedValue === 'Record 12' && pausedRows === 1,
    `"${pausedValue}", ${pausedRows} rows`);
  await shot('05-paused-search');

  // Right-click, paused: the context menu is not part of the poll.
  const at2 = await measure(`rows()[0].querySelector('td:nth-child(3)')`);
  await eval_(`
    rows()[0].querySelector('td:nth-child(3)').dispatchEvent(new MouseEvent('contextmenu', {
      bubbles: true, cancelable: true, clientX: ${Math.round(at2.x)}, clientY: ${Math.round(at2.y)},
    }));
    true;
  `);
  await sleep(400);
  check('the context menu still opens with the poll paused',
    await eval_('menus().length') === 1, `${await eval_('menus().length')} visible`);
  await eval_(`document.body.click()`);
  await sleep(300);

  // ── 4. …and resuming brings the poll back ───────────────────────────────
  await clickAt(`$q('[data-testid="polling-toggle"]')`);
  await sleep(1200);
  check('the Start control resumes the poll', await eval_('pollAttr()') === true);

  // Proof it is a live poll again rather than just an attribute: a tick has to
  // actually reach the server. The search state (still applied) must survive it.
  await sleep(2400);
  check('the resumed poll keeps the applied search',
    await searchValue() === 'Record 12' && await rowCount() === 1,
    `"${await searchValue()}", ${await rowCount()} rows`);
  await shot('06-resumed');
} catch (err) {
  console.error(`DRIVER ERROR: ${err.message}`);
  process.exitCode = 2;
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
