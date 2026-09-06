import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for MoneyInput (/previews/field-money-input).
 *
 * What Pest cannot see: the grouping. The field's whole premise is that the
 * amount is written the way a person writes it *while it is typed*, by Alpine's
 * `$money` mask configured from the field's own format — PHP only renders the
 * `x-mask:dynamic` expression, and whether the browser then groups `1234567`
 * into `1 234 567` is the browser's answer, not the markup's.
 *
 * The second claim it checks is the one the field is designed around: what the
 * mask produces is exactly what the server parses back, so the value that
 * reaches Livewire state is the grouped text and nothing has silently dropped a
 * separator or a decimal.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-money-input.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-money-input`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'money-input' });

const { check, finish } = checker();

try {
  await eval_(`
    window.input = () => document.querySelector('#data\\\\.price');
    window.wire = () => Livewire.all()[0].$wire;
    window.type = (text) => {
      const el = input();
      el.focus();
      el.value = '';
      for (const ch of text) {
        el.value += ch;
        el.dispatchEvent(new InputEvent('input', { bubbles: true, data: ch, inputType: 'insertText' }));
      }
      el.dispatchEvent(new Event('change', { bubbles: true }));
      el.blur();
    };
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !! input()`);
  check('the preview boots with Alpine and Livewire', booted);
  await shot('01-initial');

  // The mask is an expression PHP wrote from the field's format; if the mask
  // plugin were missing, everything below would still "pass" as raw text.
  const mask = await eval_(`input().getAttribute('x-mask:dynamic') ?? '<none>'`);
  check('the input carries the money mask PHP configured',
    mask.includes('$money') && mask.includes("' '"), `x-mask:dynamic=${mask}`);

  const seeded = await eval_(`input().value`);
  check('the seeded amount is shown grouped', seeded === '1 234,50', `value=${JSON.stringify(seeded)}`);

  // The currency is the affix, never part of the value — that is what makes the
  // typed figure readable back as a number.
  const inValue = await eval_(`input().value.includes('CZK')`);
  const affix = await eval_(`document.body.innerText.includes('CZK')`);
  check('the currency renders beside the input, not inside it', affix && ! inValue);

  // ── Typing groups as it goes ──────────────────────────────────────────
  await eval_(`type('1234567')`);
  const grouped = await eval_(`input().value`);
  check('the mask groups the thousands as the amount is typed',
    grouped.startsWith('1 234 567'), `value=${JSON.stringify(grouped)}`);
  await shot('02-typed');

  // ── And the grouped text is what the server receives ───────────────────
  await waitFor(`wire().get('data.price') !== '1 234,50'`);
  const state = await eval_(`wire().get('data.price')`);
  check('Livewire state holds exactly what the mask wrote',
    state === grouped, `state=${JSON.stringify(state)} input=${JSON.stringify(grouped)}`);

  // A decimal typed after the separator survives the mask's own rounding.
  await eval_(`type('89,7')`);
  const decimals = await eval_(`input().value`);
  check('a decimal typed after the separator is kept', decimals.startsWith('89,7'), `value=${JSON.stringify(decimals)}`);
  await shot('03-decimals');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
