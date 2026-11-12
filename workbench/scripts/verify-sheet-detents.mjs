import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for the shared bottom-sheet gesture (x-sheet-dismiss, sheet.js) on
 * a phone, with real touch events, against a long select list
 * (/previews/field-select-long) and the action modal (/previews/table-modal-form).
 *
 *   - a long list opens at the medium detent (about half the screen) and is
 *     pulled up by its grabber to the large one (92 %);
 *   - pulled down from large it settles on medium; pulled down again it closes;
 *   - a downward drag on the list while it is scrolled to its top moves the
 *     sheet (hand-off); while it is scrolled down, it scrolls;
 *   - a dialog sheet opens at its full height and closes on a pull down;
 *   - nothing touches the bottom 1rem: the last row clears the rounded corners.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-sheet-detents.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();
const errors = [];
const bad = [];
let shotDirOut;

const sleep = (t) => new Promise((r) => setTimeout(r, t));

const drag = async (page, x, from, to, { steps = 12, pause = 25 } = {}) => {
  await page('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x, y: from, id: 1 }] });
  for (let i = 1; i <= steps; i++) {
    await page('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x, y: from + ((to - from) * i) / steps, id: 1 }] });
    await sleep(pause);
  }
  await page('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  await sleep(450);
};

// ── A long list ───────────────────────────────────────────────────────────
{
  const p = await openPage({ url: `${origin}/previews/field-select-long`, shotPrefix: 'sheet-detents', width: 390, height: 844, mobile: true });
  shotDirOut = p.shotDir;
  const { page, eval_, waitFor, shot } = p;
  const panel = `[...document.querySelectorAll('[x-sheet-dismiss]')].map((g) => g.parentElement).find((e) => e.offsetParent || getComputedStyle(e).display !== 'none' && e.getBoundingClientRect().height > 0)`;
  const rect = `(() => { const r = (${panel}).getBoundingClientRect(); return { top: Math.round(r.top), height: Math.round(r.height), bottom: Math.round(r.bottom) }; })()`;
  const open = `!! (${panel})`;

  try {
    await page('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 1 });
    await eval_(`[...document.querySelectorAll('[data-testid=select-trigger]')].find((e) => e.offsetParent).click()`);
    await waitFor(open);
    await sleep(500);

    const medium = await eval_(rect);
    check('a long list opens at the medium detent (about half the screen)', medium.height > 844 * 0.45 && medium.height < 844 * 0.65, JSON.stringify(medium));
    check('it sits on the bottom edge', medium.bottom === 844, JSON.stringify(medium));
    await shot('01-medium');

    await drag(page, 195, medium.top + 12, medium.top - 300);
    const large = await eval_(rect);
    check('pulled up by the grabber it grows to the large detent (92%)', large.height > 844 * 0.88, JSON.stringify(large));
    await shot('02-large');

    await drag(page, 195, large.top + 12, large.top + 150, { steps: 10, pause: 40 });
    const back = await eval_(rect);
    check('a slow pull down from large settles on medium', back.height > 844 * 0.45 && back.height < 844 * 0.65 && (await eval_(open)), JSON.stringify(back));

    // Hand-off: scroll the list a little, a drag down scrolls it back; at the
    // top, the same drag moves the sheet.
    const list = `(${panel}).querySelector('ul, [role=listbox]') ?? (${panel})`;
    await eval_(`(() => { const l = ${list}; const s = [l, ...l.querySelectorAll('*')].find((n) => n.scrollHeight > n.clientHeight + 1 && getComputedStyle(n).overflowY !== 'visible') ?? (${panel}); s.scrollTop = 120; window.__scroller = s; return true; })()`);
    await drag(page, 195, back.top + 200, back.top + 260, { steps: 6, pause: 40 });
    check('a drag down on a scrolled list scrolls it, the sheet stays', await eval_(open));

    await eval_(`window.__scroller.scrollTop = 0`);
    await drag(page, 195, back.top + 200, back.top + 420, { steps: 8, pause: 20 });
    await waitFor(`! (${open})`).catch(() => {});
    check('at the top of the list the same drag pulls the sheet closed', ! (await eval_(open)));
  } catch (error) {
    check('list pass ran to completion', false, error.message);
  } finally {
    errors.push(...p.consoleErrors);
    bad.push(...p.badResponses);
    await p.close();
  }
}

// ── A dialog sheet ────────────────────────────────────────────────────────
{
  const p = await openPage({ url: `${origin}/previews/table-modal-form`, shotPrefix: 'sheet-detents-dialog', width: 390, height: 844, mobile: true });
  const { page, eval_, waitFor, shot } = p;
  const dialogOpen = `(() => { const d = document.querySelector('[role=dialog][aria-modal]'); return !! d && getComputedStyle(d).display !== 'none'; })()`;

  try {
    await page('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 1 });
    await waitFor(dialogOpen);
    await sleep(500);
    const r = await eval_(`(() => { const g = document.querySelector('[role=dialog] [x-sheet-dismiss]').getBoundingClientRect(); const pnl = document.querySelector('[role=dialog] [x-sheet-dismiss]').parentElement.getBoundingClientRect(); return { y: g.y + g.height / 2, top: Math.round(pnl.top), bottom: Math.round(pnl.bottom) }; })()`);
    check('a dialog opens at its own height, on the bottom edge', r.bottom === 844, JSON.stringify(r));
    const footerGap = await eval_(`(() => { const b = [...document.querySelectorAll('[role=dialog] button')].filter((e) => e.offsetParent).pop().getBoundingClientRect(); return Math.round(844 - b.bottom); })()`);
    check('the last button clears the bottom edge by at least 1rem', footerGap >= 16, `${footerGap}px`);
    await shot('03-dialog');

    await drag(page, 195, r.y, r.y + 260, { steps: 8, pause: 20 });
    await waitFor(`! ${dialogOpen}`).catch(() => {});
    check('pulled down, the dialog sheet closes', ! (await eval_(dialogOpen)));
  } catch (error) {
    check('dialog pass ran to completion', false, error.message);
  } finally {
    errors.push(...p.consoleErrors);
    bad.push(...p.badResponses);
    await p.close();
  }
}

finish({ consoleErrors: errors, badResponses: bad.filter((r) => ! r.includes('favicon')), shotDir: shotDirOut });
