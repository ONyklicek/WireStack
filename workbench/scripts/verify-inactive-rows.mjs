import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for `Table::rowInactive()` — a record that stays in the list and
 * stops being writable.
 *
 * Pest can see that the row carries `data-inactive`, that its cells carry a
 * disabled flag, and that the write is refused. What it cannot see is the half
 * this feature is actually made of:
 *
 *  - clicking a locked cell must open NO editor. The editable cell is an Alpine
 *    component that swaps a value for an input on click, and a disabled flag the
 *    controller ignores would render identically and still open;
 *  - the `inert` checkbox must not answer a click. `inert` is an attribute the
 *    browser enforces, not a class — a `pointer-events-none` written instead
 *    would leave the box focusable and answering Space, and nothing server-side
 *    would notice;
 *  - the row's action must still run, because it is what undoes the state. A
 *    lock applied one element too high (the whole `<tr>` rather than the cells)
 *    passes every other check and takes the way out with it.
 *
 * Fixture: `table-inactive-rows` — four seeded users, the last of them
 * deactivated, editable text/select/toggle cells, a selection and a Reactivate
 * row action.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-inactive-rows.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

let session;
try {
  session = await openPage({
    url: `${base}/table-inactive-rows`,
    shotPrefix: 'inactive-rows',
    width: 1400,
    height: 1100,
  });
  const { eval_, waitFor, shot, consoleErrors, badResponses, shotDir, page, close } = session;

  await eval_(`
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.rows = () => $qa('tr[data-row-key]');
    // The deactivated user is the one row the table marked; every check below
    // reads this row and an ordinary one, so a lock that applied to the whole
    // table would fail rather than pass twice.
    window.dead = () => rows().find((r) => r.dataset.inactive === 'true');
    window.live = () => rows().find((r) => r.dataset.inactive !== 'true');
    window.cellIn = (row, name) => row.querySelector('[data-column="' + name + '"]');
    window.sel = () => Alpine.$data($q('[data-selection-root]'));
    true;
  `);

  await waitFor('rows().length >= 4');

  const marked = await eval_(`JSON.stringify({
    rows: rows().length,
    inactive: rows().filter((r) => r.dataset.inactive === 'true').length,
    aria: dead()?.getAttribute('aria-disabled'),
  })`);
  const m = JSON.parse(marked);
  check('exactly one row is marked inactive', m.rows >= 4 && m.inactive === 1, marked);
  check('the marked row tells assistive technology too', m.aria === 'true', `aria-disabled=${m.aria}`);

  // ── the look actually lands in the browser, not just in the class string ──
  // Computed style, not the class attribute: the utilities are arbitrary
  // variants written in PHP, so a class Tailwind never extracted would sit in
  // the attribute and paint nothing.
  const painted = await eval_(`(() => {
    const cell = cellIn(dead(), 'email');
    const liveCell = cellIn(live(), 'email');
    return JSON.stringify({
      dead: getComputedStyle(cell).textDecorationLine,
      deadColor: getComputedStyle(cell).color,
      live: getComputedStyle(liveCell).textDecorationLine,
      liveColor: getComputedStyle(liveCell).color,
    });
  })()`);
  const p = JSON.parse(painted);
  check('the inactive row is struck through and an ordinary one is not',
    p.dead.includes('line-through') && ! p.live.includes('line-through'), painted);
  check('the inactive row is dimmed and an ordinary one is not',
    p.deadColor !== p.liveColor, painted);

  await shot('01-table');

  // ── a locked cell renders no editor, and a click opens none ─────────────
  // The editable cell renders its input up front and swaps the display for it
  // on click, so "no editor" is the absence of the control itself — a locked
  // column falls back to the read-only rendering.
  const editors = await eval_(`JSON.stringify({
    dead: cellIn(dead(), 'email').querySelectorAll('input, select').length,
    live: cellIn(live(), 'email').querySelectorAll('input, select').length,
  })`);
  const e = JSON.parse(editors);
  check('the locked cell renders no editor and an ordinary one does',
    e.dead === 0 && e.live > 0, editors);

  await realClick(page, eval_, `cellIn(dead(), 'email')`);
  await sleep(600);
  check('clicking the locked cell opens none either',
    await eval_(`cellIn(dead(), 'email').querySelectorAll('input, select').length`) === 0
    && await eval_(`cellIn(dead(), 'email').contains(document.activeElement)`) === false);

  // The same click on a live row must reach its editor, or the check above
  // proves only that the preview has no inline editing at all.
  await realClick(page, eval_, `cellIn(live(), 'email')`);
  await waitFor(`cellIn(live(), 'email').contains(document.activeElement)`, { timeout: 4000 }).catch(() => {});
  check('the same click on an ordinary row reaches its editor',
    await eval_(`cellIn(live(), 'email').contains(document.activeElement)`) === true);
  await eval_(`document.activeElement?.blur?.()`);
  await sleep(400);

  // ── the toggle on the locked row refuses too ────────────────────────────
  const toggleState = () => eval_(`(() => {
    const b = cellIn(dead(), 'is_active').querySelector('button, input[type=checkbox]');
    return JSON.stringify({ disabled: b?.disabled ?? b?.getAttribute('aria-disabled'), checked: b?.getAttribute('aria-checked') ?? b?.checked });
  })()`);
  const toggleBefore = await toggleState();
  await realClick(page, eval_, `cellIn(dead(), 'is_active').querySelector('button, input[type=checkbox]')`);
  await sleep(700);
  check('the toggle on the locked row does not flip', await toggleState() === toggleBefore,
    `${toggleBefore} → ${await toggleState()}`);

  // ── the inert checkbox answers nothing ──────────────────────────────────
  await eval_(`sel().selected = []`);
  await sleep(200);
  await realClick(page, eval_, `dead().querySelector('[data-select-cell]')`);
  await sleep(600);
  check('clicking the inert checkbox selects nothing',
    await eval_('sel().selected.length') === 0, `selected=${await eval_('sel().selected.length')}`);

  // And the ordinary row's checkbox still works, or the check above is vacuous.
  await realClick(page, eval_, `live().querySelector('[data-select-cell]')`);
  await waitFor('sel().selected.length === 1', { timeout: 4000 }).catch(() => {});
  check('an ordinary row still ticks', await eval_('sel().selected.length') === 1,
    `selected=${await eval_('sel().selected.length')}`);

  // The header box completes over the rows a tick can reach, rather than
  // waiting for one it can never have.
  await eval_(`$q('[data-testid="table-select-all"], thead [role="checkbox"], thead input[type=checkbox]')?.click()`);
  await sleep(700);
  const swept = JSON.parse(await eval_(`JSON.stringify({
    selected: sel().selected.length,
    deadSelected: sel().isSelected(dead().dataset.rowKey),
  })`));
  check('"select page" skips the locked row', swept.deadSelected === false, JSON.stringify(swept));

  await shot('02-selection');

  // ── the way out is still there ──────────────────────────────────────────
  const actionButton = `dead().querySelector('td:last-child button')`;
  const actionState = JSON.parse(await eval_(`(() => {
    const cell = dead().querySelector('td:last-child');
    const btn = cell?.querySelector('button');
    return JSON.stringify({
      cellInert: cell?.hasAttribute('inert') ?? null,
      hasButton: !! btn,
      label: btn?.innerText?.trim() ?? null,
    });
  })()`));
  check('the action cell is NOT inert on this fixture', actionState.cellInert === false, JSON.stringify(actionState));
  check('the row still offers the action that undoes the state', actionState.hasButton === true, JSON.stringify(actionState));

  const subjectKey = await eval_(`dead().dataset.rowKey`);

  await realClick(page, eval_, actionButton);
  // The action writes, so the row stops being inactive — which is the one
  // end-to-end proof that the lock is a state and not a rendering.
  await waitFor(`rows().filter((r) => r.dataset.inactive === 'true').length === 0`, { timeout: 8000 }).catch(() => {});
  check('running it clears the state and the row becomes writable again',
    await eval_(`rows().filter((r) => r.dataset.inactive === 'true').length`) === 0);

  await shot('03-after-reactivate');

  // ── and back, which is also what keeps this driver re-runnable ──────────
  // The fixture is a seeded row and the action above wrote to it, so a second
  // run would find no inactive record at all and fail on its first check. The
  // toggle that refused a moment ago is the way back: it is live now, which is
  // the same claim read from the other side.
  await eval_(`window.subject = () => rows().find((r) => r.dataset.rowKey === ${JSON.stringify(subjectKey)}); true;`);
  await realClick(page, eval_, `cellIn(subject(), 'is_active').querySelector('button, input[type=checkbox]')`);
  await waitFor(`subject()?.dataset.inactive === 'true'`, { timeout: 8000 }).catch(() => {});
  check('flipping the toggle back puts the record into the state again',
    await eval_(`subject()?.dataset.inactive`) === 'true',
    `data-inactive=${await eval_(`subject()?.dataset.inactive`)}`);

  await shot('04-restored');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await session?.close();
}

/** A real pointer click at the element's centre — Alpine listens for those. */
async function realClick(page, eval_, expression) {
  const at = JSON.parse(await eval_(`(() => {
    const el = ${expression};
    el.scrollIntoView({ block: 'center' });
    const r = el.getBoundingClientRect();
    return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
  })()`));
  await sleep(120);
  await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: at.x, y: at.y, button: 'left', clickCount: 1 });
  await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: at.x, y: at.y, button: 'left', clickCount: 1 });
}
