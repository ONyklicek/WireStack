import { openPage, checker } from './lib/cdp.mjs';

/*
 * The sign-in screen with a passkey-capable browser that holds no passkey.
 *
 * Found on a live installation: the screen reloaded itself about twice a
 * second. It starts a conditional (autofill) passkey ceremony on load; with no
 * credential for the site the browser rejects it, `@laravel/passkeys` answers
 * `undefined` for "nothing signed in", and the controller treated that as an
 * arrival — `location.href = '/'`, bounced straight back to /login by `guest`,
 * a new ceremony, round again, until the passkey limiter answered 429 and the
 * button failed too. The same `undefined` arrives when the button aborts the
 * autofill to start its own ceremony, so a press was cut off by a navigation.
 *
 * `verify-passkeys.mjs` never saw it: it registers a key first, and with a key
 * the autofill succeeds. This one is the other half — the first visit, before
 * anybody has added one.
 *
 * Browses `localhost`, for the reason verify-passkeys.mjs gives.
 */

const origin = (process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085').replace('127.0.0.1', 'localhost');
const { check, finish } = checker();

// Counted from inside every document, before the page's own scripts run: a
// reload loop is a sequence of documents, and nothing on any one of them sees it.
// about:blank, where the driver starts, has no storage to write to.
const preload = `(() => {
  try {
    const trace = JSON.parse(sessionStorage.getItem('__trace') || '[]');
    trace.push(['document', location.pathname]);
    sessionStorage.setItem('__trace', JSON.stringify(trace));
  } catch (e) {}
})();`;

const page_ = await openPage({ url: 'about:blank', shotPrefix: 'passkey-autofill', width: 1000, height: 800, preload });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  await page('WebAuthn.enable', { enableUI: false });
  await page('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });

  await eval_(`window.location.href = ${JSON.stringify(`${origin}/login`)}`);
  await waitFor(`!! document.querySelector('[data-testid="auth-passkey-button"]') && !! window.Alpine`, 15000);

  // Long enough for several rounds of the old loop (one every ~450ms).
  await new Promise((resolve) => setTimeout(resolve, 3000));

  const documents = await eval_(`JSON.parse(sessionStorage.getItem('__trace') || '[]').filter(([kind]) => kind === 'document').length`);

  check('the sign-in screen stays put when the browser has no passkey', documents === 1, `${documents} document(s) loaded`);
  check('and is still the sign-in screen', await eval_(`location.pathname === '/login'`), await eval_('location.pathname'));

  await eval_(`document.querySelector('[data-testid="auth-passkey-button"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="auth-passkey-notice"]')?.textContent.trim()`, 10000).catch(() => {});

  check('pressing the button says what to do instead of nothing', await eval_(`
    !! document.querySelector('[data-testid="auth-passkey-notice"]')?.textContent.trim()
      && ! document.querySelector('[data-testid="auth-passkey-error"]')?.textContent.trim()
  `));
  check('without navigating away from under the person', await eval_(`
    location.pathname === '/login'
      && JSON.parse(sessionStorage.getItem('__trace') || '[]').filter(([kind]) => kind === 'document').length === 1
  `));
  check('and the limiter was never reached', ! badResponses.some((entry) => String(entry).startsWith('429')), badResponses.join('; '));

  await shot('01-no-passkey');
  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
