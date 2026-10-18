import { openPage, checker } from './lib/cdp.mjs';

/*
 * TimePicker's desktop panel: the clock dial (Fiori's TimePicker). The preview
 * is bound to 08:00 … 17:00 at 15 minutes.
 *
 * What needs a browser: that the face takes the pointer where the labels are
 * drawn, that the bounds grey whole hours and off-step minutes, that lifting
 * off an hour turns the face to minutes, that nothing reaches the value before
 * OK (and Cancel leaves it), and that the keyboard drives the same face. The
 * phone's slot list is verify-time-picker-sheet's.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-time-picker.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const T = 'form-time-data.opens_at';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const { check, finish } = checker();

const page = await openPage({ url: `${origin}/previews/field-time-picker`, shotPrefix: 'time-picker-dial', width: 1280, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page;

const q = (id) => `document.querySelector('[data-testid="${T}-${id}"]')`;
const dial = () => eval_(`(() => { const d = Alpine.$data(${q('dial')}); return JSON.parse(JSON.stringify({ unit: d.dialUnit, draft: d.draft, open: d.open, value: d.value })) })()`);
// Press and lift on the face, where a label is drawn — the face, not the label,
// takes the pointer.
const press = (item) => eval_(`(() => {
  const face = ${q('dial')};
  const r = ${q('dial-' + item)}.getBoundingClientRect();
  const at = { clientX: r.left + r.width / 2, clientY: r.top + r.height / 2, pointerId: 1, bubbles: true };
  face.dispatchEvent(new PointerEvent('pointerdown', at));
  face.dispatchEvent(new PointerEvent('pointerup', at));
  return true;
})()`);
const key = (k) => eval_(`${q('dial')}.dispatchEvent(new KeyboardEvent('keydown', { key: '${k}', bubbles: true }))`);

try {
  await eval_(`${q('toggle')}.click()`);
  await waitFor(`!! ${q('dial')}`);
  await shot('01-dial-hours');

  check('desktop opens a clock dial, not the slot list',
    await eval_(`!! ${q('dial')} && ! document.querySelector('[data-testid^="${T}-slot-"]')`));
  check('the toggle hands focus to the face', await eval_(`document.activeElement === ${q('dial')}`));

  const opened = await dial();
  check('an empty field opens on a time inside the bounds',
    opened.draft.hours >= 8 && opened.draft.hours <= 17, JSON.stringify(opened.draft));
  check('hours outside 08–17 are greyed, the ones inside are not',
    await eval_(`['7', '18', '0'].every((h) => document.querySelector('[data-testid="${T}-dial-hours-' + h + '"]').dataset.disabled === 'true')`)
      && await eval_(`['8', '12', '17'].every((h) => document.querySelector('[data-testid="${T}-dial-hours-' + h + '"]').dataset.disabled === 'false')`));

  await press('hours-7');
  check('a greyed hour does not move the hand', (await dial()).draft.hours !== 7);

  await press('hours-9');
  await sleep(150);
  const afterHour = await dial();
  check('lifting off an hour turns the face to minutes', afterHour.unit === 'minutes' && afterHour.draft.hours === 9, JSON.stringify(afterHour));
  check('minutes off the 15-minute step are greyed',
    await eval_(`${q('dial-minutes-20')}.dataset.disabled === 'true' && ${q('dial-minutes-15')}.dataset.disabled === 'false'`));

  await press('minutes-15');
  await sleep(150);
  check('nothing reaches the value before OK', ! (await dial()).value);
  await shot('02-dial-minutes');

  await eval_(`${q('ok')}.click()`);
  await waitFor(`Livewire.all()[0].$wire.get('data.opens_at') === '09:15'`);
  check('OK commits the draft', await eval_(`Livewire.all()[0].$wire.get('data.opens_at')`) === '09:15');
  check('the box shows it', await eval_(`${q('trigger')}.value`) === '09:15');

  // The keyboard drives the same face: an hour up, Enter on to minutes, Enter to OK.
  await eval_(`${q('toggle')}.click()`);
  await waitFor(`!! ${q('dial')}`);
  const reopened = await dial();
  check('reopening starts from the value, on the hours', reopened.unit === 'hours' && reopened.draft.hours === 9 && reopened.draft.minutes === 15, JSON.stringify(reopened));
  await key('ArrowUp');
  await key('Enter');
  await key('ArrowUp');
  await key('Enter');
  await waitFor(`Livewire.all()[0].$wire.get('data.opens_at') === '10:30'`);
  check('arrows step the part and Enter moves on, then confirms', await eval_(`Livewire.all()[0].$wire.get('data.opens_at')`) === '10:30');

  // Cancel leaves the value as it was.
  await eval_(`${q('toggle')}.click()`);
  await waitFor(`!! ${q('dial')}`);
  await press('hours-14');
  await eval_(`${q('cancel')}.click()`);
  await sleep(600);
  check('Cancel drops the draft', await eval_(`Livewire.all()[0].$wire.get('data.opens_at')`) === '10:30');

  await eval_(`${q('toggle')}.click()`);
  await waitFor(`!! ${q('clear')}`);
  await eval_(`${q('clear')}.click()`);
  await waitFor(`! Livewire.all()[0].$wire.get('data.opens_at')`);
  check('Clear empties the field', ! (await eval_(`Livewire.all()[0].$wire.get('data.opens_at')`)));
} finally {
  await close();
}

finish({ consoleErrors, badResponses: badResponses.filter((r) => ! r.includes('favicon')), shotDir });
