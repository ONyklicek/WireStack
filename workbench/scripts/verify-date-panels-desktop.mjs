import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for the desktop picker panels — what the phone never sees.
 *
 * From the mobile breakpoint up a DateTimePicker draws a Fiori-style panel
 * (month and year grids behind the header, the clock as columns, a keyboard
 * grid) and a DateRangePicker draws one box over a two-month panel. All of it
 * is Alpine; Pest sees the markup, not whether a click lands.
 *
 *   - the year → month → day → hour → minute path writes one value;
 *   - the arrows walk the grid and Enter picks the focused day;
 *   - the daily window (minTime/maxTime) greys out hours on every day;
 *   - a range is two clicks, previewed between them, ordered however clicked,
 *     and the presets and the typed box write both ends;
 *   - the phone's pair of pickers stays in the DOM, hidden, for the phone.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-date-panels-desktop.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const helpers = (prefix) => `
  window.tid = (s) => document.querySelector('[data-testid="${prefix}-' + s + '"]');
  window.click = (s) => { const el = tid(s); if (! el) throw new Error('missing ' + s); el.click(); return true; };
  window.wire = () => Livewire.all()[0].$wire;
  // The clock dial: press and lift on the face where a label is drawn, and
  // read whether a label is greyed.
  window.press = (item) => {
    const face = tid('dial'), r = tid('dial-' + item).getBoundingClientRect();
    const at = { clientX: r.left + r.width / 2, clientY: r.top + r.height / 2, pointerId: 1, bubbles: true };
    face.dispatchEvent(new PointerEvent('pointerdown', at));
    face.dispatchEvent(new PointerEvent('pointerup', at));
    return true;
  };
  window.greyed = (item) => tid('dial-' + item)?.dataset.disabled === 'true';
  true;
`;

// ── DateTimePicker ────────────────────────────────────────────────────────
{
  const { eval_, waitFor, shot, consoleErrors, close } =
    await openPage({ url: `${origin}/previews/field-date-time-picker`, shotPrefix: 'date-panels-dt' });

  try {
    await eval_(helpers('form-datetime-data.event_at'));
    await eval_(`click('toggle')`);
    await waitFor(`!! tid('month-toggle')`);
    check('the desktop header opens month and year grids', await eval_(`!! tid('year-toggle') && ! tid('hours-up')`));
    check('the toggle puts keyboard focus on the picked day',
      await eval_(`document.activeElement?.dataset?.date`) === '2026-06-15');

    await eval_(`click('year-toggle')`);
    await waitFor(`!! tid('year-2030')`);
    await eval_(`click('year-2030')`);
    await waitFor(`!! tid('month-3')`);
    await eval_(`click('month-3')`);
    await waitFor(`!! tid('day-12')`);
    await eval_(`click('day-12')`);
    await waitFor(`!! tid('dial-hours-9')`);
    await eval_(`press('hours-9')`);
    await waitFor(`!! tid('dial-minutes-45')`);
    check('a 1-minute step ticks every minute between the labels',
      await eval_(`tid('dial').querySelectorAll('.h-1.w-1').length === 48`));
    await eval_(`press('minutes-45')`);
    await waitFor(`wire().get('data.event_at') === '2030-03-12 09:45'`);
    check('year, month, day, hour and minute write one value',
      await eval_(`wire().get('data.event_at')`) === '2030-03-12 09:45');
    await shot('01-datetime');

    // The keyboard: ArrowRight a day, ArrowDown a week, PageDown a month.
    // The preview closes on a picked day (closeOnDateSelection), so one
    // toggle opens it again — with focus on the day just picked.
    if (await eval_(`Alpine.$data(tid('trigger').closest('[x-data]')).open`)) await eval_(`click('toggle')`);
    await eval_(`click('toggle')`);
    await waitFor(`document.activeElement?.dataset?.date === '2030-03-12'`);
    const key = (k) => `document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: '${k}', bubbles: true }))`;
    await eval_(key('ArrowRight'));
    await waitFor(`document.activeElement?.dataset?.date === '2030-03-13'`);
    await eval_(key('ArrowDown'));
    await waitFor(`document.activeElement?.dataset?.date === '2030-03-20'`);
    await eval_(key('PageDown'));
    await waitFor(`document.activeElement?.dataset?.date === '2030-04-20'`);
    check('the arrows and PageDown walk the grid across months',
      await eval_(`document.activeElement?.dataset?.date`) === '2030-04-20');
    await eval_(`document.activeElement.click()`);
    await waitFor(`wire().get('data.event_at') === '2030-04-20 09:45'`);
    check('picking the focused day keeps the clock', await eval_(`wire().get('data.event_at')`) === '2030-04-20 09:45');

    check('no console errors', consoleErrors.length === 0, consoleErrors[0]);
  } finally {
    await close();
  }
}

// ── The daily window ──────────────────────────────────────────────────────
{
  const { eval_, waitFor, consoleErrors, close } =
    await openPage({ url: `${origin}/previews/field-date-time-picker-hours`, shotPrefix: 'date-panels-hours' });

  try {
    await eval_(helpers('form-datetime-data.slot_at'));
    await eval_(`click('toggle')`);
    await waitFor(`!! tid('day-15')`);
    await eval_(`click('day-15')`);
    await waitFor(`!! wire().get('data.slot_at')`);
    check('a fresh clock lands on the window, not on midnight',
      (await eval_(`wire().get('data.slot_at')`)).endsWith(' 06:00'));
    check('hours outside 06:00–18:00 are greyed on an ordinary day',
      await eval_(`greyed('hours-5') && greyed('hours-19') && ! greyed('hours-6') && ! greyed('hours-18')`));
    await eval_(`press('hours-18')`);
    await waitFor(`greyed('minutes-15')`);
    check('at 18 the minutes past the window are greyed', await eval_(`greyed('minutes-15') && ! greyed('minutes-0')`));
    check('days outside the bounds stay closed', await eval_(`tid('prev-month').disabled`));
    check('no console errors', consoleErrors.length === 0, consoleErrors[0]);
  } finally {
    await close();
  }
}

// ── DateRangePicker ───────────────────────────────────────────────────────
{
  const { eval_, waitFor, shot, consoleErrors, close } =
    await openPage({ url: `${origin}/previews/field-date-range-picker`, shotPrefix: 'date-panels-range' });
  const period = `wire().get('data.valid_from') + '..' + wire().get('data.valid_to')`;

  try {
    await eval_(helpers('form-date-range-data.valid_from'));
    check('one box reads the whole period', await eval_(`tid('trigger').value`) === '01.06.2026 – 30.06.2026');
    check('the phone\'s two pickers are there, hidden',
      await eval_(`(() => { const el = document.querySelector('[data-testid="form-datetime-data.valid_from-trigger"]'); return !! el && el.offsetParent === null })()`));

    await eval_(`click('toggle')`);
    await waitFor(`!! tid('day-2026-07-08')`);
    check('two months are on screen', await eval_(`!! tid('day-2026-06-10') && !! tid('day-2026-07-31')`));

    await eval_(`click('day-2026-06-10'); tid('day-2026-07-08').dispatchEvent(new MouseEvent('mouseenter')); true`);
    await waitFor(`tid('day-2026-06-20').className.includes('text-primary-900')`);
    check('the span is previewed under the pointer before the second click', true);
    await shot('01-range-preview');

    await eval_(`click('day-2026-07-08')`);
    await waitFor(`${period} === '2026-06-10..2026-07-08'`);
    check('the second click writes both ends', await eval_(period) === '2026-06-10..2026-07-08');

    await eval_(`click('toggle')`);
    await waitFor(`!! tid('day-2026-07-20')`);
    await eval_(`click('day-2026-07-20'); click('day-2026-07-02')`);
    await waitFor(`${period} === '2026-07-02..2026-07-20'`);
    check('a period clicked backwards is stored forwards', await eval_(period) === '2026-07-02..2026-07-20');

    await eval_(`click('toggle')`);
    await waitFor(`!! tid('option-2')`);
    const expected = await eval_(`(() => {
      const now = new Date(), pad = (n) => String(n).padStart(2, '0');
      const fmt = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
      return fmt(new Date(now.getFullYear(), now.getMonth(), 1)) + '..' + fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0));
    })()`);
    await eval_(`click('option-2')`);
    await waitFor(`${period} === '${expected}'`);
    check('a preset in the panel writes both ends', await eval_(period) === expected, await eval_(period));

    await eval_(`(() => { const i = tid('trigger'); i.focus(); i.value = '1. 9. 2026 – 15. 9. 2026'; i.dispatchEvent(new Event('input')); i.blur(); return true })()`);
    await waitFor(`${period} === '2026-09-01..2026-09-15'`);
    check('a typed period writes both ends', await eval_(period) === '2026-09-01..2026-09-15');

    check('no console errors', consoleErrors.length === 0, consoleErrors[0]);
  } finally {
    await close();
  }
}

// ── A range with time, inside working hours ───────────────────────────────
{
  const { eval_, waitFor, consoleErrors, close } =
    await openPage({ url: `${origin}/previews/field-date-range-picker-hours`, shotPrefix: 'date-panels-shift' });
  const period = `wire().get('data.shift_from') + '..' + wire().get('data.shift_to')`;

  try {
    await eval_(helpers('form-date-range-data.shift_from'));
    await eval_(`click('toggle')`);
    await waitFor(`!! tid('day-2026-10-20')`);
    check('the period cannot start before its first day', await eval_(`tid('prev-month').disabled`));
    await eval_(`click('day-2026-10-20'); click('day-2026-10-22')`);
    await waitFor(`${period} === '2026-10-20 06:00..2026-10-22 18:00'`);
    check('fresh ends are pulled inside the window', await eval_(period) === '2026-10-20 06:00..2026-10-22 18:00', await eval_(period));
    // One dial, two ends: the From / To switch says which one it sets.
    check('the dial greys the hours outside the window for the start',
      await eval_(`greyed('hours-5') && ! greyed('hours-8')`));
    const step = (ms = 300) => new Promise((r) => setTimeout(r, ms));
    await eval_(`press('hours-8')`); await step();
    await eval_(`press('minutes-30')`); await step();
    await eval_(`click('dial-end-to')`); await step();
    check('the switch turns the dial to the end, back on the hours',
      await eval_(`!! tid('dial-hours-16') && greyed('hours-19')`));
    await eval_(`press('hours-16')`); await step();
    await eval_(`press('minutes-45')`); await step(800);
    check('each end keeps its own clock', await eval_(period) === '2026-10-20 08:30..2026-10-22 16:45', await eval_(period));
    check('the switch reads both times', await eval_(`tid('dial-end-from').textContent.includes('08:30') && tid('dial-end-to').textContent.includes('16:45')`));
    check('a 15-minute step draws no ticks between the labels',
      await eval_(`greyed('minutes-20') && ! tid('dial').querySelector('.h-1.w-1')`));
    check('no console errors', consoleErrors.length === 0, consoleErrors[0]);
  } finally {
    await close();
  }
}

finish();
