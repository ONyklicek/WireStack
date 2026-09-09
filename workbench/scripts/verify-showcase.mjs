import { openPage, checker } from './lib/cdp.mjs';

/*
 * Screenshots of the admin, for a human rather than for an assertion.
 *
 * A driver proves behaviour; this one records what the behaviour looks like —
 * the shell in both themes, the rail, the phone, and every module's screen. It
 * still runs through the same CDP helper, so a page that throws is a failure
 * here too: a screenshot of a stack trace is worse than no screenshot.
 *
 *   SHOT_DIR=/somewhere npm run verify:drivers -- showcase
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/invoices`, shotPrefix: 'showcase', width: 1440, height: 960 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

/**
 * Go somewhere and wait for *that* page, not merely for a shell.
 *
 * Waiting on the sidebar alone returns while the old page is still on screen —
 * the shell is on both — so a check that followed read the previous document and
 * a screenshot taken after it showed the right one. The path is what changes.
 */
async function visit(path) {
  await eval_(`window.location.href = ${JSON.stringify(base + path)}`);
  await waitFor(`location.pathname === ${JSON.stringify(new URL(base + path).pathname)}`, 15000);
  // Waits for the *store*, not for `window.Alpine`. The two are not the same
  // moment: the bundle sets `window.Alpine` and only then fires `alpine:init`,
  // which is where the layout registers `wireAdmin`. Waiting on the earlier one
  // let `theme()` run against a store that did not exist yet — green in
  // isolation, red once a full sweep put the machine under load, which is the
  // worst shape a driver failure has.
  await waitFor(`(() => {
    try { return !! Alpine.store('wireAdmin') && !! document.querySelector('[data-testid="admin-sidebar"]'); }
    catch { return false; }
  })()`, 15000);
}

/**
 * Put the page in a known theme, so a shot does not depend on the last one.
 *
 * Says which theme it wants rather than toggling towards it. The switch is three
 * states now — day, system, night — and "toggle until it looks right" has no
 * meaning once `system` is one of them: it depends on what the machine taking
 * the screenshots happens to prefer.
 */
async function theme(mode) {
  await eval_(`Alpine.store('wireAdmin').setTheme(${JSON.stringify(mode === 'dark' ? 'dark' : 'light')})`);
  await waitFor(`document.documentElement.classList.contains('dark') === ${mode === 'dark'}`, 4000);
}

try {
  // The machine taking these may be in dark mode, and the shell honours the
  // system preference before Alpine has run — so the *first* paint would be dark
  // whatever the store says afterwards. Emulated here so a "light" shot is one.
  await page('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }] });
  await eval_(`window.localStorage.removeItem('wire-admin.theme')`);
  await eval_(`window.location.reload()`);

  // Waits for the *store*, not for `window.Alpine`. The two are not the same
  // moment: the bundle sets `window.Alpine` and only then fires `alpine:init`,
  // which is where the layout registers `wireAdmin`. Waiting on the earlier one
  // let `theme()` run against a store that did not exist yet — green in
  // isolation, red once a full sweep put the machine under load, which is the
  // worst shape a driver failure has.
  await waitFor(`(() => {
    try { return !! Alpine.store('wireAdmin') && !! document.querySelector('[data-testid="admin-sidebar"]'); }
    catch { return false; }
  })()`, 15000);
  await theme('light');

  // ── The shell ────────────────────────────────────────────────────────────
  check('the shell renders with its menu', await eval_(`!! document.querySelector('[data-testid="admin-sidebar"]')`));
  await shot('01-shell-light');

  await theme('dark');
  await shot('02-shell-dark');
  await theme('light');

  // Waited on the rendered width rather than on the store: the flag flips
  // synchronously and Alpine applies the class on its next tick, so a shot taken
  // on the flag catches the sidebar mid-change — which is how the first run of
  // this produced a "rail" screenshot of a full-width menu.
  await eval_(`Alpine.store('wireAdmin').toggleRail()`);
  await waitFor(`document.querySelector('[data-testid="admin-sidebar"]').offsetWidth < 120`, 4000);
  await shot('03-shell-rail');

  await eval_(`Alpine.store('wireAdmin').toggleRail()`);
  await waitFor(`document.querySelector('[data-testid="admin-sidebar"]').offsetWidth > 200`, 4000);

  // ── One record: breadcrumbs, infolist, relation managers ────────────────
  await visit('/routed/invoices/1');
  await theme('light');
  check('a record page shows its trail', await eval_(`!! document.querySelector('[data-testid="breadcrumbs"]')`));
  await shot('04-record-with-breadcrumbs');

  // ── The modules ──────────────────────────────────────────────────────────
  const modules = [
    ['users', '05-users'],
    ['settings', '06-settings'],
    ['audit-log', '07-audit-log'],
    ['notifications', '08-notifications'],
    ['media', '09-media'],
  ];

  for (const [key, name] of modules) {
    await visit(`/routed/${key}`);
    await theme('light');
    check(`the ${key} module renders`, await eval_(`!! document.querySelector('[data-testid="admin-content"]')`));
    await shot(name);
  }

  // ── A phone, with the menu open ──────────────────────────────────────────
  await visit('/routed/invoices');
  await page('Emulation.setDeviceMetricsOverride', { width: 420, height: 900, deviceScaleFactor: 2, mobile: true });
  // The drawer is always in the document and moved by a transform, so
  // `offsetParent` says nothing about whether it is on screen. Its left edge
  // does — and waiting for it to *land* rather than for the click to register is
  // what stops the screenshot catching the menu halfway through its slide.
  const drawerLeft = `document.querySelector('[data-testid="admin-sidebar"]').getBoundingClientRect().left`;

  await waitFor(`${drawerLeft} < -100`, 4000);
  check('the menu is off screen on a phone until it is asked for', (await eval_(drawerLeft)) < -100);

  await eval_(`document.querySelector('[data-testid="admin-sidebar-toggle"]').click()`);
  await waitFor(`${drawerLeft} === 0`, 4000);
  check('the handle slides it in', (await eval_(drawerLeft)) === 0);
  // Measured, not asked with `offsetParent`: the overlay is `position: fixed`,
  // and a fixed element reports no offset parent whether it is on screen or not.
  check('it dims the page behind it', (await eval_(`document.querySelector('[data-testid="admin-sidebar-overlay"]')?.getBoundingClientRect().width ?? 0`)) > 0);
  await shot('10-phone-menu');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
