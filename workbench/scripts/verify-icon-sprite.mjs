import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for the icon sprite (`wire-core.icons.sprite`).
 *
 * Pest reads the markup and can say every `<use>` names a `<symbol>` that is in
 * the same string. What it cannot say is the part the switch promises: that the
 * page LOOKS the same. So this loads each page twice — sprite off, sprite on, via
 * the workbench's `wire_sprite` cookie — and compares the screenshots pixel for
 * pixel. Then it does what the browser half exists for: it deletes every symbol
 * the server put into the markup and checks the icons still paint from the
 * sprite `wire-core-icons.js` built at the end of <body>.
 *
 * And the two pieces Livewire sends on their own: a cell save answered with one
 * row (`rowPartials()`), and a sort answered with the table's island. After each,
 * every `<use>` on the page must still resolve.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-icon-sprite.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const pages = [
  'table-actions-group',
  'table-selection',
  'table-inactive-rows',
  'table-column-surfaces',
  'table-editable-row-partials',
  'forms-overview',
];

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url: 'about:blank', shotPrefix: 'icon-sprite', width: 1400, height: 1800, settle: 0,
});

const { check, finish } = checker();

/**
 * A screenshot of the page at rest. Headless Chrome may first paint tiles at a
 * lower resolution after heavy script work (the canvas comparison below is
 * some), which differs in a few antialiased pixels from the same page a moment
 * later — so take shots until two in a row agree.
 */
const capture = async () => {
  let last = (await page('Page.captureScreenshot', { format: 'png' })).data;
  for (let i = 0; i < 6; i++) {
    await sleep(250);
    const next = (await page('Page.captureScreenshot', { format: 'png' })).data;
    if (next === last) return next;
    last = next;
  }
  return last;
};

/** Differing pixels between two PNG screenshots, counted in the page. */
const diff = (a, b) => eval_(`(async () => {
  const load = (src) => new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = 'data:image/png;base64,' + src;
  });
  const [x, y] = await Promise.all([load(${JSON.stringify(a)}), load(${JSON.stringify(b)})]);
  if (x.width !== y.width || x.height !== y.height) return -1;
  const pixels = (img) => {
    const c = document.createElement('canvas');
    c.width = img.width; c.height = img.height;
    const ctx = c.getContext('2d');
    ctx.drawImage(img, 0, 0);
    return ctx.getImageData(0, 0, img.width, img.height).data;
  };
  const p = pixels(x), q = pixels(y);
  let n = 0, x0 = 1e9, y0 = 1e9, x1 = -1, y1 = -1;
  for (let i = 0; i < p.length; i += 4) {
    if (p[i] !== q[i] || p[i + 1] !== q[i + 1] || p[i + 2] !== q[i + 2] || p[i + 3] !== q[i + 3]) {
      n++;
      const px = (i / 4) % x.width, py = Math.floor(i / 4 / x.width);
      x0 = Math.min(x0, px); y0 = Math.min(y0, py); x1 = Math.max(x1, px); y1 = Math.max(y1, py);
    }
  }
  window.__lastDiffBox = n ? [x0, y0, x1, y1] : null;
  return n;
})()`);

const unresolved = () => eval_(`[...document.querySelectorAll('use[href^="#wi-"]')]
  .filter((u) => ! document.getElementById(u.getAttribute('href').slice(1))).length`);

const uses = () => eval_(`document.querySelectorAll('use[href^="#wi-"]').length`);

const visit = async (slug) => {
  await page('Page.navigate', { url: `${origin}/previews/${slug}` });
  await waitFor(`typeof window.Alpine !== 'undefined' && document.readyState === 'complete'`, { timeout: 15000 });
  // Fonts, Alpine x-cloak and the first transitions — a screenshot taken in
  // the middle of any of them differs for reasons that are not the sprite.
  await sleep(1500);
  await eval_(`document.activeElement?.blur(); window.scrollTo(0, 0); true`);
};

const sprite = async (on) => {
  if (on) {
    await page('Network.setCookie', { name: 'wire_sprite', value: '1', url: origin });
  } else {
    await page('Network.deleteCookies', { name: 'wire_sprite', url: origin });
  }
};

try {
  for (const slug of pages) {
    await sprite(false);
    await visit(slug);
    const inlineUses = await uses();
    const before = await capture();

    // The control: the same page, the same mode, loaded again.
    await visit(slug);
    const noise = await diff(before, await capture());
    const noiseBox = await eval_('JSON.stringify(window.__lastDiffBox)');
    console.log(`      ${slug} · control (sprite off twice): ${noise} px ${noiseBox}`);

    await sprite(true);
    await visit(slug);
    const count = await uses();
    const after = await capture();
    // A second load with the sprite on: the checkbox column of some fixtures
    // differs between two loads in either mode (the control above shows it), so
    // the claim checked is "some load with the sprite matches some load without".
    await visit(slug);
    const after2 = await capture();

    check(`${slug} · sprite off draws no <use>`, inlineUses === 0, `uses=${inlineUses}`);
    check(`${slug} · sprite on draws icons as <use>`, count > 0, `uses=${count}`);
    check(`${slug} · every <use> resolves`, (await unresolved()) === 0);

    const control = await (async () => { await sprite(false); await visit(slug); const c = await capture(); await sprite(true); await visit(slug); return c; })();
    const pairs = [[before, after], [before, after2], [control, after], [control, after2]];
    let changed = Infinity;
    for (const [x, y] of pairs) {
      changed = Math.min(changed, await diff(x, y));
      if (changed === 0) break;
    }
    const changedBox = await eval_('JSON.stringify(window.__lastDiffBox)');
    check(`${slug} · looks the same pixel for pixel`, changed === 0, `differing pixels=${changed} box=${changedBox}`);
    if (changed !== 0) await shot(`${slug}-sprite-on`);

    // What the browser half is for: the server's symbols gone, the icons stay.
    //
    // Asked of the geometry, not the pixels: Chrome re-rasterises a repainted
    // icon a few antialiased pixels differently even with no sprite at all —
    // emptying and refilling every inline <svg> on a sprite-off table page
    // changes pixels too — so a pixel diff here measures the repaint.
    const boxes = `JSON.stringify([...document.querySelectorAll('use[href^="#wi-"]')]
      .filter((u) => u.ownerSVGElement.getBoundingClientRect().width > 0)
      .map((u) => { const b = u.getBBox(); return [Math.round(b.x * 10), Math.round(b.y * 10), Math.round(b.width * 10), Math.round(b.height * 10)].join(','); }))`;
    const drawn = await eval_(boxes);
    const moved = await eval_(`(() => {
      const sprite = document.getElementById('wire-icon-sprite');
      const inMarkup = [...document.querySelectorAll('symbol[id^="wi-"]')].filter((s) => s.parentNode !== sprite);
      inMarkup.forEach((s) => s.remove());
      return JSON.stringify({ sprite: !! sprite, kept: sprite ? sprite.children.length : 0, removed: inMarkup.length });
    })()`);
    const m = JSON.parse(moved);
    check(`${slug} · the page keeps its own sprite at the end of <body>`, m.sprite && m.kept > 0 && m.kept >= m.removed, moved);

    await sleep(500);
    check(`${slug} · with the markup's symbols removed, every <use> resolves`, (await unresolved()) === 0);
    const redrawn = await eval_(boxes);
    check(`${slug} · …and draws the same shape it did`, redrawn === drawn && JSON.parse(drawn).every((b) => ! b.endsWith(',0,0')),
      `${JSON.parse(drawn).length} visible icons`);
  }

  // ── A row partial: the browser morphs one <tr> in on its own. ──────────────
  await sprite(true);
  await visit('table-editable-row-partials');

  const saved = await eval_(`(async () => {
    // The e-mail cell: the one text cell here, and a real address because the
    // workbench user mails a verification to whatever is written (see
    // verify-cell-island). Unique per run — the column is unique.
    const address = 'sprite-' + Date.now() + '@example.test';
    const cell = [...document.querySelectorAll('[data-record-key][data-column-name="email"]')]
      .find((c) => typeof (window.Alpine.$data(c) || {}).commit === 'function'
        && ! c.closest('tr')?.querySelector('symbol[id^="wi-"]'));
    if (! cell) return JSON.stringify({ found: false });
    const row = cell.closest('tr');
    const before = row.querySelectorAll('symbol[id^="wi-"]').length;
    await window.Alpine.$data(cell).commit(address);
    await new Promise((r) => setTimeout(r, 1500));
    const now = document.querySelector('tr[data-row-key="' + row.dataset.rowKey + '"]');
    return JSON.stringify({
      found: true,
      saved: window.Alpine.$data(cell).serverValue === address,
      before,
      after: now ? now.querySelectorAll('symbol[id^="wi-"]').length : -1,
    });
  })()`);
  const s = JSON.parse(saved);
  // The full render put the symbols in the page's first icon, not in this row;
  // the row that comes back alone has to bring its own.
  check('row partial · the save landed', s.found && s.saved, saved);
  check('row partial · the row came back carrying the symbols it references', s.before === 0 && s.after > 0, saved);
  check('row partial · every <use> on the page still resolves', (await unresolved()) === 0);

  // ── An island: a sort re-renders the table's data region. ──────────────────
  await visit('table-actions-group');
  const sorted = await eval_(`(async () => {
    const header = document.querySelector('[data-testid^="table-sort-"]');
    if (! header) return false;
    header.click();
    await new Promise((r) => setTimeout(r, 1500));
    return true;
  })()`);
  check('island · a sort re-rendered the data region', sorted === true);
  check('island · every <use> on the page still resolves', (await unresolved()) === 0);
  await shot('island-after-sort');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  await sprite(false).catch(() => {});
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
