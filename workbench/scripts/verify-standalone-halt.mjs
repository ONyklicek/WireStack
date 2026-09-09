import { openPage, checker } from './lib/cdp.mjs';

/*
 * CDP driver for a halt raised on a host that is not a table
 * (/previews/actions-modal-stacking, card 08).
 *
 * A halt stops the action pipeline mid-flight and asks. The engine that raises
 * one is core's, so it can happen on any WithActions host — but until 2.0 only
 * wire-table had a view for it: the standalone host wrote `mountedHalt` state
 * that nothing read, so pressing the button ran the action, stopped it, and
 * showed the user nothing at all. Pest sees that state and calls it a pass,
 * which is exactly why this check is here instead of there.
 *
 * What the browser is asked: the modal appears, its rules keep it open, Escape
 * is refused because the halt said closeOnEscape(false), and confirming re-runs
 * the action with what the modal collected.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/actions-modal-stacking`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'standalone-halt' });

const { check, finish } = checker();

try {
  await eval_(`
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    // By text, not by tag: a confirmation draws its heading in whatever element
    // the modal surface uses, and pinning the tag here would be asserting the
    // markup rather than the behaviour.
    // Painted, not offsetParent: a modal is position:fixed, and for a fixed
    // element offsetParent is null however visible it is — which reads as
    // 'the modal never opened' for the one element that always is one.
    window.shown = (el) => el.getClientRects().length > 0;
    window.haltOpen = () => [...document.querySelectorAll('[role="dialog"]')]
        .some((d) => shown(d) && d.innerText.includes('Why are you archiving'));
    window.dialogText = () => [...document.querySelectorAll('[role="dialog"]')]
        .filter(shown).map((d) => d.innerText.slice(0, 80));
    window.answer = () => ($q('[data-testid="halt-answer"]')?.innerText ?? '').trim();
    window.press = (key) => document.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
    true;
  `);

  // ── 1. The action runs, halts, and the modal it asked for is on screen ──
  await eval_(`$q('[data-testid="open-haltAsk"]').click()`);
  const opened = await waitFor('haltOpen()', { timeout: 8000 }).catch(() => false);
  check('a halt on a plain component draws its modal', opened === true, JSON.stringify(await eval_('dialogText()')));
  await shot('01-halt-open');

  // ── 2. Its own rules keep it open ───────────────────────────────────────
  await eval_(`
    (() => {
      const submit = $qa('button').find((b) => b.innerText.trim() === 'Archive');
      if (submit) submit.click();
      return true;
    })()
  `);
  const stillOpen = await waitFor('haltOpen()', { timeout: 8000 }).catch(() => false);
  check('a failed rule keeps the halt open', stillOpen === true);
  const ranAnyway = await eval_("answer().includes('Archived')");
  check('and the action did not run behind it', ranAnyway === false);

  // …and the action did not run behind it. This is the half that matters and the
  // half a markup test cannot see: the modal is still up, so the only proof the
  // rules were enforced is that nothing was archived.
  //
  // Not checked here: the message itself. The server renders it (the forms suite
  // asserts the re-rendered HTML carries it, keyed onto the field's state path)
  // but it does not reach the screen inside a teleported confirmation — same in
  // a table, and older than this driver. Worth its own fix; asserting it here
  // would only paint the sweep red for something this page is not about.
  await shot('02-validation');

  // ── 3. closeOnEscape(false) is honoured ─────────────────────────────────
  await eval_(`press('Escape')`);
  const survivedEscape = await eval_('haltOpen()');
  check('Escape does not dismiss a halt that refused it', survivedEscape === true);

  // ── 4. Answer it, and the action runs again with what it collected ──────
  const typed = await eval_(`
    (() => {
      const field = $qa('input[type="text"], input:not([type])').find((i) => (i.id || '').includes('reason'));
      if (!field) return false;
      field.value = 'superseded';
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    })()
  `);
  check('the halt form is a real form, with the field the action declared', typed === true);

  await waitFor("$qa('input').some((i) => i.value === 'superseded')", { timeout: 6000 }).catch(() => {});
  await eval_(`
    (() => {
      const submit = $qa('button').find((b) => b.innerText.trim() === 'Archive');
      if (submit) submit.click();
      return true;
    })()
  `);

  const answered = await waitFor("answer().includes('superseded') ? answer() : false", { timeout: 9000 }).catch(() => '');
  check('confirming re-runs the action with the halt data', String(answered).includes('superseded'), String(answered));

  const closed = await waitFor('haltOpen() === false', { timeout: 6000 }).catch(() => false);
  check('the halt closes once it has been answered', closed === true);
  await shot('03-confirmed');

  check('no console errors', consoleErrors.length === 0, consoleErrors.slice(0, 2).join(' | '));
  // The favicon a headless Chrome asks for is not this page's business.
  const realFailures = badResponses.filter((r) => ! r.includes('favicon.ico'));
  check('no failed responses', realFailures.length === 0, realFailures.slice(0, 2).join(' | '));
} finally {
  finish({ shotDir });
  await close();
}
