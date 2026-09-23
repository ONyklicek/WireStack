import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * The block a tour opens with, and the third answer it makes possible.
 *
 * `verify-tour` drives the walkthrough itself. This drives the beat before it:
 * a tour that asks whether to start, and what happens to each of the two answers
 * a person can give. Every one of these is invisible to Pest, which sees the
 * markup the host renders and nothing about what Alpine does with it.
 *
 * The decisions held here, rather than the mechanisms:
 *
 *   - **The greeting gates the tour.** Nothing is pointed at and no step panel
 *     is on screen until somebody has said yes. A regression that started the
 *     walkthrough underneath the card would look fine in a screenshot of the
 *     card.
 *   - **It asks once, not on every page load.** Somebody who started the tour
 *     and is part-way through it is not asked again when they come back — they
 *     are returned to where they were. A greeting that reappeared mid-tour
 *     would ask whether to start something already running.
 *   - **"Later" is not "Skip".** It closes the tour without acknowledging it,
 *     and holds for the sitting: a reload does not greet again. The count is the
 *     server's, so the last one acknowledges — `welcome-tour` allows two, so the
 *     second "Later" is final and the third visit is silent.
 *
 * The workbench registers `welcome-tour` only when the cookie below is set, so
 * no other driver ever meets a card over the page it came to click.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-tour-welcome.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

const host = new URL(origin).hostname;
const page_ = await openPage({ url: 'about:blank', shotPrefix: 'tour-welcome', width: 1400, height: 950 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const enableWelcome = () => page('Network.setCookie', {
  name: 'wire-tour-welcome', value: '1', domain: host, path: '/',
});

/** Showing is a box with a size: both surfaces are `x-show`n, so present is not enough. */
const showing = (hook) => `(() => {
  const el = document.querySelector('[data-wire="${hook}"]');
  if (! el) return false;
  const box = el.getBoundingClientRect();
  return box.width > 0 && box.height > 0;
})()`;

const welcomeVisible = showing('tour-welcome');
const panelVisible = showing('tour-panel');
const welcomeText = `(document.querySelector('[data-wire="tour-welcome"]')?.innerText ?? '')`;
const panelText = `(document.querySelector('[data-wire="tour-panel"]')?.innerText ?? '')`;

/*
 * Recording a step, a postponement and an acknowledgement are all Livewire
 * requests nothing on screen waits for, so navigating straight after one could
 * outrun it. Count what is in flight rather than sleeping at a number.
 */
const watchRequests = `(() => {
  if (window.__wireInflight !== undefined) return true;
  window.__wireInflight = 0;
  const fetch = window.fetch;
  window.fetch = (...args) => {
    window.__wireInflight++;
    return fetch(...args).finally(() => { window.__wireInflight--; });
  };
  return true;
})()`;

const settled = async () => {
  await sleep(300);
  return until(() => eval_('window.__wireInflight === 0'), { timeout: 10000 });
};

const visit = async () => {
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/routed/invoices`)}; true;`);
  await waitFor('!! window.Alpine', 15000);
  await eval_(watchRequests);
};

const click = async (hook) => {
  await eval_(`document.querySelector('[data-wire="${hook}"]').click(); true;`);
  await sleep(600);
};

try {
  await enableWelcome();
  await visit();
  await until(() => eval_(welcomeVisible), { timeout: 15000 });

  // ── It asks before it points ─────────────────────────────────────────────
  check('a tour with a welcome opens with it', await eval_(welcomeVisible));

  check(
    '…and points at nothing until somebody says yes',
    ! (await eval_(panelVisible)),
    await eval_(panelText),
  );

  check(
    'the card carries what the author wrote',
    await eval_(`${welcomeText}.includes('Two minutes') && ${welcomeText}.includes('Not just now')`),
    await eval_(welcomeText),
  );

  check('and the element it is drawn over is dimmed', await eval_(showing('tour-backdrop')));

  await shot('01-welcome');

  // ── Start ────────────────────────────────────────────────────────────────
  await click('tour-welcome-start');
  await until(() => eval_(panelVisible), { timeout: 10000 });

  check('starting it begins the walkthrough', await eval_(panelVisible));
  check('and takes the card away', ! (await eval_(welcomeVisible)));

  check(
    'the first step is anchored to its element',
    await eval_(`(() => {
      const sidebar = document.querySelector('[data-wire="admin-sidebar"]');
      const panel = document.querySelector('[data-wire="tour-panel"]');
      if (! sidebar || ! panel) return false;
      const s = sidebar.getBoundingClientRect();
      const p = panel.getBoundingClientRect();
      return p.left >= s.right - 4 && p.top < s.bottom && p.bottom > s.top;
    })()`),
  );

  await shot('02-started');

  // ── It does not ask twice ────────────────────────────────────────────────
  // Part-way through, then back: the tour resumes where it was left, and asking
  // again would be asking whether to start something already running.
  await click('tour-next');
  await until(async () => (await eval_(panelText)).includes('Find a row'), { timeout: 8000 });
  await settled();

  await visit();
  await until(() => eval_(panelVisible), { timeout: 15000 });

  check(
    'coming back part-way through is not greeted again',
    ! (await eval_(welcomeVisible)) && (await eval_(panelVisible)),
    await eval_(panelText),
  );

  check(
    '…and lands back on the step it was left on',
    await eval_(`${panelText}.includes('Find a row')`),
    await eval_(panelText),
  );

  // ── Later ────────────────────────────────────────────────────────────────
  // A fresh identity, because everything above left this one part-way through a
  // tour. Through CDP rather than `document.cookie`: Laravel's session cookie is
  // HttpOnly, so clearing it from JS reports success and changes nothing.
  await page('Network.clearBrowserCookies');
  await enableWelcome();
  await visit();
  await until(() => eval_(welcomeVisible), { timeout: 15000 });

  check('a new person is greeted', await eval_(welcomeVisible));

  await click('tour-welcome-later');
  await until(async () => ! (await eval_(welcomeVisible)), { timeout: 8000 });
  await settled();

  check('"Later" closes the tour', ! (await eval_(welcomeVisible)) && ! (await eval_(panelVisible)));

  await visit();
  await sleep(1500);

  check(
    '…and holds for the sitting rather than for one page load',
    ! (await eval_(welcomeVisible)) && ! (await eval_(panelVisible)),
    `welcome ${await eval_(welcomeVisible)}, panel ${await eval_(panelVisible)}`,
  );

  await shot('03-postponed');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
