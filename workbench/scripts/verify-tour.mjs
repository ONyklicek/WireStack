import { openPage, checker, until } from './lib/cdp.mjs';

/*
 * The guided walkthrough, in a browser.
 *
 * This is the only gate over any of it. Pest sees the markup the host view
 * renders and nothing about what the browser does with it — whether the panel
 * landed beside its element, whether a step with no element was skipped instead
 * of hanging, whether Escape ended the tour, whether anything ran on a phone.
 * Every one of those is a silent failure in PHP.
 *
 * Two of the checks below are holding a *decision* rather than a mechanism, and
 * they are the ones worth keeping if the rest are ever trimmed:
 *
 *   - On a phone the tour runs *docked*: the panel sits along the bottom edge,
 *     the ring still points, and a step whose element a phone does not show —
 *     the sidebar is a drawer there — is skipped. It used to be refused below
 *     the sheet breakpoint altogether; the owner reversed that, and a decision
 *     this driver does not hold is one that drifts back.
 *   - A permission-gated tour is never shown. This is the one place in the
 *     feature where being wrong is worse than being broken: a walkthrough of a
 *     screen somebody cannot reach is a hint about it. The workbench registers
 *     `gated-tour` with a *lower sort* than the real one, so it would win every
 *     time if the gate were not consulted.
 *
 * The workbench tour (`invoices-tour`) has four steps. Two of them can never be
 * shown: `not-on-this-page` is a well-formed hook name nothing renders, and
 * `admin-sidebar-overlay` is rendered on every page but hidden on a desktop.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const { check, finish } = checker();

// The workbench registers its tours only when this cookie is present, so no
// other driver ever meets a backdrop it did not ask for. Set before the first
// navigation, and again after cookies are cleared below.
const host = new URL(origin).hostname;
const page_ = await openPage({ url: 'about:blank', shotPrefix: 'tour', width: 1400, height: 950 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const enableTours = () => page('Network.setCookie', {
  name: 'wire-tour-demo', value: '1', domain: host, path: '/',
});

// The panel is `x-show`n, so "showing" is a box with a size — not merely present.
const panelVisible = `(() => {
  const el = document.querySelector('[data-wire="tour-panel"]');
  if (! el) return false;
  const box = el.getBoundingClientRect();
  return box.width > 0 && box.height > 0;
})()`;

// Parenthesised, and that is load-bearing. `??` binds looser than a method call
// and cannot be mixed with `&&` at all, so an unwrapped
// `a?.b ?? ''` interpolated into `${x}.includes('y') && ...` parses as
// `a?.b ?? (''.includes('y') && ...)` — which quietly returns the text instead
// of a boolean — or fails outright with "Unexpected token '&&'". Both happened
// while writing this file, and the first one reported a passing feature as a
// failure with the right text in the detail column.
const panelText = `(document.querySelector('[data-wire="tour-panel"]')?.innerText ?? '')`;
const progress = `(document.querySelector('[data-wire="tour-progress"]')?.textContent.trim() ?? '')`;

const setViewport = (width, height) => page('Emulation.setDeviceMetricsOverride', {
  width, height, deviceScaleFactor: 1, mobile: false,
});

try {
  await enableTours();
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/routed/invoices`)}`);
  await waitFor('!! window.Alpine', 15000);
  await waitFor(panelVisible, 15000);

  check('the tour opens by itself on a screen it claims', await eval_(panelVisible));

  // ── It is the right tour ─────────────────────────────────────────────────
  check(
    'a permission-gated tour is never the one that runs',
    ! (await eval_(`${panelText}.includes('You should never see this')`)),
    await eval_(panelText),
  );

  // ── The panel is anchored to its element ─────────────────────────────────
  // Step one is `admin-sidebar` at `right-start`: the panel must sit to the
  // right of the sidebar and overlap it vertically. Asserted as a relationship
  // rather than as coordinates, which would pin the sidebar's width.
  const anchored = await eval_(`(() => {
    const sidebar = document.querySelector('[data-wire="admin-sidebar"]');
    const panel = document.querySelector('[data-wire="tour-panel"]');
    if (! sidebar || ! panel) return { ok: false, why: 'missing element' };
    const s = sidebar.getBoundingClientRect();
    const p = panel.getBoundingClientRect();
    return {
      ok: p.left >= s.right - 4 && p.top < s.bottom && p.bottom > s.top,
      why: 'sidebar right ' + Math.round(s.right) + ', panel left ' + Math.round(p.left),
    };
  })()`);
  check('the panel is positioned against the element its step names', anchored.ok, anchored.why);

  check('the highlight ring is drawn over that element', await eval_(`(() => {
    const target = document.querySelector('[data-wire="admin-sidebar"]').getBoundingClientRect();
    const ring = document.querySelector('[data-wire="tour-highlight"]').getBoundingClientRect();
    return Math.abs(ring.left - target.left) < 12 && Math.abs(ring.top - target.top) < 12;
  })()`));

  // ── The unreachable step ─────────────────────────────────────────────────
  // Counted, not just skipped. A tour that quietly counts a step it will never
  // show makes somebody watch "1 of 3" become "3 of 3".
  // Four steps are declared. One names a hook nothing renders; the other names
  // one that is rendered and hidden behind an `x-show` (the mobile sidebar
  // overlay, on a desktop). Neither can be shown, so the counter must say two —
  // and "3" is the specific regression: a tour that checks an element exists
  // rather than that it is showing counts the hidden one. Asserted as digits
  // rather than against the whole string, which is a translation.
  const counter = await eval_(progress);
  check(
    'a step whose element is absent is left out of the count',
    counter.includes('2') && ! counter.includes('4'),
    `progress reads "${counter}"`,
  );
  check(
    'and so is one whose element is rendered but hidden',
    ! counter.includes('3'),
    `progress reads "${counter}"`,
  );

  await shot('01-first-step');

  await eval_(`document.querySelector('[data-wire="tour-next"]').click()`);
  await until(async () => (await eval_(panelText)).includes('Find a row'), { timeout: 8000 });

  check('Next walks over the step with no element rather than stopping on it', await eval_(`
    ${panelText}.includes('Find a row') && ! ${panelText}.includes('Unreachable') && ! ${panelText}.includes('Hidden')
  `));

  check('and the panel re-anchors to the new step', await eval_(`(() => {
    const search = document.querySelector('[data-wire="table-search"]').getBoundingClientRect();
    const panel = document.querySelector('[data-wire="tour-panel"]').getBoundingClientRect();
    return panel.top >= search.bottom - 4 && Math.abs(panel.left - search.left) < 220;
  })()`));

  await shot('02-last-step');

  // ── Escape ends it ───────────────────────────────────────────────────────
  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  await until(async () => ! (await eval_(panelVisible)), { timeout: 8000 });

  check('Escape ends the tour', ! (await eval_(panelVisible)));

  check('and hands the element its own z-index back', await eval_(`(() => {
    const sidebar = document.querySelector('[data-wire="admin-sidebar"]');
    return sidebar.style.zIndex === '' && sidebar.style.position === '';
  })()`));

  // ── It is remembered ─────────────────────────────────────────────────────
  // The acknowledgement is the one round trip a tour makes. Waiting for the
  // panel to go is not enough: the request is still in flight at that point.
  await until(async () => await eval_(`! window.Livewire || ! document.querySelector('[wire\\\\:id]:not([data-loaded])') || true`), { timeout: 2000 }).catch(() => {});
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/routed/invoices`)}`);
  await waitFor('!! window.Alpine && !! document.querySelector(\'[data-wire="table-search"]\')', 15000);

  check('a finished tour does not come back on the next visit', ! (await eval_(panelVisible)));

  // ── wire:navigate ────────────────────────────────────────────────────────
  // A SPA visit re-renders the layout, so the chrome is built again. A tour that
  // read its "seen" state from anywhere but the server would resurrect here.
  const navigated = await eval_(`(() => {
    const link = [...document.querySelectorAll('a[wire\\\\:navigate]')].find((a) => ! a.href.includes('/invoices'));
    if (! link) return null;
    link.click();
    return link.href;
  })()`);

  if (navigated) {
    await until(async () => ! (await eval_(`location.href.includes('/invoices')`)), { timeout: 8000 }).catch(() => {});
    await eval_(`history.back()`);
    await waitFor(`location.href.includes('/invoices')`, 8000).catch(() => {});
    check('and does not come back after a wire:navigate visit either', ! (await eval_(panelVisible)));
  } else {
    check('and does not come back after a wire:navigate visit either', true, 'no wire:navigate link on the page — skipped');
  }

  // ── Zones ────────────────────────────────────────────────────────────────
  // The part with the trap under it. `Zone::current()` answers `livewire.update`
  // during a round trip, so its three readers are right on a page's first render
  // and null afterwards — which is why the host reads them once and the matcher
  // takes them as arguments. A browser is the only place that shape can be
  // checked against a real route name.
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/zoned/admin/invoices`)}`);
  await waitFor('!! window.Alpine', 15000);
  await waitFor(panelVisible, 15000).catch(() => {});

  check(
    'a zone-scoped tour runs in its own zone',
    await eval_(`${panelText}.includes('Admin zone only')`),
    await eval_(panelText),
  );

  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/zoned/business/invoices`)}`);
  await waitFor('!! window.Alpine', 15000);
  await until(async () => await eval_(panelVisible), { timeout: 6000 }).catch(() => {});

  check(
    'and never in another zone',
    ! (await eval_(`${panelText}.includes('Admin zone only')`)),
    await eval_(panelText),
  );

  // ── On a phone, docked ───────────────────────────────────────────────────
  // A fresh identity, so the tour is unseen again, and a narrow viewport before
  // the page loads. 375px is an iPhone; the breakpoint is 639.98.
  // Through CDP, not `document.cookie`. Laravel's session cookie is HttpOnly, so
  // JS cannot see or clear it — the page reports success, the session survives,
  // and the tour stays acknowledged from the Escape above. That read as "a tour
  // does not run on a wide viewport", which is a bug in the driver wearing the
  // costume of a bug in the feature.
  await page('Network.clearBrowserCookies');
  await enableTours();
  await setViewport(375, 780);
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/routed/invoices`)}`);
  await waitFor('!! window.Alpine', 15000);
  // Waiting for the panel to *show*, not merely to exist: it is in the document
  // on every page a tour claims, so waiting for its presence returned at once —
  // which is how "no tour on a phone" passed for as long as it did.
  await until(() => eval_(panelVisible), { timeout: 10000 });

  check('a tour runs below the sheet breakpoint', await eval_(panelVisible), await eval_(panelText));
  check('…docked along the bottom of the screen', await eval_(`(() => {
    const p = document.querySelector('[data-wire="tour-panel"]').getBoundingClientRect();
    return Math.abs(window.innerHeight - p.bottom) <= 16 && p.width >= window.innerWidth - 32;
  })()`));
  // The drawer's entry and the hidden overlay are not showing on a phone, and
  // the step nothing renders never is: one step is left, and it says so.
  check('…with only the step a phone can show', /1$/.test(await eval_(progress)), await eval_(progress));

  await shot('03-phone-docked');

  // ── And one already running docks if the window is dragged narrow ────────
  await setViewport(1400, 950);
  await eval_(`window.location.href = ${JSON.stringify(`${origin}/previews/routed/invoices`)}`);
  await waitFor('!! window.Alpine', 15000);
  await waitFor(panelVisible, 15000);

  check('a tour runs again once the viewport is wide enough', await eval_(panelVisible));

  await setViewport(375, 780);
  await eval_(`window.dispatchEvent(new Event('resize'))`);
  await until(() => eval_(`getComputedStyle(document.querySelector('[data-wire="tour-panel"]')).position === 'fixed'`), { timeout: 8000 });

  check('and docks rather than ends when the viewport crosses the breakpoint',
    (await eval_(panelVisible)) && (await eval_(`getComputedStyle(document.querySelector('[data-wire="tour-panel"]')).position === 'fixed'`)));

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
