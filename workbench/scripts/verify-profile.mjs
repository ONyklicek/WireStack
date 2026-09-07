import { openPage, checker } from './lib/cdp.mjs';

/*
 * The signed-in user's own page, which was reported as "an unformatted bare
 * thing": three full-width inputs and a Save button, with the password sitting
 * among them under "leave empty to keep the current one".
 *
 * What only a browser can answer here is the part Pest cannot see:
 *
 *   - each card is its own Livewire component, so a failed password change must
 *     not touch the profile form standing above it, and vice versa. Pest asserts
 *     one component at a time; this is the page they share.
 *   - the two-factor card has three states and the middle one is where an
 *     interrupted setup lands. Getting into it, and back out, is a sequence of
 *     round trips.
 *   - the team switcher is mounted by the chrome registry, not by this page —
 *     the markup is registered in a provider and rendered by the shell's layout,
 *     which is exactly the wiring a rendered page proves and a unit test assumes.
 *   - the avatar control replaced a dashed dropzone with a round preview and a
 *     button. Whether it is *there* is markup; whether it opens a picker is not.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/users/profile`, shotPrefix: 'profile', width: 1300, height: 1200 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const cardIds = () => eval_(`[...document.querySelectorAll('[data-testid^="profile-"]')].map(e => e.dataset.testid)`);

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="profile-information"]')`, 8000);

  // ── 1. One page, four questions ──────────────────────────────────────────
  const cards = await cardIds();
  check('the profile is a page of cards, not one form', cards.includes('profile-information'), cards.join(', '));
  check('the password has a card of its own', cards.includes('profile-password'));
  check('two-factor has one too, because Fortify is here', cards.includes('profile-two-factor'));
  check('and so does closing the account, where it is switched on', cards.includes('profile-delete-account'));
  await shot('01-profile');

  // ── 2. The photo ─────────────────────────────────────────────────────────
  // `avatar()` used to change only the shape of a thumbnail, so one picture of
  // one person got the same full-width dashed dropzone as a document library.
  check('the photo is a round control, not a drop target with a file list', await eval_(`
    !! document.querySelector('[data-testid="form-file-data.avatar_path-dropzone"]')
      && ! document.body.textContent.includes('or drag and drop')
  `));
  check('with a button that opens a picker', await eval_(`!! document.querySelector('[data-testid="form-file-data.avatar_path-choose"]')`));

  // ── 3. The cards do not stand on each other ──────────────────────────────
  // A wrong current password must fail inside its own card. The one-form
  // alternative has to explain "your name saved but your password did not".
  await eval_(`(() => {
    const set = (path, value) => {
      const el = document.querySelector(\`[data-testid="profile-password"] input[wire\\\\:model="\${path}"], [data-testid="profile-password"] input[id$="\${path.split('.').pop()}"]\`);
      if (! el) return false;
      el.focus(); el.value = value; el.dispatchEvent(new Event('input', { bubbles: true }));
      return true;
    };
    set('data.current_password', 'definitely-not-it');
    set('data.password', 'a-much-longer-new-one');
    set('data.password_confirmation', 'a-much-longer-new-one');
  })()`);

  await eval_(`document.querySelector('[data-testid="password-save"]').click()`);
  await waitFor(`document.querySelector('[data-testid="profile-password"]').textContent.match(/password is incorrect|nesouhlas/i)`, 6000)
    .then(() => check('a wrong current password is refused, in its own card', true))
    .catch(() => check('a wrong current password is refused, in its own card', false));

  check('and the profile form above it is untouched', await eval_(`
    document.querySelector('[data-testid="profile-information"] input[type="text"]')?.value?.length > 0
  `));
  await shot('02-password-refused');

  // ── 4. Two-factor: three states, and the middle one is leavable ──────────
  // The preview database is shared with every other run, and this driver turns
  // two-factor on. Reset first rather than assume: a driver that only passes on
  // a freshly seeded database is one that reports a bug on the second run.
  if (await eval_(`!! document.querySelector('[data-testid="two-factor-disable"]')`)) {
    await eval_(`document.querySelector('[data-testid="two-factor-disable"]').click()`);
    await waitFor(`!! document.querySelector('[data-testid="two-factor-enable"]')`, 8000);
  }

  check('two-factor starts off', (await eval_(`document.querySelector('[data-testid="two-factor-state"]')?.textContent.trim()`)) === 'Off');

  await eval_(`document.querySelector('[data-testid="two-factor-enable"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="two-factor-qr"] svg')`, 8000);

  // Fortify writes the secret the moment the QR code is generated, so somebody
  // who opened this and closed the tab has one and is protected by nothing.
  check('turning it on shows a code to scan', await eval_(`!! document.querySelector('[data-testid="two-factor-qr"] svg')`));
  check('and a key for a phone that cannot scan', await eval_(`(document.querySelector('[data-testid="two-factor-setup-key"]')?.textContent ?? '').trim().length > 10`));
  check('but does not yet claim to be on', (await eval_(`document.querySelector('[data-testid="two-factor-state"]')?.textContent.trim()`)) !== 'On');
  await shot('03-two-factor-pending');

  // The half-finished state has to be leavable in both directions, or it is a
  // trap: this is the state an interrupted setup is left in.
  check('the half-finished setup offers a way back out', await eval_(`!! document.querySelector('[data-testid="two-factor-disable"]')`));

  await eval_(`document.querySelector('[data-testid="two-factor-disable"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="two-factor-enable"]')`, 8000);
  check('and taking it puts the card back to off', (await eval_(`document.querySelector('[data-testid="two-factor-state"]')?.textContent.trim()`)) === 'Off');

  // ── 5. Closing the account asks twice ────────────────────────────────────
  await eval_(`document.querySelector('[data-testid="delete-account-open"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="delete-account-confirm"]')?.offsetParent`, 6000);
  check('deleting an account opens a dialog rather than doing it', await eval_(`
    !! document.querySelector('[data-testid="delete-account-confirm"]')?.offsetParent
  `));
  await shot('04-delete-dialog');

  await eval_(`document.querySelector('[data-testid="delete-account-confirm"]').click()`);
  await waitFor(`document.body.textContent.match(/password is incorrect|required|povinn|nesouhlas/i)`, 6000)
    .then(() => check('and refuses without the account’s own password', true))
    .catch(() => check('and refuses without the account’s own password', false));

  check('the account is still there', await eval_(`!! document.querySelector('[data-testid="admin-user"]')`));

  // ── 6. The switcher the shell renders, and this page never mentions ──────
  check('the team switcher reached the top bar through the chrome registry', await eval_(`
    !! document.querySelector('[data-testid="team-switcher-trigger"]')
  `));

  const before = await eval_(`document.querySelector('[data-testid="team-switcher-trigger"]').textContent.trim()`);

  await eval_(`document.querySelector('[data-testid="team-switcher-trigger"]').click()`);
  await waitFor(`!! [...document.querySelectorAll('[data-testid="team-switcher-option"]')].find(b => b.offsetParent)`, 6000);

  const options = await eval_(`document.querySelectorAll('[data-testid="team-switcher-option"]').length`);
  check('it lists the teams this person belongs to', options >= 2, `${options} teams`);
  await shot('05-team-switcher');

  await eval_(`[...document.querySelectorAll('[data-testid="team-switcher-option"]')].find(b => b.dataset.current !== 'true').click()`);

  // A redirect rather than a re-render, and not out of laziness: the team scopes
  // every permission read, so the page around it was built for the team just
  // left.
  await waitFor(`document.querySelector('[data-testid="team-switcher-trigger"]')?.textContent.trim() !== ${JSON.stringify(before)}`, 8000)
    .then(() => check('switching lands on a page rebuilt for the new team', true))
    .catch(() => check('switching lands on a page rebuilt for the new team', false));

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
