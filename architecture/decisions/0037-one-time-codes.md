# ADR 0037: One-Time Codes on the Way In

## Status

ACCEPTED — 2026-09-10. Requested by the repo owner ("asi bych chtěl v auth
podporu otp"), for all four flows a code can stand in for: signing in without a
password, a second factor delivered by mail, confirming an address, and resetting
a password.

Amends [ADR 0032](0032-authentication-surface.md), whose first rule — *Fortify
owns authentication, this repository owns none of it* — is the one this decision
has to bend without breaking. Uses the native-submit forms of
[ADR 0036](0036-native-submit-forms.md) and the `OtpInput` field that already
renders Fortify's TOTP challenge.

## Context

`wire-module-auth` answers Fortify's seven view callbacks and owns no
authentication. Four requests arrived at once, and only one of them fits inside
that sentence:

| Ask | Fortify 1.39 |
| --- | --- |
| Sign in with a code sent by mail | nothing — no passwordless flow at all |
| A second factor that is not an authenticator app | `TwoFactorAuthenticationProvider` verifies TOTP against `two_factor_secret`; a mailed code has no secret to verify against |
| Boxes instead of one input where a code is typed | already the case on the challenge; the *setup* card in `wire-module-users` is a plain `<input>` |
| A code in the mail instead of a link | the broker's token is a 40-character string and the notification is a link to it |

So three of the four need a code that this repository issues, stores, expires and
verifies. There is no maintained first-party owner to delegate that to, and
`grep` over `vendor/laravel/fortify` for `otp`, `one-time` and `passwordless`
returns nothing.

The temptation is to write four small flows, each with its own code column, its
own expiry and its own idea of how many wrong guesses are too many. That is four
security surfaces, and the one with the weakest expiry is the one that matters.

## Decision

### 1. One mechanism issues every code, and it is the only thing this package owns

`Contracts\OneTimeCodes` — issue, verify, invalidate, and "was one just sent" —
with `Services\DatabaseOneTimeCodes` behind it. One table, one hashing rule
(`Hash::make`, the same as Laravel's own token repository), one expiry, one
attempt counter, one resend throttle, scoped by a `CodePurpose`.

**A code is scoped to what it is for.** A code mailed to confirm an address
cannot be typed into the sign-in challenge: `purpose` is part of the lookup, not
a label on the row. Four flows sharing one table without that is one flow.

**The plain code exists once**, in the return value of `issue()`, on its way into
the notification. What the row holds is a hash, so a database copy is not a set
of live credentials.

**Wrong guesses are counted on the code, not only on the route.** Laravel's
`RateLimiter` throttles the address and the IP; the attempt counter kills the
code itself after `codes.attempts` tries, which is what stops a six-digit code
from being walked through from a hundred addresses.

### 2. Everything Fortify already does stays with Fortify

The four flows are wired so that the security-critical half is Fortify's or
Laravel's wherever one exists:

- **Second factor by mail** — `Actions\RedirectIfCodeRequired` *extends*
  `RedirectIfTwoFactorAuthenticatable` and is bound to the
  `RedirectsIfTwoFactorAuthenticatable` contract Fortify's own pipeline resolves.
  So the credential check, the failed-login event, the login throttle and the
  `login.id` session key are Fortify's, unchanged; the subclass answers one
  question — *this user has no authenticator app but wants a code* — and
  redirects to a challenge instead of Fortify's. A TOTP secret always wins.
  Rejected: composing our own `Fortify::authenticateThrough()` list, which is a
  copy of Fortify's default pipeline that drifts on Fortify's next release.
- **Reset by code** — the broker's token is untouched and still what resets the
  password. The mail carries a code, the code's *payload* is that token, and
  `PasswordResetCodeController` swaps one for the other and hands the request to
  Fortify's `NewPasswordController`. Token expiry, single use and the reset
  action itself never move.
- **Verify by code** — the controller marks the address verified and fires
  `Illuminate\Auth\Events\Verified`, which is what Fortify's own controller does
  with a signed link; the signed link keeps working beside it.
- **Passwordless sign-in** — the one flow with no Fortify half to keep. It ends
  in `Auth::login()` and `session()->regenerate()`, and a user with two-factor on
  is handed to Fortify's challenge rather than let in: a code to an inbox is one
  factor, and it must not be a way around the second.

### 3. Every flow is off by default, and each one is its own switch

`wire-module-auth.codes.login`, `.second_factor`, `.verify_email`,
`.reset_password`. An installation that says nothing gets exactly the surface ADR
0032 describes, and no new routes appear in `route:list`.

They are four switches rather than one because they are four different
decisions about the same trade: a code in an inbox is only as strong as the
inbox. An application may well want a mailed second factor and not want a mailed
password to be a way in on its own.

### 4. Who gets a mailed second factor is the model's answer, then config's

`Contracts\ReceivesLoginCodes` — one method, `wantsLoginCode()`. A user model
that implements it decides per user (a column, a preference, a role); a model
that does not falls back to the config switch for everyone without a confirmed
TOTP secret. The alternative was a migration on the application's `users` table,
which a package has no business writing.

### 5. The mailed second factor needs Fortify's two-factor feature on

Fortify only puts `RedirectsIfTwoFactorAuthenticatable` in its login pipeline
when `Features::twoFactorAuthentication()` is enabled, and §2 is built on being
that binding. With the feature off, the pipe is not in the pipeline and no code
is ever sent — so the installer reports it, `php artisan about` shows it, and the
config comment says it.

Rejected: forcing the feature on from the package, which would turn on the TOTP
routes and the profile card as a side effect of an unrelated switch.

### 6. The boxes are the same field everywhere a code is typed

`OtpInput` renders the challenge on the way in, the two new code screens, and —
now — the confirmation step of the two-factor card in `wire-module-users`. That
last one was a plain `<input autocomplete="one-time-code">`: the same six digits
in a different shape, three clicks from the screen that draws boxes for them.

## Consequences

### Positive

- A code has one owner, one expiry and one attempt counter across four flows.
- Fortify still owns every part of authentication it had an answer for, so the
  new surface is the code table and nothing else.
- The passwordless flow cannot be used to skip a second factor.
- An installation that wants none of this sees no change at all.

### Negative / risks

- **This repository now issues credentials.** ADR 0032 §1 said it would not. The
  boundary moved for the three flows Fortify has no answer to, and it is written
  down here so the next addition has to argue against a decision rather than
  against a habit.
- **A six-digit code is weaker than a 40-character token**, which is why the
  reset flow keeps the broker token underneath and why expiry, attempts and the
  resend throttle are not optional.
- **`RedirectIfCodeRequired` extends a Fortify class.** A change to that class's
  `handle()` is a change to this package's behaviour. It is the smallest such
  coupling available — the alternative in §2 copies more of Fortify, not less —
  and the tests go through Fortify's login route so a break is loud.
- **Mail delivery is the application's.** A code that never arrives looks like a
  wrong code, and nothing in this package can tell the difference.
