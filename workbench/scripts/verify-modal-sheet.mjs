import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for the action modal as a phone's bottom sheet
 * (/previews/table-modal-form at 390px).
 *
 * `wire-core.modals.mobile` makes every dialog a sheet below the breakpoint
 * unless it says otherwise. What only a browser can say about that:
 *
 *   - the page behind is locked while the sheet is up, and unlocked when the
 *     server's re-render removes it (not only when `show` turns false);
 *   - the sheet is pulled down by its grabber to close — a real touch drag;
 *   - a short pull springs back and leaves it open;
 *   - Escape that a control inside the dialog consumed does not also close it.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-modal-sheet.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-modal-form`;

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'modal-sheet', width: 390, height: 844, mobile: true });

const { check, finish } = checker();

const dialogOpen = `(() => { const d = document.querySelector('[role=dialog][aria-modal]'); return !! d && getComputedStyle(d).display !== 'none'; })()`;
const locked = `document.documentElement.dataset.wireScrollLocked !== undefined`;
const openModal = `[...document.querySelectorAll('button')].find((b) => /invite user/i.test(b.textContent)).click()`;

const drag = async (from, to) => {
  const grabber = await eval_(`(() => { const r = document.querySelector('[role=dialog] [x-sheet-dismiss]').getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; })()`);
  const point = (y) => [{ x: grabber.x, y: grabber.y + y, id: 1 }];

  await page('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: point(from) });
  for (let y = from; y <= to; y += 20) {
    await page('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: point(y) });
    await new Promise((r) => setTimeout(r, 30));
  }
  await page('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
};

try {
  await page('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 1 });

  // The preview opens the modal on its first render (once); clicking the
  // header action as well would stack a second copy on top of it.
  await waitFor(dialogOpen);
  check('the action modal opens as a sheet with a grabber', await eval_(`!! document.querySelector('[role=dialog] [x-sheet-dismiss]')`));
  check('the page behind is locked while it is open', await waitFor(locked).then(() => true, () => false));
  check('the dialog is labelled by its own heading', await eval_(`(() => { const d = document.querySelector('[role=dialog][aria-modal]'); const h = document.getElementById(d.getAttribute('aria-labelledby')); return !! h && /invite user/i.test(h.textContent); })()`));
  await shot('01-sheet-open');

  // A short pull springs back.
  await drag(0, 40);
  await new Promise((r) => setTimeout(r, 400));
  check('a short, slow pull leaves the sheet open', await eval_(dialogOpen));

  // A long pull closes it, and the lock goes with it.
  await drag(0, 260);
  await waitFor(`! ${dialogOpen}`).catch(() => {});
  check('a long pull on the grabber closes the sheet', ! (await eval_(dialogOpen)));
  check('the page is unlocked once it is gone', await waitFor(`! (${locked})`).then(() => true, () => false));
  await shot('02-sheet-dismissed');

  // Opened again by hand, now on a touch screen: the focus goes to the dialog,
  // not into a field — focusing a field raises the keyboard over the sheet.
  await eval_(openModal);
  await waitFor(dialogOpen);
  await waitFor(`document.querySelector('[role=dialog][aria-modal]').contains(document.activeElement)`).catch(() => {});
  check('a touch screen does not focus a field (no keyboard over an unread sheet)', await eval_(`document.activeElement?.getAttribute('role') === 'dialog'`), await eval_(`document.activeElement?.tagName`));

  // Escape consumed inside the dialog does not also close it.
  await eval_(`(() => {
    const field = document.querySelector('[role=dialog] input');
    field.addEventListener('keydown', (e) => { if (e.key === 'Escape') e.preventDefault(); }, { once: true });
    field.focus();
    field.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
  })()`);
  await new Promise((r) => setTimeout(r, 400));
  check('an Escape a control consumed leaves the dialog open', await eval_(dialogOpen));

  await eval_(`document.querySelector('[role=dialog] input').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))`);
  await waitFor(`! ${dialogOpen}`).catch(() => {});
  check('a plain Escape closes it', ! (await eval_(dialogOpen)));
  check('and releases the page', await waitFor(`! (${locked})`).then(() => true, () => false));
} catch (error) {
  check('driver ran to completion', false, error.message);
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
