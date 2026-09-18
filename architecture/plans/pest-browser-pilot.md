---
title: Pest browser tests — the pilot
date: 2026-09-18
status: pilot done, no decision yet
---

# Pest browser tests — the pilot

The question: should browser checks that depend on data and permissions move
from the CDP drivers (`workbench/scripts/verify-*.mjs`) to
`pestphp/pest-plugin-browser`?

One flow was written both ways as a comparison: resetting a password by code.

- CDP: `workbench/scripts/verify-auth-reset-code.mjs`
- Pest: `packages/module-auth/tests/Browser/PasswordResetCodeTest.php`
  (`composer test:browser`)

## What got installed

- `pestphp/pest-plugin-browser ^5.0`, **installed by hand and not in
  `composer.json`**. It needs PHP 8.4 and Symfony 8; as a require-dev it made
  `composer install` unresolvable on every CI job below PHP 8.4 or on Laravel
  12. `composer test:browser` (`scripts/test-browser.sh`) says how to install
  it where it is missing. It pulled Pest from 5.1.4 to 5.2.1 within the
  existing constraint. `composer.lock` is not tracked.
- `playwright ^1.63` (devDependencies) and `npx playwright install chromium`,
  about 100 MB in the user's cache.
- `tests/Pest.php` binds `module-auth/tests/Browser` to `ModuleAuthTestCase`.
  No `phpunit.xml` has a suite for that directory, so neither `composer test`
  nor CI runs it. It runs only when asked for by path.

## What it showed

**It runs over Testbench without any glue.** The plugin starts the application
in-process (amphp) inside the test's own process. The test's database,
`config()->set()`, bindings and `RecordingCodes` are all what the browser
sees. That is the main difference from the drivers: they talk to a separate
`testbench serve` process with a shared seeded database.

| | CDP driver | Pest browser |
|---|---|---|
| Data | seeded workbench, shared by all 122 drivers | own sqlite per test |
| The mailed code | parsed out of `laravel.log` | `CodeWorld::recordCodes()->last->code` |
| Cleaning up state | resets the seeded account's password back to the old one | nothing to clean up |
| Waiting for navigation | a hand-written marker on `window` | built in (`assertPathIs` waits) |
| Preconditions | whatever `WorkbenchServiceProvider` sets | PHP in `beforeEach` |
| Length | ~190 lines | ~110 lines |
| Time | ~15 s plus a running server | ~5.6 s, three runs in a row with no flake |
| Needs | Chrome, a running server | Playwright + Chromium |

**It catches the same class of fault.** With the `ResetsUserPasswords` binding
removed (the state the workbench was really in until today, where every reset
ended in a 500), the reset test goes red in both.

**One trap:** `->type()` is Playwright's `fill()`, which sets the whole value at
once. Six digits into an `OtpInput` box leave two behind, because the
controller treats it as neither typing nor pasting. What works is
`->typeSlowly('@form-otp-code-0', $code, 20)`: real keys, and the controller
moves focus between the boxes. The drivers have the same trap and get around it
with a hand-built paste.

**A frame with scripts in it.** The markup tests' frame
(`Fixtures/views/auth-layout.blade.php`) is only a title and a slot. The browser
tests have their own (`Fixtures/browser-views/`) with `@livewireStyles`,
`@wireStackScripts` and `@livewireScripts`. Every package that adopts this
needs one.

## What it did not answer

- **CI.** It was not added. It would mean Playwright + Chromium in the job and a
  separate step. Until then the tests are local only.
- **Gestures.** Drag, touch, the fill handle and keyboard grid navigation are
  things the drivers do through raw CDP (`Input.dispatchTouchEvent`,
  throttling). Whether Playwright can do all of them here was not tried. It
  should be able to, but that is a separate pilot.
- **Coverage.** The in-process server runs in the test's process, so coverage
  may count lines from browser tests. Not measured.
- **Assets.** `@wireStackScripts` served the controllers from the mirror in
  `tests/Pest.php` (`usePublicPath`). A package whose JS comes from a Vite
  build (`npm run build`) has not been tried.

## Recommendation for the decision

A split, not a migration:

- **Pest browser** for flows whose question is *who / with what data*:
  auth screens, two users over one library, a foreign record over a URL. These
  are the tests that fight the shared workbench state today.
- **CDP drivers** stay for Alpine/gestures/layout, where the question is *what
  the browser does with the markup* and the seeded workbench is an advantage
  (screenshots for the docs).

If the split holds, it belongs in an ADR, with a CI job, and without rewriting
any of the drivers that exist today — a driver moves only when it is changed
anyway. If it does not hold (CI too slow, gestures not reachable), remove the
plugin and the one test file, and `verify-auth-reset-code.mjs` stays as it is.
