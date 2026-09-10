import { openPage, checker } from './lib/cdp.mjs';

/*
 * The four one-time-code screens (ADR 0037), in a browser.
 *
 * `verify-auth-screens.mjs` covers the screens Fortify routes; these are the
 * ones this repository routes itself, and three things about them cannot be
 * asserted from Pest:
 *
 *   - the code is six boxes driven by Alpine with one named input behind them.
 *     Pest sees the markup; only a browser says whether typing into a box
 *     reaches the field that posts — the failure is a form that submits an
 *     empty code and reports nothing.
 *   - the screens are one view rendered for three flows, with the URLs passed
 *     in. A wrong one renders perfectly and posts to the wrong controller, and
 *     the only place that shows is a real submit.
 *   - the resend button is its own `<form>`. Pressing Enter in the boxes must
 *     submit the code, not mail a new one over the top of the one being typed.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const address = 'toast-driver@example.test';
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/login`, shotPrefix: 'auth-codes', width: 900, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

/**
 * Put a code into the boxes the way a person with the mail open does: paste it.
 *
 * Into the first box, whole. The controller treats more than one character in a
 * box as a paste and fills the row from there, which is the gesture worth
 * driving — and setting each box in turn instead loses digits, because the
 * controller moves the caret between them while the loop is still assigning.
 */
const typeCode = (digits) => eval_(`
  (() => {
    const first = document.querySelector('[data-testid="form-otp-code-0"]');

    first.focus();
    first.value = ${JSON.stringify(digits)};
    first.dispatchEvent(new Event('input', { bubbles: true }));
  })()
`);

try {
  await waitFor(`!! document.querySelector('[data-testid="auth-login-form"]')`);

  // ── 1. The second way in is offered, and leads somewhere ─────────────────
  check('the sign-in screen offers a code instead of a password', await eval_(`
    !! document.querySelector('[data-testid="auth-code-link"]')
  `));

  await eval_(`document.querySelector('[data-testid="auth-code-link"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="auth-login-code-form"]')`, 8000);

  check('which asks for an address, and nothing else', await eval_(`
    !! document.querySelector('[data-testid="auth-login-code-form"] input[name="email"]')
      && ! document.querySelector('[data-testid="auth-login-code-form"] input[name="password"]')
  `));
  check('with a CSRF token, or the first real use is a 419', await eval_(`
    !! document.querySelector('[data-testid="auth-login-code-form"] input[name="_token"]')
  `));
  await shot('01-login-code');

  // ── 2. Asking for one lands on the boxes ─────────────────────────────────
  await eval_(`
    (() => {
      const form = document.querySelector('[data-testid="auth-login-code-form"]');
      form.querySelector('input[name="email"]').value = ${JSON.stringify(address)};
      form.submit();
    })()
  `);
  await waitFor(`!! document.querySelector('[data-testid="auth-code-form"]')`, 10000);
  await waitFor(`!! window.Alpine`, 6000);

  check('asking for a code lands on the screen that takes one', await eval_(`
    !! document.querySelector('[data-testid="auth-code-form"]')
  `));
  check('which says which inbox to look in', await eval_(`
    document.body.textContent.includes(${JSON.stringify(address)})
  `));
  check('the code is boxes', await eval_(`
    document.querySelectorAll('[data-testid^="form-otp-code-"]').length >= 7
  `));
  check('with one named input behind them, hidden', await eval_(`
    !! document.querySelector('input[name="code"]')
      && ! document.querySelector('input[name="code"]')?.offsetParent
  `));

  // The half that only a browser can answer: a code typed into the boxes has to
  // reach the field that posts, or the form submits an empty code.
  await typeCode('483021');
  await waitFor(`document.querySelector('input[name="code"]')?.value === '483021'`, 4000);
  check('and a code pasted into the boxes reaches the field that posts', await eval_(`
    document.querySelector('input[name="code"]').value === '483021'
  `));

  // Two forms on one screen, and which one Enter belongs to. A resend button
  // inside the code form would mail a new code over the one being typed.
  check('the resend button is a form of its own', await eval_(`
    document.querySelector('[data-testid="auth-code-resend"]')?.closest('form')
      !== document.querySelector('[data-testid="auth-code-form"]')
  `));
  check('and posts somewhere else than the code does', await eval_(`
    document.querySelector('[data-testid="auth-code-resend"]').closest('form').action
      !== document.querySelector('[data-testid="auth-code-form"]').action
  `));
  await shot('02-login-code-challenge');

  // A wrong code is refused, and says so on the field rather than anywhere else.
  await typeCode('000000');
  await eval_(`document.querySelector('[data-testid="auth-code-form"]').submit()`);
  await waitFor(`!! document.querySelector('[data-testid="auth-errors"]')`, 10000);
  check('a wrong code is refused, on the screen it was typed on', await eval_(`
    !! document.querySelector('[data-testid="auth-errors"]')
      && !! document.querySelector('[data-testid="auth-code-form"]')
  `));
  await shot('03-login-code-refused');

  // ── 3. The mailed second factor, from a pending sign-in ──────────────────
  // Through the workbench route that puts Fortify's own session key in place:
  // the screen is served from a half-authenticated session, and visiting the URL
  // cold is a redirect a driver would happily photograph.
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/auth/second-factor-code`)}`);
  await waitFor(`!! document.querySelector('[data-testid="auth-code-form"]')`, 10000);
  await waitFor(`!! window.Alpine`, 6000);

  check('the second factor is reached with a sign-in pending', await eval_(`
    !! document.querySelector('[data-testid="auth-code-form"]')
  `));
  check('and posts to its own route, not to the passwordless one', await eval_(`
    document.querySelector('[data-testid="auth-code-form"]').action.includes('/two-factor/code')
  `));
  await shot('04-second-factor-code');

  // ── 4. A new password from a code ────────────────────────────────────────
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/reset-password-code`)}`);
  await waitFor(`!! document.querySelector('[data-testid="auth-reset-code-form"]')`, 10000);
  await waitFor(`!! window.Alpine`, 6000);

  check('the reset screen takes a code, an address and a new password', await eval_(`
    !! document.querySelector('[data-testid="auth-reset-code-form"] input[name="email"]')
      && !! document.querySelector('[data-testid="auth-reset-code-form"] input[name="password"]')
      && document.querySelectorAll('[data-testid^="form-otp-code-"]').length >= 7
  `));
  // No token on the screen at all: it is what the code's row carries, and a
  // screen that showed it would be a screen where the code is decoration.
  check('and no token, which is the whole point of the code', await eval_(`
    ! document.querySelector('[data-testid="auth-reset-code-form"] input[name="token"]')
  `));

  await typeCode('483021');
  await waitFor(`document.querySelector('input[name="code"]')?.value === '483021'`, 4000);
  check('the same boxes, on the screen that also takes a new password', await eval_(`
    document.querySelector('input[name="code"]').value === '483021'
  `));
  await shot('06-reset-password-code');

  // ── 5. Confirming an address by code ─────────────────────────────────────
  // Last, because the preview route that reaches it signs somebody in — and
  // every other screen here is behind `guest`, so a step after this one would
  // be photographing a redirect.
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/auth/verify-email-code`)}`);
  await waitFor(`!! document.querySelector('[data-testid="auth-code-form"]')`, 10000);
  await waitFor(`!! window.Alpine`, 6000);

  check('the address confirmation asks for a code too', await eval_(`
    document.querySelector('[data-testid="auth-code-form"]').action.includes('/email/verify/code')
  `));
  await shot('05-verify-email-code');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
