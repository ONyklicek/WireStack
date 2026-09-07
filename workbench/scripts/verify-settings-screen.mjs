import { openPage, checker, until } from './lib/cdp.mjs';

/*
 * CDP driver for the settings screen (/previews/routed/settings).
 *
 * Everything here is server-rendered, so most of it is Pest's job — except the
 * two things Pest structurally cannot see. The screen is reached through
 * `Route::wireResources()`, which is where the bug this driver exists for lived:
 * the route is `settings/{record}` and the page took `$group`, so Livewire
 * matched neither a property nor a mount parameter, filed the value away as an
 * HTML attribute, and every group URL rendered the *first* group while looking
 * exactly like it had worked. A component test that mounts the page by hand
 * cannot reproduce that; only a real URL can.
 *
 * The second is `wire:navigate` on the switcher: the links swap the page
 * document, and a heading or a form that does not come back with it is
 * invisible to every server-side assertion.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-settings-screen.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = process.env.PREVIEW_URL ?? `${origin}/previews/routed/settings`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'settings-screen' });

const { check, finish } = checker();

try {
  const helpers = `
    window.heading = () => document.querySelector('.wire-settings-page h1')?.textContent.trim() ?? '';
    window.crumbs = () => [...document.querySelectorAll('.wire-settings-page nav a, nav[aria-label] a')]
      .map((a) => a.textContent.trim());
    window.switcher = () => document.querySelector('[data-testid="settings-groups"]');
    window.groupLinks = () => [...document.querySelectorAll('[data-testid="settings-group-link"]')]
      .map((a) => ({
        group: a.dataset.group,
        label: a.textContent.trim(),
        current: a.getAttribute('aria-current') === 'page',
        icon: !! a.querySelector('svg'),
      }));
    // By the wrapper's data-field rather than by wire:model — a colon in an
    // attribute name needs escaping in a selector, and the escape does not
    // survive being sent through CDP as a string.
    window.fieldNames = () => [...document.querySelectorAll('[data-testid="settings-form"] [data-field]')]
      .map((el) => el.dataset.field);
    window.fieldValue = (path) => document.getElementById(path)?.value ?? null;
    window.description = () => document.querySelector('.wire-settings-page p')?.textContent.trim() ?? '';
    window.surface = () => !! document.querySelector('[data-testid="settings-surface"]');
    window.go = (group) => {
      const link = [...document.querySelectorAll('[data-testid="settings-group-link"]')]
        .find((a) => a.dataset.group === group);
      link?.click();
    };
    true;
  `;

  // Re-injected after a hard navigation: `wire:navigate` keeps the window, a
  // real page load does not, and the helpers go with it.
  await eval_(helpers);

  const booted = await eval_(`typeof Alpine !== 'undefined' && !! switcher()`);
  check('the screen renders with Alpine booted and a switcher present', booted);
  await shot('01-branding');

  // ── The heading and the trail the page did not used to have ───────────
  check('the open group is the heading', await eval_(`heading() === 'Branding'`));
  check(
    'the trail says where the page sits',
    await eval_(`crumbs().some((c) => /Settings|Nastaven/.test(c))`),
  );
  check(
    'the description the group declares is rendered',
    await eval_(`description().includes('How the panel introduces itself')`),
  );

  // ── The switcher ──────────────────────────────────────────────────────
  const links = await eval_(`JSON.stringify(groupLinks())`).then(JSON.parse);
  check('both declared groups are in the switcher', links.length === 2);
  check(
    'the switcher is in the order the groups declared',
    links.map((l) => l.group).join(',') === 'branding,mail',
  );
  check('each group carries the icon it declared', links.every((l) => l.icon));
  check(
    'the open group is the one marked current',
    links.filter((l) => l.current).map((l) => l.group).join(',') === 'branding',
  );

  check(
    'a flat group\'s fields sit on a card rather than on the page background',
    await eval_(`surface()`),
  );

  // ── What is stored wins over the declared default ─────────────────────
  // Polled rather than read: `wire:model` renders no `value` attribute, so the
  // field is filled from the snapshot after Livewire boots. Reading it straight
  // away asserts a timing, and passes only on a fast machine.
  await until(() => eval_(`fieldValue('data.company_name') === 'Nyon Industries'`), { timeout: 8000 });
  check('a stored value wins over the group\'s declared default', true);

  // ── The bug this driver exists for: a group is a URL ───────────────────
  await eval_(`go('mail')`);
  await until(() => eval_(`heading() === 'Mail'`), { timeout: 8000 });

  check('following a group link opens that group, not the first one', true);
  check(
    'the form that comes back is the one that group declared',
    await eval_(`JSON.stringify(fieldNames())`).then((n) => n.includes('from_address')),
  );
  check(
    'the switcher survives the navigation with the new group current',
    await eval_(`groupLinks().filter((l) => l.current).map((l) => l.group).join(',') === 'mail'`),
  );
  await shot('02-mail');

  // ── And the same URL, opened cold ─────────────────────────────────────
  await eval_(`window.location.href = '${origin}/previews/routed/settings/mail'`);
  await until(() => eval_(`document.querySelector('.wire-settings-page h1')?.textContent.trim() === 'Mail'`), { timeout: 8000 });
  await eval_(helpers);

  check('a bookmarked group URL opens that group', true);
  await until(() => eval_(`fieldValue('data.from_address') === 'noreply@example.com'`), { timeout: 8000 });
  check('its declared default is in the field, on a group nothing has saved', true);
  await shot('03-mail-direct');

  finish({ consoleErrors, badResponses, shotDir });
} finally {
  await close();
}
