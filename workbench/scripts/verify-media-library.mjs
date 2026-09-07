import { openPage, checker } from './lib/cdp.mjs';

/*
 * The media library — folders, and moving things between them.
 *
 * Pest drives the component directly and asserts what it does to the database
 * (packages/module-media/tests). What only a browser can answer is the half that
 * is markup and Alpine:
 *
 *   - the tree really is a tree. It is one query rendered by a recursive
 *     include, and a depth bug in that include renders a flat list that still
 *     passes every server-side assertion.
 *   - a tile can be dragged onto a folder. The drag carries `wire/media` and a
 *     folder carries `wire/folder`, and the drop handler tells them apart — a
 *     distinction that exists only in the DOM.
 *   - dropping files from the desktop reveals the drop zone rather than
 *     navigating away, which is what a page without a `dragover.prevent` does.
 *   - the breadcrumb walks back out. It is built from the stored path, so a
 *     folder whose path was not rewritten after a move shows a rung that leads
 *     nowhere — and only clicking it says so.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/media`, shotPrefix: 'media-library', width: 1300, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

/** Fire a full HTML5 drag from one element onto another, carrying one key. */
const dragOnto = (fromSelector, ontoSelector) => eval_(`
  (() => {
    const from = document.querySelector(${JSON.stringify(fromSelector)});
    const onto = document.querySelector(${JSON.stringify(ontoSelector)});
    if (! from || ! onto) return false;

    // A real DataTransfer, so the handlers read exactly what a mouse would put
    // there — a stubbed object would let a typo in the key pass unnoticed.
    const data = new DataTransfer();
    from.dispatchEvent(new DragEvent('dragstart', { bubbles: true, dataTransfer: data }));
    onto.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer: data }));
    onto.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: data }));
    return true;
  })()
`);


/**
 * Put the library in a known view.
 *
 * Says which one rather than pressing the toggle: this driver walks through six
 * steps and each needs a particular shape on screen, so "toggle and hope" made
 * every step depend on how many times the ones before it had toggled. It drifted
 * exactly once and reported a database it had correctly restored as broken.
 */
const setView = (mode) => eval_(`
  (() => {
    let host = document.querySelector('[data-testid="media-manager"]');
    while (host && ! host.hasAttribute('wire:id')) host = host.parentElement;

    window.Livewire.find(host.getAttribute('wire:id')).set('view', ${JSON.stringify(mode)});

    // Not returned: set() answers a Promise, and a Promise is not something
    // Runtime.evaluate can hand back, so the call simply never settled and the
    // driver stopped there with no failure to show for it. The caller waits on
    // the DOM instead, which is what it wanted to know anyway.
    // (No backticks in this comment: it lives inside a template literal.)
    return true;
  })()
`);

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="media-manager"]')`);

  // ── 1. The tree ──────────────────────────────────────────────────────────
  const folders = await eval_(`document.querySelectorAll('[data-testid="media-folder"]').length`);
  check('the seeded folders are drawn', folders >= 3, `found ${folders}`);

  // Indentation is the only thing that says a child is a child, and it is
  // computed from the recursion depth: a flat render has every row at the same
  // inset and looks fine until you look for the nesting.
  const insets = await eval_(`
    [...document.querySelectorAll('[data-testid="media-folder"]')]
      .map(el => parseFloat(getComputedStyle(el).paddingInlineStart))
  `);
  check('the tree is nested, not flat', new Set(insets).size > 1, `insets ${insets.join(',')}`);

  // ── 2. Opening one ───────────────────────────────────────────────────────
  // The folder's own name, by its test id rather than by being the first button
  // in the row: the row grew a disclosure triangle in front of the name, and a
  // positional selector quietly started collapsing the branch instead of
  // opening the folder.
  await eval_(`document.querySelector('[data-testid="media-folder-open"]').click()`);
  await waitFor(`document.querySelectorAll('[data-testid="media-breadcrumb"] button').length > 1`, 5000);

  const crumbs = await eval_(`document.querySelectorAll('[data-testid="media-breadcrumb"] button').length`);
  check('the breadcrumb grows a rung for the folder', crumbs > 1, `crumbs ${crumbs}`);
  await shot('01-folder-open');

  // ── 3. Dragging a file onto a folder ─────────────────────────────────────
  await eval_(`document.querySelector('[data-testid="media-breadcrumb"] button').click()`);
  await waitFor(`!! document.querySelector('[data-testid="media-tile"]')`, 5000);

  const before = await eval_(`document.querySelectorAll('[data-testid="media-tile"]').length`);
  check('the library root holds files', before > 0, `tiles ${before}`);

  check('a tile and a folder can be dragged together', await dragOnto('[data-testid="media-tile"]', '[data-testid="media-folder"]'));
  await waitFor(`document.querySelectorAll('[data-testid="media-tile"]').length === ${before - 1}`, 5000);
  check('the file leaves the folder it was dragged out of', (await eval_(`document.querySelectorAll('[data-testid="media-tile"]').length`)) === before - 1);

  // ── 4. The desktop drop zone ─────────────────────────────────────────────
  // Revealed, not inserted: an element that appears under the cursor mid-drag
  // cancels the drag in some browsers, which is why it is `x-show` and not `@if`.
  await eval_(`
    (() => {
      const zone = document.querySelector('[data-testid="media-manager"] > div:last-child');
      const data = new DataTransfer();
      data.items.add(new File(['x'], 'dropped.png', { type: 'image/png' }));
      zone.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer: data }));
    })()
  `);
  await waitFor(`[...document.querySelectorAll('div')].some(el => el.textContent.trim().length && getComputedStyle(el).borderStyle === 'dashed' && el.offsetParent)`, 4000);
  check('dragging files over the grid shows where they will land', await eval_(`
    [...document.querySelectorAll('div')].some(el => getComputedStyle(el).borderStyle === 'dashed' && el.offsetParent)
  `));
  await shot('02-drop-zone');

  // ── 5. The list view ─────────────────────────────────────────────────────
  // The drop hint covers the grid while a drag is over it, so the drag has to be
  // let go of before anything under it can be clicked — which is also the state
  // a real user leaves behind when they drag away instead of dropping.
  await eval_(`
    document.querySelector('[data-testid="media-manager"] > div:last-child')
      .dispatchEvent(new DragEvent('dragleave', { bubbles: true }))
  `);
  await waitFor(`!! document.querySelector('[data-testid="media-view-toggle"]')?.offsetParent`, 4000);

  // Inside the folder the tile was just dropped into, which is also the proof
  // that it landed: the root is empty by now, and a list view asserted against
  // an empty folder asserts nothing.
  // The folder's own name, by its test id rather than by being the first button
  // in the row: the row grew a disclosure triangle in front of the name, and a
  // positional selector quietly started collapsing the branch instead of
  // opening the folder.
  await eval_(`document.querySelector('[data-testid="media-folder-open"]').click()`);
  await waitFor(`document.querySelectorAll('[data-testid="media-tile"]').length > 0`, 5000);
  check('the dropped file is in the folder it was dragged onto', (await eval_(`document.querySelectorAll('[data-testid="media-tile"]').length`)) > 0);

  // The one place the button itself is exercised — everything after this says
  // which view it wants.
  await eval_(`document.querySelector('[data-testid="media-view-toggle"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="media-row"]')`, 5000);
  check('the same files render as a list', await eval_(`!! document.querySelector('[data-testid="media-row"]')`));
  await shot('03-list-view');

  // ── 5b. What a grid actually loads ───────────────────────────────────────
  // The whole point of the scaled copy: a grid of a hundred files must not be a
  // hundred full-size photographs. Only the browser can say which URL the tile
  // asked for, and only the browser can say whether it arrived.
  await setView('grid');
  // Waited on the decode, not on the element: an <img> exists the moment it is
  // rendered and reports naturalWidth 0 until the bytes arrive, so checking on
  // sight measures the network rather than the page.
  await waitFor(`document.querySelector('[data-testid="media-tile"] img')?.naturalWidth > 0`, 8000).catch(() => {});

  const preview = await eval_(`
    (() => {
      const img = document.querySelector('[data-testid="media-tile"] img');
      return { src: img?.getAttribute('src') ?? '', loaded: !! img?.naturalWidth, width: img?.naturalWidth ?? 0 };
    })()
  `);

  check('a tile loads the thumbnail, not the original', preview.src.includes('thumbnails/'), preview.src);
  check('and the thumbnail is really there', preview.loaded, `naturalWidth=${preview.width}`);
  check('scaled to the configured bound', preview.width > 0 && preview.width <= 400, `naturalWidth=${preview.width}`);
  await shot('04-thumbnails');

  // ── 5c. The preview that exists before the server has anything ───────────
  await setView('grid');
  // Entirely client-side: the browser already holds the bytes, so a dropped
  // photograph is on screen before the round trip starts. Pest sees the markup
  // for the stored files and nothing at all of this — only a browser can drop a
  // file, and only a browser has an object URL to draw from.
  const dropped = await eval_(`
    (() => {
      const zone = document.querySelector('[data-testid="media-manager"] > div:last-child');
      // Unique bytes every run, deliberately: the library refuses a second copy
      // of a file it already holds and hands back the row it has, so a fixed
      // image would upload once and be recognised as a duplicate for ever after
      // — which is the library working and the driver asserting nonsense.
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 8;
      // Concatenated, not interpolated: this whole block is itself inside a
      // template literal on the Node side, so a nested one would be evaluated
      // there rather than in the browser.
      const rand = () => Math.floor(Math.random() * 255);
      const context = canvas.getContext('2d');
      context.fillStyle = 'rgb(' + rand() + ',' + rand() + ',' + rand() + ')';
      context.fillRect(0, 0, 8, 8);

      return new Promise((resolve) => {
        canvas.toBlob((blob) => {
          const data = new DataTransfer();
          data.items.add(new File([blob], 'dropped-live.png', { type: 'image/png' }));

          zone.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer: data }));
          zone.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: data }));
          resolve(true);
        }, 'image/png');
      });
    })()
  `);

  check('a dropped file can be handed to the page', dropped === true);

  await waitFor(`!! document.querySelector('[data-testid="media-pending"]')`, 5000);
  check('it is on screen before the upload finishes', await eval_(`!! document.querySelector('[data-testid="media-pending"]')`));

  // Drawn from the file itself, not from a spinner: a blob: URL is the proof
  // that what is on screen is the actual image somebody dropped.
  const pendingSrc = await eval_(`document.querySelector('[data-testid="media-pending"] img')?.getAttribute('src') ?? ''`);
  check('drawn from the browser’s own copy of it', pendingSrc.startsWith('blob:'), pendingSrc.slice(0, 24));
  await shot('05-live-preview');

  // And it goes away once the stored file takes its place — an object URL left
  // behind pins the whole file in memory for as long as the tab is open.
  await waitFor(`! document.querySelector('[data-testid="media-pending"]')`, 15000);
  check('the placeholder gives way to the stored file', await eval_(`! document.querySelector('[data-testid="media-pending"]')`));
  check('which is now in the folder', await eval_(`
    [...document.querySelectorAll('[data-testid="media-tile"]')].some(el => el.textContent.includes('dropped-live.png'))
  `));

  // Taken away again, because this driver really did add a file to the library.
  // Addressed through the component rather than through the tile's own button:
  // that one carries `wire:confirm`, and a browser dialog blocks a driver with
  // nothing to click it.
  await eval_(`
    (() => {
      const tile = [...document.querySelectorAll('[data-testid="media-tile"]')]
        .find(el => el.textContent.includes('dropped-live.png'));

      // Walked rather than selected: a wire:id attribute needs escaping to be a
      // valid CSS selector, and the escape has to survive being written inside a
      // template literal on the Node side, which is two levels at which a
      // backslash goes missing. (No backticks in here either, for the same
      // reason: this comment is inside that template literal.)
      let host = tile;
      while (host && ! host.hasAttribute('wire:id')) host = host.parentElement;

      return window.Livewire.find(host.getAttribute('wire:id'))
        .call('deleteOne', parseInt(tile.getAttribute('data-media')));
    })()
  `);
  await waitFor(`! [...document.querySelectorAll('[data-testid="media-tile"]')].some(el => el.textContent.includes('dropped-live.png'))`, 6000);
  check('and taken away again, so the next driver sees what this one did', await eval_(`
    ! [...document.querySelectorAll('[data-testid="media-tile"]')].some(el => el.textContent.includes('dropped-live.png'))
  `));

  // ── 6. Putting it back ───────────────────────────────────────────────────
  // Dropping onto the library row is the only way to get something back out of
  // a folder by dragging, so it is worth asserting on its own — and it leaves
  // the database as this driver found it. A driver that quietly rearranges the
  // preview data makes every driver that runs after it order-dependent, which
  // is how the picker driver came to fail on an empty library.
  await setView('grid');
  await waitFor(`!! document.querySelector('[data-testid="media-tile"]')`, 5000);

  // The name of what is about to be dragged out, so the check at the end can be
  // about *that file* rather than about arithmetic over a count that three
  // earlier steps have each moved.
  const moved = await eval_(`document.querySelector('[data-testid="media-tile"] p')?.textContent?.trim() ?? ''`);

  const returned = await dragOnto('[data-testid="media-tile"]', '[data-testid="media-folder-root"]');
  check('a file can be dragged back out to the library', returned);

  // Waited on before navigating away: the drag fires one Livewire call and the
  // breadcrumb fires another, and clicking straight through raced them — the
  // second re-rendered from state the first had not reached yet. That is what
  // made this step fail once in fifty rather than never.
  await waitFor(`! document.querySelector('[data-testid="media-tile"]')`, 6000);

  await eval_(`document.querySelector('[data-testid="media-breadcrumb"] button').click()`);

  // Either shape, because by this point the driver has toggled the view three
  // times and a file is a file whether it is drawn as a tile or as a row. The
  // first version of this counted tiles only, and reported a database it had
  // correctly restored as a database it had broken.
  const files = `[...document.querySelectorAll('[data-testid="media-tile"], [data-testid="media-row"]')]`;

  await waitFor(`${files}.length === ${before}`, 8000).catch(() => {});

  check('the library root has the file back', await eval_(`
    ${files}.some(el => el.textContent.includes(${JSON.stringify(moved)}))
  `), `moved=${moved}`);
  check('and holds what it started with', (await eval_(`${files}.length`)) === before);

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
