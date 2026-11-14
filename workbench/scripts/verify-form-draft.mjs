import { openPage, checker } from './lib/cdp.mjs';

/*
 * A form page that keeps its unsaved input as a draft (remembersDraft()).
 *
 * Only a browser can say this: typed input survives a reload in localStorage,
 * the page offers it back, Restore puts it into the form, a save throws the
 * draft away, and Discard leaves the form as it loaded.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url: `${base}/resource-edit-draft`, shotPrefix: 'form-draft', width: 1300, height: 900,
});

const field = `document.getElementById('data.number')`;
const ready = `!! ${field} && !! document.querySelector('form[x-data^="wireUnsavedChanges"]')?._x_dataStack`;
const banner = `(() => { const b = document.querySelector('[data-testid=form-draft-banner]'); return !! b && getComputedStyle(b).display !== 'none'; })()`;
const type = (value) => eval_(`(() => { const i = ${field}; i.value = ${JSON.stringify(value)}; i.dispatchEvent(new Event('input', { bubbles: true })); })()`);
const keep = () => eval_(`window.dispatchEvent(new Event('beforeunload', { cancelable: true }))`);
// A marker the old document carries and the new one does not, so waiting for
// "ready" cannot pass on the page that is about to go away.
const reload = async () => {
  await eval_('window.__beforeReload = true');
  await page('Page.reload');
  await waitFor(`! window.__beforeReload && ${ready}`);
};

try {
  await waitFor(ready);
  await eval_(`Object.keys(localStorage).filter((k) => k.startsWith('wire-draft:')).forEach((k) => localStorage.removeItem(k))`);
  await reload();
  const original = await eval_(`${field}.value`);
  check('a page with no draft offers nothing', ! (await eval_(banner)));

  // Typed, then the page goes away.
  await type(`${original}-draft`);
  await keep();
  await reload();
  await waitFor(banner).catch(() => {});
  check('the reloaded page offers the draft back', await eval_(banner));
  check('and still shows what was saved until asked', (await eval_(`${field}.value`)) === original);
  await shot('01-offered');

  await eval_(`document.querySelector('[data-testid=form-draft-restore]').click()`);
  await waitFor(`${field}.value === ${JSON.stringify(`${original}-draft`)}`).catch(() => {});
  check('Restore puts the draft into the form', (await eval_(`${field}.value`)) === `${original}-draft`);
  check('and the offer goes away', ! (await eval_(banner)));

  // Putting it back and saving throws the draft away.
  await type(original);
  await eval_(`document.querySelector('form[x-data^="wireUnsavedChanges"]').requestSubmit()`);
  await waitFor(`! Object.keys(localStorage).some((k) => k.startsWith('wire-draft:'))`).catch(() => {});
  check('a save throws the draft away', ! (await eval_(`Object.keys(localStorage).some((k) => k.startsWith('wire-draft:'))`)));

  // Discard: offered, refused, gone.
  await type(`${original}-again`);
  await keep();
  await reload();
  await waitFor(banner).catch(() => {});
  check('a second draft is offered too', await eval_(banner));
  await eval_(`document.querySelector('[data-testid=form-draft-discard]').click()`);
  await waitFor(`! ${banner}`).catch(() => {});
  const after = { offered: await eval_(banner), value: await eval_(`${field}.value`) };
  check('Discard leaves the form as it loaded', ! after.offered && after.value === original, JSON.stringify({ ...after, original }));
  await reload();
  check('and the draft is not offered again', ! (await eval_(banner)));
  await shot('02-discarded');
} catch (error) {
  check('driver ran to completion', false, error.message);
} finally {
  finish({ consoleErrors, badResponses: badResponses.filter((r) => ! r.includes('favicon')), shotDir });
  await close();
}
