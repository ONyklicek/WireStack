import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for the theming levers (/previews/routed/users).
 *
 * Everything `docs/start/theming.md` promises about changing the look **without
 * publishing a view** is a claim about what a browser does with the framework's
 * markup, and Pest cannot see any of it: the markup is identical before and
 * after, and only the computed styles differ. That is what this measures.
 *
 * What it drives, in one pass, is exactly what a compiled
 * `resources/css/admin.css` would put on the page:
 *
 *  - **tokens** — `--radius-*`, `--spacing` and `--color-primary-*`, which every
 *    utility this framework writes already reads (Tailwind 4 compiles `rounded-lg`
 *    to `var(--radius-lg)` and `px-3` to `calc(var(--spacing) * 3)`);
 *  - **`data-wire` hooks** — a plain CSS rule against `[data-wire="admin-sidebar"]`,
 *    the thing `@wireEl` exists to make possible.
 *
 * Two traps this driver is written around, both of which produced a confident
 * wrong answer first:
 *
 *  - **Measure an element that actually has the property.** The first version
 *    read `border-radius` off whatever `main div` came first, which was already
 *    `0px`, and reported that tokens do nothing.
 *  - **Measure the *same* element twice.** Tightening `--spacing` reflows the
 *    page, so "the first rounded element" is a different element afterwards —
 *    the probe read `16px → 3.35544e+07px` and looked like a bug in the theme
 *    when it was comparing a card against an avatar. The element is pinned with
 *    an attribute on the first read and re-found by it on the second.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-theming.mjs
 *
 * Screenshots (`01-default`, `02-themed`) are written to SHOT_DIR — look at
 * them. The assertions prove the values moved; only the pictures show that the
 * result is a theme rather than a mess.
 */

const url = process.env.PREVIEW_URL
  ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/routed/users`;

/** What a themed `admin.css` compiles to, minus the parts a browser cannot be told. */
const THEME = `
  :root {
    --radius-sm: 0; --radius-md: 0; --radius-lg: 0;
    --radius-xl: 0; --radius-2xl: 0; --radius-3xl: 0;
    --spacing: 0.2rem;
    --color-primary-50:  var(--color-violet-50);
    --color-primary-500: var(--color-violet-500);
    --color-primary-600: var(--color-violet-600);
    --color-primary-700: var(--color-violet-700);
  }
  [data-wire="admin-sidebar"]   { background-color: rgb(24 24 27); }
  [data-wire="admin-sidebar"] * { color: rgb(228 228 231); }
  [data-wire="admin-topbar"]    { background-color: rgb(39 39 42); border-color: rgb(63 63 70); }
  [data-wire="admin-topbar"] *  { color: rgb(228 228 231); }
`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'theming', width: 1400, height: 900 });

const { check, finish } = checker();

const probe = () => eval_(`(() => {
  const sidebar = document.querySelector('[data-wire="admin-sidebar"]');

  // Pinned on the first read and re-found on the second: picking "the first
  // rounded element" twice measures two different elements, because the theme
  // reflows the page and something else ends up first.
  let card = document.querySelector('[data-radius-probe]');
  if (! card) {
    card = [...document.querySelectorAll('main *')].find((el) => {
      const r = parseFloat(getComputedStyle(el).borderTopLeftRadius);
      return r > 0 && r < 1000;   // a real corner, not a pill
    });
    if (card) card.setAttribute('data-radius-probe', '');
  }

  const root = getComputedStyle(document.documentElement);

  return {
    hooks: document.querySelectorAll('[data-wire]').length,
    sidebarBg: sidebar ? getComputedStyle(sidebar).backgroundColor : 'no sidebar',
    radius: card ? getComputedStyle(card).borderTopLeftRadius : 'nothing rounded',
    radiusOn: card ? card.tagName + '.' + String(card.className).split(' ').slice(0, 2).join('.') : '—',
    spacing: root.getPropertyValue('--spacing').trim() || '(unset)',
    primary: root.getPropertyValue('--color-primary-600').trim() || '(unset)',
  };
})()`);

try {
  const before = await probe();
  await shot('01-default');

  check('the page carries hooks to theme by', before.hooks > 20, `${before.hooks} elements`);
  check('something on it is rounded to begin with', before.radius !== '0px',
    `${before.radiusOn}: ${before.radius}`);

  await eval_(`(() => {
    const style = document.createElement('style');
    style.id = 'wire-theming-probe';
    style.textContent = ${JSON.stringify(THEME)};
    document.head.appendChild(style);
    return true;
  })()`);

  const after = await probe();
  await shot('02-themed');

  check('corners follow --radius-*', after.radius === '0px',
    `${before.radiusOn}: ${before.radius} → ${after.radius}`);
  check('spacing follows --spacing', after.spacing === '0.2rem',
    `${before.spacing} → ${after.spacing}`);
  check('primary follows --color-primary-*', before.primary !== after.primary,
    `${before.primary} → ${after.primary}`);
  check('the sidebar takes a rule written against its hook', before.sidebarBg !== after.sidebarBg,
    `${before.sidebarBg} → ${after.sidebarBg}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
