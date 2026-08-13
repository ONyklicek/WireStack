import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for the Builder field (/previews/forms-builder).
 *
 * What makes a builder a builder is the picker: adding an item means choosing
 * which block to add, and the item is then edited with *that* block's schema.
 * So the checks are: each seeded item renders its own block's fields under
 * `<path>.<index>.data`, the picker lists every declared block, choosing one
 * appends an item of that type through the Livewire endpoint, and the shared
 * repeater remove/collapse still work.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-builder.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/forms-builder`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'builder' });

const { check, finish } = checker();

try {
  await eval_(`
    window.items = () => [...document.querySelectorAll('[wire\\\\:key^="builder-content-"]')];
    window.headerText = (i) => items()[i].querySelector('span').textContent.trim();
    window.fieldPaths = (i) => [...items()[i].querySelectorAll('input, textarea')]
      .map((e) => e.getAttribute('wire:model') ?? e.getAttribute('wire:model.live') ?? '')
      .filter(Boolean);
    window.addTrigger = () => document.querySelector('[data-testid="form-builder-content-add"]');
    window.pickerEntry = (block) => document.querySelector('[data-testid="form-builder-content-add-' + block + '"]');
    window.pickerEntries = () => [...document.querySelectorAll('[data-testid^="form-builder-content-add-"]')]
      .map((b) => b.textContent.trim());
    window.removeBtn = (i) => document.querySelector('[data-testid="form-builder-content-remove-' + i + '"]');
    true;
  `);

  const booted = await eval_(`typeof Alpine !== 'undefined' && items().length > 0`);
  check('the builder renders its seeded items', booted);
  await shot('01-initial');

  const itemCount = await eval_(`items().length`);
  check('one item per stored entry', itemCount === 2, `items=${itemCount}`);

  // ── Each item is edited with its own block's schema ───────────────────
  const firstFields = await eval_(`fieldPaths(0).join('|')`);
  check('the heading item edits the heading block only',
    firstFields === 'content.0.data.text', firstFields);

  const secondFields = await eval_(`fieldPaths(1).join('|')`);
  check('the paragraph item edits the paragraph block only',
    secondFields === 'content.1.data.body', secondFields);

  const firstHeader = await eval_(`headerText(0)`);
  check('an item is headed by its block label', firstHeader.includes('Heading'), `header=${firstHeader}`);

  // ── The picker is what makes it a builder ─────────────────────────────
  await eval_(`addTrigger().click()`);
  await sleep(600);
  const entries = await eval_(`pickerEntries().join('|')`);
  check('the picker lists every declared block',
    entries === 'Heading|Paragraph|Callout', entries || '(picker did not open)');
  await shot('02-picker-open');

  // ── Choosing a block appends an item of that type ─────────────────────
  await eval_(`pickerEntry('callout').click()`);
  await sleep(1700);
  const afterAdd = await eval_(`items().length`);
  check('choosing a block appends an item', afterAdd === 3, `items=${afterAdd}`);

  const addedFields = await eval_(`fieldPaths(2).join('|')`);
  check('the appended item is edited with the chosen block’s schema',
    addedFields === 'content.2.data.title|content.2.data.body', addedFields);

  const addedHeader = await eval_(`headerText(2)`);
  check('the appended item is headed by the block it chose', addedHeader.includes('Callout'), `header=${addedHeader}`);
  await shot('03-callout-added');

  // ── Typing into a block field stays in that item ──────────────────────
  await eval_(`(() => {
    const input = items()[2].querySelector('input');
    input.value = 'Driver callout';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  })()`);
  await sleep(1400);
  const typed = await eval_(`items()[2].querySelector('input').value`);
  const untouched = await eval_(`items()[0].querySelector('input').value`);
  check('typing reaches the item it was typed into', typed === 'Driver callout', `value=${typed}`);
  check('and leaves the other items alone', untouched === 'Release notes', `item0=${untouched}`);

  // ── Remove runs through the shared repeater endpoint ──────────────────
  await eval_(`removeBtn(0).click()`);
  await sleep(1700);
  const afterRemove = await eval_(`items().length`);
  check('an item can be removed', afterRemove === 2, `items=${afterRemove}`);

  const shiftedFields = await eval_(`fieldPaths(0).join('|')`);
  check('removing an item shifts the rest up, re-binding their paths',
    shiftedFields === 'content.0.data.body', shiftedFields);
  await shot('04-after-remove');

  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
