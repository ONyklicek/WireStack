import { openPage, checker } from './lib/cdp.mjs';

/*
 * Column widths the user drags and columns pinned to the start
 * (/previews/table-column-layout).
 *
 * Only a browser can say this: a header's edge drags the column wider with the
 * pointer, the width is kept across a reload, a double click puts it back, a
 * pinned column stays at the start while the table scrolls sideways, and a
 * second pinned column sits right after the first rather than on top of it.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-column-layout`;
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({ url, shotPrefix: 'column-layout', width: 900, height: 700 });
const { check, finish } = checker();

const ready = `!! document.querySelector('[data-testid=table-column-resize-email]') && typeof window.__wireTableColumns !== 'undefined'`;
const widthOf = (name) => `Math.round(document.querySelector('thead th[data-column=${name}]').getBoundingClientRect().width)`;
const leftOf = (name) => `Math.round(document.querySelector('thead th[data-column=${name}]').getBoundingClientRect().left)`;
const scroller = `document.querySelector('.wire-scroller')`;
const reload = async () => {
  await eval_('window.__beforeReload = true');
  await page('Page.reload');
  await waitFor(`! window.__beforeReload && ${ready}`);
};
const drag = (name, dx) => eval_(`(async () => {
  const grip = document.querySelector('[data-testid=table-column-resize-${name}]');
  const r = grip.getBoundingClientRect();
  const x = r.left + r.width / 2, y = r.top + r.height / 2;
  const opts = (cx) => ({ bubbles: true, cancelable: true, clientX: cx, clientY: y, pointerId: 1, button: 0, pointerType: 'mouse' });
  grip.dispatchEvent(new PointerEvent('pointerdown', opts(x)));
  for (let i = 1; i <= 5; i++) grip.dispatchEvent(new PointerEvent('pointermove', opts(x + (${dx} * i) / 5)));
  grip.dispatchEvent(new PointerEvent('pointerup', opts(x + ${dx})));
})()`);

try {
  await waitFor(ready);
  await eval_(`Livewire.first().call('resizeTableColumn', 'email', null)`);
  await eval_(`(() => { const s = Livewire.first().get('tableState.columns.pinned') ?? []; if (s.includes('email')) Livewire.first().call('toggleColumnPin', 'email'); })()`);
  await reload();

  const before = await eval_(widthOf('email'));
  await drag('email', 120);
  await waitFor(`${widthOf('email')} >= ${before + 100}`).catch(() => {});
  const after = await eval_(widthOf('email'));
  check('dragging a header edge widens the column', after >= before + 100, `${before} → ${after}`);
  await waitFor(`(Livewire.first().get('tableState.columns.widths') ?? {}).email > 0`).catch(() => {});
  await shot('01-resized');

  await reload();
  check('the width is kept across a reload', Math.abs((await eval_(widthOf('email'))) - after) <= 2, `${await eval_(widthOf('email'))} vs ${after}`);

  await eval_(`document.querySelector('[data-testid=table-column-resize-email]').dispatchEvent(new MouseEvent('dblclick', { bubbles: true }))`);
  await waitFor(`Math.abs(${widthOf('email')} - ${before}) <= 2`).catch(() => {});
  check('a double click puts the column back to its own width', Math.abs((await eval_(widthOf('email'))) - before) <= 2, `${await eval_(widthOf('email'))} vs ${before}`);

  // Pinned: Name stays at the start while the table scrolls sideways.
  const nameLeft = await eval_(leftOf('name'));
  await eval_(`${scroller}.scrollLeft = 400`);
  await new Promise((r) => setTimeout(r, 150));
  check('the checkboxes stay with it', Math.abs((await eval_(`Math.round(document.querySelector('tbody td[data-select-cell]').getBoundingClientRect().left)`)) - (await eval_(`Math.round(${scroller}.getBoundingClientRect().left)`))) <= 1);
  check('a pinned column stays put while the rest scrolls', Math.abs((await eval_(leftOf('name'))) - nameLeft) <= 1 && (await eval_(`${scroller}.scrollLeft`)) > 0, `${nameLeft} → ${await eval_(leftOf('name'))}`);
  await eval_(`${scroller}.scrollLeft = 0`);

  // Pin a second one from the view menu: it sits after the first.
  await eval_(`Livewire.first().call('toggleColumnPin', 'email')`);
  await waitFor(`!! document.querySelector('thead th[data-pinned=email]')`);
  await waitFor(`parseFloat(document.querySelector('thead th[data-pinned=email]').style.left) > document.querySelector('thead th[data-pinned=name]').getBoundingClientRect().width`).catch(() => {});
  const offset = await eval_(`({ select: document.querySelector('thead th:has([data-testid=table-select-all])').getBoundingClientRect().width, left: parseFloat(document.querySelector('thead th[data-pinned=email]').style.left), name: document.querySelector('thead th[data-pinned=name]').getBoundingClientRect().width, cell: parseFloat(document.querySelector('tbody td[data-pinned=email]').style.left) })`);
  check('a second pinned column sits right after the first, head and body alike', Math.abs(offset.left - offset.name - offset.select) <= 1 && Math.abs(offset.cell - offset.left) <= 1, JSON.stringify(offset));
  await eval_(`${scroller}.scrollLeft = 400`);
  await new Promise((r) => setTimeout(r, 150));
  await shot('02-pinned-scrolled');
  await eval_(`${scroller}.scrollLeft = 0`);

  await eval_(`Livewire.first().call('toggleColumnPin', 'email')`);
  await waitFor(`! document.querySelector('thead th[data-pinned=email]')`);
  check('and unpinning puts it back in its place', ! (await eval_(`!! document.querySelector('thead th[data-pinned=email]')`)));
} catch (error) {
  check('driver ran to completion', false, error.message);
} finally {
  finish({ consoleErrors, badResponses: badResponses.filter((r) => ! r.includes('favicon')), shotDir });
  await close();
}
