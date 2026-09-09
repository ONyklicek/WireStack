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

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
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

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
