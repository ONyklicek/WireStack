import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for PhoneInput (/previews/field-phone-input).
 *
 * The field's claim is that two controls are one value: a dialling-code select
 * and a national number compose the single E.164-shaped string state holds, and
 * a stored number is split back across them on the way in. None of that is
 * visible in the markup — PHP renders two empty controls and a config object;
 * the splitting and composing happen in the controller.
 *
 * Three things can only fail in a browser, and each is checked below:
 *   - the seeded number reaches the two controls at all (it is parsed in JS);
 *   - changing the country rewrites the prefix of the one value;
 *   - an emptied number empties the value, rather than leaving a bare `+420`
 *     that would then fail validation on a field nobody filled in.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-phone-input.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-phone-input`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'phone-input' });

const { check, finish } = checker();

try {
  await eval_(`
    window.number = () => document.querySelector('[data-testid="form-phone-data.phone-number"]');
    window.country = () => document.querySelector('[data-testid="form-phone-data.phone-country"]');
    window.wire = () => Livewire.all()[0].$wire;
    window.state = () => wire().get('data.phone');
    window.pick = (iso) => {
      const el = country();
      el.value = iso;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    };
    window.typeNumber = (text) => {
      const el = number();
      el.focus();
      el.value = text;
      el.dispatchEvent(new InputEvent('input', { bubbles: true }));
      el.blur();
    };
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && typeof Livewire !== 'undefined' && !! number() && !! country()`);
  check('the preview boots with both controls', booted);
  await shot('01-initial');

  // ── The stored number is split across the two controls ────────────────
  const seededCountry = await eval_(`country().value`);
  const seededNumber = await eval_(`number().value`);
  check('the seeded number selects its own country', seededCountry === 'CZ', `country=${seededCountry}`);
  check('the national part is shown grouped, without the prefix',
    seededNumber === '123 456 789', `number=${JSON.stringify(seededNumber)}`);

  // Only the offered countries are in the list, and each reads as flag + prefix.
  const options = await eval_(`[...country().options].map((o) => o.value).join(',')`);
  check('only the offered countries are listed', options === 'CZ,SK,DE', `options=${options}`);
  const label = await eval_(`country().options[0].textContent.trim()`);
  check('an option reads as a flag and a prefix', label === '🇨🇿 +420', `label=${JSON.stringify(label)}`);

  // ── Changing the country rewrites the one value ───────────────────────
  await eval_(`pick('SK')`);
  await waitFor(`state() && state().startsWith('+421')`);
  const afterCountry = await eval_(`state()`);
  check('picking another country rewrites the prefix of the value',
    afterCountry === '+421 123 456 789', `state=${JSON.stringify(afterCountry)}`);
  check('the national part survives the country change',
    (await eval_(`number().value`)) === '123 456 789');
  await shot('02-country-changed');

  // ── Typing composes, and grouping is applied to what is stored ────────
  await eval_(`typeNumber('987654321')`);
  await waitFor(`state() === '+421 987 654 321'`);
  const afterTyping = await eval_(`state()`);
  check('typing a number composes it with the picked prefix',
    afterTyping === '+421 987 654 321', `state=${JSON.stringify(afterTyping)}`);

  // The caret trap: the input must not be regrouped under the typist.
  const stillTyped = await eval_(`number().value`);
  check('the input is left as it was typed, not reformatted mid-edit',
    stillTyped === '987654321', `input=${JSON.stringify(stillTyped)}`);
  await shot('03-typed');

  // ── An empty number is empty, prefix and all ──────────────────────────
  await eval_(`typeNumber('')`);
  await waitFor(`state() === ''`);
  const emptied = await eval_(`JSON.stringify(state())`);
  check('clearing the number clears the value rather than leaving a bare prefix',
    emptied === '""', `state=${emptied}`);

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
