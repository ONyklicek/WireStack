import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for TrashedFilter (/previews/table-trashed-filter).
 *
 * The filter constrains no column — it switches which global scope applies — so
 * what matters is which rows come back through a real Livewire roundtrip, not
 * what the panel markup says. Four live documents are seeded, plus two
 * soft-deleted ones.
 *
 * It also stands in for the whole panel surface: TrashedFilter extended Filter
 * directly at first, and the select panel view calls isSearchable() on whatever
 * it is handed — a 500 that every unit test missed, because they only asked for
 * the view's *name*.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-trashed-filter.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-trashed-filter`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'trashed-filter' });

const { check, finish } = checker();

const LIVE = 4;
const ARCHIVED = 2;

try {
  await eval_(`
    window.rowCount = () => document.querySelectorAll('tbody tr').length;
    window.bodyText = () => document.querySelector('tbody').textContent;

    // The filter lives behind the panel trigger, and renders as the shared
    // combobox rather than a native <select> — so drive it the way a user does.
    window.openPanel = () => {
      const trigger = [...document.querySelectorAll('button')]
        .find((b) => /Filter/i.test(b.innerText) && b.getAttribute('x-ref') === 'trigger')
        ?? [...document.querySelectorAll('button')].find((b) => /Filter/i.test(b.innerText));
      trigger?.click();
    };
    window.panel = () => [...document.querySelectorAll('[x-ref=\"panel\"]')]
      .find((p) => getComputedStyle(p).display !== 'none');
    // The combobox's own trigger, inside the already-open filter panel.
    window.openCombobox = () => {
      const el = comboRoot();
      el?.querySelector('[x-ref=\"trigger\"], button')?.click();
    };
    window.comboRoot = () => [...document.querySelectorAll('[x-data]')]
      .find((e) => (e.getAttribute('x-data') || '').includes('tableState.filters.trashed'));
    // Options are teleported out of the panel when open, so read them from the
    // whole document rather than from the panel subtree.
    window.optionLabels = () => [...document.querySelectorAll('[role=\"option\"]')]
      .map((e) => e.textContent.trim()).filter(Boolean);
    // Write straight to the entangled filter state: the option list is teleported
    // and virtualised, so clicking it is brittle in a way the *filter* is not
    // what we are testing here.
    window.pick = (value) => {
      const el = [...document.querySelectorAll('[x-data]')]
        .find((e) => (e.getAttribute('x-data') || '').includes('tableState.filters.trashed'));
      const data = Alpine.$data(el);
      data.selected = value;
      return data.selected;
    };
    true;
  `);

  await eval_(`openPanel()`);
  await sleep(900);

  const booted = await eval_(`typeof Alpine !== 'undefined' && rowCount() > 0`);
  check('preview renders with Alpine booted and rows present', booted);
  await shot('01-initial');

  // ── The panel renders at all ──────────────────────────────────────────
  const panelOpen = await eval_(`!! panel()`);
  check('the filter renders through the shared select panel', panelOpen);

  const placeholder = await eval_(`panel().textContent.includes('Without deleted')`);
  check('"without deleted" is the placeholder, not an option', placeholder);

  await eval_(`openCombobox()`);
  await sleep(700);
  const labels = await eval_(`optionLabels().filter((l) => l !== 'No results found').join('|')`);
  // The combobox renders the placeholder as its first, clearing option — so the
  // two scopes plus that, and nothing else.
  check('the open combobox offers the two scopes plus the clearing placeholder',
    labels === 'Without deleted|With deleted|Only deleted', labels || '(no options rendered)');
  await shot('01b-options-open');

  // ── Cleared: live records only ────────────────────────────────────────
  const initialRows = await eval_(`rowCount()`);
  check('a cleared filter shows live records only', initialRows === LIVE, `rows=${initialRows}`);

  const noArchivedInitially = await eval_(`! bodyText().includes('Legacy onboarding')`);
  check('a soft-deleted record is absent while the filter is cleared', noArchivedInitially);

  // ── only → onlyTrashed() ──────────────────────────────────────────────
  await eval_(`pick('only')`);
  await sleep(1600);
  const onlyRows = await eval_(`rowCount()`);
  const onlyText = await eval_(`bodyText()`);
  check('"only" returns just the soft-deleted records', onlyRows === ARCHIVED, `rows=${onlyRows}`);
  check('"only" shows an archived record and hides a live one',
    onlyText.includes('Legacy onboarding') && ! onlyText.includes('Brand guidelines'));
  await shot('02-only-trashed');

  // ── with → withTrashed() ──────────────────────────────────────────────
  await eval_(`pick('with')`);
  await sleep(1600);
  const withRows = await eval_(`rowCount()`);
  const withText = await eval_(`bodyText()`);
  check('"with" returns live and deleted together', withRows === LIVE + ARCHIVED, `rows=${withRows}`);
  check('"with" shows both a live and an archived record',
    withText.includes('Brand guidelines') && withText.includes('Legacy onboarding'));
  await shot('03-with-trashed');

  // ── Indicator chip names the active scope ─────────────────────────────
  const chipText = await eval_(`document.body.textContent.includes('Records: With deleted')`);
  check('the indicator chip names the active scope', chipText);

  // ── Clearing restores the default scope ───────────────────────────────
  await eval_(`pick('')`);
  await sleep(1600);
  const clearedRows = await eval_(`rowCount()`);
  check('clearing the filter restores live records only', clearedRows === LIVE, `rows=${clearedRows}`);
  await shot('04-cleared');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
