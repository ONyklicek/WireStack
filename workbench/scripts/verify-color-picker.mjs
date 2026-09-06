import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for ColorPicker (/previews/field-color-picker).
 *
 * The field's premise is that the state holds the notation `format()` asked for
 * while `<input type="color">` — which only speaks hex — drives the swatch. All
 * of that conversion is JavaScript: the parser, the HSL maths, and the hex
 * mirror that keeps the native control and the state in step. Pest sees a colour
 * input and a text input.
 *
 * The body moved out of the view into `wireColorPicker`, so this also pins that
 * the registered controller is what the markup evaluates against.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-color-picker.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-color-picker`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'color-picker' });

const { check, finish } = checker();

try {
  await eval_(`
    window.root = () => document.querySelector('[x-data^="wireColorPicker"]');
    window.picker = () => Alpine.$data(root());
    window.swatch = () => root().querySelector('input[type="color"]');
    window.wire = () => Livewire.all()[0].$wire;
    window.state = () => wire().get('data.brand_color');
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !! root()`);
  check('the preview boots against the registered controller', booted);
  await shot('01-initial');

  const seeded = await eval_(`state()`);
  check('the seeded colour reaches the field', seeded === '#f59e0b', `state=${seeded}`);

  const mirrored = await eval_(`swatch().value`);
  check('the native swatch shows the hex mirror of the state', mirrored === '#f59e0b', `swatch=${mirrored}`);

  // ── The parser answers in every notation the field accepts ────────────
  const parsed = await eval_(`JSON.stringify([
    picker().parse('#fff'),
    picker().parse('rgb(255, 0, 0)'),
    picker().parse('hsl(120, 100%, 50%)'),
    picker().parse('nonsense'),
  ])`);
  check('three-digit hex, rgb and hsl all parse, and nonsense falls back to black',
    parsed === JSON.stringify([
      { r: 255, g: 255, b: 255, a: 1 },
      { r: 255, g: 0, b: 0, a: 1 },
      { r: 0, g: 255, b: 0, a: 1 },
      { r: 0, g: 0, b: 0, a: 1 },
    ]), parsed);

  // ── The state is written in the notation the field was configured with ─
  // This preview asks for rgb(), so a hex the swatch produces must arrive as
  // rgb — that conversion is the whole point of the field.
  check('the field carries the format PHP configured', (await eval_(`picker().format`)) === 'rgb');

  await eval_(`picker().pick('#1d4ed8')`);
  await waitFor(`state() === 'rgb(29, 78, 216)'`);
  check('picking a colour writes the configured notation to state',
    (await eval_(`state()`)) === 'rgb(29, 78, 216)', `state=${await eval_('state()')}`);
  check('and the swatch still speaks hex', (await eval_(`swatch().value`)) === '#1d4ed8',
    `swatch=${await eval_('swatch().value')}`);
  await shot('02-picked');

  // Every notation the field offers, from the one value.
  const notations = await eval_(`JSON.stringify(['hex', 'rgb', 'rgba', 'hsl'].map((f) => {
    picker().format = f;
    return picker().stringify(picker().parse('#1d4ed8'));
  }))`);
  check('the same colour writes correctly in every notation',
    notations === JSON.stringify(['#1d4ed8', 'rgb(29, 78, 216)', 'rgba(29, 78, 216, 1)', 'hsl(224, 76%, 48%)']),
    notations);

  check('the hex mirror still drives the native control',
    (await eval_(`picker().hex`)) === '#1d4ed8', `hex=${await eval_('picker().hex')}`);

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
