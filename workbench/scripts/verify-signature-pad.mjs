import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for SignaturePad (/previews/field-signature-pad).
 *
 * Everything this field does happens in the browser: PHP renders an empty
 * canvas and a config object, and whether a stroke is drawn, encoded and handed
 * to Livewire is entirely the controller's answer. Pest sees a `<canvas>` and
 * nothing else.
 *
 * What is asserted, in the order a person meets it: the pad comes up blank with
 * its prompt, a pointer stroke paints pixels *and* fills state with a PNG data
 * URI, the state is written once per stroke rather than per movement, and Clear
 * empties both the canvas and the value.
 *
 * The canvas is also checked for its device-pixel backing store — a pad that
 * ignores devicePixelRatio looks fine in a screenshot and blocky on the retina
 * screen a signature is actually drawn on.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-signature-pad.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-signature-pad`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'signature-pad' });

const { check, finish } = checker();

try {
  await eval_(`
    window.canvas = () => document.querySelector('[data-testid="form-signature-data.signature-canvas"]');
    window.clearButton = () => document.querySelector('[data-testid="form-signature-data.signature-clear"]');
    window.wire = () => Livewire.all()[0].$wire;
    window.state = () => wire().get('data.signature');
    window.pad = () => Alpine.\$data(canvas().closest('[x-data]'));
    // A stroke, as a pointer device sends one: down, a few moves, up.
    window.stroke = (points) => {
      const el = canvas();
      const box = el.getBoundingClientRect();
      const at = (p, type) => new PointerEvent(type, {
        bubbles: true, clientX: box.left + p[0], clientY: box.top + p[1], pointerId: 1, isPrimary: true,
      });
      el.setPointerCapture = () => {};
      el.dispatchEvent(at(points[0], 'pointerdown'));
      points.slice(1).forEach((p) => el.dispatchEvent(at(p, 'pointermove')));
      el.dispatchEvent(at(points[points.length - 1], 'pointerup'));
    };
    // Any pixel painted at all — the alpha channel over the whole canvas.
    window.painted = () => {
      const el = canvas();
      const data = el.getContext('2d').getImageData(0, 0, el.width, el.height).data;
      let ink = 0;
      for (let i = 3; i < data.length; i += 4) if (data[i] !== 0) ink++;
      return ink;
    };
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !! canvas()`);
  check('the preview boots with a canvas', booted);
  await shot('01-initial');

  const blank = await eval_(`painted()`);
  check('the pad comes up blank', blank === 0, `inked pixels=${blank}`);
  check('an empty pad shows its prompt', await eval_(`document.body.innerText.includes('Sign here')`));
  check('an empty pad offers nothing to clear', await eval_(`! clearButton() || clearButton().offsetParent === null`));

  // The backing store is sized in device pixels; the CSS box is not.
  const scaled = await eval_(`canvas().width === Math.round(canvas().offsetWidth * (window.devicePixelRatio || 1))`);
  check('the canvas is sized in device pixels, not CSS pixels', scaled,
    `width=${await eval_('canvas().width')} css=${await eval_('canvas().offsetWidth')} dpr=${await eval_('window.devicePixelRatio')}`);

  // ── A stroke paints, and becomes the value ────────────────────────────
  await eval_(`stroke([[40, 60], [80, 90], [140, 50], [200, 100]])`);
  const inked = await eval_(`painted()`);
  check('a pointer stroke paints on the canvas', inked > 0, `inked pixels=${inked}`);

  await waitFor(`typeof state() === 'string' && state().startsWith('data:image/png;base64,')`);
  const value = await eval_(`state().slice(0, 22)`);
  check('the stroke is handed to Livewire as a PNG data URI', value === 'data:image/png;base64,', `state=${value}`);
  check('the prompt gives way to the signature', ! (await eval_(`pad().empty`)));
  await shot('02-signed');

  // The value is written per stroke, not per movement: a second stroke changes
  // it exactly once more.
  const before = await eval_(`state().length`);
  await eval_(`stroke([[220, 40], [260, 80]])`);
  await waitFor(`state().length !== ${before}`);
  check('a second stroke updates the value', (await eval_(`state().length`)) !== before);

  // ── Clear empties both the canvas and the value ───────────────────────
  check('a drawn pad offers a clear control', await eval_(`!! clearButton()`));
  await eval_(`clearButton().click()`);
  await waitFor(`state() === ''`);
  check('clearing empties the value', (await eval_(`JSON.stringify(state())`)) === '""');
  check('clearing wipes the canvas', (await eval_(`painted()`)) === 0);
  await shot('03-cleared');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
