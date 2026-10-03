import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver verifying that a crowded table toolbar wraps instead of squeezing
 * (/previews/table-header-actions-many: search, filters, six labelled header
 * actions and the column menu).
 *
 * The toolbar used to be one line that could not wrap: between the phone and a
 * wide desktop the search box shrank to its icon, every label broke over two
 * lines and the last buttons were clipped at the card's edge. Only a browser
 * measuring the boxes sees that — the markup was the same at every width.
 *
 * Asserts, at each width: no toolbar control past the toolbar's right edge, no
 * header-action label on two lines, and from `sm` up a search box at least 12rem
 * wide. At 1440px the toolbar still fits on one line, as it always did.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-toolbar-wrap.mjs
 *
 * Exit 0 = all checks passed; 1 = a check failed.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-header-actions-many`;

const { check, finish } = checker();
const consoleErrors = [];
const badResponses = [];
let lastShotDir;

const measure = () => {
  const toolbar = document.querySelector('[data-wire="table-toolbar"]');
  const edge = toolbar.getBoundingClientRect().right;
  const visible = [...toolbar.querySelectorAll('button, a, input')].filter((el) => el.offsetParent !== null);
  const label = (el) => (el.textContent || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ');

  return JSON.stringify({
    search: Math.round(document.querySelector('[data-testid="table-search"]').getBoundingClientRect().width),
    height: Math.round(toolbar.getBoundingClientRect().height),
    clipped: visible.filter((el) => el.getBoundingClientRect().right > edge + 1).map(label),
    // A one-line button is ~32px tall; a label broken over two lines is ~52px.
    twoLine: [...toolbar.querySelectorAll('[data-testid^="header-action-"]')]
      .filter((el) => el.offsetParent !== null && el.getBoundingClientRect().height > 44)
      .map(label),
  });
};

for (const width of [390, 850, 1024, 1440]) {
  const page = await openPage({ url, shotPrefix: 'toolbar-wrap', width, height: 700 });
  lastShotDir = page.shotDir;

  try {
    await page.waitFor(`typeof Alpine !== 'undefined' && !! document.querySelector('[data-wire="table-toolbar"]')`, 8000);
    const m = JSON.parse(await page.eval_(`(${measure.toString()})()`));
    await page.shot(`toolbar-${width}`);

    check(`${width}px: nothing clipped at the toolbar's edge`, m.clipped.length === 0, m.clipped.join(', ') || 'none');
    check(`${width}px: every header-action label on one line`, m.twoLine.length === 0, m.twoLine.join(', ') || 'none');

    if (width >= 640) {
      check(`${width}px: the search box is wide enough to type into`, m.search >= 192, `${m.search}px`);
    }

    if (width === 1440) {
      check('1440px: the toolbar still fits on one line', m.height < 80, `${m.height}px tall`);
    }
  } finally {
    consoleErrors.push(...page.consoleErrors);
    badResponses.push(...page.badResponses);
    await page.close();
  }
}

finish({ consoleErrors, badResponses, shotDir: lastShotDir });
