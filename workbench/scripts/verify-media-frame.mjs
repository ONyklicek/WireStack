import { openPage, checker } from './lib/cdp.mjs';

/*
 * The frame around the library: the rail, the tree, and the panel on a phone.
 *
 * All three are things Pest cannot answer, because none of them is state the
 * server holds:
 *
 *   - the rail's width is dragged and kept in `localStorage`, so it survives a
 *     reload without a round trip;
 *   - which branches are open is the browser's too, and a tree that forgets is
 *     a tree you re-open on every visit;
 *   - below the breakpoint the detail panel is a sheet rather than a third
 *     column stacked under the grid — a panel about the file you just tapped,
 *     two screens below the tiles, is a panel nobody sees.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const railWidth = (eval_) => eval_(`
  Math.round(document.querySelector('[data-testid="media-rail"]').getBoundingClientRect().width)
`);

/** Drag the seam by `dx`, as a pointer would. */
const dragRail = (eval_, dx) => eval_(`
  (() => {
    const handle = document.querySelector('[data-testid="media-rail-handle"]');
    if (! handle) return false;

    const box = handle.getBoundingClientRect();
    const x = box.left + box.width / 2;
    const y = box.top + box.height / 2;

    handle.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true, clientX: x, clientY: y }));
    window.dispatchEvent(new PointerEvent('pointermove', { bubbles: true, clientX: x + ${dx}, clientY: y }));
    window.dispatchEvent(new PointerEvent('pointerup', { bubbles: true }));
    return true;
  })()
`);

const page_ = await openPage({ url: `${base}/routed/media`, shotPrefix: 'media-frame', width: 1300, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
    // ── Desktop: the rail and the tree ──────────────────────────────────────
    await waitFor(`document.querySelector('[data-testid="media-rail-handle"]')`, 8000);

    const before = await railWidth(eval_);
    check('the rail starts at its remembered width', before > 100, `${before}px`);

    check('the seam can be dragged', await dragRail(eval_, 90));

    const after = await railWidth(eval_);
    check('and the rail follows it', after > before + 60, `${before} -> ${after}`);

    // Bounded, or a rail dragged to nothing is a rail nobody can get back.
    await dragRail(eval_, -2000);
    const floor = await railWidth(eval_);
    check('it cannot be dragged away entirely', floor >= 160, `${floor}px`);

    await dragRail(eval_, 4000);
    const ceiling = await railWidth(eval_);
    check('nor over the grid', ceiling <= 480, `${ceiling}px`);

    check('the width is written down', await eval_(`localStorage.getItem('wire-media-rail') !== null`));

    const twisty = await eval_(`
      (() => {
        const el = document.querySelector('[data-testid="media-folder-twisty"]');
        if (! el) return false;
        el.click();
        return true;
      })()
    `);
    check('a branch with children folds', twisty);
    check('and what is open is written down', await eval_(`localStorage.getItem('wire-media-tree') !== null`));

    await shot('desktop');

    // ── A phone: the panel is a sheet ───────────────────────────────────────
    // The same page resized rather than a second browser: launching one costs
    // more than every check in this file, and the sweep kills a driver at 180s.
    const phone = { width: 420, height: 820 };
    await page('Emulation.setDeviceMetricsOverride', { ...phone, deviceScaleFactor: 1, mobile: true });

    check('the seam is not offered where there is one column', await eval_(`
      (() => {
        const handle = document.querySelector('[data-testid="media-rail-handle"]');
        return ! handle || getComputedStyle(handle).display === 'none';
      })()
    `));

    await waitFor(`
      (() => {
        const el = document.querySelector('[data-testid="media-open"]');
        if (! el) return false;
        el.click();
        return true;
      })()
    `, 8000);

    await waitFor(`document.querySelector('[data-testid="media-detail-backdrop"]')`, 8000);

    const sheet = await eval_(`
      (() => {
        const backdrop = document.querySelector('[data-testid="media-detail-backdrop"]');
        const panel = backdrop?.nextElementSibling;
        if (! panel) return null;
        const cs = getComputedStyle(panel);
        const box = panel.getBoundingClientRect();
        return { position: cs.position, bottom: Math.round(box.bottom), width: Math.round(box.width), backdrop: getComputedStyle(backdrop).display };
      })()
    `);

    check(
        'the panel is a sheet at the bottom of the screen',
        sheet !== null && sheet.position === 'fixed' && Math.abs(sheet.bottom - phone.height) < 4,
        JSON.stringify(sheet),
    );

    check('and it spans the screen', sheet !== null && sheet.width > 380, JSON.stringify(sheet));

    check('with a backdrop to tap beside it', sheet !== null && sheet.backdrop !== 'none', JSON.stringify(sheet));

    await shot('phone');
} finally {
    await close();
}

finish({ consoleErrors, badResponses, shotDir });
