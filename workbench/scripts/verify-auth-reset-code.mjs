import { readFile, stat } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { openPage, checker } from './lib/cdp.mjs';

/*
 * A forgotten password, reset by code, all the way through — in a browser.
 *
 * `verify-auth-codes.mjs` proves the reset screen is drawn right: boxes, an
 * address, a password, no token. It never redeems a code, and that is the half
 * the token change (the broker's token no longer rides in the code's payload,
 * it is minted in the request that spends the code) moved underneath. A screen
 * that renders perfectly and a controller that cannot finish the reset look
 * identical until somebody types the digits from the mail.
 *
 * So this one reads the mail. The workbench mails to the `log` transport, which
 * appends to the skeleton's `laravel.log`; the driver notes where the file ends
 * before asking, and takes the code from what was written after.
 *
 * The password is reset to the one the seeder gave, on an account no other
 * driver signs in as, so a sweep that runs this is left as it found it.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const address = 'sofia@example.com';
const password = 'password';
const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const mailLog = join(root, 'vendor/orchestra/testbench-core/laravel/storage/logs/laravel.log');
const { check, finish } = checker();

const page_ = await openPage({ url: `${origin}/forgot-password`, shotPrefix: 'auth-reset-code', width: 900, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const logSize = async () => (await stat(mailLog).catch(() => ({ size: 0 }))).size;

/**
 * The digits in the last code mail written after `offset`.
 *
 * The mail prints them spaced and bold (`**483 021**`), and the HTML half is
 * quoted-printable, so soft line breaks are undone before looking.
 */
const codeSince = async (offset) => {
  const text = (await readFile(mailLog, 'utf8').catch(() => '')).slice(offset).replace(/=\r?\n/g, '');
  const matches = [...text.matchAll(/(?:\*\*|<strong>)\s*(\d{3}) (\d{3})\s*(?:\*\*|<\/strong>)/g)];

  return matches.length === 0 ? null : matches.at(-1)[1] + matches.at(-1)[2];
};

const typeCode = (digits) => eval_(`
  (() => {
    const first = document.querySelector('[data-testid="form-otp-code-0"]');

    first.focus();
    first.value = ${JSON.stringify(digits)};
    first.dispatchEvent(new Event('input', { bubbles: true }));
  })()
`);

const fill = (form, values) => eval_(`
  (() => {
    const form = document.querySelector(${JSON.stringify(form)});

    for (const [name, value] of Object.entries(${JSON.stringify(values)})) {
      const input = form.querySelector('input[name="' + name + '"]');
      if (input) input.value = value;
    }
  })()
`);

/**
 * Submit a native form and wait for the page it leads to.
 *
 * Waiting for "the form is there" is not enough: the old document still has it
 * until the browser swaps documents, so a check straight after a submit reads
 * the page it was meant to leave. A mark on `window` dies with the old document.
 */
const submit = async (form) => {
  await eval_(`window.__leaving = true; document.querySelector(${JSON.stringify(form)}).submit()`);
  await waitFor(`! window.__leaving && document.readyState === 'complete'`, 10000);
};

/** The code form is back, with an error on it about the code. */
const refusedOnCodeForm = `
  !! document.querySelector('[data-testid="auth-reset-code-form"]')
    && /wrong|expired|špatn|vypršel/i.test(document.querySelector('[data-testid="auth-reset-code-form"]').innerText)
`;

try {
  await waitFor(`!! document.querySelector('[data-testid="auth-forgot-form"]')`, 10000);

  // ── 1. Asking lands on the code screen, and a mail goes out ──────────────
  const before = await logSize();

  await fill('[data-testid="auth-forgot-form"]', { email: address });
  await submit('[data-testid="auth-forgot-form"]');
  await waitFor(`!! document.querySelector('[data-testid="auth-reset-code-form"]')`, 10000);
  await waitFor(`!! window.Alpine`, 6000);

  check('asking for a reset lands on the screen that takes the code', await eval_(`
    !! document.querySelector('[data-testid="auth-reset-code-form"]')
  `));
  check('with the address already filled in', await eval_(`
    document.querySelector('[data-testid="auth-reset-code-form"] input[name="email"]')?.value === ${JSON.stringify(address)}
  `));

  let code = null;
  for (let i = 0; i < 20 && code === null; i++) {
    code = await codeSince(before);
    if (code === null) await new Promise((resolve) => setTimeout(resolve, 250));
  }

  check('the mail carries six digits', /^\d{6}$/.test(code ?? ''), `got ${code}`);
  await shot('01-reset-code-screen');

  // ── 2. A wrong code is refused, and does not reset anything ──────────────
  const wrong = code === '000000' ? '111111' : '000000';

  await typeCode(wrong);
  await waitFor(`document.querySelector('input[name="code"]')?.value === '${wrong}'`, 4000);
  await fill('[data-testid="auth-reset-code-form"]', { password: 'NotThePassword-9', password_confirmation: 'NotThePassword-9' });
  await submit('[data-testid="auth-reset-code-form"]');

  check('a wrong code is refused, on the screen it was typed on', await eval_(refusedOnCodeForm),
    await eval_(`location.pathname`));
  await shot('02-wrong-code');

  // ── 3. The right one resets the password ─────────────────────────────────
  // This is the step the token change sits under: the controller has no token
  // on the row any more and has to mint one before Fortify will reset.
  await waitFor(`!! window.Alpine`, 6000);
  await fill('[data-testid="auth-reset-code-form"]', { email: address, password, password_confirmation: password });
  await typeCode(code ?? '');
  await waitFor(`document.querySelector('input[name="code"]')?.value === ${JSON.stringify(code ?? '')}`, 4000);
  await submit('[data-testid="auth-reset-code-form"]');

  check('the right code resets the password and hands back to sign-in', await eval_(`
    location.pathname === '/login' && !! document.querySelector('[data-testid="auth-login-form"]')
  `), await eval_(`location.pathname + ' — ' + (document.querySelector('main, body')?.innerText ?? '').slice(0, 200)`));
  await shot('03-reset-done');

  // ── 4. The new password signs in ─────────────────────────────────────────
  await fill('[data-testid="auth-login-form"]', { email: address, password });
  await submit('[data-testid="auth-login-form"]');

  // The workbench mails a second factor to anybody without an authenticator
  // app, so an accepted password lands on that screen rather than the panel.
  // Either is the password being right; the login form again is it being wrong.
  check('the new password signs in', await eval_(`
    location.pathname !== '/login' && ! document.querySelector('[data-testid="auth-login-form"]')
  `), await eval_('location.pathname'));
  await shot('04-signed-in');

  // ── 5. The code was spent ────────────────────────────────────────────────
  // The reset screen is behind `guest`, so sign out first, through the same
  // form the user menu posts — harmless if the sign-in is still pending.
  await eval_(`
    (() => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/logout';
      const token = document.createElement('input');
      token.name = '_token';
      token.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
      form.append(token);
      document.body.append(form);
      form.submit();
    })()
  `);
  await new Promise((resolve) => setTimeout(resolve, 1000));
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/reset-password-code`)}`);
  await waitFor(`!! document.querySelector('[data-testid="auth-reset-code-form"]')`, 10000);
  await waitFor(`!! window.Alpine`, 6000);

  await fill('[data-testid="auth-reset-code-form"]', { email: address, password: 'SomethingElse-42', password_confirmation: 'SomethingElse-42' });
  await typeCode(code ?? '');
  await waitFor(`document.querySelector('input[name="code"]')?.value === ${JSON.stringify(code ?? '')}`, 4000);
  await submit('[data-testid="auth-reset-code-form"]');

  check('the same code a second time resets nothing', await eval_(refusedOnCodeForm),
    await eval_('location.pathname'));
  await shot('05-code-spent');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
