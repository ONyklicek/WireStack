import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for what the drag controller is allowed to keep a Livewire morph
 * from doing.
 *
 * wireSortable registers two global morph hooks so a drag in progress, and a
 * cell being typed into, survive a re-render. Both used to ask the wrong
 * question — "is ANY input inside the table focused?" — of every node from the
 * sortable wrapper down. `skip()` takes the whole subtree with it and
 * `contains()` is inclusive, so the answer came back yes at the wrapper and the
 * morph never entered the table at all.
 *
 * The search box is an input inside the table. Typing in it therefore silenced
 * exactly the render it had just asked for: the server filtered correctly, sent
 * the rows back, and the client threw them away. To the user the table simply
 * stopped responding to search — no error, nothing in the console, the same
 * "10 of 7097" as before.
 *
 * None of this is visible to Pest: the response is right on every render. The
 * bug is only what the client does with it.
 *
 * The fixture (`sortable-morph`) is column-reorderable — enough to render the
 * wrapper and its hooks — but NOT in row-reorder mode, which bypasses search on
 * the server by design and would hide the very thing under test. It carries a
 * search box and an editable column, the two halves of the guard.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-sortable-morph.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/sortable-morph';

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'sortable-morph', width: 1400, height: 1000, settle: 3500,
});
const { check, finish } = checker();

const type = async (text) => {
  for (const ch of text) {
    await page('Input.dispatchKeyEvent', { type: 'keyDown', text: ch });
    await page('Input.dispatchKeyEvent', { type: 'keyUp' });
    await sleep(60);
  }
};

try {
  await eval_(`
    window.root = document.querySelector('[x-data*="wireSortable"]');
    window.d = Alpine.$data(root);
    window.cmp = Livewire.find(root.closest('[wire\\\\:id]').getAttribute('wire:id'));

    window.search = () => document.querySelector('[data-testid="table-search"]');
    window.rows = () => document.querySelectorAll('tbody tr[data-row-key]').length;
    window.titles = () => [...document.querySelectorAll('tbody tr[data-row-key] [data-column="title"]')]
      .map((el) => el.textContent.trim());
    window.cells = () => [...document.querySelectorAll('[data-record-key][data-column-name]')];
    window.cellInput = (i = 0) => cells()[i].querySelector('input');

    // How far a morph actually gets inside the table, and how often the drag
    // controller rebuilds itself while it happens. Both are counted from the
    // real hooks, so a skip at the wrapper shows up as "the body was never
    // visited" rather than as an absence of symptoms.
    window.probe = { visited: 0, inBody: 0, setups: 0 };
    window.resetProbe = () => { probe.visited = 0; probe.inBody = 0; probe.setups = 0; };
    Livewire.hook('morph.updating', ({ el }) => {
      if (! root.contains(el)) return;
      probe.visited++;
      if (el.closest && el.closest('tbody')) probe.inBody++;
    });
    const realSetup = d.setup.bind(d);
    d.setup = () => { probe.setups++; return realSetup(); };

    // One morph, on demand, with nothing else moving. The promise is parked on
    // window and not returned: awaiting it over CDP is what "Promise was
    // collected" comes from, and the sleep after each call is the real wait.
    window.morph = () => { resetProbe(); window._pending = cmp.$refresh(); return true; };
    true;
  `);

  const baseline = await eval_('rows()');
  check('the fixture booted with the sortable wrapper, a search box and editable cells',
    await eval_('!! root && !! d && !! search() && cells().length > 0'),
    `${baseline} rows, ${await eval_('cells().length')} editable cells`);
  check('the table is column-reorderable, not in row-reorder mode',
    await eval_('!! d.columnSortableInstance && d.isReordering === false'),
    `columnSortable=${await eval_('!! d.columnSortableInstance')} isReordering=${await eval_('d.isReordering')}`);
  await shot('01-loaded');

  // ── 1. search, with the search box still focused ─────────────────────────
  // The regression, exactly as a user meets it: click the box, type, never
  // click away. `wire:model.live.debounce` commits 300ms after the last
  // keystroke, while focus is still in the input.
  const needle = 'Publish';
  await eval_(`resetProbe(); search().focus()`);
  await type(needle);
  await sleep(1200);

  const filtered = await eval_('rows()');
  check('search filters while the search box still has focus',
    filtered > 0 && filtered < baseline,
    `${baseline} rows → ${filtered} rows for "${needle}"`);
  check('the rows left are the matching ones',
    (await eval_('JSON.stringify(titles())')).includes(needle),
    await eval_('JSON.stringify(titles())'));
  check('the search box kept focus and the typed term',
    await eval_(`document.activeElement === search() && search().value === ${JSON.stringify(needle)}`),
    `value=${JSON.stringify(await eval_('search().value'))} focused=${await eval_('document.activeElement === search()')}`);
  check('the morph reached the table body instead of stopping at the wrapper',
    await eval_('probe.inBody') > 0,
    `${await eval_('probe.visited')} nodes visited, ${await eval_('probe.inBody')} of them in <tbody>`);
  check('the drag controller rebuilt itself once for that morph, not once per node',
    await eval_('probe.setups') === 1, `${await eval_('probe.setups')} setup() calls`);
  check('column dragging is still wired up after the morph',
    await eval_('!! d.columnSortableInstance'));
  await shot('02-searched');

  // Clearing it puts the rows back — still without leaving the input.
  await eval_(`resetProbe(); search().focus()`);
  for (let i = 0; i < needle.length; i++) {
    await page('Input.dispatchKeyEvent', { type: 'keyDown', windowsVirtualKeyCode: 8, nativeVirtualKeyCode: 8, key: 'Backspace', code: 'Backspace' });
    await page('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 8, nativeVirtualKeyCode: 8, key: 'Backspace', code: 'Backspace' });
    await sleep(60);
  }
  await sleep(1200);
  check('clearing the search restores every row, focus still in the box',
    await eval_('rows()') === baseline && await eval_('document.activeElement === search()'),
    `${await eval_('rows()')} rows`);
  await shot('03-cleared');

  // ── 1b. a morph landing mid-word ─────────────────────────────────────────
  // A poll tick, or someone else's broadcast, arriving inside the 300ms
  // debounce window: the server still holds the previous term, so its render
  // carries the OLD value for the box the user is typing into. That question
  // could not even be asked on a reorderable table before — the global skip
  // answered it by throwing the whole render away.
  await eval_(`search().focus()`);
  await type('Pub');
  await eval_(`cmp.$refresh()`);
  await sleep(900);
  check('a morph landing mid-word leaves the half-typed term alone',
    await eval_('search().value') === 'Pub',
    `value=${JSON.stringify(await eval_('search().value'))}`);
  await sleep(1200);
  check('and the term still filters once the debounce settles',
    await eval_('rows()') < baseline, `${await eval_('rows()')} rows`);

  for (let i = 0; i < 3; i++) {
    await page('Input.dispatchKeyEvent', { type: 'keyDown', windowsVirtualKeyCode: 8, nativeVirtualKeyCode: 8, key: 'Backspace', code: 'Backspace' });
    await page('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 8, nativeVirtualKeyCode: 8, key: 'Backspace', code: 'Backspace' });
    await sleep(60);
  }
  await sleep(1200);
  check('and clears again', await eval_('rows()') === baseline, `${await eval_('rows()')} rows`);

  // ── 2. a cell mid-write still survives a morph ───────────────────────────
  // The half the guards are actually for. Type into an editable cell without
  // committing it, then make an unrelated re-render land on top.
  const original = await eval_('cellInput(0).value');
  await eval_(`cellInput(0).focus()`);
  await type('ZZ');
  const typed = await eval_('cellInput(0).value');
  check('the editable cell took the keystrokes', typed !== original, `${JSON.stringify(original)} → ${JSON.stringify(typed)}`);

  await eval_(`morph()`);
  await sleep(800);
  check('the half-typed cell survives a morph that lands on top of it',
    await eval_('cellInput(0).value') === typed,
    `${JSON.stringify(typed)} → ${JSON.stringify(await eval_('cellInput(0).value'))}`);
  check('focus stayed in the cell being edited',
    await eval_('document.activeElement === cellInput(0)'));
  check('the rest of the table morphed anyway — only that one cell was skipped',
    await eval_('probe.inBody') > 1,
    `${await eval_('probe.visited')} nodes visited, ${await eval_('probe.inBody')} in <tbody>`);
  check('no re-init while a cell is being edited (it would drop the drag handles under focus)',
    await eval_('probe.setups') === 0, `${await eval_('probe.setups')} setup() calls`);
  await shot('04-editing');

  // Escape reverts the cell, so the run leaves the fixture's data as it found
  // it — the cell saves on blur otherwise, and the next run would start from
  // whatever this one typed.
  await page('Input.dispatchKeyEvent', { type: 'keyDown', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27, key: 'Escape', code: 'Escape' });
  await page('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 27, nativeVirtualKeyCode: 27, key: 'Escape', code: 'Escape' });
  await eval_(`document.activeElement.blur()`);
  await sleep(700);
  check('escape put the cell back, so nothing was written',
    await eval_('cellInput(0).value') === original,
    `${JSON.stringify(await eval_('cellInput(0).value'))} (was ${JSON.stringify(original)})`);

  // ── 3. the guard that stays: a drag in progress still blocks the morph ───
  await eval_(`d.isDragging = true; morph()`);
  await sleep(600);
  check('a drag in progress still stops the morph at the wrapper',
    await eval_('probe.inBody') === 0,
    `${await eval_('probe.visited')} nodes visited, ${await eval_('probe.inBody')} in <tbody>`);
  await eval_(`d.isDragging = false`);

  await eval_(`morph()`);
  await sleep(600);
  check('and the next morph after the drag ends goes all the way through',
    await eval_('probe.inBody') > 0,
    `${await eval_('probe.visited')} nodes visited, ${await eval_('probe.inBody')} in <tbody>`);
  await shot('05-after-drag-guard');

  // ── 4. a re-initialised controller replaces the old one, it does not stack ─
  // `Livewire.hook()` has no off switch, so hooks registered from init() pile
  // up — one set per wire:navigate, per lazily loaded table, per second table
  // on the page — and each stacked copy goes on running against a component
  // that no longer exists. Tearing the Alpine tree down and building it again
  // is what a navigation does to this wrapper, in one step.
  await eval_(`
    window.stale = d;
    Alpine.destroyTree(root);
    Alpine.initTree(root);
    window.d = Alpine.$data(root);
    true;
  `);
  await sleep(800);
  check('re-initialising the wrapper yields a new controller',
    await eval_('d !== stale') && await eval_('!! d.columnSortableInstance'));

  // The destroyed controller still points at the same element, so a stale
  // registration would let its isDragging keep blocking every morph the page
  // asks for — the original bug's failure mode, arrived at from the other side.
  await eval_(`stale.isDragging = true; morph()`);
  await sleep(800);
  check('a destroyed controller no longer speaks for the table',
    await eval_('probe.inBody') > 0,
    `${await eval_('probe.visited')} nodes visited, ${await eval_('probe.inBody')} in <tbody>`);
  await eval_(`stale.isDragging = false`);

  // …and the live one still does.
  await eval_(`d.isDragging = true; morph()`);
  await sleep(800);
  check('the controller that replaced it guards the morph instead',
    await eval_('probe.inBody') === 0,
    `${await eval_('probe.visited')} nodes visited, ${await eval_('probe.inBody')} in <tbody>`);
  await eval_(`d.isDragging = false`);
  await shot('06-after-reinit');
} catch (err) {
  console.error('DRIVER ERROR:', err.message);
  process.exitCode = 2;
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
