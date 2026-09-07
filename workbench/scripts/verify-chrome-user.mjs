import { openPage, checker } from './lib/cdp.mjs';

/*
 * The corner of the shell that says who you are, and the switch that says what
 * you want to look at.
 *
 * Both were reported as missing, and for different reasons worth keeping apart:
 *
 *   - the theme control was a two-way toggle, so `system` existed until somebody
 *     touched it and then never again. Only a browser can say whether the third
 *     state applies and whether it keeps following the OS.
 *   - the user corner renders for an authenticated user, and the preview server
 *     browsed as a guest — so the markup was right and the corner was empty.
 *     Pest cannot see that; it renders a component, not a session.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/invoices`, shotPrefix: 'chrome-user', width: 1300, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const isDark = () => eval_(`document.documentElement.classList.contains('dark')`);
const stored = () => eval_(`window.localStorage.getItem('wire-admin.theme')`);

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="admin-theme"]')`);

  // ── 1. Three states, and each one holds ──────────────────────────────────
  check('the switch offers three states', (await eval_(`document.querySelectorAll('[data-testid^="admin-theme-"]').length`)) === 3);
  check('it is a radio group, not three unrelated buttons', (await eval_(`document.querySelector('[data-testid="admin-theme"]')?.getAttribute('role')`)) === 'radiogroup');

  await eval_(`document.querySelector('[data-testid="admin-theme-dark"]').click()`);
  await waitFor(`document.documentElement.classList.contains('dark')`, 3000);
  check('night darkens the page', await isDark());
  check('and is what gets remembered', (await stored()) === 'dark');
  check('the chosen one says so to assistive tech', (await eval_(`document.querySelector('[data-testid="admin-theme-dark"]')?.getAttribute('aria-checked')`)) === 'true');
  await shot('01-night');

  await eval_(`document.querySelector('[data-testid="admin-theme-light"]').click()`);
  await waitFor(`! document.documentElement.classList.contains('dark')`, 3000);
  check('day lightens it again', (await isDark()) === false);

  // ── 2. System is somewhere to go back to ─────────────────────────────────
  // The whole reason for the third state: a two-way toggle forces a choice the
  // moment it is touched and then keeps it for ever.
  await eval_(`document.querySelector('[data-testid="admin-theme-system"]').click()`);
  check('system can be returned to', (await stored()) === 'system');

  // And it keeps following. Emulated at the CDP level rather than faked in JS,
  // so what is being tested is the media-query listener and not a stub.
  await page('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'dark' }] });
  await waitFor(`document.documentElement.classList.contains('dark')`, 4000);
  check('and follows the system while the page is open', await isDark());

  await page('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }] });
  await waitFor(`! document.documentElement.classList.contains('dark')`, 4000);
  check('in both directions', (await isDark()) === false);

  // ── 3. And it survives the way the shell is actually browsed ─────────────
  // Reported as "set dark, open another page, it is white again", and it was:
  // Livewire's navigate copies the *fetched* document's <html> attributes over
  // the live ones, and the server has no way to know what this browser chose,
  // so `dark` was stripped on every SPA visit. Only a browser sees this — the
  // markup Pest reads is the markup that causes it.
  await eval_(`document.querySelector('[data-testid="admin-theme-dark"]').click()`);
  await waitFor(`document.documentElement.classList.contains('dark')`, 3000);

  // Every intermediate value of the class attribute, not just the final one: a
  // theme reapplied one paint late is a white flash, which is the complaint
  // even when the end state is right.
  await eval_(`(() => {
    window.__themeLapses = [];
    new MutationObserver(() => {
      if (! document.documentElement.classList.contains('dark')) window.__themeLapses.push(document.documentElement.className);
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  })()`);

  const here = await eval_(`location.pathname`);
  // The *exact* pathname, not a prefix: the entry this lands on is
  // `/previews/routed`, which the page we are standing on starts with — so a
  // `startsWith` wait is satisfied before the navigation has even begun, and
  // everything after it races the swap.
  const away = await eval_(`(() => {
    const link = [...document.querySelectorAll('[data-testid="admin-nav-item"]')].find(a => a.getAttribute('href') && a.dataset.active !== 'true');
    if (! link) return null;
    const to = new URL(link.getAttribute('href'), location.href).pathname;
    link.click();
    return to;
  })()`);
  check('there is a second page to navigate to', !! away && away !== here, `${here} → ${away}`);
  await waitFor(`location.pathname === ${JSON.stringify(String(away))}`, 6000);
  await waitFor(`!! document.querySelector('[data-testid="admin-theme"]')`, 6000);

  check('night survives a wire:navigate', await isDark());
  check('without a single light frame in between', (await eval_(`window.__themeLapses.length`)) === 0, `${await eval_(`JSON.stringify(window.__themeLapses)`)}`);
  check('and the switch still agrees with the page', (await eval_(`Alpine.store('wireAdmin').theme`)) === 'dark');

  // The back button is Livewire's other swap — from a cached snapshot — and
  // some visits it is not Livewire's at all but a real browser navigation. Which
  // one a run gets is not this driver's business; landing on a page the user
  // already told what colour to be is. So the wait tolerates the execution
  // context being torn out from under it, which a hard navigation does.
  const tolerant = async (expression, tries = 40) => {
    for (let i = 0; i < tries; i++) {
      try {
        if (await eval_(expression)) return true;
      } catch (e) {
        // The document went away mid-poll: that is one of the two answers.
      }
      await new Promise((resolve) => setTimeout(resolve, 150));
    }
    return false;
  };

  await eval_(`setTimeout(() => history.back(), 0)`).catch(() => {});
  check(
    'and the way back, cached swap or real load',
    await tolerant(`location.pathname === ${JSON.stringify(String(here))}
      && !! document.querySelector('[data-testid="admin-theme"]')
      && document.documentElement.classList.contains('dark')`),
  );

  // And the cold path the head script exists for: a fresh document, before a
  // single line of Alpine or Livewire has run.
  await page('Page.navigate', { url: `${base}/routed/invoices` });
  check('and a plain page load, decided before anything else runs', await tolerant(`
    document.documentElement.classList.contains('dark') && !! document.querySelector('[data-testid="admin-theme"]')
  `));

  await eval_(`document.querySelector('[data-testid="admin-theme-system"]').click()`);
  await page('Emulation.setEmulatedMedia', { features: [] });

  // ── 4. Somebody is signed in, and can get to their own account ───────────
  check('the shell shows who is signed in', await eval_(`!! document.querySelector('[data-testid="admin-user"]')`));

  await eval_(`document.querySelector('[data-testid="admin-user"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="admin-profile-link"]')?.offsetParent`, 4000);
  check('the menu opens with a way to their profile', await eval_(`!! document.querySelector('[data-testid="admin-profile-link"]')?.offsetParent`));
  // Contributed by wire-module-auth, not written into this layout: the way out
  // posts to Fortify's own logout route, and the row is one the module puts in
  // the menu through PageChrome::USER_MENU.
  check('and a way out', await eval_(`!! document.querySelector('[data-testid="auth-sign-out"]')`));
  await shot('02-user-menu');

  await eval_(`document.querySelector('[data-testid="admin-profile-link"]').click()`);
  await waitFor(`location.pathname.endsWith('/users/profile')`, 6000);
  await waitFor(`!! document.querySelector('form input')`, 6000);

  check('which is a page about them', await eval_(`!! document.querySelector('[data-testid="admin-content"] form')`));

  // Roles are removed from the schema, not merely ignored on save: editing your
  // own account must not be a way into a role you were not given.
  //
  // Scoped to the page's own content, not the whole document: the sidebar has a
  // "Roles" entry of its own now that the workbench seeds them, and a check that
  // could not tell the menu from the form would have started failing on a page
  // that is still perfectly correct.
  check('with no way to give themselves a role', await eval_(`
    ! [...document.querySelectorAll('[data-testid="admin-content"] label, [data-testid="admin-content"] span')]
        .some(el => el.textContent.trim() === 'Roles')
  `));
  await shot('03-profile');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
