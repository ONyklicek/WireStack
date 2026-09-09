import { openPage, checker, until } from './lib/cdp.mjs';

/*
 * CDP driver for the per-person density switch (/previews/routed/users).
 *
 * `verify-density.mjs` proves the rules do what they claim once the attribute is
 * set. This proves the other half: that a person can set it, that it is
 * remembered, and that it survives the one thing that silently undoes an
 * attribute in this stack.
 *
 * **Config is the default, a stored choice overrides it.** Somebody who never
 * touches the switch keeps whatever the application shipped — including a later
 * change to it — which is why the script falls back to the attribute the server
 * rendered rather than to a hard-coded `normal`.
 *
 * **The navigate check is the point of the whole driver.** Livewire copies the
 * `<html>` attributes of the *fetched* document over the live one, and the
 * server cannot know what this browser chose; without the `livewire:navigating`
 * listener every SPA visit arrives with the config default and quietly throws
 * the choice away. It is the same bug that stripped `dark` from the theme, on a
 * different attribute, and neither Pest nor a single-page driver can see it.
 *
 * Two things this driver learned the hard way, both worth keeping:
 *
 *  - **Clear the stored choice first.** The browser profile outlives any single
 *    run, so a driver that does not clear it measures the previous run. This one
 *    reported "starts at the default — compact" until it did.
 *  - **Navigate somewhere known.** Clicking whatever `wire:navigate` link came
 *    first landed on a page with no table and no switch, and the driver crashed
 *    on the element rather than reporting anything.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-density-switch.mjs
 */

const ORIGIN = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';

const { eval_, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url: `${ORIGIN}/previews/routed/users`, shotPrefix: 'density-switch', width: 1400, height: 900 });

const { check, finish } = checker();

const state = () => eval_(`(() => ({
  attr: document.documentElement.dataset.density ?? null,
  stored: (() => { try { return localStorage.getItem('wire.density'); } catch (e) { return 'BLOCKED'; } })(),
  row: (() => {
    const row = document.querySelector('tbody tr');
    return row ? Math.round(row.getBoundingClientRect().height) : null;
  })(),
}))()`);

const press = (value) => eval_(`(() => {
  const button = document.querySelector('[data-testid="admin-density-${value}"]');
  if (! button) return 'missing';
  button.click();
  return 'clicked';
})()`);

try {
  await eval_(`(() => {
    try { localStorage.removeItem('wire.density'); } catch (e) {}
    return window.wireDensity.apply();
  })()`);

  const shipped = await state();
  await shot('01-default');
  check('starts at the application default, remembering nothing',
    shipped.attr === 'normal' && shipped.stored === null, JSON.stringify(shipped));

  check('the switch is on the page', await press('compact') === 'clicked');
  await until(async () => (await state()).attr === 'compact', { timeout: 5000 });

  const chosen = await state();
  await shot('02-compact');
  check('the choice reaches the document', chosen.attr === 'compact', chosen.attr);
  check('and the rows with it', chosen.row < shipped.row, `${shipped.row}px → ${chosen.row}px`);
  check('and it is remembered', chosen.stored === 'compact', String(chosen.stored));

  // The whole reason this driver exists.
  await eval_(`(() => {
    const link = [...document.querySelectorAll('a[wire\\\\:navigate]')]
      .find((a) => /\\/previews\\/routed\\/[a-z]+$/.test(a.getAttribute('href') ?? ''));
    if (link) link.click();
    return link ? link.getAttribute('href') : 'no link';
  })()`);
  await until(async () => (await state()).attr !== null, { timeout: 5000 });

  const navigated = await state();
  check('survives a wire:navigate, which strips html attributes',
    navigated.attr === 'compact', JSON.stringify(navigated));

  // Back, from whichever page the navigate landed on.
  if (await press('normal') === 'clicked') {
    await until(async () => (await state()).attr === 'normal', { timeout: 5000 });
    const back = await state();
    check('switches back', back.attr === 'normal' && back.stored === 'normal', JSON.stringify(back));
  }

  await eval_(`(() => { try { localStorage.removeItem('wire.density'); } catch (e) {} })()`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
