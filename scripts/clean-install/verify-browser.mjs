import { openPage, checker } from '../../workbench/scripts/lib/cdp.mjs';

/*
 * The browser half of scripts/verify-clean-install.sh.
 *
 * The shell half proves the HTTP answers are right. What it cannot see is what
 * the browser does with them, in the application `wire:install` produced rather
 * than in the workbench: that the sign-in form posts from a real page, that the
 * admin it lands in is styled by the stylesheet that installer built, that
 * Livewire and Alpine boot, and that nothing on the way throws.
 *
 * Reads its target from the environment the shell script sets.
 */

const origin = process.env.CLEAN_ORIGIN ?? 'http://127.0.0.1:8096';
const email = process.env.CLEAN_EMAIL ?? 'admin@example.test';
const password = process.env.CLEAN_PASSWORD ?? '';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/login`, shotPrefix: 'clean-install', width: 1280, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

/** Submit a native form and wait for the document it leads to. */
const submit = async (selector) => {
  await eval_(`window.__leaving = true; document.querySelector(${JSON.stringify(selector)}).submit()`);
  await waitFor(`! window.__leaving && document.readyState === 'complete'`, 15000);
};

try {
  await waitFor(`!! document.querySelector('[data-testid="auth-login-form"]')`, 15000);

  check('the sign-in screen renders its form', true);
  check('in the frame the installer wrote, with the built stylesheet', await eval_(`
    [...document.styleSheets].some((sheet) => (sheet.href ?? '').includes('/build/assets/'))
  `));

  // The fields, measured. A stylesheet can load and still leave every input a
  // bare line: the field views take their border and padding from
  // @tailwindcss/forms, and a fresh application did not have it. This used to
  // be a check on the font family, which passed on an unstyled page because
  // Chrome names its default "Times", not "Times New Roman".
  const field = await eval_(`
    (() => {
      const style = getComputedStyle(document.querySelector('[data-testid="auth-login-form"] input[name="email"]'));

      return { border: style.borderTopWidth, padding: style.paddingLeft, height: document.querySelector('[data-testid="auth-login-form"] input[name="email"]').getBoundingClientRect().height };
    })()
  `);
  check('the sign-in fields have a border and padding', field.border !== '0px' && field.padding !== '0px' && field.height >= 30,
    JSON.stringify(field));
  await shot('01-login');

  await eval_(`
    (() => {
      const form = document.querySelector('[data-testid="auth-login-form"]');
      form.querySelector('input[name="email"]').value = ${JSON.stringify(email)};
      form.querySelector('input[name="password"]').value = ${JSON.stringify(password)};
    })()
  `);
  await submit('[data-testid="auth-login-form"]');

  // `/admin` answers with a redirect to its first page, so the browser is
  // already past it by now.
  check('signing in lands inside the admin', await eval_(`location.pathname.startsWith('/admin/')`),
    await eval_('location.pathname'));

  await waitFor(`!! window.Livewire && !! window.Alpine`, 10000).catch(() => {});

  check('Livewire and Alpine booted', await eval_(`!! window.Livewire && !! window.Alpine`));
  check('the sidebar is drawn, with entries in it', await eval_(`
    !! document.querySelector('[data-wire="admin-sidebar"]')
      && document.querySelectorAll('[data-wire="admin-nav-item"]').length > 0
  `));
  check('the sidebar is laid out, not an unstyled list', await eval_(`
    (() => {
      const sidebar = document.querySelector('[data-wire="admin-sidebar"]');
      const box = sidebar?.getBoundingClientRect();

      return !! box && box.width > 0 && box.width < window.innerWidth / 2;
    })()
  `));
  check('the brand leads back into the admin, not to the welcome page', await eval_(`
    [...document.querySelectorAll('[data-wire="admin-sidebar"] a')]
      .some((a) => new URL(a.href).pathname === '/admin')
  `));
  await shot('02-admin');

  // Night, chosen with the shell's own switch. The switch puts `dark` on the
  // document; only a class-based `dark` variant in the stylesheet turns that
  // into a dark page — without it, Tailwind 4 follows the operating system.
  // Day first, chosen explicitly: the default is `system`, and on a machine in
  // dark mode that already starts dark — nothing would change and the check
  // would fail on the machine rather than on the code.
  await eval_(`window.wireAdminTheme.set('light')`);
  await waitFor(`! document.documentElement.classList.contains('dark')`, 3000).catch(() => {});
  const lightBackground = await eval_(`getComputedStyle(document.querySelector('[data-wire="admin-sidebar"]')).backgroundColor`);
  await eval_(`window.wireAdminTheme.set('dark')`);
  await waitFor(`document.documentElement.classList.contains('dark')`, 3000).catch(() => {});
  const darkBackground = await eval_(`getComputedStyle(document.querySelector('[data-wire="admin-sidebar"]')).backgroundColor`);
  check('the theme switch darkens the page', darkBackground !== lightBackground, `${lightBackground} → ${darkBackground}`);
  await shot('02b-admin-dark');
  await eval_(`window.wireAdminTheme.set('system')`);

  // One hop through the menu, the way a person moves on from the first page:
  // `wire:navigate` swaps the page without a reload, and a broken asset or
  // controller registration shows up here rather than on the first load.
  const next = await eval_(`
    (() => {
      const here = location.pathname;
      const link = [...document.querySelectorAll('[data-wire="admin-sidebar"] a[wire\\\\:navigate]')]
        .find((a) => new URL(a.href).pathname !== here && new URL(a.href).pathname.startsWith('/admin/'));

      if (! link) return null;
      link.click();

      return new URL(link.href).pathname;
    })()
  `);

  if (next !== null) {
    await waitFor(`location.pathname === ${JSON.stringify(next)}`, 10000).catch(() => {});
    check('the menu navigates to another page', await eval_(`location.pathname === ${JSON.stringify(next)}`),
      `${next} — at ${await eval_('location.pathname')}`);
    await shot('03-navigated');
  }

  // The account page, reached the way a person reaches it: the user menu. It
  // has to offer what the sign-in screen promises — a passkey button on the
  // login page with no way to register a passkey is a dead end, and after a
  // clean install that is exactly what it was: nothing put the passkey or
  // two-factor trait on the user model.
  const profile = await eval_(`document.querySelector('[data-testid="admin-profile-link"]')?.href ?? null`);
  check('the user menu links to the account page', profile !== null);

  if (profile !== null) {
    await eval_(`window.__leaving = true; location.href = ${JSON.stringify(profile)}`);
    await waitFor(`! window.__leaving && document.readyState === 'complete'`, 15000);
    check('the account page offers passkeys', await eval_(`!! document.querySelector('[data-testid="profile-passkeys"]') && ! document.body.innerText.includes('PasskeyAuthenticatable')`));
    check('and two-factor authentication', await eval_(`!! document.querySelector('[data-testid="profile-two-factor"]')`));
    await shot('04-account');
  }

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
