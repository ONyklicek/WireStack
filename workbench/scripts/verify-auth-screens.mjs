import { openPage, checker } from './lib/cdp.mjs';

/*
 * The screens on the way in.
 *
 * Three things here cannot be asserted from Pest, and all three are the kind
 * that render perfectly while being wrong:
 *
 *   - the screens reach the browser through Fortify's routes and this repo's
 *     frame. Pest renders a response; only a browser says whether the assets,
 *     the theme decision and the card actually arrive together.
 *   - the two-factor challenge switches between a code and a recovery code in
 *     Alpine. A markup assertion passes for a toggle that does nothing, and the
 *     input it reveals has to be focused — `focus()` on a hidden element is a
 *     no-op that reports nothing.
 *   - `x-cloak` on the hidden half only works if the stylesheet that hides it
 *     is on the page. Without it, both halves of the challenge are visible for
 *     the first frame and the screen reads as broken.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/login`, shotPrefix: 'auth-screens', width: 900, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  await waitFor(`!! document.querySelector('[data-testid="auth-login-form"]')`);

  // ── 1. The sign-in screen, in the shell's frame ──────────────────────────
  check('the login screen renders', await eval_(`!! document.querySelector('[data-testid="auth-login-form"]')`));
  check('inside the shell\'s signed-out frame', await eval_(`!! document.querySelector('[data-testid="admin-auth"]')`));
  check('with the fields Fortify posts on', await eval_(`
    !! document.querySelector('input[name="email"]') && !! document.querySelector('input[name="password"]')
  `));
  // A POST with a token. A GET logout — or a login form without one — is a
  // 419 the first time somebody actually uses it.
  check('and a CSRF token', await eval_(`!! document.querySelector('[data-testid="auth-login-form"] input[name="_token"]')`));

  // The interaction layer is on the page even here: the frame is the shell's,
  // so the theme decision and the asset directives are the ones the panel uses.
  check('the theme decision runs before the paint', await eval_(`typeof window.wireAdminTheme === 'object'`));
  await shot('01-login');

  // ── 2. The links are only drawn for routes that exist ────────────────────
  // The workbench enables password resets and leaves registration off, which is
  // what an admin panel looks like.
  check('a way to a forgotten password', await eval_(`!! document.querySelector('[data-testid="auth-forgot-link"]')`));
  // Every feature Fortify can route is on in this workbench, so every link is
  // drawn — which is the half a browser can check. That a link *disappears*
  // with its feature is a question about config, and `ScreensTest` asks it
  // where config questions belong.
  check('and an invitation to register, which is routed here', await eval_(`
    !! document.querySelector('[data-testid="auth-register-link"]')
  `));

  const forgot = await eval_(`document.querySelector('[data-testid="auth-forgot-link"]').href`);
  await eval_(`document.querySelector('[data-testid="auth-forgot-link"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="auth-forgot-form"]')`, 6000);
  check('which leads to a screen that asks for an address', await eval_(`
    !! document.querySelector('[data-testid="auth-forgot-form"] input[name="email"]')
  `));
  check('and offers the way back', await eval_(`!! document.querySelector('[data-testid="auth-back-link"]')`));
  check('the link was a real URL, not a fragment', typeof forgot === 'string' && forgot.includes('forgot-password'));
  await shot('02-forgot');

  // ── 3. The two-factor challenge switches, in the browser ─────────────────
  // Through the workbench route that puts Fortify's own session key in place,
  // not by visiting the URL: the challenge is served from a **half**
  // authenticated session and Fortify redirects anyone without one back to the
  // login form. Visiting it directly asserted nothing and looked like it
  // asserted six things — which is how this driver first passed with the focus
  // handler deleted.
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/auth/two-factor`)}`);
  await waitFor(`!! document.querySelector('[data-testid="auth-two-factor-form"]')`, 8000);
  await waitFor(`!! window.Alpine`, 4000);

  check('the challenge is reached with a sign-in pending', await eval_(`
    !! document.querySelector('[data-testid="auth-two-factor-form"]')
  `));

  // The code is six boxes, and the input carrying its name is the one a browser
  // with no Alpine would type into — so with Alpine it is on the page and
  // hidden. Both halves of that are asserted, because getting either wrong
  // renders perfectly: visible boxes that post nothing, or a stray text input
  // beside them.
  check('the code arrives as boxes', await eval_(`
    document.querySelectorAll('[data-testid^="form-otp-code-"]').length >= 7
  `));
  check('with one named input behind them, hidden', await eval_(`
    !! document.querySelector('input[name="code"]')
      && ! document.querySelector('input[name="code"]')?.offsetParent
  `));

  // x-cloak, and the stylesheet that gives it meaning. Without it both halves
  // are visible for the first frame.
  check('only the code half is visible to start', await eval_(`
    !! document.querySelector('[data-testid="form-otp-code-0"]')?.offsetParent
      && ! document.querySelector('input[name="recovery_code"]')?.offsetParent
  `));

  // Typing into the boxes has to reach the input that posts, or the challenge
  // submits an empty code — the failure native submit exists to make impossible.
  await eval_(`
    (() => {
      const first = document.querySelector('[data-testid="form-otp-code-0"]');
      first.focus();
      first.value = '4';
      first.dispatchEvent(new Event('input', { bubbles: true }));
    })()
  `);
  await waitFor(`document.querySelector('input[name="code"]')?.value === '4'`, 4000);
  check('and a digit typed into a box reaches the field that posts', await eval_(`
    document.querySelector('input[name="code"]').value === '4'
  `));

  await eval_(`document.querySelector('[data-testid="auth-two-factor-toggle"]').click()`);
  await waitFor(`!! document.querySelector('input[name="recovery_code"]')?.offsetParent`, 4000);

  check('the toggle swaps in the recovery code', await eval_(`
    !! document.querySelector('input[name="recovery_code"]')?.offsetParent
      && ! document.querySelector('[data-testid="form-otp-code-0"]')?.offsetParent
  `));

  // The half a markup test cannot see: focus() on an element that is still
  // hidden is a no-op that reports nothing, so the switch is only finished if
  // the caret ends up in the field it just revealed.
  check('and puts the caret in it', await eval_(`document.activeElement?.name === 'recovery_code'`));

  await eval_(`document.querySelector('[data-testid="auth-two-factor-toggle"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="form-otp-code-0"]')?.offsetParent`, 4000);
  check('and switches back, into the first box', await eval_(`
    document.activeElement?.dataset.testid === 'form-otp-code-0'
  `));
  await shot('03-two-factor');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
