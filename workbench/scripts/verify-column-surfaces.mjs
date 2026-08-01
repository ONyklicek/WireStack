import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for the new display/edit column surfaces
 * (/previews/table-column-surfaces).
 *
 * What Pest cannot see, and this can:
 *  - the swatch's `style` as the browser actually parsed it — including the
 *    seeded row whose stored value is `red; background-image: url(…)`, which
 *    must produce no background at all rather than a smuggled declaration;
 *  - a CheckboxColumn cell committing through Livewire and surviving a fresh GET.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-column-surfaces.mjs
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-column-surfaces';

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'column-surfaces' });

const { check, finish } = checker();

try {
  await eval_(`
    window.rowFor = (title) => [...document.querySelectorAll('tbody tr')]
      .find((tr) => tr.textContent.includes(title));
    window.cellsOf = (title) => [...rowFor(title).querySelectorAll('td')];
    // The swatch is the only span carrying an inline background-color.
    window.swatchBg = (title) => {
      const el = [...rowFor(title).querySelectorAll('span')]
        .find((s) => s.style && s.style.backgroundColor);
      return el ? getComputedStyle(el).backgroundColor : null;
    };
    window.styleAttrs = (title) => cellsOf(title)
      .flatMap((td) => [...td.querySelectorAll('[style]')].map((e) => e.getAttribute('style')));
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && !!rowFor('Brand guidelines')`);
  check('preview renders with Alpine booted and the seeded rows present', booted);
  await shot('01-initial');

  // ── ColorColumn ───────────────────────────────────────────────────────
  const hexBg = await eval_(`swatchBg('Brand guidelines')`);
  check('hex color renders as a swatch the browser parsed', hexBg === 'rgb(99, 102, 241)', `computed=${hexBg}`);

  const keywordBg = await eval_(`swatchBg('Release checklist')`);
  check('css keyword renders as a swatch', keywordBg === 'rgb(102, 51, 153)', `computed=${keywordBg}`);

  const funcBg = await eval_(`swatchBg('Pricing sheet')`);
  check('rgb() color renders as a swatch', funcBg === 'rgb(16, 185, 129)', `computed=${funcBg}`);

  // The seeded value is `red; background-image: url(https://evil.test/x)`.
  const unsafeSwatch = await eval_(`swatchBg('Support macros')`);
  const unsafeStyles = await eval_(`styleAttrs('Support macros').join(' | ')`);
  check('a non-color value draws no swatch at all', unsafeSwatch === null, `swatch=${unsafeSwatch}`);
  check('the rejected value never reaches a style attribute', ! (unsafeStyles ?? '').includes('evil.test'), unsafeStyles || 'no inline styles');
  const requestedEvil = badResponses.some((r) => r.includes('evil.test'));
  check('the browser never requested the smuggled url', ! requestedEvil);

  // ── RatingColumn ──────────────────────────────────────────────────────
  const fullStars = await eval_(`cellsOf('Brand guidelines')[2].querySelectorAll('svg').length`);
  check('a rating renders one icon per position', fullStars === 5, `svgs=${fullStars}`);

  const halfClip = await eval_(`cellsOf('Release checklist')[2].querySelector('.overflow-hidden') !== null`);
  check('a .5 score clips a half star', halfClip);

  const noHalfOnWhole = await eval_(`cellsOf('Pricing sheet')[2].querySelector('.overflow-hidden') === null`);
  check('a whole score clips nothing', noHalfOnWhole);

  const ratingLabel = await eval_(`cellsOf('Brand guidelines')[2].querySelector('[role="img"]').getAttribute('aria-label')`);
  check('the star row is announced once, as a value', ratingLabel === '4 out of 5', `aria-label=${ratingLabel}`);

  // ── TagsColumn ────────────────────────────────────────────────────────
  const tagChips = await eval_(`cellsOf('Brand guidelines')[3].querySelectorAll('span.rounded-full').length`);
  check('tags render one chip each', tagChips === 2, `chips=${tagChips}`);

  // 'Release checklist' carries four tags against limitList(3).
  const overflowText = await eval_(`cellsOf('Release checklist')[3].textContent.trim().replace(/\\s+/g, ' ')`);
  check('tags beyond the limit collapse into a +N chip', overflowText.endsWith('+1'), `text=${overflowText}`);

  const emptyTags = await eval_(`cellsOf('Support macros')[3].textContent.trim()`);
  check('an empty tag list renders the empty cell text', emptyTags === '-', `text=${emptyTags}`);

  // ── CheckboxColumn ────────────────────────────────────────────────────
  await eval_(`
    window.box = () => rowFor('Brand guidelines').querySelector('[data-testid="table-editable-is_published"]');
    window.boxData = () => Alpine.$data(box());
    // Read the row back by its record key rather than by scanning forward from
    // the title: the cell markup sits well past it, and a distance-bounded match
    // silently returns null instead of failing.
    window.freshChecked = async () => {
      const key = box().getAttribute('data-record-key');
      const html = await fetch('${url}', { headers: { 'X-Requested-With': 'fetch' } }).then((r) => r.text());
      const re = new RegExp('wire:key="chk-' + key + '-is_published"[\\\\s\\\\S]{0,900}?data-server-value="([^"]*)"');
      const m = html.match(re);
      return m ? m[1] : null;
    };
    true;
  `);

  const wired = await eval_(`!!box() && box().getAttribute('data-record-key') !== ''`);
  check('the checkbox cell is wired to its record', wired);

  const before = await eval_(`boxData().value`);
  await eval_(`box().querySelector('input[type=checkbox]').click()`);
  await sleep(1800);
  const after = await eval_(`boxData().value`);
  check('clicking flips the checkbox optimistically', after === ! before, `${before} -> ${after}`);

  const persisted = await eval_(`(async () => await freshChecked())()`);
  const expected = after ? '1' : '0';
  check('the checkbox write persisted to the record', persisted === expected, `server=${persisted} expected=${expected}`);

  const cellError = await eval_(`boxData().error`);
  check('the commit raised no inline error', ! cellError, cellError || 'none');
  await shot('02-checkbox-committed');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
