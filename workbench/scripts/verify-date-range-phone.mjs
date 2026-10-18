import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for a DateRangePicker on a phone, with and without the wheel.
 *
 * The two ends are coupled: each end's bound is the other end's value, so a
 * pick on one end changes the other end's x-data. Alpine meets a changed
 * x-data by reconciling the old object onto the new one, which wrote the
 * fresh `$wire.entangle` placeholder into the bound property — and from there
 * to the server as the second end's value. The next render then threw an
 * InvalidDateBoundaryException ("Date bound of type [array]"). Pest never sees
 * it: the markup is right, and only a browser's morph makes the write.
 *
 * Both phone controls are driven — the touch wheel (`touchOnMobile()`) and the
 * default bottom-sheet calendar — and each end must stay a string or null.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-date-range-phone.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const scalar = (v) => v === null || typeof v === 'string';
let errors = [];
let responses = [];

// ── touchOnMobile(true): the wheel ────────────────────────────────────────
{
  const page = await openPage({ url: `${origin}/previews/field-pickers-touch`, shotPrefix: 'range-phone-wheel', width: 390, mobile: true });
  const { eval_, waitFor, close } = page;
  const get = (path) => eval_(`Livewire.all()[0].$wire.get('data.${path}')`);
  const tap = async (testid) => {
    await eval_(`document.querySelector('[data-testid="form-data.${testid}"]').click()`);
    await sleep(500);
  };

  try {
    for (const [from, to] of [['period_from', 'period_to'], ['shift_from', 'shift_to']]) {
      await tap(`${from}-wheel-trigger`);
      await tap(`${from}-wheel-done`);
      await waitFor(`!! Livewire.all()[0].$wire.get('data.${from}')`);
      await sleep(800);
      const after = [await get(from), await get(to)];
      check(`a wheel pick on ${from} leaves ${to} a value, not a placeholder`, after.every(scalar), JSON.stringify(after));

      await tap(`${to}-wheel-trigger`);
      await tap(`${to}-wheel-done`);
      await waitFor(`!! Livewire.all()[0].$wire.get('data.${to}')`);
      await sleep(800);
      const both = [await get(from), await get(to)];
      check(`both ends of ${from.replace('_from', '')} are values after both wheels`, both.every((v) => typeof v === 'string'), JSON.stringify(both));
    }
    check('the page still renders after the coupled picks', await eval_(`!! document.querySelector('[data-testid="form-data.period_to-wheel-trigger"]')`));
  } finally {
    errors = errors.concat(page.consoleErrors);
    responses = responses.concat(page.badResponses);
    await close();
  }
}

// ── touchOnMobile(false): the bottom-sheet calendar ───────────────────────
{
  const page = await openPage({ url: `${origin}/previews/field-date-range-picker-hours`, shotPrefix: 'range-phone-sheet', width: 390, mobile: true });
  const { eval_, waitFor, close } = page;
  const get = (path) => eval_(`Livewire.all()[0].$wire.get('data.${path}')`);
  const tap = async (testid) => {
    await eval_(`document.querySelector('[data-testid="${testid}"]').click()`);
    await sleep(500);
  };

  try {
    await tap('form-datetime-data.shift_from-trigger');
    await tap('form-datetime-data.shift_from-day-20');
    await waitFor(`!! Livewire.all()[0].$wire.get('data.shift_from')`);
    await sleep(800);
    const after = [await get('shift_from'), await get('shift_to')];
    check('a sheet pick on the start leaves the end a value, not a placeholder', after.every(scalar), JSON.stringify(after));

    await tap('form-datetime-data.shift_to-trigger');
    await tap('form-datetime-data.shift_to-day-22');
    await waitFor(`!! Livewire.all()[0].$wire.get('data.shift_to')`);
    await sleep(800);
    const both = [await get('shift_from'), await get('shift_to')];
    check('both ends are values after both sheet picks', both.every((v) => typeof v === 'string'), JSON.stringify(both));
    check('the phone keeps its own sheet, not the desktop range box',
      await eval_(`document.querySelector('[data-testid="form-date-range-data.shift_from-trigger"]').offsetParent === null`));
  } finally {
    errors = errors.concat(page.consoleErrors);
    responses = responses.concat(page.badResponses.filter((r) => ! r.includes('favicon')));
    await close();
  }
}

finish({ consoleErrors: errors, badResponses: responses });
