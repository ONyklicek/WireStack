import { openPage, checker } from './lib/cdp.mjs';

/*
 * A block pasted from a spreadsheet into the editable table
 * (/previews/table-editable-fill), and rows copied out of it.
 *
 * Only a browser can say this: a paste of tab-separated text into one editable
 * cell lands down and to the right — row by row, over the fillable columns — in
 * ONE request, survives a reload because the server wrote it, skips the column
 * that opted out, and leaves a single value to the input as typing does. And
 * Ctrl/⌘+C on a focused row puts that row on the clipboard as a block.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-fill`;
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({ url, shotPrefix: 'fill-paste', width: 1300, height: 900 });
const { check, finish } = checker();

const ready = `document.querySelectorAll('tbody tr[data-row-key] td[data-column=role] select').length > 2`;
const roles = `[...document.querySelectorAll('tbody tr[data-row-key] td[data-column=role] select')].slice(0, 3).map((s) => s.value)`;
const actives = `[...document.querySelectorAll('tbody tr[data-row-key] td[data-column=is_active] input[type=checkbox], tbody tr[data-row-key] td[data-column=is_active] [role=switch]')].slice(0, 3).map((t) => t.checked ?? t.getAttribute('aria-checked') === 'true')`;
const paste = (selector, text) => eval_(`(() => {
  const target = document.querySelector(${JSON.stringify(selector)});
  const data = new DataTransfer();
  data.setData('text/plain', ${JSON.stringify(text)});
  const event = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true });
  target.dispatchEvent(event);
  return event.defaultPrevented;
})()`);
const reload = async () => {
  await eval_('window.__beforeReload = true');
  await page('Page.reload');
  await waitFor(`! window.__beforeReload && ${ready}`);
};

try {
  await waitFor(ready);
  await eval_(`(() => { window.__fills = 0; Livewire.interceptRequest?.(({ request }) => {}); })()`);
  const beforeRoles = await eval_(roles);
  const beforeActive = await eval_(actives);

  // Count the fill requests the paste makes.
  await eval_(`(() => {
    const original = window.fetch;
    window.fetch = (...args) => { if (String(args[1]?.body ?? '').includes('fillTableCells')) window.__fills++; return original(...args); };
  })()`);

  const first = 'tbody tr[data-row-key] td[data-column=role] select';
  const block = 'viewer\tno\neditor\tyes\nadmin\tno\n';
  check('a block pasted into a cell is taken by the table', await paste(first, block));
  await waitFor(`JSON.stringify(${roles}) === JSON.stringify(['viewer', 'editor', 'admin'])`).catch(() => {});
  check('it lands down the rows and across the fillable columns', JSON.stringify(await eval_(roles)) === JSON.stringify(['viewer', 'editor', 'admin']), JSON.stringify(await eval_(roles)));
  await waitFor('window.__fills >= 1').catch(() => {});
  await new Promise((r) => setTimeout(r, 300));
  check('in one request', (await eval_('window.__fills')) === 1, String(await eval_('window.__fills')));
  await shot('01-pasted');

  await reload();
  check('the server wrote it — it is there after a reload', JSON.stringify(await eval_(roles)) === JSON.stringify(['viewer', 'editor', 'admin']), JSON.stringify(await eval_(roles)));
  check('the toggles took yes / no as booleans', JSON.stringify(await eval_(actives)) === JSON.stringify([false, true, false]), JSON.stringify(await eval_(actives)));

  check('one value is left to the cell, as typing is', ! (await paste(first, 'editor')));

  // Copy: the selected rows as a block. Only a table the keyboard can drive
  // row by row (selectable, or record actions) owns Ctrl/⌘+C on a row, so this
  // half runs on the selectable variant of the same table.
  const back = await eval_('location.href');
  await page('Page.navigate', { url: url.replace('table-editable-fill', 'table-editable-fill-selectable') });
  await waitFor(`!! document.querySelector('[data-selection-root]')?._x_dataStack && ${ready}`);
  await eval_(`(() => {
    const boxes = document.querySelectorAll('tbody tr[data-row-key] [data-testid=table-row-select]');
    boxes[0].click(); boxes[1].click();
  })()`);
  await waitFor(`document.querySelectorAll('tbody tr[data-row-key][aria-selected=true]').length === 2`).catch(() => {});
  await eval_(`(() => {
    window.__copied = null;
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: (text) => { window.__copied = text; return Promise.resolve(); } } });
    const row = document.querySelector('tbody tr[data-row-key]');
    row.focus();
    row.dispatchEvent(new KeyboardEvent('keydown', { key: 'c', metaKey: true, ctrlKey: true, bubbles: true, cancelable: true }));
  })()`);
  await waitFor('window.__copied !== null').catch(() => {});
  const copied = await eval_('window.__copied');
  check('Ctrl/⌘+C copies the selected rows as tab-separated lines', typeof copied === 'string' && copied.split('\n').length === 2 && copied.includes('\t') && copied.includes('viewer'), JSON.stringify(copied));
  await page('Page.navigate', { url: back });
  await waitFor(ready);

  // Put the seed back the way the other drivers expect it.
  const restore = beforeRoles.map((role, i) => `${role}\t${beforeActive[i] ? 'yes' : 'no'}`).join('\n');
  await paste(first, `${restore}\n`);
  await waitFor(`JSON.stringify(${roles}) === ${JSON.stringify(JSON.stringify(beforeRoles))}`).catch(() => {});
  await new Promise((r) => setTimeout(r, 500));
  await reload();
  check('and the seed is put back', JSON.stringify(await eval_(roles)) === JSON.stringify(beforeRoles), JSON.stringify(await eval_(roles)));
} catch (error) {
  check('driver ran to completion', false, error.message);
} finally {
  finish({ consoleErrors, badResponses: badResponses.filter((r) => ! r.includes('favicon')), shotDir });
  await close();
}
