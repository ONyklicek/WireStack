import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for a polling widget (/previews/widgets-polling).
 *
 * Livewire reads a bare `wire:poll` as `$refresh` — `directive.expression ?
 * directive.expression : "$refresh"` — and the widget grid carries no island, so
 * island targeting (which needs `wire:island` on the origin, or
 * `closestIsland(origin.el)` to find one) never engages. One polling widget
 * therefore re-rendered the entire host: every other widget on the dashboard,
 * and any table sharing the component. Measured on this repository, a 12-widget
 * grid costs 6.5 ms and 57 219 B against one widget's 0.311 ms and 3 940 B.
 *
 * The tick now calls `refreshWidget('key')` and the host queues that widget as a
 * partial. Only a browser can check the part that matters: the tick fires on its
 * own schedule, the response has to carry regions rather than a page, and — the
 * one that would have bitten — the poll has to keep ticking afterwards. The
 * anchor is deliberately nested INSIDE the element carrying `wire:poll`, because
 * Livewire stops a poll whose directive has left the element; an anchor on the
 * polling element itself would switch its own poll off unless every tick
 * re-emitted the directive.
 *
 * Each widget renders the moment it was built, so a full render moves all four
 * stamps and a targeted one moves exactly the polling widget's.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-widget-poll.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/widgets-polling`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'widget-poll', width: 1200, height: 900, settle: 2500,
});

const { check, finish } = checker();

try {
  await eval_(`
    window.__wire = [];
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
      const target = typeof args[0] === 'string' ? args[0] : (args[0]?.url ?? '');
      const promise = originalFetch.apply(this, args);

      if (target.includes('/livewire')) {
        promise.then((response) => {
          response.clone().json().then((body) => {
            const bodies = body?.components ?? [];
            const partials = bodies.reduce((acc, c) => ({ ...acc, ...(c?.effects?.wirePartials ?? {}) }), {});
            window.__wire.push({
              hasHtml: bodies.some((c) => c?.effects?.html !== undefined),
              partials: Object.keys(partials),
              bytes: Object.values(partials).reduce((n, h) => n + String(h).length, 0),
            });
          }).catch(() => {});
        }).catch(() => {});
      }

      return promise;
    };

    // The grid's direct children are the widget cells.
    window.$cells = () => Array.from(document.querySelectorAll('.wire-widget-grid > div > div'));

    // Each widget's stamp, read off the stat value it renders.
    window.$stamps = () => window.$cells()
      .map((cell) => (cell.textContent.match(/\\d{10,}-w\\d/) ?? [null])[0])
      .filter(Boolean);

    // The modifiers are part of the attribute NAME (wire:poll.2s.visible), so
    // there is no [wire:poll] to select — match the prefix instead.
    window.$pollAttrs = () => window.$cells().flatMap((cell) =>
      cell.getAttributeNames()
        .filter((n) => n.startsWith('wire:poll'))
        .map((n) => ({ name: n, value: cell.getAttribute(n) })));
    true;
  `);

  const start = await eval_(`(() => {
    const polls = window.$pollAttrs();

    return JSON.stringify({
      stamps: window.$stamps(),
      polls: polls.length,
      pollName: polls[0]?.name ?? null,
      pollExpression: polls[0]?.value ?? null,
      anchors: document.querySelectorAll('[wire\\\\:partial]').length,
      anchorNestedInPoll: window.$cells().some((cell) =>
        cell.getAttributeNames().some((n) => n.startsWith('wire:poll'))
        && !! cell.querySelector('[wire\\\\:partial]')),
    });
  })()`);

  const info = JSON.parse(start);

  check('four widgets rendered, each with its own stamp', info.stamps.length === 4, start);
  check('exactly one of them polls', info.polls === 1, start);
  check('…on the interval it asked for', info.pollName === 'wire:poll.2s.visible', String(info.pollName));
  check('…and the tick names the widget, not $refresh',
    info.pollExpression === "refreshWidget('w1')", String(info.pollExpression));
  check('exactly one anchor, nested inside the polling element',
    info.anchors === 1 && info.anchorNestedInPoll === true, start);

  await shot('01-before');

  // ── Wait for a tick ────────────────────────────────────────────────
  // The interval is 2s; give it room for the request to land and morph.
  await eval_('window.__wire = []; true');
  await sleep(4500);

  const afterOne = await eval_(`(() => JSON.stringify({
    wire: window.__wire,
    stamps: window.$stamps(),
  }))()`);

  const one = JSON.parse(afterOne);
  console.log('tick →', JSON.stringify(one.wire));

  check('the tick actually fired', one.wire.length > 0, JSON.stringify(one.wire));

  check('…and answered with a region, not the page',
    one.wire.length > 0
      && one.wire.every((r) => r.hasHtml === false)
      && one.wire.every((r) => r.partials.includes('widget-w1')),
    JSON.stringify(one.wire));

  // The proof that nothing else re-rendered: three stamps unchanged, one moved.
  const moved = info.stamps.filter((s, i) => s !== one.stamps[i]);
  check('only the polling widget re-rendered',
    moved.length === 1 && moved[0].endsWith('-w2'),
    `moved: ${moved.join(', ') || 'none'}`);

  await shot('02-after-tick');

  // ── Still ticking ──────────────────────────────────────────────────
  // The trap this fixture's nesting exists to avoid: Livewire stops a poll whose
  // directive has left the element, so a partial that replaced the polling
  // element would switch its own poll off after exactly one tick.
  await eval_('window.__wire = []; true');
  await sleep(4500);

  const afterTwo = await eval_(`(() => JSON.stringify({
    wire: window.__wire,
    stamps: window.$stamps(),
    stillPolling: window.$pollAttrs().length,
    stillAnchored: document.querySelectorAll('[wire\\\\:partial]').length,
  }))()`);

  const two = JSON.parse(afterTwo);
  console.log('second tick →', JSON.stringify(two.wire));

  check('the poll keeps ticking after a partial replaced its region',
    two.wire.length > 0 && two.wire.every((r) => r.partials.includes('widget-w1')),
    JSON.stringify(two.wire));

  check('…with the directive and the anchor both still in place',
    two.stillPolling === 1 && two.stillAnchored === 1, afterTwo);

  check('…and the widget kept moving while its neighbours did not',
    two.stamps.filter((s, i) => s !== one.stamps[i]).length === 1, JSON.stringify(two.stamps));

  check('no console errors during the run', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  // The preview ships no favicon; a 404 for it is not this feature's problem.
  const realFailures = badResponses.filter((r) => ! r.endsWith('/favicon.ico'));
  check('no failed requests', realFailures.length === 0, realFailures.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${shotDir}`);
} finally {
  await close();
}

finish();
