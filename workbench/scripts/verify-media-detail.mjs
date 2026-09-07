import { openPage, checker } from './lib/cdp.mjs';

/*
 * One file's own page — the read-only counterpart of the library grid.
 *
 * Pest asserts the markup (packages/module-media/tests). What only a browser can
 * answer:
 *
 *   - the preview is a picture that actually loaded. A broken `<img>` renders
 *     with the right `src` in the markup and `naturalWidth === 0` in the page,
 *     which is the difference between "the URL was built" and "the file is
 *     there" — and the URL is built through `previewUrl()`, whose whole job is
 *     to pick between the scaled copy, the original and the streamed route.
 *   - a file with no pixels draws no image at all. A PDF rendered through
 *     `<img>` is a broken-image icon claiming the file is damaged, and the
 *     markup for that is indistinguishable from a working one.
 *   - Open and Download are links. They are section header actions, and the
 *     infolist partial used to render every action as a button that dispatched
 *     `callInfolistAction` — which a `ViewPage` does not have, so the affordance
 *     existed and did nothing. Only the DOM says which element was emitted.
 *   - the collapsed Storage section really opens. It is `x-collapse` over
 *     `x-show`, invisible to any server-side assertion.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/media`, shotPrefix: 'media-detail', width: 1300, height: 1000 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="media-tile"]')`, 10000);

  // ── 1. An image ──────────────────────────────────────────────────────────
  // Reached by its own URL rather than by clicking through the grid: the grid's
  // tile opens the details *panel*, which is a different surface with a
  // different job, and this driver is about the page.
  await eval_(`window.location.href = ${JSON.stringify(`${base}/routed/media/3`)}`);
  await waitFor(`!! document.querySelector('.wire-infolist')`, 10000);

  check('the page is headed by the file, not by the word "file"', await eval_(`
    document.querySelector('h1')?.textContent.trim().endsWith('.jpg')
  `), await eval_(`document.querySelector('h1')?.textContent.trim()`));

  // `complete && naturalWidth > 0` is the only thing that separates a picture
  // from a URL that 404s: both have the same markup.
  await waitFor(`(() => {
    const img = document.querySelector('.wire-infolist img');
    return !! img && img.complete;
  })()`, 8000);

  check('the preview is a picture that loaded', await eval_(`
    (() => {
      const img = document.querySelector('.wire-infolist img');
      return !! img && img.naturalWidth > 0;
    })()
  `));

  // 240px, not the 40px thumbnail the grid uses — this is the page whose whole
  // job is to show you the file.
  check('and it is drawn at a size you can see it at', await eval_(`
    (document.querySelector('.wire-infolist img')?.getBoundingClientRect().width ?? 0) >= 200
  `));

  const detail = await eval_(`document.querySelector('.wire-infolist').innerText`);

  // Each of these was wrong in the flat version, and wrong beside a screen that
  // had it right: a raw byte count, an unformatted timestamp, a dimension pair
  // the record carried and nobody drew, and no folder at all.
  check('the size reads as a size', /\d+(\.\d+)?\s*(B|kB|MB|GB)/.test(detail), detail.replace(/\s+/g, ' ').slice(0, 120));
  check('the dimensions are there', /\d+\s*×\s*\d+/.test(detail));
  check('and so is the folder it is filed under', /Logos/.test(detail));
  check('the upload date is formatted, not a raw timestamp', /\d{2}\.\d{2}\.\d{4}/.test(detail) && ! /\d{4}-\d{2}-\d{2}T/.test(detail));

  // ── 2. Open and Download are links ───────────────────────────────────────
  check('Open is a link, not a dead button', await eval_(`
    document.querySelector('[data-testid="infolist-action-open"]')?.tagName === 'A'
  `));
  check('and it opens in its own tab', await eval_(`
    document.querySelector('[data-testid="infolist-action-open"]')?.target === '_blank'
  `));
  check('Download is a link that downloads', await eval_(`
    (() => {
      const el = document.querySelector('[data-testid="infolist-action-download"]');
      return el?.tagName === 'A' && el.hasAttribute('download');
    })()
  `));
  await shot('01-image');

  // ── 3. The collapsed section opens ───────────────────────────────────────
  // Storage is the half nobody opens the page for, so it starts folded — and a
  // fold that cannot be unfolded is worse than no fold.
  const storageToggle = `[...document.querySelectorAll('[data-testid="section-toggle"]')].at(-1)`;

  check('the storage section starts folded', await eval_(`
    ${storageToggle}?.getAttribute('aria-expanded') === 'false'
  `));

  await eval_(`${storageToggle}.click()`);
  await waitFor(`${storageToggle}?.getAttribute('aria-expanded') === 'true'`, 4000);

  check('and opens onto the disk and the path', await eval_(`
    document.querySelector('.wire-infolist').innerText.includes('photos/')
  `));
  await shot('02-storage');

  // ── 4. A file with no pixels ─────────────────────────────────────────────
  await eval_(`window.location.href = ${JSON.stringify(`${base}/routed/media/1`)}`);
  await waitFor(`!! document.querySelector('.wire-infolist')`, 10000);

  check('a PDF draws no image at all', await eval_(`! document.querySelector('.wire-infolist img')`));
  check('and says so instead', await eval_(`
    document.querySelector('.wire-infolist').innerText.toLowerCase().includes('nothing to show')
  `));
  // The one thing a PDF still has to offer: a way to actually read it.
  check('while still offering a way to open it', await eval_(`
    !! document.querySelector('[data-testid="infolist-action-open"]')
  `));
  await shot('03-pdf');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
