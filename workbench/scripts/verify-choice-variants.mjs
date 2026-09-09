import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for the CheckboxList toggle-button variants
 * (/previews/field-checkbox-list-choices).
 *
 * The claim is that these render the same chrome as the matching Radio variants
 * while staying *multiple* choice. Markup alone cannot show that: what matters
 * is that clicking a second option adds to the selection instead of replacing
 * it, and that the peer-checked styling the shared vocabulary relies on actually
 * applies in the browser.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-choice-variants.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-checkbox-list-choices`;

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'choice-variants' });

const { check, finish } = checker();

// The testid carries the field's full state path, not its bare name.
const PERMISSIONS = 'data.permissions';
const SKILLS = 'data.skills_choice';

try {
  await eval_(`
    window.boxes = (field) => [...document.querySelectorAll(
      '[data-testid^="form-checklist-' + field + '-"]'
    )].filter((e) => e.type === 'checkbox');
    window.box = (field, value) => document.querySelector(
      '[data-testid="form-checklist-' + field + '-' + value + '"]'
    );
    window.checkedValues = (field) => boxes(field).filter((b) => b.checked).map((b) => b.value);
    // Segmented: input, then the aria-hidden pill, then the text span.
    // Buttons: input, then the single button face.
    window.pillOf = (field, value) => box(field, value).nextElementSibling;
    window.faceOf = (field, value) => [...box(field, value).parentElement.querySelectorAll('span')].pop();
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && boxes('${PERMISSIONS}').length > 0`);
  check('the preview renders with Alpine booted', booted);
  await shot('01-initial');

  // ── Still checkboxes, not radios ──────────────────────────────────────
  const inputTypes = await eval_(`[...new Set(boxes('${PERMISSIONS}').map((b) => b.type))].join(',')`);
  check('the segmented variant is built from checkboxes', inputTypes === 'checkbox', `types=${inputTypes}`);

  const optionCount = await eval_(`boxes('${PERMISSIONS}').length`);
  check('every option renders', optionCount === 4, `options=${optionCount}`);

  const seeded = await eval_(`checkedValues('${PERMISSIONS}').join(',')`);
  check('the seeded selection is applied', seeded === 'view,edit', `checked=${seeded}`);

  // ── Segmented chrome ──────────────────────────────────────────────────
  const segmentedTrack = await eval_(`
    !! [...document.querySelectorAll('[role="group"]')]
      .find((g) => g.className.includes('rounded-lg') && g.className.includes('bg-gray-50'))
  `);
  check('the segmented variant renders the shared track', segmentedTrack);

  // The pill highlight is peer-checked driven; a checked option must actually
  // paint differently from an unchecked one.
  const checkedBg = await eval_(`getComputedStyle(pillOf('${PERMISSIONS}', 'view')).backgroundColor`);
  const uncheckedBg = await eval_(`getComputedStyle(pillOf('${PERMISSIONS}', 'create')).backgroundColor`);
  check('a selected segment is painted, an unselected one is not',
    checkedBg !== uncheckedBg, `checked=${checkedBg} unchecked=${uncheckedBg}`);

  // ── Multiple choice: a second click adds, it does not replace ─────────
  await eval_(`box('${PERMISSIONS}', 'create').click()`);
  await sleep(1400);
  const afterAdd = await eval_(`checkedValues('${PERMISSIONS}').join(',')`);
  check('clicking a second option adds to the selection',
    afterAdd === 'view,create,edit', `checked=${afterAdd}`);
  await shot('02-third-selected');

  // ── And clicking a selected one removes it ────────────────────────────
  await eval_(`box('${PERMISSIONS}', 'view').click()`);
  await sleep(1400);
  const afterRemove = await eval_(`checkedValues('${PERMISSIONS}').join(',')`);
  check('clicking a selected option removes it', afterRemove === 'create,edit', `checked=${afterRemove}`);

  // ── Buttons variant: per-option icon and color ────────────────────────
  const buttonsOptions = await eval_(`boxes('${SKILLS}').length`);
  check('the buttons variant renders its options', buttonsOptions === 3, `options=${buttonsOptions}`);

  const hasIcon = await eval_(`!! faceOf('${SKILLS}', 'php').querySelector('svg')`);
  check('a per-option icon renders inside its button', hasIcon);

  const phpFace = await eval_(`faceOf('${SKILLS}', 'php').className`);
  check('a per-option color reaches the button', phpFace.includes('peer-checked:bg-red-600'), phpFace.slice(0, 120));

  const inline = await eval_(`
    !! [...document.querySelectorAll('[role="group"]')].find((g) => g.className.includes('flex-row'))
  `);
  check('inline() lays the buttons out in a row', inline);

  await eval_(`box('${SKILLS}', 'js').click()`);
  await sleep(1400);
  const skills = await eval_(`checkedValues('${SKILLS}').join(',')`);
  check('the buttons variant is multiple choice too', skills === 'php,js', `checked=${skills}`);
  await shot('03-buttons-selected');

  // ── Hover, in both themes ─────────────────────────────────────────────
  // Pest sees `hover:bg-…` in the markup and calls it a day. What it cannot see
  // is that the rule loses, or wins where it should not: `dark:` compiles to a
  // zero-specificity `:where()` variant and `peer-checked:` only ties with
  // `hover:`, so one careless light-mode hover repaints the *dark* face
  // near-white and wipes the accent off a *selected* one. Only a browser, with
  // a real pointer over the element, can say which declaration actually landed.
  const bgOf = (value) => eval_(
    "getComputedStyle(faceOf('" + SKILLS + "', '" + value + "')).backgroundColor"
  );

  const boxOf = (value) => eval_(
    "(() => { const r = faceOf('" + SKILLS + "', '" + value + "').getBoundingClientRect();"
    + " return { x: Math.round(r.x + r.width / 2), y: Math.round(r.y + r.height / 2) }; })()"
  );

  const pointAt = async (x, y) => {
    await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y, buttons: 0 });
    await sleep(300);
  };

  const setTheme = async (theme) => {
    await eval_("document.documentElement.classList.toggle('dark', " + (theme === 'dark') + "); true");
    await sleep(200);
  };

  // Computed colors arrive as `oklch(L C H)` (Tailwind v4) or `rgb(…)`. L says
  // whether the face moved and which side of the theme it stayed on; C
  // separates a live accent from a grey.
  const lc = (css) => {
    const ok = css.match(/^oklch\(([\d.]+) ([\d.]+)/);
    if (ok) return { l: Number(ok[1]), c: Number(ok[2]) };
    const rgb = css.match(/rgba?\((\d+), (\d+), (\d+)/);
    if (! rgb) return { l: NaN, c: NaN };
    const [r, g, b] = rgb.slice(1).map(Number);
    const grey = Math.max(r, g, b) === Math.min(r, g, b);
    return { l: (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255, c: grey ? 0 : 1 };
  };

  // `php` is selected and carries ->colors(['php' => 'danger']); `css` is not.
  const measure = async (value) => {
    const rest = lc(await bgOf(value));
    const at = await boxOf(value);
    await pointAt(at.x, at.y);
    const over = lc(await bgOf(value));
    await pointAt(2, 2);
    return { rest, over };
  };

  for (const theme of ['light', 'dark']) {
    await setTheme(theme);

    const unselected = await measure('css');
    // The bug this block exists for: gray-50 over white moved L by 0.015, which
    // a person reads as "nothing happened".
    check(`[${theme}] hovering an unselected button visibly repaints it`,
      Math.abs(unselected.over.l - unselected.rest.l) >= 0.03,
      `rest L=${unselected.rest.l} hover L=${unselected.over.l}`);
    check(`[${theme}] the hovered face stays on its own side of the theme`,
      theme === 'dark' ? unselected.over.l < 0.5 : unselected.over.l > 0.5,
      `hover L=${unselected.over.l}`);

    const selected = await measure('php');
    check(`[${theme}] hovering a selected button keeps its accent`,
      selected.over.c > 0.05, `hover C=${selected.over.c}`);
    check(`[${theme}] hovering a selected button still answers`,
      Math.abs(selected.over.l - selected.rest.l) >= 0.02,
      `rest L=${selected.rest.l} hover L=${selected.over.l}`);

    await shot(`04-hover-${theme}`);
  }

  await setTheme('light');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
