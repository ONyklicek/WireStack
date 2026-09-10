import { openPage, checker } from './lib/cdp.mjs';

/*
 * Passkeys, end to end, with a virtual authenticator.
 *
 * This is the one surface in the repository where a Pest test can prove almost
 * nothing. The credential is created by the *platform* — Touch ID, Windows
 * Hello, a phone — inside a dialog no page can script, and the value it returns
 * is a signature over a challenge the server issued. A markup assertion sees a
 * button; a browser without an authenticator sees a dialog it cannot answer.
 *
 * Chrome's WebAuthn domain solves exactly that: `addVirtualAuthenticator` puts a
 * software authenticator behind `navigator.credentials`, with user presence and
 * verification simulated. So the ceremony really runs — the same
 * `@laravel/passkeys` client, the same `laravel/passkeys` routes, the same
 * signature check — and what this driver proves is the thing nothing else can:
 * that a key registered on the profile card can be signed in with afterwards.
 *
 * Three things it is here to catch, all of which render perfectly:
 *
 *   - the bundle not reaching the page. It is deliberately not declared as a
 *     `@wireStackScripts` entry (12 kB on every page), so both surfaces include
 *     `wire-core::partials.passkey-assets` — and a screen that forgot it has a
 *     button bound to an Alpine component that does not exist.
 *   - the routes passed from PHP. `Passkeys.verify()` takes the URLs the view
 *     gives it, because Fortify lets an application rename every path it
 *     registers. A wrong one posts into a 404 and reports "that did not work".
 *   - the origin. WebAuthn is bound to one, and a relying party that disagrees
 *     with the address the page is served from fails inside the browser, before
 *     any of this repository's code runs.
 */

// By name rather than by address, and it is not a preference: WebAuthn's
// secure-context exception is written for `localhost`, and Laravel's own client
// refuses `127.0.0.1` before the browser is asked anything ("For local
// development, use localhost"). Every other driver here browses the IP.
const origin = (process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085').replace('127.0.0.1', 'localhost');
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/previews/routed/invoices`, shotPrefix: 'passkeys', width: 1100, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  // ── The authenticator, before anything asks for one ──────────────────────
  // `internal` + resident keys is a platform authenticator: what a laptop with a
  // fingerprint reader looks like, and the only kind a *discoverable* sign-in —
  // no address typed first — can come from.
  await page('WebAuthn.enable', { enableUI: false });

  const { authenticatorId } = await page('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });

  check('a virtual authenticator is attached', typeof authenticatorId === 'string');

  // ── 1. The card, on the profile page ─────────────────────────────────────
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/routed/users/profile`)}`);
  await waitFor(`!! document.querySelector('[data-testid="profile-passkeys"]')`, 12000);
  await waitFor(`!! window.Alpine`, 8000);

  check('the profile page draws the passkey card', await eval_(`
    !! document.querySelector('[data-testid="profile-passkeys"]')
  `));
  const before = await eval_(`document.querySelectorAll('[data-testid="passkeys-list"] li').length`);
  // The controller is core's, and it is on the page only because the card
  // includes the partial that emits it — the failure this catches is a button
  // bound to a component that was never registered.
  check('and the controller the button is bound to', await eval_(`
    typeof window.Alpine.data === 'function'
      && !! document.querySelector('[data-testid="passkeys-add"]')?.offsetParent
  `));
  await shot('01-card-empty');

  // ── 2. Register one, for real ────────────────────────────────────────────
  await eval_(`
    (() => {
      const input = document.querySelector('[data-testid="passkey-name"]');
      input.value = 'Driver laptop';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    })()
  `);

  await eval_(`document.querySelector('[data-testid="passkey-add"]').click()`);

  // The list is server-rendered, so what is waited for is the row — not the
  // ceremony, which finished somewhere inside the browser.
  await waitFor(`
    !! [...document.querySelectorAll('[data-testid="passkeys-list"] li')]
      .find((row) => row.textContent.includes('Driver laptop'))
  `, 15000);

  check('a passkey can be registered from the card', await eval_(`
    !! [...document.querySelectorAll('[data-testid="passkeys-list"] li')]
      .find((row) => row.textContent.includes('Driver laptop'))
  `));
  check('and the list grew by exactly the one that was added',
    (await eval_(`document.querySelectorAll('[data-testid="passkeys-list"] li').length`)) === before + 1);

  const credentials = await page('WebAuthn.getCredentials', { authenticatorId });
  check('the credential really reached the authenticator', (credentials?.credentials ?? []).length === 1);
  check('and it is discoverable, or a sign-in would need the address typed first',
    (credentials?.credentials ?? [])[0]?.isResidentCredential === true);
  await shot('02-card-registered');

  // ── 3. Sign out, and sign back in with it ────────────────────────────────
  await eval_(`
    (() => {
      const form = document.querySelector('[data-testid="auth-sign-out"]')?.closest('form');
      if (form) form.submit();
    })()
  `);
  await waitFor(`window.location.pathname !== '/previews/routed/users/profile'`, 10000);

  await eval_(`window.location.href = ${JSON.stringify(`${origin}/login`)}`);
  await waitFor(`!! document.querySelector('[data-testid="auth-passkey-button"]')`, 12000);
  await waitFor(`!! window.Alpine`, 8000);

  check('the sign-in screen offers the passkey', await eval_(`
    !! document.querySelector('[data-testid="auth-passkey-button"]')?.offsetParent
  `));
  // The token the browser's own credential dropdown anchors to. Without an input
  // carrying it, autofill shows nothing and says nothing about why.
  check('and the address field carries the webauthn autofill token', await eval_(`
    (document.querySelector('input[name="email"]')?.getAttribute('autocomplete') ?? '').includes('webauthn')
  `));
  await shot('03-login-offer');

  // Click only if the screen is still there. With a resident credential on a
  // platform authenticator, the browser's own credential dropdown can complete
  // the ceremony on its own — `autofill: true` on this screen asks for exactly
  // that — and a driver that clicked unconditionally would fail on the button
  // being gone *because the thing it was testing already worked*.
  if (await eval_(`!! document.querySelector('[data-testid="auth-passkey-button"]')`)) {
    await eval_(`document.querySelector('[data-testid="auth-passkey-button"]').click()`);
  }

  // Signed in is a *navigation*: the client follows the redirect the server put
  // in its reply, which is Fortify's answer to where a sign-in lands.
  await waitFor(`! document.location.pathname.startsWith('/login')`, 15000);

  check('signing in with it opens a session', await eval_(`
    ! document.location.pathname.startsWith('/login')
  `));
  check('and no error was left under the button', await eval_(`
    ! document.querySelector('[data-testid="auth-passkey-error"]')?.textContent?.trim()
  `));
  await shot('04-signed-in');

  // ── 4. And take it away again ────────────────────────────────────────────
  // Both the last check and the tidy-up: the demo database outlives this run,
  // so a driver that only ever adds rows fails the next sweep on its own
  // leftovers.
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/routed/users/profile`)}`);
  await waitFor(`!! document.querySelector('[data-testid="passkey-remove"]')`, 12000);
  await waitFor(`!! window.Alpine`, 8000);

  // `wire:confirm` is a real `window.confirm`, and in a headless browser nothing
  // answers it — the renderer would block until the driver timed out. Answering
  // it in the page is smaller than driving the dialog over CDP, and it is the
  // dialog rather than the removal that is being got out of the way.
  await eval_(`window.confirm = () => true`);
  await eval_(`document.querySelector('[data-testid="passkey-remove"]').click()`);
  await waitFor(`document.querySelectorAll('[data-testid="passkeys-list"] li').length === ${before}`, 12000);

  check('and it can be taken away again', (await eval_(`
    document.querySelectorAll('[data-testid="passkeys-list"] li').length
  `)) === before);
  await shot('05-removed');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
