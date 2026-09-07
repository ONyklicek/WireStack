import { openPage, checker } from './lib/cdp.mjs';

/*
 * The library used from somewhere else on the page.
 *
 * This is the seam nothing else can check. Three packages have to meet in one
 * document and none of them may name the others:
 *
 *   - `wire-forms` owns the rich text editor and must never require the media
 *     module, so its image button *offers* the job as a cancelable DOM event and
 *     falls back to a URL prompt when nobody takes it.
 *   - `wire-module-media` takes it, in a modal that
 *   - `wire-admin` renders without knowing what it is, because the module
 *     registered the view in PageChrome.
 *
 * Every one of those is a runtime handshake. Pest sees three packages that
 * compile; only a browser sees whether the event was claimed, whether the modal
 * was in the document to claim it, and whether what came back reached the editor.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/invoices/create`, shotPrefix: 'media-picker', width: 1300, height: 950 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="media-field"]')`);

  // ── 1. The shell rendered a view it has never heard of ───────────────────
  check('the shell rendered the module’s picker modal', await eval_(`!! document.querySelector('[data-testid="media-picker"]')`));
  check('the form carries a media field', await eval_(`!! document.querySelector('[data-testid="media-field-open"]')`));

  // ── 2. The field opens it ────────────────────────────────────────────────
  await eval_(`document.querySelector('[data-testid="media-field-open"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`, 5000);
  check('the field opens the library', await eval_(`!! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`));

  // The library itself, not a second screen that looks like it: the folder tree
  // is the tell, because a hand-written picker never has one.
  await waitFor(`!! document.querySelector('[data-testid="media-picker"] [data-testid="media-folder"]')`, 5000);
  check('what opened is the library, folders and all', await eval_(`!! document.querySelector('[data-testid="media-picker"] [data-testid="media-folder"]')`));

  // ── 3. Choosing answers the field ───────────────────────────────────────
  // This field takes several files, so a click selects and the bar confirms.
  // A single-file field answers on the click itself — which is the path the
  // editor takes further down, and the reason both are worth walking.
  await waitFor(`!! document.querySelector('[data-testid="media-picker"] [data-testid="media-open"]')`, 5000);
  await eval_(`document.querySelector('[data-testid="media-picker"] [data-testid="media-open"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="media-pick-confirm"]')`, 5000);
  check('choosing several waits for a confirmation', await eval_(`!! document.querySelector('[data-testid="media-pick-confirm"]')`));
  await shot('01-picker-open');

  await eval_(`document.querySelector('[data-testid="media-pick-confirm"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="media-field-item"]')`, 6000);
  check('the chosen file lands in the field', await eval_(`!! document.querySelector('[data-testid="media-field-item"]')`));
  // Waited on, not checked on sight: the modal leaves on an opacity transition,
  // so for a few frames after it is told to close it is still laid out and
  // `offsetParent` still answers. Checking immediately measures the animation.
  await waitFor(`! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`, 4000).catch(() => {});
  check('and the modal closed itself', await eval_(`! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`));
  await shot('02-picked');

  // ── 4. Taking it back out ────────────────────────────────────────────────
  await eval_(`
    (() => {
      const item = document.querySelector('[data-testid="media-field-item"]');
      item.querySelector('[data-testid="media-field-remove"]').click();
    })()
  `);
  await waitFor(`! document.querySelector('[data-testid="media-field-item"]')`, 5000);
  check('and can be taken back out', await eval_(`! document.querySelector('[data-testid="media-field-item"]')`));

  // ── 5. The editor asks the same question ─────────────────────────────────
  // The button is in the TipTap toolbar; it is the one whose action is
  // insertImage(). Clicking it must open the modal rather than raise a prompt —
  // and a prompt would hang this driver, which is exactly the failure to catch.
  const opened = await eval_(`
    (() => {
      const button = [...document.querySelectorAll('[x-on\\\\:click], [wire\\\\:click], button')]
        .find(el => (el.getAttribute('x-on:click') ?? el.getAttribute('@click') ?? '').includes('insertImage'));

      if (! button) return 'no-button';

      // A prompt would block the page and there would be nothing to assert, so
      // it is replaced by something that records having been asked.
      window.__promptCalled = false;
      window.prompt = () => { window.__promptCalled = true; return null; };

      button.click();
      return 'clicked';
    })()
  `);

  check('the editor has an image button', opened === 'clicked', opened);
  await waitFor(`!! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`, 5000);
  check('the editor opens the library instead of asking for a URL', await eval_(`!! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`));
  check('and never fell back to the prompt', (await eval_(`window.__promptCalled`)) === false);
  await shot('03-editor-picker');

  // ── 6. What comes back reaches the document ──────────────────────────────
  await waitFor(`!! document.querySelector('[data-testid="media-picker"] [data-testid="media-open"]')`, 5000);
  await eval_(`document.querySelector('[data-testid="media-picker"] [data-testid="media-open"]').click()`);
  await waitFor(`!! document.querySelector('.ProseMirror img')`, 6000);
  check('the picked image is inserted into the editor', await eval_(`!! document.querySelector('.ProseMirror img')`));

  // The alt text travels with the file, because it was written once about the
  // file rather than at each insertion.
  const alt = await eval_(`document.querySelector('.ProseMirror img')?.getAttribute('alt') ?? ''`);
  check('with the alt text the library holds', alt.length > 0, `alt="${alt}"`);

  // And with the row's id, which is the whole of ADR 0034: without it the saved
  // HTML is a bare URL, the library reports zero uses for a photograph that is
  // in twelve articles, and the warning in front of every delete and every
  // replacement is a false all-clear. Read off the *document* rather than the
  // node's attributes, because what matters is what gets saved.
  const mediaId = await eval_(`
    (() => {
      const img = document.querySelector('.ProseMirror img');
      return img?.getAttribute('data-media-id') ?? '';
    })()
  `);
  check('and the id that says which file it is', /^\d+$/.test(mediaId), `data-media-id="${mediaId}"`);

  await shot('04-inserted');

  // ── 7. The same question on a phone ──────────────────────────────────────
  // The same page resized rather than a second browser, for the reason
  // verify-media-frame.mjs gives: launching one costs more than every check in
  // this file, and the sweep kills a driver at 180 seconds.
  //
  // Two kinds of check live here. The ones that must hold at any width — the
  // modal fits, a single-file choice offers no checkbox to tap instead of the
  // tile, the tap target is one, the chain still ends in the document — and the
  // ones that are the phone layout itself: the tree folded behind a row, the
  // toolbar not carrying controls it has no room for. The second kind is pinned
  // deliberately, because every one of them is a decision that a stray `lg:`
  // would quietly undo.
  const phone = { width: 390, height: 844 };
  await page('Emulation.setDeviceMetricsOverride', { ...phone, deviceScaleFactor: 1, mobile: true });

  await eval_(`
    (() => {
      const button = [...document.querySelectorAll('button')]
        .find(el => (el.getAttribute('x-on:click') ?? el.getAttribute('@click') ?? '').includes('insertImage'));
      window.__promptCalled = false;
      window.prompt = () => { window.__promptCalled = true; return null; };
      button?.click();
    })()
  `);
  await waitFor(`!! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`, 6000);
  check('the editor opens the library on a phone too', await eval_(`!! document.querySelector('[data-testid="media-picker-close"]')?.offsetParent`));
  check('and still never falls back to the prompt', (await eval_(`window.__promptCalled`)) === false);

  const fits = JSON.parse(await eval_(`
    (() => {
      const box = document.querySelector('[data-testid="media-picker"] .max-h-full');
      if (! box) return JSON.stringify(null);
      const r = box.getBoundingClientRect();
      return JSON.stringify({
        width: Math.round(r.width),
        height: Math.round(r.height),
        overflowsRight: Math.round(r.right) > window.innerWidth,
        tallerThanScreen: Math.round(r.height) > window.innerHeight,
        pageScrollsSideways: document.documentElement.scrollWidth > window.innerWidth,
      });
    })()
  `));

  check('the modal fits the screen it was opened on',
    fits !== null && ! fits.overflowsRight && ! fits.tallerThanScreen, JSON.stringify(fits));
  check('and nothing pushes the page sideways', fits !== null && ! fits.pageScrollsSideways, JSON.stringify(fits));
  await shot('05-phone-picker');

  // The guard that drops the checkboxes is `$picking && ! $multiple` on the
  // server, not a breakpoint — so it has to hold at every width. A checkbox here
  // would be the dead end again: ticking selects, and a single-file picker has
  // no bar to act on a selection with.
  check('a one-file choice offers no checkbox to tap instead of the tile',
    (await eval_(`document.querySelectorAll('[data-testid="media-picker"] [data-testid="media-select"], [data-testid="media-picker"] [data-testid="media-select-all"]').length`)) === 0);

  const target = JSON.parse(await eval_(`
    (() => {
      const el = document.querySelector('[data-testid="media-picker"] [data-testid="media-open"]');
      if (! el) return JSON.stringify(null);
      const r = el.getBoundingClientRect();
      return JSON.stringify({ w: Math.round(r.width), h: Math.round(r.height) });
    })()
  `));

  // 44 CSS pixels is the floor every mobile HIG has agreed on for twenty years.
  check('and the tile is a thumb-sized target',
    target !== null && target.w >= 44 && target.h >= 44, JSON.stringify(target));

  // The tree is a disclosure here, not a column. Stacked it put 158px of
  // navigation above the first file, on the one screen size where the files are
  // the entire reason the modal is open.
  const shown = (selector) => eval_(`!! document.querySelector('[data-testid="media-picker"] ${selector}')?.offsetParent`);

  check('the folder tree is folded behind one row', await shown('[data-testid="media-folder-toggle"]'));
  check('and is not taking the top of the screen', (await shown('[data-testid="media-folder-root"]')) === false);

  // Two answers to "where am I" in a toolbar that was already wrapping is one
  // too many — the breadcrumb was the thing being truncated to "Li…".
  check('the breadcrumb steps aside for it', (await shown('[data-testid="media-breadcrumb"]')) === false);
  check('and so does the list/grid switch', (await shown('[data-testid="media-view-toggle"]')) === false);

  check('the grid scrolls on its own here too', (await eval_(`
    getComputedStyle(document.querySelector('[data-testid="media-picker"] ul.grid')).overflowY
  `)) === 'auto');

  await eval_(`document.querySelector('[data-testid="media-picker"] [data-testid="media-folder-toggle"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="media-picker"] [data-testid="media-folder-root"]')?.offsetParent`, 4000);
  check('tapping it opens the tree', await shown('[data-testid="media-folder-root"]'));
  await shot('05-phone-folders');

  // Choosing a folder is the end of the errand, so it closes behind you. The
  // root is chosen rather than a branch so the grid still has the file the last
  // check needs — testing the disclosure must not empty the folder under it.
  await eval_(`document.querySelector('[data-testid="media-picker"] [data-testid="media-folder-root"]').click()`);
  await waitFor(`! document.querySelector('[data-testid="media-picker"] [data-testid="media-folder-root"]')?.offsetParent`, 4000);
  check('and choosing one closes it again', (await shown('[data-testid="media-folder-root"]')) === false);

  // The caret is put back in the text first, which is what a person does before
  // reaching for the image button — and here it is load-bearing. Step 6 left the
  // image it inserted *selected*, and inserting into a selected node replaces it,
  // so a second picture would land on top of the first and the count would never
  // move. That is TipTap behaving correctly; this driver simply must not open the
  // picker from a selection it did not mean to overwrite.
  await eval_(`
    (() => {
      const view = document.querySelector('.ProseMirror');
      view.focus();
      const selection = window.getSelection();
      selection.selectAllChildren(view);
      selection.collapseToEnd();
    })()
  `);

  const before = await eval_(`document.querySelectorAll('.ProseMirror img').length`);
  await eval_(`document.querySelector('[data-testid="media-picker"] [data-testid="media-open"]').click()`);
  await waitFor(`document.querySelectorAll('.ProseMirror img').length > ${before}`, 6000);
  check('and one tap still puts a picture in the document',
    (await eval_(`document.querySelectorAll('.ProseMirror img').length`)) > before);
  await shot('06-phone-inserted');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
