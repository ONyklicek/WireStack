import { openPage, checker } from './lib/cdp.mjs';

/*
 * The toast that has to survive leaving the page.
 *
 * A create page redirects to the record it just filed, and the notification the
 * form raised is a *browser event*: it is dispatched into a document that
 * `wire:navigate` is about to replace. Nothing in Pest can see that — the
 * component under test dispatched, the assertion passes, and the user sees
 * nothing at all. So the flash exists, and only a browser can say whether it
 * arrives.
 *
 * The other half is just as invisible and matters more: the flash must NOT
 * arrive when the save stayed put, or every page opened after an ordinary edit
 * carries a stale "Record saved". That one leans on Livewire forgetting what an
 * update flashed unless it redirected (SupportRedirects), which is a fact about
 * a framework we do not own — worth a check that would go red if it changed.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/invoices/create`, shotPrefix: 'toast-redirect', width: 1200, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

/** Everything the toast container is currently showing. */
const toastText = () => eval_(`
  [...document.querySelectorAll('[role="status"], [role="alert"]')]
    .map(el => el.innerText.trim()).join(' | ')
`);

try {
  await waitFor(`!! window.Alpine && !! document.getElementById('data.number')`);

  // ── 1. Fill the form the way a person would ──────────────────────────────
  await eval_(`
    (() => {
      const set = (id, value) => {
        const el = document.getElementById(id);
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
      };
      set('data.number', 'TOAST-1');
      set('data.customer', 'Toast Ltd');
    })()
  `);

  // The status column is not nullable, so the select is part of a valid create
  // rather than decoration: without it the save fails and the page never leaves.
  //
  // Two things this driver had to learn about the select, both of which fail
  // quietly rather than loudly: its dropdown is teleported out of the field, so
  // a selector scoped to the field matches nothing — and the row that listens
  // for a click is the button inside the option, not the option itself.
  await eval_(`document.getElementById('data.status').click()`);
  check('the status select opens', !! (await waitFor(`!! document.querySelector('[data-testid="select-option-draft"]')`, 5000)));
  await eval_(`document.querySelector('[data-testid="select-option-draft"]').click()`);
  check('and takes a value', !! (await waitFor(`document.getElementById('data.status').innerText.trim() === 'Draft'`, 5000)));

  check('nothing is being announced before the save', (await toastText()) === '');

  // ── 2. Save, and land somewhere else ─────────────────────────────────────
  await eval_(`document.querySelector('form button[type="submit"]').click()`);
  await waitFor(`! location.pathname.endsWith('/create')`, 10000);
  const landed = await eval_(`location.pathname`);
  check('a create leaves the page it was filed on', ! landed.endsWith('/create'), landed);

  // ── 3. And the toast is there when it does ───────────────────────────────
  // The whole point: this toast was raised by the request that redirected, so
  // the event carrying it died with the previous document.
  await waitFor(`
    [...document.querySelectorAll('[role="status"], [role="alert"]')]
      .some(el => el.innerText.includes('Record created'))
  `, 8000);
  check('the toast crossed the redirect', (await toastText()).includes('Record created'));
  check(
    'and is recorded as shown, so it cannot be shown twice',
    (await eval_(`(window.sessionStorage.getItem('wire-toast-flashed') ?? '').includes('toast-')`)) === true,
  );
  await shot('01-toast-after-redirect');

  // ── 4. Once, not on every visit ──────────────────────────────────────────
  // The back button is the case the id in the payload exists for: `wire:navigate`
  // keeps a copy of a visited page and re-initialises Alpine over it, markup and
  // flashed payload and all. Without the guard the toast is raised again on
  // every press — of a save that happened once.
  await eval_(`
    (() => {
      // By attribute rather than by CSS selector: the colon in wire:navigate
      // needs escaping there, and the template literal carrying this eats the
      // backslash that would have done it.
      const link = [...document.querySelectorAll('a')]
        .find(a => a.hasAttribute('wire:navigate') && a.getAttribute('href')?.endsWith('/routed/users'));
      if (link) link.click(); else location.href = '${base}/routed/users';
    })()
  `);
  await waitFor(`location.pathname.endsWith('/routed/users')`, 10000);
  await eval_(`history.back()`);
  await waitFor(`! location.pathname.endsWith('/routed/users')`, 10000);
  check('and is not raised again by the back button', (await toastText()) === '', await toastText());

  // The flash itself is spent too: a reload renders the page from a session that
  // no longer holds it.
  await eval_(`location.reload()`);
  await waitFor(`!! window.Alpine && document.readyState === 'complete'`, 10000);
  check('and does not come back on a reload', (await toastText()) === '', await toastText());

  // ── 5. An edit that stays put leaves nothing behind ──────────────────────
  // A user rather than the invoice just filed: the workbench guards the invoice
  // edit page with `can:invoices.update`, which the demo user does not hold —
  // and a 403 is not what this is trying to measure.
  await eval_(`location.href = '${base}/routed/users/1/edit'`);
  check('the edit page is open', !! (await waitFor(`!! window.Alpine && !! document.getElementById('data.name')`, 10000)));

  // Both fields, and a valid address in the second: the preview database is
  // shared between drivers and one of them leaves an unroutable email behind.
  // The browser refuses to submit a form holding an invalid `type=email`, which
  // reads here as "the save raised no toast" rather than as "the save never ran".
  await eval_(`
    (() => {
      const set = (id, value) => {
        const el = document.getElementById(id);
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
      };
      set('data.name', 'Amelia Renamed');
      set('data.email', 'toast-driver@example.test');
    })()
  `);
  check('the edit form is submittable', await eval_(`document.querySelector('form').checkValidity()`));
  await eval_(`document.querySelector('form button[type="submit"]').click()`);

  await waitFor(`
    [...document.querySelectorAll('[role="status"], [role="alert"]')]
      .some(el => el.innerText.includes('Record saved'))
  `, 8000);
  check('an edit announces itself where it is', (await toastText()).includes('Record saved'));
  const stayed = await eval_(`location.pathname`);
  check('and stays there', stayed.endsWith('/edit'), stayed);
  await shot('02-toast-in-place');

  // The stale-toast check: nothing was carried, so the next full page load is
  // silent. This is what goes red if a flash is ever left behind by a save that
  // did not redirect — the failure that would otherwise reach a user as "Record
  // saved" on a page they only walked past.
  await eval_(`location.href = '${base}/routed/users'`);
  await waitFor(`!! window.Alpine && document.readyState === 'complete'`, 10000);
  check('and leaves no toast for the next page', (await toastText()) === '', await toastText());
  await shot('03-next-page-silent');
} catch (error) {
  check('driver ran to completion', false, error.message);
  await shot('99-failure').catch(() => {});
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
