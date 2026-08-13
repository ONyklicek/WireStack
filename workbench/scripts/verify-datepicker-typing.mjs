import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Typing into a picker's trigger.
 *
 * The trigger used to be unconditionally `readonly`: the calendar and the
 * hour/minute steppers were the only route to a value, so a date three years out
 * cost 36 clicks on the month arrow and a time off the slot interval could not be
 * expressed at all. The box now takes text, read back through the same format it
 * is written in.
 *
 * Pest sees the markup — that the input has no `readonly` and carries an @input
 * handler. What it cannot see is whether a typed string actually reaches the
 * state: the parse happens in Alpine, against a format resolved in PHP, and the
 * bounds that refuse a picked day have to refuse a typed one the same way.
 *
 * Three previews, one for each thing only a browser can answer, driven from one
 * Chrome by navigating between them — three separate boots ran past the sweep's
 * per-driver timeout:
 *   field-date-time-picker         — displayFormat 'j. n. Y H:i' round-trips
 *   field-date-time-picker-bounds  — 2030-03-10 08:30 … 2030-03-20 17:00
 *   field-time-picker              — 08:00 … 17:00 at 15 minutes
 *
 * See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

let session;
try {
  session = await openPage({ url: `${base}/field-date-time-picker`, shotPrefix: 'datepicker-typing' });
  const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = session;

  const goto = async (slug) => {
    await page('Page.navigate', { url: `${base}/${slug}` });
    await sleep(2600);
  };

  // Type the way a person does — set the value, then fire the input event Alpine
  // listens for — and commit with a real blur.
  const type = async (testid, text) => {
    await eval_(`(() => {
      const el = document.querySelector('[data-testid="${testid}"]');
      el.focus();
      el.value = ${JSON.stringify(text)};
      el.dispatchEvent(new Event('input', { bubbles: true }));
    })()`);
    await sleep(120);
    await eval_(`document.querySelector('[data-testid="${testid}"]').blur()`);
    await sleep(350);
  };
  const read = async (testid) => JSON.parse(await eval_(`(() => {
    const el = document.querySelector('[data-testid="${testid}"]');
    const d = Alpine.$data(el.closest('[x-data]'));
    return JSON.stringify({ value: d.value, shown: el.value, open: d.open });
  })()`));

  // ── The display format round-trips ─────────────────────────────────
  const dt = 'form-datetime-data.event_at-trigger';
  const initial = await read(dt);
  check('the trigger shows the value through displayFormat', initial.shown === '15. 6. 2026 14:30', initial.shown);
  check('the trigger is not readonly any more',
    (await eval_(`document.querySelector('[data-testid="${dt}"]').readOnly`)) === false);

  await type(dt, '9. 3. 2027 08:45');
  let state = await read(dt);
  check('a date typed in the format the box shows reaches the state',
    state.value === '2027-03-09 08:45', `${state.value} / shown ${state.shown}`);
  check('and comes back out formatted', state.shown === '9. 3. 2027 08:45', state.shown);
  await shot('01-typed-datetime');

  // Separators and padding are the user's business, not the parser's.
  await type(dt, '1/4/2027 6:05');
  state = await read(dt);
  check('loose separators and unpadded numbers read the same',
    state.value === '2027-04-01 06:05', state.value);

  // A refused entry must leave the last good value alone, not half-write it.
  await type(dt, 'nonsense');
  state = await read(dt);
  check('text that will not parse puts the last good value back',
    state.value === '2027-04-01 06:05' && state.shown === '1. 4. 2027 06:05',
    `${state.value} / shown ${state.shown}`);

  await type(dt, '31. 2. 2027 10:00');
  state = await read(dt);
  check('31 February is refused rather than rolled forward into March',
    state.value === '2027-04-01 06:05', state.value);

  await type(dt, '');
  state = await read(dt);
  check('emptying the box clears the field', state.value === null && state.shown === '',
    `${state.value} / shown '${state.shown}'`);

  // ── The panel, from the keyboard and from the chevron ──────────────
  await eval_(`document.querySelector('[data-testid="${dt}"]').focus()`);
  await page('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 });
  await page('Input.dispatchKeyEvent', { type: 'keyUp', key: 'ArrowDown', code: 'ArrowDown', windowsVirtualKeyCode: 40 });
  await sleep(350);
  check('ArrowDown opens the calendar — there was no keyboard route at all before',
    (await read(dt)).open === true);
  await shot('02-opened-from-keyboard');

  await eval_(`document.querySelector('[data-testid="form-datetime-data.event_at-toggle"]').click()`);
  await sleep(350);
  check('the chevron closes it again, rather than only ever opening',
    (await read(dt)).open === false);

  await eval_(`document.querySelector('[data-testid="form-datetime-data.event_at-toggle"]').click()`);
  await sleep(350);
  check('and opens it again', (await read(dt)).open === true);

  // Clicking a day while the box is untouched must not be read as an empty edit.
  await eval_(`document.querySelector('[data-testid="${dt}"]').focus()`);
  await eval_(`document.querySelector('[data-testid="form-datetime-data.event_at-day-12"]')?.click()`);
  await sleep(350);
  state = await read(dt);
  check('picking a day after focusing the box does not blank the value',
    typeof state.value === 'string' && state.value.includes('-12 '), String(state.value));

  // ── The bounds refuse a typed day exactly as they refuse a picked one ──
  await goto('field-date-time-picker-bounds');
  const bounds = 'form-datetime-data.slot_at-trigger';

  // No displayFormat here, so the box speaks the state's own shape.
  await type(bounds, '2030-03-09 12:00');
  check('a day before minDate cannot be typed in either',
    (await read(bounds)).value === null, String((await read(bounds)).value));

  await type(bounds, '2030-03-21 12:00');
  check('nor a day after maxDate', (await read(bounds)).value === null, String((await read(bounds)).value));

  await type(bounds, '2030-03-10 07:00');
  state = await read(bounds);
  check('a clock before the bound on the boundary day is pulled up, as the steppers do',
    state.value === '2030-03-10 08:30', String(state.value));

  await type(bounds, '2030-03-15 23:00');
  state = await read(bounds);
  check('a day between the bounds leaves the typed clock alone',
    state.value === '2030-03-15 23:00', String(state.value));
  await shot('03-bounds-typed');

  // ── TimePicker: typing is the only way to leave the interval ────────
  await goto('field-time-picker');
  const tp = 'form-time-data.opens_at-trigger';

  await type(tp, '08:07');
  check('a time between two 15-minute slots can be typed — the list could never offer it',
    (await read(tp)).value === '08:07', String((await read(tp)).value));

  await type(tp, '7:30');
  check('the bounds still refuse a typed time', (await read(tp)).value === '08:07', String((await read(tp)).value));

  await type(tp, '16:45');
  check('a time inside the bounds commits', (await read(tp)).value === '16:45', String((await read(tp)).value));
  await shot('04-time-typed');

  finish({ consoleErrors, badResponses, shotDir });
  await close();
  session = null;
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await session?.close();
}
