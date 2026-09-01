import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for the delegated copy affordance
 * (/previews/table-column-surfaces — a copyable ColorColumn).
 *
 * The copy button used to be an Alpine component per cell; it is now a plain
 * `<button data-copy>` and one document listener in the core `copy` bundle. Pest
 * sees the markup either way — everything that makes the new shape correct is
 * browser behaviour:
 *
 *   - the value actually reaches the clipboard (the write is what resolves, and
 *     the feedback is only shown when it does — so a visible pill IS the proof),
 *   - there is ONE feedback pill for the page, not one per cell, which is the
 *     whole reason the markup shrank,
 *   - a delegated listener keeps working on rows Livewire morphed in AFTER the
 *     script ran; a per-cell component was re-created each time and this is the
 *     regression a re-binding mistake would produce,
 *   - copying does not fall through into the row's own click behaviour.
 *
 * The click is dispatched through Input.* rather than `el.click()` on purpose:
 * `navigator.clipboard.writeText` needs transient user activation, which a
 * scripted click does not carry.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-copy-cell.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-column-surfaces`;

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'copy-cell', width: 1400, height: 1000, settle: 4000,
});

const { check, finish } = checker();

try {
  // Clipboard read-back needs the permission; the write itself is granted by the
  // user activation the dispatched click carries.
  await page('Browser.grantPermissions', {
    origin: new URL(url).origin,
    permissions: ['clipboardReadWrite', 'clipboardSanitizedWrite'],
  }).catch(() => { /* older builds: the write still works, only read-back is lost */ });

  const clickCopy = async (index = 0) => {
    const box = await eval_(`(() => {
      const b = document.querySelectorAll('[data-copy]')[${index}];
      if (! b) return null;
      b.scrollIntoView({ block: 'center' });
      // The button is opacity-0 until its cell is hovered; opacity does not move
      // it, so its box is real and hit-testable either way.
      const r = b.getBoundingClientRect();
      return JSON.stringify({ x: Math.round(r.x + r.width / 2), y: Math.round(r.y + r.height / 2) });
    })()`);

    if (! box) return false;

    const { x, y } = JSON.parse(box);
    await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', clickCount: 1 });
    await sleep(400);

    return true;
  };

  const pillState = () => eval_(`(() => {
    const p = document.querySelector('[data-copy-feedback]');
    if (! p) return JSON.stringify({ present: false });
    return JSON.stringify({
      present: true,
      hidden: p.hidden,
      // The attribute alone does not hide it: the pill also carries inline-flex,
      // which ties with Tailwind's preflight rule for the attribute on
      // specificity and wins on order. Reading p.hidden only would report a
      // pill parked, painted, in the table's corner as hidden.
      display: getComputedStyle(p).display,
      text: p.querySelector('[data-copy-feedback-text]')?.textContent ?? '',
      left: p.style.left,
    });
  })()`);

  // ── 1. Markup shape ──────────────────────────────────────────────────
  const shape = JSON.parse(await eval_(`(() => {
    const buttons = [...document.querySelectorAll('[data-copy]')];
    return JSON.stringify({
      buttons: buttons.length,
      firstValue: buttons[0]?.getAttribute('data-copy') ?? null,
      anyAlpine: buttons.some((b) => b.closest('[x-data]') && b.closest('[x-data]').contains(b)
        && b.closest('[x-data]').querySelector('[data-copy]') === b
        && b.closest('[x-data]').getAttribute('x-data')?.includes('copied')),
      pills: document.querySelectorAll('[data-copy-feedback]').length,
      scripts: [...document.querySelectorAll('script[src]')]
        // The bundle lives in core now — two packages ask for the same affordance,
        // and core is the lowest layer that can own it.
        .filter((s) => s.src.includes('wire-core-copy') || s.src.includes('wire-core/assets/copy')).length,
    });
  })()`));

  check('copy buttons rendered', shape.buttons > 0, `${shape.buttons} found`);
  check('cell carries the value on data-copy', !! shape.firstValue, `value "${shape.firstValue}"`);
  check('no per-cell Alpine copy component', shape.anyAlpine === false);
  check('exactly one feedback pill for the page', shape.pills === 1, `${shape.pills} pill(s), ${shape.buttons} buttons`);
  check('copy bundle loaded once', shape.scripts === 1, `${shape.scripts} script tag(s)`);

  const before = JSON.parse(await pillState());
  check('pill starts hidden', before.present && before.hidden === true);
  check('pill starts unpainted, not just flagged hidden', before.display === 'none', `display ${before.display}`);

  // ── 2. A copy actually copies ────────────────────────────────────────
  await clickCopy(0);

  const after = JSON.parse(await pillState());
  check('pill shows after a copy', after.hidden === false, `text "${after.text}"`);
  check('pill is actually painted once shown', after.display !== 'none', `display ${after.display}`);
  check('pill carries the column message', after.text.length > 0, `"${after.text}"`);
  check('pill is positioned at the button', after.left !== '' && after.left !== undefined, `left ${after.left}`);
  await shot('01-copied');

  const clip = await eval_(`navigator.clipboard.readText().then(t => t).catch(() => null)`);
  check('the value reached the clipboard', clip === shape.firstValue, `clipboard "${clip}" vs data-copy "${shape.firstValue}"`);

  // ── 3. It hides again ────────────────────────────────────────────────
  await sleep(2200);
  const settled = JSON.parse(await pillState());
  check('pill hides itself again', settled.hidden === true);

  // ── 4. The row did not act on the copy click ─────────────────────────
  const noDialog = await eval_(`document.querySelectorAll('[role="dialog"]').length === 0`);
  check('copying did not open a record action', noDialog === true);

  // ── 5. The delegation survives a morph ───────────────────────────────
  // The payoff of moving the behaviour off the cell: these rows are re-created by
  // the sort commit, and nothing re-binds anything.
  const sorted = await eval_(`(() => {
    const h = document.querySelector('th button, th [wire\\\\:click]');
    if (! h) return false;
    h.click();
    return true;
  })()`);
  await sleep(1200);

  if (sorted) {
    await eval_(`navigator.clipboard.writeText('sentinel-before-second-copy')`);
    const copied = await clickCopy(0);
    const afterMorph = JSON.parse(await pillState());
    const clip2 = await eval_(`navigator.clipboard.readText().then(t => t).catch(() => null)`);

    check('copy still works on morphed-in rows', copied && afterMorph.hidden === false);
    check('the morphed row copies its own value', clip2 !== 'sentinel-before-second-copy', `clipboard "${clip2}"`);
    await shot('02-after-morph');
  } else {
    check('copy still works on morphed-in rows', false, 'no sortable header on this preview');
  }

} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
