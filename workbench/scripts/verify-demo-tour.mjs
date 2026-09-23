import { openPage, checker, sleep, until } from './lib/cdp.mjs';

/*
 * The demo, as somebody opening the link meets it (/previews/demo) — on a
 * desktop and on a phone, across two pages.
 *
 * `verify-tour` drives the tour *mechanism* over fixtures built to be awkward —
 * a step nothing renders, a step that is rendered and hidden, a tour nobody may
 * see. This drives the one tour a person is meant to enjoy, and asks what that
 * one raises:
 *
 *   - the link starts it, every time — the demo user is shared, so a tour that
 *     stayed acknowledged would greet the second visitor with nothing;
 *   - every step lands on a control that is on screen, in the order written;
 *   - a step `on()` another page navigates there and carries on, and "Back"
 *     from it returns — the query that carries the tour is spent on arrival;
 *   - on a phone the panel docks to the bottom, the ring still points, the page
 *     scrolls each element into the room above the panel, and a control a phone
 *     does not show (the sidebar is a drawer) is skipped with the counter saying
 *     so;
 *   - somebody who leaves it halfway meets it again where they left it — at
 *     the step itself, or, when that step was on another page, at the last one
 *     before it here, so "Next" leads back on;
 *   - finishing it is final until the link is opened again.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-demo-tour.mjs
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

// "Showing" is a box with a size: the panel is `x-show`n, so present is not enough.
const panelVisible = `(() => {
  const el = document.querySelector('[data-wire="tour-panel"]');
  if (! el) return false;
  const box = el.getBoundingClientRect();
  return box.width > 0 && box.height > 0;
})()`;

const heading = `(document.querySelector('[data-wire="tour-heading"]')?.textContent.trim() ?? '')`;
const progress = `(document.querySelector('[data-wire="tour-progress"]')?.textContent.trim() ?? '')`;

/** Whether the highlight ring sits over the element a step describes. */
const anchoredOn = (selector) => `(() => {
  const target = document.querySelector(${JSON.stringify(selector)});
  const ring = document.querySelector('[data-wire="tour-highlight"]');
  if (! target || ! ring) return false;
  const a = target.getBoundingClientRect();
  const b = ring.getBoundingClientRect();
  const cx = (r) => r.left + r.width / 2;
  const cy = (r) => r.top + r.height / 2;
  return a.width > 0 && Math.abs(cx(a) - cx(b)) < 12 && Math.abs(cy(a) - cy(b)) < 12;
})()`;

/** Whether the element is inside the viewport and clear of a docked panel. */
const inView = (selector) => `(() => {
  const target = document.querySelector(${JSON.stringify(selector)});
  const panel = document.querySelector('[data-wire="tour-panel"]');
  if (! target || ! panel) return false;
  const t = target.getBoundingClientRect();
  const p = panel.getBoundingClientRect();
  const docked = getComputedStyle(panel).position === 'fixed';
  const floor = docked ? p.top : window.innerHeight;
  return t.top >= 0 && t.top < floor;
})()`;

/*
 * How far somebody got is a Livewire request nothing on screen waits for, so
 * leaving the page straight after a step could outrun it. Count the requests
 * in flight instead of sleeping, and wait for none.
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

const settled = async (eval_) => {
  await sleep(300);
  return until(() => eval_('window.__wireInflight === 0'), { timeout: 10000 });
};

const visit = async (eval_, path) => {
  await eval_(`window.location.href = ${JSON.stringify(`${origin}${path}`)}; true;`);
  await sleep(2500);
  await until(() => eval_(`document.readyState === 'complete'`), { timeout: 15000 });
};

const click = async (eval_, hook) => {
  await eval_(`document.querySelector('[data-wire="${hook}"]').click(); true;`);
  await sleep(600);
};

const steps = [
  { heading: 'Your overview', anchor: '[data-wire="admin-nav-item"][data-resource="overview"]', path: '/previews/zoned/admin' },
  { heading: 'Real numbers', anchor: '[data-wire="widget-grid"]', path: '/previews/zoned/admin' },
  { heading: 'Make it yours', anchor: '[data-wire="widget-layout-edit"]', path: '/previews/zoned/admin' },
  { heading: 'Keep more than one', anchor: '[data-wire="widget-layout-save-as"]', path: '/previews/zoned/admin' },
  { heading: 'Every list works like this', anchor: '[data-wire="table-search"]', path: '/previews/zoned/admin/invoices' },
];

let shotDir;
const consoleErrors = [];
const badResponses = [];

// ─── On a desktop, across two pages ──────────────────────────────────────────
{
  const page = await openPage({ url: `${origin}/previews/demo`, shotPrefix: 'demo-tour', width: 1400, height: 950, settle: 2500 });
  const { eval_, shot } = page;
  shotDir = page.shotDir;

  try {
    check('the link lands on the admin zone s dashboard',
      await until(() => eval_(`location.pathname === '/previews/zoned/admin' && !! document.querySelector('[data-wire="widget-grid"]')`)),
      await eval_('location.pathname'));

    check('…and the tour opens itself', await until(() => eval_(panelVisible)));

    for (const [index, step] of steps.entries()) {
      const shown = await until(async () => (await eval_(heading)) === step.heading, { timeout: 15000 });

      check(`step ${index + 1} of ${steps.length} is "${step.heading}"`, shown === true,
        `${await eval_(heading)} — ${await eval_(progress)} — ${await eval_('location.pathname')}`);

      check('…on the page it belongs to', (await eval_('location.pathname')) === step.path, await eval_('location.pathname'));

      check('…pointing at the control it describes', await until(() => eval_(anchoredOn(step.anchor))), step.anchor);

      if (index === steps.length - 1) {
        check('…with the address it arrived by spent', ! (await eval_(`location.search.includes('wire-tour')`)), await eval_('location.search'));

        // Back across the page boundary, and forward again.
        await click(eval_, 'tour-back');

        check('"Back" from a step on another page returns to the one before it',
          await until(async () => (await eval_(heading)) === steps[index - 1].heading && (await eval_('location.pathname')) === steps[index - 1].path, { timeout: 15000 }),
          `${await eval_(heading)} — ${await eval_('location.pathname')}`);

        await click(eval_, 'tour-next');
        await until(async () => (await eval_(heading)) === step.heading, { timeout: 15000 });
      }

      await shot(`desktop-${index + 1}`);
      await click(eval_, 'tour-next');
    }

    check('finishing the last step closes the tour', await until(async () => ! (await eval_(panelVisible))));

    await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/zoned/admin`)}; true;`);
    await sleep(2500);

    check('the dashboard after finishing does not reopen it', ! (await eval_(panelVisible)));

    await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/demo`)}; true;`);
    await sleep(2500);

    check('opening the link again starts it from the first step',
      await until(async () => (await eval_(panelVisible)) && (await eval_(heading)) === steps[0].heading),
      await eval_(heading));

    // ── Left halfway, on this page ──
    await eval_(watchRequests);
    await click(eval_, 'tour-next');
    await click(eval_, 'tour-next');
    await until(async () => (await eval_(heading)) === steps[2].heading);
    await settled(eval_);

    // Somewhere no tour claims, and back.
    await visit(eval_, '/previews/zoned/admin/invoices');
    check('a page the tour does not start on shows nothing of it', ! (await eval_(panelVisible)));

    await visit(eval_, '/previews/zoned/admin');
    check('coming back reopens it at the step it was left on',
      await until(async () => (await eval_(panelVisible)) && (await eval_(heading)) === steps[2].heading, { timeout: 15000 }),
      `${await eval_(heading)} — ${await eval_(progress)}`);
    check('…counted as that step of the whole tour', /^Step 3 of 5$/.test(await eval_(progress)), await eval_(progress));
    check('…pointing at its control', await until(() => eval_(anchoredOn(steps[2].anchor))), steps[2].anchor);

    // ── Left halfway, on the other page ──
    await click(eval_, 'tour-next');
    await click(eval_, 'tour-next');
    await until(async () => (await eval_(heading)) === steps[4].heading && (await eval_('location.pathname')) === steps[4].path, { timeout: 15000 });
    await eval_(watchRequests);
    await settled(eval_);

    await visit(eval_, '/previews/zoned/admin');
    check('left on another page, it reopens at the last step before it here',
      await until(async () => (await eval_(panelVisible)) && (await eval_(heading)) === steps[3].heading, { timeout: 15000 }),
      `${await eval_(heading)} — ${await eval_(progress)}`);

    await click(eval_, 'tour-next');
    check('…and "Next" leads back to where it was left',
      await until(async () => (await eval_(heading)) === steps[4].heading && (await eval_('location.pathname')) === steps[4].path, { timeout: 15000 }),
      `${await eval_(heading)} — ${await eval_('location.pathname')}`);

    await eval_(watchRequests);
    await click(eval_, 'tour-skip');
    await settled(eval_);

    await visit(eval_, '/previews/zoned/admin');
    check('skipping it from there is final — no progress left to reopen', ! (await eval_(panelVisible)));
  } catch (err) {
    check('desktop run completed', false, err?.message ?? String(err));
  } finally {
    consoleErrors.push(...page.consoleErrors);
    badResponses.push(...page.badResponses);
    await page.close();
  }
}

// ─── On a phone ──────────────────────────────────────────────────────────────
{
  const page = await openPage({ url: `${origin}/previews/demo`, shotPrefix: 'demo-tour-phone', width: 390, height: 844, settle: 2500 });
  const { eval_, shot } = page;

  try {
    check('phone · the tour runs on a phone too', await until(() => eval_(panelVisible), { timeout: 15000 }));

    check('phone · the panel is docked to the bottom of the screen', await eval_(`(() => {
      const p = document.querySelector('[data-wire="tour-panel"]').getBoundingClientRect();
      return Math.abs(window.innerHeight - p.bottom) <= 16 && p.width >= window.innerWidth - 32;
    })()`));

    // The sidebar is a drawer on a phone, so the step pointing at its entry
    // cannot be shown — and must not be counted.
    check('phone · the step a phone cannot show is skipped', (await eval_(heading)) === steps[1].heading, await eval_(heading));
    check('…and the counter says the tour is shorter', /4$/.test(await eval_(progress)), await eval_(progress));

    for (const step of steps.slice(1)) {
      await until(async () => (await eval_(heading)) === step.heading, { timeout: 15000 });
      // The scroll is smooth, so give it the time it takes before asking.
      await until(() => eval_(inView(step.anchor)), { timeout: 5000 });

      check(`phone · "${step.heading}" is scrolled into the room above the panel`, await eval_(inView(step.anchor)), step.anchor);
      // The page before skipped a step, and the next page has to agree.
      check('…counted out of the same four on every page', /of 4$/.test(await eval_(progress)), await eval_(progress));
      check('…and the ring still points at it', await until(() => eval_(anchoredOn(step.anchor))), step.anchor);

      await shot(`phone-${step.heading.toLowerCase().replace(/[^a-z]+/g, '-')}`);
      await click(eval_, 'tour-next');
    }

    check('phone · finishing closes it', await until(async () => ! (await eval_(panelVisible))));
  } catch (err) {
    check('phone run completed', false, err?.message ?? String(err));
  } finally {
    consoleErrors.push(...page.consoleErrors);
    badResponses.push(...page.badResponses);
    await page.close();
  }
}

finish({ consoleErrors, badResponses, shotDir });
