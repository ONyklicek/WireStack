import { openPage, checker } from './lib/cdp.mjs';

/*
 * A field's <label for> follows the control each screen shows. Below the
 * mobile breakpoint ->touchOnMobile() puts a wheel in front of the picker and
 * ->nativeOnMobile() the browser's own control; the desktop control is then
 * display:none, and a label still aimed at it does nothing when tapped. Pest
 * sees the two labels; only a browser can say which one is on screen, that it
 * names a control that is on screen too, and that tapping it reaches that control.
 * Previews: /previews/field-time-wheel, /previews/field-native-on-mobile
 * (FieldPreview), /previews/table-native-filters (TablePreview).
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();
const errors = [];
const responses = [];
let shotDir;

// Every <label for> inside `scope`, as { for, shown, target, targetShown }.
// checkVisibility() walks the ancestors, so a control inside a display:none
// wrapper reads as hidden, which is the case the label must not point at.
const labels = (scope) => `[...document.querySelectorAll(${JSON.stringify(scope)} + ' label[for]')].map((l) => {
  const t = document.getElementById(l.htmlFor);
  return { for: l.htmlFor, shown: l.checkVisibility(), target: !! t, targetShown: !! t && t.checkVisibility() };
})`;

async function screen({ slug, width, mobile, scope, expect, before, tap }) {
  const name = `${slug}@${width}`;
  const p = await openPage({ url: `${base}/${slug}`, shotPrefix: 'label-target', width, height: 900, mobile });
  shotDir = p.shotDir;
  try {
    await p.waitFor(`document.querySelector(${JSON.stringify(scope)} + ' label[for]')`);
    if (before) await before(p);

    const found = await p.eval_(labels(scope));
    const shown = found.filter((l) => l.shown);

    for (const [id, suffix] of Object.entries(expect)) {
      const own = found.filter((l) => l.for === id || l.for === id + suffix);
      const visible = own.filter((l) => l.shown);
      check(`${name}: ${id} shows one label, for="${id + suffix}"`,
        visible.length === 1 && visible[0].for === id + suffix, JSON.stringify(own));
    }
    check(`${name}: every shown label names a control on screen`,
      shown.length > 0 && shown.every((l) => l.target && l.targetShown),
      JSON.stringify(shown.filter((l) => ! l.targetShown)));

    if (tap) await tap(p, name);
    await p.shot(name.replace(/[@/]/g, '-'));
  } finally {
    errors.push(...p.consoleErrors);
    responses.push(...p.badResponses);
    await p.close();
  }
}

// Clicking a label is what a tap does: the browser forwards it to the control.
const clickLabel = (p, id) => p.eval_(`(() => {
  const l = [...document.querySelectorAll('label[for="${id}"]')].find((x) => x.checkVisibility());
  if (! l) return false;
  l.click();
  return true;
})()`);

const idOf = (p, testid) => p.eval_(`document.querySelector('[data-testid="form-field-${testid}"] label[for]')?.htmlFor.replace(/-(wheel|native)$/, '')`);

// ── ->touchOnMobile(): the wheel below the breakpoint ──────────────────────
{
  const p = await openPage({ url: `${base}/field-time-wheel`, shotPrefix: 'label-target', width: 1400, height: 900 });
  await p.waitFor(`document.querySelector('[data-testid^="form-field-"] label[for]')`);
  var dueOn = await idOf(p, 'data.due_on') ?? await idOf(p, 'due_on');
  var opensAt = await idOf(p, 'data.opens_at') ?? await idOf(p, 'opens_at');
  errors.push(...p.consoleErrors);
  await p.close();
}
check('the wheel preview has the fields', !! dueOn && !! opensAt, `${dueOn} ${opensAt}`);

await screen({
  slug: 'field-time-wheel', width: 390, mobile: true, scope: '[data-testid^="form-field-"]',
  expect: { [dueOn]: '-wheel', [opensAt]: '-wheel' },
  tap: async (p, name) => {
    check(`${name}: tapping the label opens the wheel`, await clickLabel(p, `${dueOn}-wheel`)
      && await p.waitFor(`document.querySelector('[data-testid$="due_on-wheel-sheet"]')?.checkVisibility()`));
  },
});
await screen({
  slug: 'field-time-wheel', width: 1400, mobile: false, scope: '[data-testid^="form-field-"]',
  expect: { [dueOn]: '', [opensAt]: '' },
  tap: async (p, name) => {
    check(`${name}: tapping the label focuses the desktop control`, await clickLabel(p, dueOn)
      && await p.waitFor(`document.activeElement?.id === ${JSON.stringify(dueOn)}`));
  },
});

// ── ->nativeOnMobile(): the browser's control below the breakpoint ─────────
await screen({
  slug: 'field-native-on-mobile', width: 390, mobile: true, scope: '[data-testid^="form-field-"]',
  expect: { [dueOn]: '-native', [opensAt]: '-native' },
  tap: async (p, name) => {
    check(`${name}: tapping the label focuses the native control`, await clickLabel(p, `${dueOn}-native`)
      && await p.waitFor(`document.activeElement?.id === ${JSON.stringify(`${dueOn}-native`)}`));
  },
});
await screen({
  slug: 'field-native-on-mobile', width: 1400, mobile: false, scope: '[data-testid^="form-field-"]',
  expect: { [dueOn]: '', [opensAt]: '' },
});

// ── A table filter with ->nativeOnMobile() ─────────────────────────────────
const openFilters = async (p) => {
  await p.eval_(`document.querySelector('[data-testid="table-filters-trigger"]')?.click()`);
  await p.waitFor(`document.querySelector('label[for^="filter-role"]')?.parentElement.checkVisibility()`);
};
await screen({
  slug: 'table-native-filters', width: 390, mobile: true, scope: 'body', before: openFilters,
  expect: { 'filter-role': '-native' },
  tap: async (p, name) => {
    check(`${name}: tapping the label focuses the native select`, await clickLabel(p, 'filter-role-native')
      && await p.waitFor(`document.activeElement?.id === 'filter-role-native'`));
  },
});
await screen({
  slug: 'table-native-filters', width: 1400, mobile: false, scope: 'body', before: openFilters,
  expect: { 'filter-role': '' },
});

finish({ consoleErrors: errors, badResponses: responses, shotDir });
