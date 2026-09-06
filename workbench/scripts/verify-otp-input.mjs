import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for OtpInput (/previews/field-otp-input).
 *
 * The field's body moved out of the view into `wireOtpInput` — the same move the
 * other field controllers already made — and every behaviour that made the boxes
 * feel like one field lives in JavaScript: the focus advance, Backspace stepping
 * back, the arrow keys, and a pasted code filling the row. Pest sees six inputs
 * and cannot tell any of that still works.
 *
 * It also pins the one behaviour that changed with the move: `numericOnly()`
 * used to be a keyboard hint that a letter walked straight past, on a field
 * whose whole name says digits.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-otp-input.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-otp-input`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'otp-input' });

const { check, finish } = checker();

try {
  await eval_(`
    window.boxes = () => [...document.querySelectorAll('[data-testid^="form-otp-data.code-"]')];
    window.box = (i) => boxes()[i];
    window.wire = () => Livewire.all()[0].$wire;
    window.state = () => wire().get('data.code');
    window.shown = () => boxes().map((b) => b.value).join('');
    window.typeInto = (i, text) => {
      const el = box(i);
      el.focus();
      el.value = text;
      el.dispatchEvent(new InputEvent('input', { bubbles: true }));
    };
    window.press = (i, key) => box(i).dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
    window.paste = (text) => {
      const event = new Event('paste', { bubbles: true, cancelable: true });
      event.clipboardData = { getData: () => text };
      box(0).dispatchEvent(event);
    };
    window.focused = () => boxes().indexOf(document.activeElement);
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && boxes().length > 0`);
  check('the preview boots with Alpine and Livewire', booted);
  await shot('01-initial');

  check('the body is registered, not inlined in the markup',
    await eval_(`document.querySelector('[x-data^="wireOtpInput"]') !== null`));

  const count = await eval_(`boxes().length`);
  check('every box renders', count === 6, `boxes=${count}`);

  const seeded = await eval_(`shown()`);
  check('the seeded code is spread across the boxes', seeded === '283041', `boxes=${seeded}`);

  // ── Typing advances ───────────────────────────────────────────────────
  await eval_(`typeInto(0, '9')`);
  await waitFor(`focused() === 1`);
  check('typing advances to the next box', (await eval_(`focused()`)) === 1);

  await waitFor(`state() === '983041'`);
  check('the boxes are joined into one value', (await eval_(`state()`)) === '983041', `state=${await eval_('state()')}`);

  // ── numericOnly refuses a letter without eating the digit ─────────────
  await eval_(`typeInto(1, 'x')`);
  const afterLetter = await eval_(`shown()`);
  check('a letter never lands in a digits-only box, and does not delete what was there',
    afterLetter === '983041', `boxes=${JSON.stringify(afterLetter)}`);
  check('the box shows the digit it still holds', (await eval_(`box(1).value`)) === '8');
  await shot('02-letter-refused');

  // ── Backspace: clear in place, then step back ─────────────────────────
  await eval_(`typeInto(1, '8'); typeInto(2, '3')`);
  await waitFor(`shown().startsWith('983')`);

  // A filled box is emptied where it stands — the caret must not run away
  // from the digit the user is still looking at.
  await eval_(`box(3).focus(); press(3, 'Backspace')`);
  await waitFor(`box(3).value === ''`);
  check('backspace empties a filled box in place',
    (await eval_(`focused()`)) === 3, `focus=${await eval_('focused()')}`);

  // The same key on the now-empty box steps back and clears the one before.
  await eval_(`press(3, 'Backspace')`);
  await waitFor(`focused() === 2`);
  check('backspace in an empty box steps back and clears the one before',
    (await eval_(`shown()`)) === '9841', `boxes=${await eval_('shown()')}`);

  // ── Arrows walk the row ───────────────────────────────────────────────
  await eval_(`box(2).focus(); press(2, 'ArrowLeft')`);
  check('the arrow keys walk the row', (await eval_(`focused()`)) === 1);

  // ── A pasted code fills it ────────────────────────────────────────────
  await eval_(`paste('12 34 56')`);
  await waitFor(`shown() === '123456'`);
  check('a pasted code fills the row, whitespace and all', (await eval_(`shown()`)) === '123456');
  await waitFor(`state() === '123456'`);
  check('the pasted code reaches Livewire state', (await eval_(`state()`)) === '123456');
  check('the caret lands on the last filled box', (await eval_(`focused()`)) === 5);
  await shot('03-pasted');

  // A pasted string with letters is filtered, not truncated at the letter.
  await eval_(`paste('9a8b7c6d5e4f')`);
  await waitFor(`shown() === '987654'`);
  check('a pasted code keeps its digits and drops the rest',
    (await eval_(`shown()`)) === '987654', `boxes=${await eval_('shown()')}`);

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
