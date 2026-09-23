import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for the profile's browser-sessions card
 * (/previews/profile/browser-sessions, which seeds a second device and hands
 * over to /previews/routed/users/profile).
 *
 * What only a browser can say here is the *dialog*: the card asks for the
 * password in a modal, and everything about that — the modal opening, the
 * field's `wire:model` reaching the component, the validation message coming
 * back without closing it, the list being one row shorter afterwards — is
 * Alpine and Livewire agreeing over several roundtrips. Pest sees each half and
 * none of the seams.
 *
 * The seeded row is an iPhone and this browser is a desktop Chrome, so the two
 * rows also prove the user-agent reading and the two icons apart.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-browser-sessions.mjs
 */

const url = process.env.PREVIEW_URL
  ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/profile/browser-sessions`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'browser-sessions' });

const { check, finish } = checker();

try {
  // The entry URL seeds a second device and redirects to the profile page, so
  // the helpers below are installed on whatever document is there *after* the
  // redirect — install them on the first one and the next load takes them with
  // it, which reads as "rows is not defined" three checks later.
  await waitFor('document.querySelector(\'[data-testid="profile-browser-sessions"]\') !== null');

  await eval_(`
    window.card = () => document.querySelector('[data-testid="profile-browser-sessions"]');
    window.rows = () => [...document.querySelectorAll('[data-testid="browser-sessions-list"] li')];
    window.rowText = () => rows().map((r) => r.innerText.replace(/\\s+/g, ' ').trim());
    window.icons = () => rows().map((r) => r.querySelector('svg')?.getBoundingClientRect().width ?? 0);
    window.openButton = () => document.querySelector('[data-testid="browser-sessions-open"]');
    // Both buttons are in the DOM whether or not the modal is open, and the
    // profile page has more than one password box — so everything here is
    // scoped to the *visible* confirm button and the field beside it.
    window.confirmButton = () => [...document.querySelectorAll('[data-testid="browser-sessions-confirm"]')]
      .find((b) => b.offsetParent !== null);
    window.passwordField = () => {
      const input = dialog()?.querySelector('input[id="data.password"]');

      return input && input.offsetParent !== null ? input : undefined;
    };
    window.type = (value) => {
      const el = passwordField();
      el.value = value;
      el.dispatchEvent(new Event('input', { bubbles: true }));
    };
    // The dialog this card opened, and the validation message inside *it*. The
    // profile page has other modals with their own password boxes and their own
    // prose about passwords, and a looser match read one of those as a refusal.
    window.dialog = () => {
      let node = confirmButton();

      for (let i = 0; i < 12 && node; i++) {
        if (node.querySelector('input[id="data.password"]')) return node;

        node = node.parentElement;
      }

      return null;
    };
    window.errorText = () => (dialog()?.innerText ?? '')
      .split(String.fromCharCode(10)).map((line) => line.trim())
      .filter((line) => /password is incorrect/i.test(line)).join(' | ');
    window.dialogOpen = () => confirmButton() !== undefined;
  `);

  await waitFor('rows().length === 2');

  const text = await eval_('rowText()');
  check('the card lists both sessions', text.length === 2, JSON.stringify(text));
  check('this browser is read as Chrome, and marked as this device',
    /Chrome/.test(text[0]) && /This device/i.test(text[0]), text[0]);
  check('the seeded device is read as Safari on iOS, with when it was last active',
    /iOS/.test(text[1]) && /Safari/.test(text[1]) && /Last active/i.test(text[1]), text[1]);
  check('the seeded device shows its own address', /203\.0\.113\.7/.test(text[1]), text[1]);

  const iconWidths = await eval_('icons()');
  check('each row draws a device icon at the size the card asks for',
    iconWidths.length === 2 && iconWidths.every((w) => w > 24 && w < 40), JSON.stringify(iconWidths));

  // The card sits below four others, so the screenshots are worth nothing
  // unless the page is scrolled to it first.
  await eval_("card().scrollIntoView({ block: 'center' })");
  await shot('01-card');

  // ─── The dialog, which is the half Pest cannot see ────────────────────────

  await eval_('openButton().click()');
  await waitFor('dialogOpen() && passwordField() !== undefined');
  check('the button opens the password dialog', true);
  await shot('02-dialog');

  await waitFor('passwordField() !== undefined');
  await eval_('type("not-the-password")');
  await waitFor('passwordField().value === "not-the-password"');
  await eval_('confirmButton().click()');

  // The wrong password must leave everything as it was: the dialog open, the
  // list untouched. A card that closed on a refusal would read as success.
  await waitFor('errorText() !== \'\'');
  const refusal = await eval_('errorText()');
  check('the list is untouched while the refusal stands', await eval_('rows().length') === 2 && await eval_('dialogOpen()') === true);
  check('a wrong password is refused, with the dialog still open and nothing signed out',
    refusal !== '', refusal || 'no message found');
  await shot('03-refused');

  await waitFor('passwordField() !== undefined');
  await eval_('type("password")');
  await waitFor('passwordField().value === "password"');
  await eval_('confirmButton().click()');

  await waitFor('! dialogOpen()');
  check('the right password closes the dialog', true);

  await waitFor('rows().length === 1');
  const left = await eval_('rowText()');
  check('every other session is gone, and this one is still listed',
    left.length === 1 && /This device/i.test(left[0]), JSON.stringify(left));
  await eval_("card().scrollIntoView({ block: 'center' })");
  await shot('04-after');

  // Still signed in: the rehash moved the session's copy of the hash along, so
  // a further roundtrip must answer as this user rather than redirect to login.
  await eval_('openButton().click()');
  await waitFor('dialogOpen() && passwordField() !== undefined');
  const stillHere = await eval_('card() !== null && document.body.innerText.includes("Browser sessions")');
  check('this session survives the password rehash, and the card still answers', stillHere === true);
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
