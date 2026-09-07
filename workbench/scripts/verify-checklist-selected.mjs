import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for a CheckboxList's chosen-chips row, its search and its groups
 * (/previews/field-checkbox-list-permissions).
 *
 * Three things here are Alpine and only Alpine, and each renders perfectly while
 * being wrong:
 *
 *   - the chips read the *entangled state*, not the ticked boxes. A markup test
 *     cannot tell the two apart; a browser can, because filtering removes rows
 *     from view and the chips must survive that — which is the whole reason
 *     they exist.
 *   - a group heading has to leave with its options. Before the fix, searching
 *     hid every row and kept every heading, so the list read as groups that
 *     failed to load.
 *   - removing from a chip has to write through to the checkbox, or the two
 *     controls disagree about the same field.
 *   - the bulk toggles act on what the search left. Filtering to `invoices` and
 *     pressing "Select all" used to grant every permission in the system, which
 *     is a control acting outside what it is pointed at.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-checkbox-list-permissions`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'checklist-selected' });

const { check, finish } = checker();

const FIELD = 'data.permissions';

try {
  await eval_(`
    window.box = (value) => document.querySelector(
      '[data-testid="form-checklist-${FIELD}-' + value + '"]'
    );
    window.chips = () => [...document.querySelectorAll(
      '[data-testid="form-checklist-${FIELD}-selected"] span > span'
    )].map((e) => e.textContent.trim());
    window.chipRow = () => document.querySelector('[data-testid="form-checklist-${FIELD}-selected"]');
    window.groups = () => [...document.querySelectorAll(
      '[data-testid="form-checklist-${FIELD}-group"]'
    )];
    window.visibleGroups = () => groups().filter((g) => g.offsetParent !== null).length;
    window.searchBox = () => document.querySelector('[data-testid="form-checklist-${FIELD}-search"]');
    window.selectAllBtn = () => document.querySelector('[data-testid="form-checklist-${FIELD}-select-all"]');
    window.deselectAllBtn = () => document.querySelector('[data-testid="form-checklist-${FIELD}-deselect-all"]');
    window.type = (text) => {
      const input = searchBox();
      input.value = text;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    true;
  `);

  await waitFor(`typeof Alpine !== 'undefined' && !! box('invoices.view')`, 8000);

  // ── 1. Nothing chosen, nothing shown ─────────────────────────────────────
  // An empty bar is a row of chrome that never says anything.
  check('the chip row is absent while nothing is selected', await eval_(`chipRow().offsetParent === null`));
  check('and the groups are all there to start', (await eval_(`visibleGroups()`)) === 3);

  // ── 2. Ticking a box says so above the list ──────────────────────────────
  await eval_(`box('invoices.view').click()`);
  await waitFor(`chips().length === 1`, 4000);
  check('ticking an option adds a chip', (await eval_(`JSON.stringify(chips())`)) === '["invoices.view"]');

  // Out of declaration order on purpose: the chips must not reshuffle into the
  // order things happened to be ticked, which is harder to read than the list.
  await eval_(`box('impersonate').click()`);
  await waitFor(`chips().length === 2`, 4000);
  await eval_(`box('invoices.create').click()`);
  await waitFor(`chips().length === 3`, 4000);

  check('the chips keep the list order, not the order of ticking', await eval_(`
    JSON.stringify(chips()) === JSON.stringify(['invoices.view', 'invoices.create', 'impersonate'])
  `));
  await shot('01-chips');

  // ── 3. Filtering hides rows, and takes empty groups with it ──────────────
  await eval_(`type('users')`);
  await waitFor(`visibleGroups() === 1`, 4000);

  check('a search leaves only the groups that still have options', (await eval_(`visibleGroups()`)) === 1);
  check('and no heading stands over nothing', await eval_(`
    groups().filter((g) => g.offsetParent !== null)
      .every((g) => [...g.querySelectorAll('input[type=checkbox]')].some((b) => b.offsetParent !== null))
  `));

  // The point of the chips: the selection is off screen now, and still legible.
  check('what is selected survives the filter that hides it', await eval_(`
    box('invoices.view').offsetParent === null && chips().length === 3
  `));
  await shot('02-filtered');

  // ── 4. A chip is a way back out ──────────────────────────────────────────
  await eval_(`document.querySelector('[data-testid="form-checklist-${FIELD}-unpick-invoices.view"]').click()`);
  await waitFor(`chips().length === 2`, 4000);

  check('removing a chip drops it from the selection', await eval_(`
    ! chips().includes('invoices.view')
  `));

  // And it writes through: the box behind the filter must agree with the chip.
  await eval_(`type('')`);
  await waitFor(`!! box('invoices.view')?.offsetParent`, 4000);
  check('and unticks the box it stands for', (await eval_(`box('invoices.view').checked`)) === false);
  check('leaving the others alone', await eval_(`box('invoices.create').checked && box('impersonate').checked`));
  await shot('03-unpicked');

  // ── 5. The bulk toggles act on what the search left ──────────────────────
  // The accident this prevents: filter to one resource, press "Select all", and
  // grant every permission in the system.
  await eval_(`deselectAllBtn().click()`);
  await waitFor(`chips().length === 0`, 4000);

  await eval_(`type('invoices')`);
  await waitFor(`box('users.view').offsetParent === null`, 4000);
  await eval_(`selectAllBtn().click()`);
  await waitFor(`chips().length === 3`, 4000);

  check('select-all takes the matches and nothing beyond them', await eval_(`
    JSON.stringify(chips()) === JSON.stringify(['invoices.view', 'invoices.create', 'invoices.*'])
  `));

  // And it must not drop what is selected outside the filter.
  await eval_(`type('users')`);
  await waitFor(`!! box('users.view')?.offsetParent`, 4000);
  await eval_(`selectAllBtn().click()`);
  await waitFor(`chips().length === 5`, 4000);

  check('and adds to what was already selected elsewhere', await eval_(`
    chips().includes('invoices.view') && chips().includes('users.view')
  `));

  // The mirror image: deselect must not revoke what the filter is hiding.
  await eval_(`deselectAllBtn().click()`);
  await waitFor(`chips().length === 3`, 4000);

  check('deselect-all takes off the matches, and only them', await eval_(`
    JSON.stringify(chips()) === JSON.stringify(['invoices.view', 'invoices.create', 'invoices.*'])
  `));

  // Unfiltered, both still mean everything — one code path, no special case.
  await eval_(`type('')`);
  await waitFor(`!! box('users.view')?.offsetParent`, 4000);
  await eval_(`selectAllBtn().click()`);
  await waitFor(`chips().length === 6`, 4000);
  check('with nothing typed, select-all still means every option', (await eval_(`chips().length`)) === 6);

  await eval_(`deselectAllBtn().click()`);
  // Waiting on the row, not on the chip count: an empty `x-for` renders nothing
  // a frame before `x-show` takes the row away, so counting chips says "none"
  // while the bar is still on screen.
  await waitFor(`chips().length === 0 && chipRow().offsetParent === null`, 4000);
  check('and deselect-all still means none of them', await eval_(`chipRow().offsetParent === null`));
  await shot('04-bulk-filtered');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
