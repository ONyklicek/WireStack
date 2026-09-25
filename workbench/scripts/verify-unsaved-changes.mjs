import { openPage, checker } from './lib/cdp.mjs';

/*
 * The warning before a form page's unsaved input is left behind.
 *
 * Pest sees that the edit page arms `wireUnsavedChanges`; only a browser can say
 * what the controller does with it:
 *
 *   - a `wire:navigate` away from a changed form asks, and "stay" keeps the page;
 *   - a reload or a closed tab asks too — `beforeunload` is cancelled;
 *   - typing a value and putting it back is not a change, because what is
 *     compared is the form's state, not whether a key was pressed;
 *   - a successful save moves the baseline, so leaving afterwards asks nothing.
 *
 * `confirm()` is replaced before the page loads so the driver can answer it and
 * count it; a real dialog would block the evaluation that triggered it. The
 * `beforeunload` check dispatches the event itself and reads `defaultPrevented`,
 * which is exactly what the browser looks at before drawing its own prompt.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const preload = `
  window.__confirms = [];
  window.__answer = false;
  window.confirm = (message) => { window.__confirms.push(message); return window.__answer; };
`;

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url: `${base}/resource-edit`, shotPrefix: 'unsaved-changes', width: 1300, height: 900, preload,
});

const field = `document.getElementById('data.number')`;
const type = (value) => eval_(`(() => {
  const input = ${field};
  input.value = ${JSON.stringify(value)};
  input.dispatchEvent(new Event('input', { bubbles: true }));
})()`);
const beforeUnloadCancelled = () => eval_(`(() => {
  const event = new Event('beforeunload', { cancelable: true });
  window.dispatchEvent(event);
  return event.defaultPrevented;
})()`);
const navigateAway = () => eval_(`Livewire.navigate('${base}/resource-list')`);

try {
  await waitFor(`!! ${field} && !! document.querySelector('form[x-data^="wireUnsavedChanges"]')?._x_dataStack`);
  const original = await eval_(`${field}.value`);

  // ── 1. Untouched: nothing to warn about ─────────────────────────────────
  check('an untouched form lets the page unload', ! (await beforeUnloadCancelled()));

  // ── 2. Changed: both ways out ask ───────────────────────────────────────
  await type(`${original}-draft`);
  check('a changed form cancels beforeunload', await beforeUnloadCancelled());

  await navigateAway();
  await waitFor('window.__confirms.length === 1');
  check('wire:navigate away asks first', (await eval_('window.__confirms[0]')).includes('unsaved'), await eval_('window.__confirms[0]'));
  check('"stay" keeps the page and the typed value', (await eval_(`location.pathname.endsWith('/resource-edit') && ${field}.value`)) === `${original}-draft`);
  await shot('01-stayed');

  // ── 3. Put back: not a change ───────────────────────────────────────────
  await type(original);
  check('a value typed and put back is not a change', ! (await beforeUnloadCancelled()));

  // ── 4. Saved: the baseline moves ───────────────────────────────────────
  await type(`${original}-saved`);
  await eval_(`document.querySelector('form[x-data^="wireUnsavedChanges"]').requestSubmit()`);
  await waitFor(`! (() => { const e = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; })()`);
  check('after a save the page unloads without asking', ! (await beforeUnloadCancelled()));

  // Put the workbench record back the way the other drivers expect it.
  await type(original);
  await eval_(`document.querySelector('form[x-data^="wireUnsavedChanges"]').requestSubmit()`);
  await waitFor(`! (() => { const e = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; })()`);

  const confirmsBefore = await eval_('window.__confirms.length');
  await navigateAway();
  await waitFor(`location.pathname.endsWith('/resource-list')`);
  check('wire:navigate after a save asks nothing', (await eval_('window.__confirms.length')) === confirmsBefore);

  // ── 5. The controller let go of the page it left ────────────────────────
  check('the list page does not inherit the warning', ! (await beforeUnloadCancelled()));
  await shot('02-left');

  // ── 6. A create page redirects from inside its save ─────────────────────
  // Livewire runs the redirect before the save action resolves, so this is the
  // case the in-flight hold exists for: the form is still "changed" at the
  // moment the navigation starts, and must not ask.
  // A full load rather than wire:navigate: the routed pages sit in the admin
  // shell and the previews above do not, and crossing into a layout by
  // navigate is not what this driver is about.
  await page('Page.navigate', { url: `${base}/routed/invoices/create` });
  // The controller itself, initialised — not the markup it sits on: the form is
  // in the HTML before Alpine starts, and typing into a page still starting up
  // is a race a busy machine loses.
  await waitFor(`location.pathname.endsWith('/invoices/create') && !! document.querySelector('form[x-data^="wireUnsavedChanges"]')?._x_dataStack`);
  const confirmsBeforeCreate = await eval_('window.__confirms.length');
  await eval_(`(() => {
    for (const [id, value] of [['data.number', 'UNSAVED-1'], ['data.customer', 'Unsaved Ltd']]) {
      const input = document.getElementById(id);
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
  })()`);
  check('a filled create form is a change', await beforeUnloadCancelled());
  await eval_(`document.querySelector('form[x-data^="wireUnsavedChanges"]').requestSubmit()`);
  await waitFor(`! location.pathname.endsWith('/create')`);
  check('the save redirects without asking', (await eval_('window.__confirms.length')) === confirmsBeforeCreate, await eval_('location.pathname'));
  await shot('03-created');
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
