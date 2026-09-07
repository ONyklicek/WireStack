import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * The collapsed menu — `wire-admin`'s rail, and the column it becomes when you
 * point at it.
 *
 * Pest sees this markup and can say nothing useful about it: every question the
 * rail raises is answered by the browser at a width Pest has no notion of.
 *
 *   - the row keeps a name. The label is `display: none` in the rail, and a
 *     hidden element carries no accessible name — so without an aria-label every
 *     entry announces itself as its badge, or as nothing.
 *   - a badge keeps its colour. A count that is red for being overdue must not
 *     turn brand-blue for being narrow: in a dot that small the hue is the only
 *     thing left saying anything.
 *   - pointing at a row shows what the width took away, and the *kind* of thing
 *     it shows depends on what there is to show. SAP Fiori's rule for a collapsed
 *     rail: a tooltip with the label on hover, subitems in a popover. Making both
 *     the same menu-sized card is what an earlier attempt got wrong.
 *   - brushing past it opens nothing. Reaching past the menu for the edge of the
 *     page must not throw a panel over what you were reaching for.
 *   - the panel survives the crossing to it. It is teleported to <body>, so the
 *     pointer moving from row to panel LEAVES the row's subtree, and a naive
 *     mouseleave closes the panel mid-reach.
 *   - a group folded shut is still there. Its heading is the control that would
 *     unfold it, and the rail hides headings.
 *   - and none of it flashes. The choice lives in localStorage, so a menu that
 *     read it from Alpine rendered the *other* answer first — and with
 *     `transition-[width]` that one wrong frame became a third of a second of the
 *     column sliding shut, on every load and every wire:navigate.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/invoices`, shotPrefix: 'admin-rail', width: 1300, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const SIDEBAR = '[data-testid="admin-sidebar"]';

// mouseenter does not bubble, and a real pointer fires it on every element it
// enters — so a synthetic one must be dispatched on the element the handler is
// ON, which for a menu row is the flyout wrapper around the link.
const fire = (selector, type) => eval_(`(() => {
  const el = document.querySelector(${JSON.stringify(selector)});
  if (! el) return false;
  el.dispatchEvent(new MouseEvent(${JSON.stringify(type)}, { bubbles: false }));
  return true;
})()`);

const hover = (selector) => fire(selector, 'mouseenter');
const unhover = (selector) => fire(selector, 'mouseleave');

// The two things a rail row can put on screen, which are deliberately not the
// same object: a word gets a tooltip, a list gets a menu.
const flyout = (key) => `[data-testid="admin-nav-flyout"][data-resource="${key}"]`;
const tip = (key) => `[data-testid="admin-nav-tip"][data-resource="${key}"]`;

// The wrapper around one top-level row — what a pointer enters, and where the
// hover handlers live.
const row = (key) => `[data-testid="admin-nav-row"]:has([data-resource="${key}"])`;

const visible = (selector) => `(() => {
  const el = document.querySelector(${JSON.stringify(selector)});
  return !! el && el.getBoundingClientRect().width > 0;
})()`;

// Shown AND settled. The panels slide in, so their position mid-transition is
// not the position they are being asserted about.
const settled = (selector) => `(() => {
  const el = document.querySelector(${JSON.stringify(selector)});
  return !! el && el.getBoundingClientRect().width > 0 && getComputedStyle(el).opacity === '1';
})()`;

const width = `document.querySelector('${SIDEBAR}').offsetWidth`;
const contentLeft = `document.querySelector('[data-testid="admin-content"]').getBoundingClientRect().left`;

/**
 * Is this the red the workbench's `badge(..., 'danger')` asked for, rather than
 * the brand blue a hard-coded fill would have produced?
 *
 * Read out and judged here rather than in the page: a regex written inside the
 * evaluated template literal loses its backslashes on the way over, so `\d`
 * arrives as a literal `d` with nothing to say it happened.
 */
const isRed = (color) => {
  const oklch = color.match(/oklch\(\s*[\d.]+\s+([\d.]+)\s+([\d.]+)/);

  if (oklch) return Number(oklch[1]) > 0.1 && Number(oklch[2]) < 60;

  const rgb = color.match(/\d+/g)?.map(Number) ?? [];

  return rgb.length >= 3 && rgb[0] > 180 && rgb[1] < 110 && rgb[2] < 110;
};

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('${SIDEBAR}')`);

  // ── 1. Collapse it ───────────────────────────────────────────────────────
  check('the menu starts wide', (await eval_(width)) > 200, `${await eval_(width)}px`);

  const contentWhenWide = await eval_(contentLeft);

  await eval_(`document.querySelector('[data-testid="admin-rail-toggle"]').click()`);
  await waitFor(`${width} < 70`, 4000);

  check('the handle narrows it to a rail', (await eval_(width)) < 70, `${await eval_(width)}px`);
  check('the page takes the room back', (await eval_(contentLeft)) < contentWhenWide - 150);
  check('the handle now offers to expand it', /xpand|ozbal/.test(await eval_(`document.querySelector('[data-testid="admin-rail-toggle"]').getAttribute('title') ?? ''`)));
  await shot('01-rail');

  // ── 2. The row keeps a name ──────────────────────────────────────────────
  check('the labels are gone', ! (await eval_(visible('[data-testid="admin-nav-label"]'))));
  check('every entry still has an accessible name', await eval_(`
    [...document.querySelectorAll('[data-testid="admin-nav-item"]')]
      .every(el => (el.getAttribute('aria-label') ?? '').trim().length > 0)
  `));
  check('the entry for this page is still marked', await eval_(visible('[data-testid="admin-nav-active-mark"]')));

  // ── 2b. The logo is on the same axis as everything below it ──────────────
  // It was not: the header centres the anchor, and centring a 32-pixel anchor
  // inside itself leaves it wherever the header put it — hard against the left
  // edge, 16 pixels off the column every icon below it sits on.
  const centreOf = (sel) => `(() => { const r = document.querySelector(${JSON.stringify(sel)}).getBoundingClientRect(); return Math.round(r.left + r.width / 2) })()`;

  const brandCentre = await eval_(centreOf('[data-testid="admin-brand-mark"] [data-rail-only]'));
  const iconCentre = await eval_(centreOf('[data-testid="admin-nav-item"] span.relative'));

  check('the brand sits on the same axis as the icons', Math.abs(brandCentre - iconCentre) <= 1, `brand ${brandCentre}px, icons ${iconCentre}px`);
  check('and it is the mark, not the wordmark squeezed into 64 pixels', await eval_(`
    (() => {
      const a = document.querySelector('[data-testid="admin-brand-mark"]');
      const [mark, wide] = a.children;
      return mark.getBoundingClientRect().width > 0 && wide.getBoundingClientRect().width === 0;
    })()
  `));

  // ── 3. The badge keeps its colour ────────────────────────────────────────
  const dot = `document.querySelector('[data-resource="invoices"] [data-testid="admin-nav-badge-dot"]')`;

  check('the badge becomes a dot on the icon', await eval_(`${dot} ? ${dot}.getBoundingClientRect().width > 0 : false`));

  const dotColor = await eval_(`${dot} ? getComputedStyle(${dot}).backgroundColor : ''`);

  check('and keeps the colour the entry declared', isRed(dotColor), dotColor || 'no dot');

  // The in-place submenu belongs to the wide menu. A stale reference to a store
  // getter that no longer existed left it rendering inside the 64-pixel column,
  // where `overflow-x-hidden` showed it as a stray vertical rule and a sliver of
  // a highlighted row — visible in a screenshot, invisible to every assertion.
  check('the in-place submenu is folded away', ! (await eval_(visible('[data-testid="admin-nav-child"]'))));

  // ── 4. A tooltip and a menu are different objects ────────────────────────
  // The rule SAP Fiori states for a collapsed rail: a tooltip with the label on
  // hover, subitems in a popover. An earlier attempt made them the same
  // menu-sized card, so pointing at an entry with no children answered "what is
  // this icon?" with a panel holding one word.
  await hover(row('media'));
  await waitFor(settled(tip('media')), 4000);

  check('an entry with no children gets a tooltip', await eval_(visible(tip('media'))));
  check('and it is tooltip-sized, not menu-sized', (await eval_(`
    document.querySelector('${tip('media')} > *').getBoundingClientRect().width
  `)) < 120, `${await eval_(`document.querySelector('${tip('media')} > *').getBoundingClientRect().width`)}px`);
  check('and says so to assistive tech', (await eval_(`document.querySelector('${tip('media')} [role="tooltip"], ${tip('media')}[role="tooltip"]') !== null || !! document.querySelector('${tip('media')} span[role="tooltip"]')`)));
  check('an entry with no children gets no menu', (await eval_(`document.querySelectorAll('[data-testid="admin-nav-flyout"][data-resource="media"]').length`)) === 0);
  await shot('02-tooltip');

  await unhover(row('media'));
  await waitFor(`! ${visible(tip('media'))}`, 3000);

  // ── 5. Subitems, in a popover ────────────────────────────────────────────
  await hover(row('invoices'));
  await waitFor(settled(flyout('invoices')), 4000);

  check('an entry with children gets a popover', await eval_(visible(flyout('invoices'))));
  check('it lists the children', (await eval_(`document.querySelectorAll('${flyout('invoices')} [data-testid="admin-nav-child"]').length`)) === 3);
  check('it names the parent above them', /Invoice/i.test(await eval_(`document.querySelector('${flyout('invoices')} [data-testid="admin-nav-flyout-label"]')?.textContent ?? ''`)));
  check('it stands clear of the rail', await eval_(`
    document.querySelector('${flyout('invoices')} > div').getBoundingClientRect().left
      >= document.querySelector('${SIDEBAR}').getBoundingClientRect().right
  `));
  check('the menu did not re-open itself to show it', (await eval_(width)) < 70, `${await eval_(width)}px`);
  await shot('03-popover');

  // ── 6. Brushing past it opens nothing ────────────────────────────────────
  await unhover(row('invoices'));
  await waitFor(`! ${visible(flyout('invoices'))}`, 3000);

  await hover(row('media'));
  await sleep(80);
  await unhover(row('media'));
  await sleep(600);

  check('a pointer merely crossing a row opens nothing', ! (await eval_(visible(tip('media')))));

  // ── 7. The crossing, and Escape ──────────────────────────────────────────
  // The panel is teleported to <body>: leaving the row leaves its subtree, so a
  // bare mouseleave→close shuts the panel the pointer is travelling towards.
  await hover(row('invoices'));
  await waitFor(settled(flyout('invoices')), 4000);

  await unhover(row('invoices'));
  await hover(flyout('invoices'));
  await sleep(400);

  check('the popover survives the pointer crossing into it', await eval_(visible(flyout('invoices'))));

  check('only one panel is ever open', (await eval_(`
    [...document.querySelectorAll('[data-testid="admin-nav-flyout"], [data-testid="admin-nav-tip"]')]
      .filter(el => el.getBoundingClientRect().width > 0).length
  `)) === 1);

  // A child link navigates, and closes the popover behind it.
  const href = await eval_(`document.querySelectorAll('${flyout('invoices')} [data-testid="admin-nav-child"]')[1]?.getAttribute('href')`);

  check('its children are real links', typeof href === 'string' && href.length > 1, href ?? 'none');

  await eval_(`document.querySelectorAll('${flyout('invoices')} [data-testid="admin-nav-child"]')[1].click()`);
  await waitFor(`location.search.includes('overdue')`, 6000);

  check('following one leaves the menu collapsed', (await eval_(width)) < 70, `${await eval_(width)}px`);
  check('and closes the popover behind it', ! (await eval_(visible(flyout('invoices')))));

  // Escape dismisses, and means it: focus goes back to a row that opens on
  // focus, so a plain close would reopen a fifth of a second later.
  await hover(row('invoices'));
  await waitFor(settled(flyout('invoices')), 4000);

  await eval_(`document.querySelector('${flyout('invoices')}').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await waitFor(`! ${visible(flyout('invoices'))}`, 3000);
  await sleep(600);

  check('Escape dismisses it and it stays dismissed', ! (await eval_(visible(flyout('invoices')))));

  await unhover(row('invoices'));
  await sleep(400);

  // ── 8. A group folded shut is not lost ───────────────────────────────────
  // The heading that would unfold it is hidden in the rail, so a group whose
  // stored state was "closed" used to take its entries out of the menu entirely.
  await eval_(`Object.keys(localStorage).filter(k => k.startsWith('wire-admin.nav.')).forEach(k => localStorage.setItem(k, '0'))`);

  // ── 9. No flash on the way in ────────────────────────────────────────────
  await page('Page.addScriptToEvaluateOnNewDocument', { source: `
    window.__railWidths = [];
    window.__brandForms = [];
    const tick = () => {
      const el = document.querySelector('[data-testid="admin-sidebar"]');
      const brand = document.querySelector('[data-testid="admin-brand-mark"]');
      if (el) window.__railWidths.push(el.offsetWidth);
      if (brand) window.__brandForms.push([...brand.children].map(c => Math.round(c.getBoundingClientRect().width)).join('/'));
      if (window.__railWidths.length < 40) requestAnimationFrame(tick);
    };
    document.addEventListener('DOMContentLoaded', tick);
  ` });

  await eval_(`location.reload()`);
  await waitFor(`window.__railWidths?.length >= 20`, 8000);

  const widths = [...new Set(await eval_(`window.__railWidths`))];

  check('the rail is its own width from the first frame', widths.length === 1 && widths[0] < 70, widths.join(' → '));

  const brands = [...new Set(await eval_(`window.__brandForms`))];

  // The corner the file's own comment calls unmissable. While this was `x-show`
  // the first frame drew the wordmark clipped to 31 pixels and swapped it for
  // the square one frame later.
  check('and the logo is the right one from the first frame too', brands.length === 1 && brands[0] === '32/0', brands.join(' → '));
  check('and the document said so before Alpine did', await eval_(`document.documentElement.getAttribute('data-rail') === 'true'`));

  check('every group still shows its entries with every group folded shut', await eval_(`
    [...document.querySelectorAll('[data-testid="admin-nav-group"]')]
      .every(g => g.querySelectorAll('[data-testid="admin-nav-item"]').length === 0
                  || [...g.querySelectorAll('[data-testid="admin-nav-item"]')].some(i => i.getBoundingClientRect().width > 0))
  `));
  check('a divider stands in for the hidden headings', (await eval_(`
    [...document.querySelectorAll('[data-testid="admin-nav-rail-divider"]')].filter(d => d.getBoundingClientRect().width > 0).length
  `)) > 0);
  await shot('03-folded-groups');

  // ── 10. The shortcut ─────────────────────────────────────────────────────
  const chord = (target) => eval_(`(() => {
    const el = ${target};
    el.focus?.();
    (el.ownerDocument ? el : document.body).dispatchEvent(new KeyboardEvent('keydown', {
      key: 'b', ctrlKey: true, bubbles: true, cancelable: true,
    }));
    return true;
  })()`);

  await chord(`document.body`);
  await waitFor(`${width} > 200`, 4000);

  check('the keyboard toggles the menu', (await eval_(width)) > 200, `${await eval_(width)}px`);

  // In a rich-text editor the same chord is bold, so it must not reach here.
  await chord(`document.querySelector('[data-testid="table-search"]')`);
  await sleep(400);

  check('and yields to a text field', (await eval_(width)) > 200, `${await eval_(width)}px`);

  // ── 11. Expanded, hover does nothing ─────────────────────────────────────
  await hover(row('media'));
  await hover(row('invoices'));
  await sleep(600);

  check('an expanded menu opens no panels at all', ! (await eval_(visible(tip('media')))) && ! (await eval_(visible(flyout('invoices')))));
  check('the labels are there without pointing', await eval_(visible('[data-testid="admin-nav-label"]')));
  await shot('04-expanded');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
