import { openPage, checker } from './lib/cdp.mjs';

/*
 * The image editor.
 *
 * Pest drives the component and asserts what lands in the database
 * (packages/module-media/tests/Feature/MediaEditorTest.php). Everything this
 * checks is the half Pest cannot see, because all of it happens in a canvas:
 *
 *   - the source is readable at all. The editor fetches the original through
 *     the module's own route so the fetch is same-origin whatever disk the file
 *     is on; a misconfigured URL fails here and nowhere else.
 *   - the crop frame is a frame. It is positioned in percentages of the picture
 *     and those percentages are what `processImage` cuts, so a frame that
 *     renders in the wrong place cuts in the wrong place — and both look fine
 *     on the server.
 *   - a locked ratio actually locks. The maths puts the source's own
 *     proportions back in, which no server-side assertion can reach.
 *   - a quarter turn swaps the reported output size, which is the number a
 *     person reads before they press Save.
 *   - Save produces a file. The whole chain — fetch, canvas, Livewire upload —
 *     only exists in the browser.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/media`, shotPrefix: 'media-editor', width: 1300, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

/**
 * Wait for an element and click it in the same evaluation.
 *
 * Two steps — wait, then click — read better and race: Livewire re-renders
 * between them and the node the first step saw is not the node the second one
 * clicks. That failed here once and reported it as a missing button.
 */
const clickWhenReady = (selector, timeout = 8000) => waitFor(`
  (() => {
    const el = document.querySelector(${JSON.stringify(selector)});
    if (! el) return false;
    el.click();
    return true;
  })()
`, timeout);

const size = () => eval_(`document.querySelector('[data-testid="media-editor-result"]')?.textContent?.trim() ?? ''`);

/** The frame's rendered box, which is what the crop maths is describing. */
const frameBox = () => eval_(`
  (() => {
    const el = document.querySelector('[data-testid="media-editor-frame"]');
    if (! el) return null;
    const box = el.getBoundingClientRect();
    return { width: Math.round(box.width), height: Math.round(box.height) };
  })()
`);

try {
    await waitFor(`document.querySelector('[data-testid="media-tile"]')`, 8000);

    const before = await eval_(`document.querySelectorAll('[data-testid="media-tile"]').length`);

    // Find a tile that is a picture: the editor is not offered for anything
    // else, and a library preview holds documents too.
    const opened = await eval_(`
      (() => {
        const tiles = [...document.querySelectorAll('[data-testid="media-tile"]')];
        const withImage = tiles.find(tile => tile.querySelector('img'));
        if (! withImage) return false;
        withImage.querySelector('[data-testid="media-open"]').click();
        return true;
      })()
    `);
    check('a picture can be opened in the library', opened);

    await clickWhenReady('[data-testid="media-edit"]');

    await waitFor(`document.querySelector('[data-testid="media-editor"]')`, 8000);
    check('the editor opens', true);

    // The picture has to be readable before anything else means anything: this
    // is the fetch through the module's own route.
    await waitFor(`
      (() => {
        const img = document.querySelector('[data-testid="media-editor-image"]');
        return img && img.complete && img.naturalWidth > 0;
      })()
    `, 8000);
    check('the source loads', true);

    await waitFor(`document.querySelector('[data-testid="media-editor-result"]')?.textContent?.includes('×')`, 5000);
    const initial = await size();
    check('it reports an output size', /\d+ × \d+/.test(initial), `size=${initial}`);
    await shot('open');

    // ── A quarter turn ────────────────────────────────────────────────────
    // Before the crop is made square, or this compares a square with itself and
    // passes whatever the code does.
    const beforeTurn = await size();
    await clickWhenReady('[data-testid="media-editor-rotate-right"]');
    await new Promise((resolve) => setTimeout(resolve, 150));
    const afterTurn = await size();

    const transform = await eval_(`document.querySelector('[data-testid="media-editor-image"]').style.transform`);

    // Two assertions, because the seeded library may hand this a square
    // photograph and "480 × 480 swapped is 480 × 480" is a check that cannot
    // fail. The picture turning is the part that is always true.
    check('the picture turns', transform.includes('rotate(90deg)'), transform);
    check(
        'and the reported output size turns with it',
        beforeTurn.split(' × ').reverse().join(' × ') === afterTurn,
        `${beforeTurn} -> ${afterTurn}`,
    );

    // ── A locked ratio ────────────────────────────────────────────────────
    await eval_(`
      [...document.querySelectorAll('[data-testid="media-editor-ratio"]')]
        .find(button => button.textContent.trim() === '1:1')?.click()
    `);

    await new Promise((resolve) => setTimeout(resolve, 150));

    const square = await frameBox();
    check(
        'a 1:1 frame is drawn square',
        // Width > 0 first: a frame that has not laid out is 0×0, and "0 equals
        // 0" is a check that passes for exactly the failure it exists to catch —
        // which it did, for as long as the preview's CSS was stale.
        square !== null && square.width > 0 && Math.abs(square.width - square.height) <= 2,
        `frame=${JSON.stringify(square)}`,
    );

    // ── The warning ───────────────────────────────────────────────────────
    const warning = await eval_(`document.querySelector('[data-testid="media-editor-warning"]')?.textContent?.trim() ?? ''`);
    check('the replacement warning is on screen before either button', warning.length > 20, warning.slice(0, 60));

    await shot('edited');

    // ── Saving as a new file ──────────────────────────────────────────────
    await clickWhenReady('[data-testid="media-editor-save-new"]');

    // The editor closes when the upload has landed and the component has
    // re-rendered, which is the only signal that the whole chain worked.
    await waitFor(`! document.querySelector('[data-testid="media-editor"]')`, 20000);
    check('the editor closes once the file has landed', true);

    await waitFor(`document.querySelectorAll('[data-testid="media-tile"]').length === ${before + 1}`, 15000).catch(() => {});

    const after = await eval_(`document.querySelectorAll('[data-testid="media-tile"]').length`);
    check('a new file appears in the library', after === before + 1, `${before} -> ${after}`);

    const named = await eval_(`
      [...document.querySelectorAll('[data-testid="media-tile"] p')].some(p => p.textContent.includes('('))
    `);
    check('and it is named as a derivative', named);

    await shot('saved');

    // Put the library back. Every run of this driver used to leave one more
    // derivative behind, and the next driver's "holds what it started with"
    // counted them — a driver that changes shared state and does not undo it
    // makes the one after it lie.
    const removed = await eval_(`
      (() => {
        const tile = [...document.querySelectorAll('[data-testid="media-tile"]')]
          .find(el => el.querySelector('p')?.textContent?.includes('('));
        if (! tile) return false;

        // The component method rather than the delete button: that button
        // carries a wire:confirm, and a native dialog blocks the driver instead
        // of answering it.
        //
        // The host is walked to rather than selected, because wire:id is not a
        // valid CSS selector unescaped and the escaping does not survive the
        // trip into the page.
        let host = tile;
        while (host && ! host.hasAttribute('wire:id')) host = host.parentElement;
        if (! host) return false;

        window.Livewire.find(host.getAttribute('wire:id')).call('deleteOne', parseInt(tile.dataset.media));
        return true;
      })()
    `);

    if (removed) {
        await waitFor(`document.querySelectorAll('[data-testid="media-tile"]').length === ${before}`, 10000).catch(() => {});
    }

    check('and the library is left as it was found', (await eval_(`document.querySelectorAll('[data-testid="media-tile"]').length`)) === before);

    console.log(`Screenshots: ${shotDir}`);
} finally {
    await close();
}

finish({ consoleErrors, badResponses, shotDir });
