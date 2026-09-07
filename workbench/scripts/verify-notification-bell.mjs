import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * The notification bell — its panel, and the live bridge behind it.
 *
 * Pest sees the markup (packages/core/tests/Unit/Notifications). What only a
 * browser can answer is what it *does*:
 *
 *   - the panel is a teleported slide-over entangled with a server property, so
 *     "does clicking the bell open anything" is a question about Alpine, a
 *     `<template x-teleport>` and a Livewire round trip, not about HTML.
 *   - the bridge subscribes to a channel named after the recipient. A mistyped
 *     name raises nothing: the subscription is refused, the push stops arriving,
 *     the bell keeps being right on every render — and nobody finds out.
 *   - a broadcast has to make it re-read. That is the whole feature; the event
 *     carries no payload, so if the round trip does not happen, nothing does.
 *
 * `window.Echo` is stubbed before the page loads, which is also the honest shape
 * of the contract: anything Echo-like will do, and a page with no Echo at all
 * must degrade to a bell that is late rather than to one that throws.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-notification-bell.mjs
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

// A minimal Echo: records what was subscribed to and hands back a way to fire.
const echoStub = `
  window.__echoLog = { channels: [], listeners: [], left: [], errorHandlers: [] };
  window.Echo = {
    private(name) {
      window.__echoLog.channels.push(name);
      return {
        listen(event, cb) {
          window.__echoLog.listeners.push({ channel: name, event, cb });
          return this;
        },
        error(cb) { window.__echoLog.errorHandlers.push(cb); return this; },
      };
    },
    leave(name) { window.__echoLog.left.push(name); },
    // Livewire reads this on every request to stamp X-Socket-Id, so a stub
    // without it breaks every round trip on the page.
    socketId() { return 'driver-stub-socket'; },
  };
  window.__refuseSubscription = () => {
    window.__echoLog.errorHandlers.forEach((cb) => cb({ status: 403 }));
    return window.__echoLog.errorHandlers.length;
  };
  window.__fireBroadcast = () => {
    window.__echoLog.listeners.forEach((l) => l.cb({}));
    return window.__echoLog.listeners.length;
  };
`;

const page_ = await openPage({
  url: `${base}/routed/invoices`,
  shotPrefix: 'notification-bell',
  width: 1300,
  height: 900,
  preload: echoStub,
});
const { eval_, waitFor, shot, shotDir, consoleErrors, close } = page_;

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="notification-bell"]')`);

  // ── 1. The bridge ────────────────────────────────────────────────────────
  const channels = await eval_(`window.__echoLog.channels`);
  check('the bell subscribed to exactly one channel',
    Array.isArray(channels) && channels.length === 1, JSON.stringify(channels));
  // Named after the recipient, not after a model: a notification is addressed to
  // somebody, and this is the name the server broadcasts on.
  check('the channel names the signed-in recipient',
    (channels ?? [])[0] === 'wire-notifications.Workbench-App-Models-User.1', (channels ?? [])[0]);

  const listeners = await eval_(`window.__echoLog.listeners.map(l => l.event)`);
  check('it listens for the event name the server sends',
    JSON.stringify(listeners) === JSON.stringify(['.wire-notification.received']), JSON.stringify(listeners));

  // A refused subscription is the one failure that looks like success — the bell
  // keeps updating on every render — so it has to say something, once.
  const warned = await eval_(`(() => {
    const seen = [];
    const orig = console.warn;
    console.warn = (...a) => { seen.push(a.join(' ')); orig.apply(console, a); };
    const n = window.__refuseSubscription();
    window.__refuseSubscription();
    console.warn = orig;
    return JSON.stringify({ handlers: n, hits: seen.filter(s => s.includes('wire-notifications')).length,
                            text: seen.find(s => s.includes('wire-notifications')) ?? null });
  })()`);
  const refusal = JSON.parse(warned);
  check('a refused subscription is reported, with the fix in the message',
    refusal.handlers === 1 && refusal.text && refusal.text.includes('routes/channels.php'), warned);
  check('and only once, however often the connector retries', refusal.hits === 1, warned);

  // ── 2. A broadcast makes it re-read ──────────────────────────────────────
  // Counted through Livewire's own commit hook rather than by wrapping fetch:
  // "it re-read" is then an observation of the round trip actually happening,
  // and it does not depend on how the client happens to make its request.
  await eval_(`(() => {
    window.__updates = 0;
    window.Livewire.hook('commit', () => { window.__updates++; });
    return true;
  })()`);

  await eval_(`window.__fireBroadcast()`);
  // Longer than the bridge's 250ms settle.
  await sleep(1500);
  check('a broadcast makes the bell re-read',
    (await eval_(`window.__updates`)) >= 1, `updates=${await eval_(`window.__updates`)}`);

  // A bulk job notifying one user forty times is forty broadcasts, and forty
  // re-reads of the same list would be the feature costing more than it saves.
  await eval_(`(() => { window.__updates = 0; for (let i = 0; i < 8; i++) window.__fireBroadcast(); return true; })()`);
  await sleep(1500);
  const burst = await eval_(`window.__updates`);
  check('a burst coalesces into one re-read', burst === 1, `updates=${burst}`);

  // ── 3. The panel ─────────────────────────────────────────────────────────
  await eval_(`document.querySelector('[data-testid="notification-bell"]').click()`);
  await waitFor(`(() => {
    const p = document.querySelector('#wire-notifications');
    return !! p && getComputedStyle(p).display !== 'none';
  })()`);
  check('the bell opens the panel', true);
  await shot('01-panel-open');

  const items = await eval_(`document.querySelectorAll('[data-testid="notification-item"]').length`);
  check('the panel lists the stored notifications', items > 0, `${items} rows`);
  // A row is either unread (dot) or read; the panel says which without colour
  // alone, which is the half a screenshot cannot check.
  check('an unread row carries a dot as well as a tint', await eval_(`
    !! document.querySelector('[data-testid="notification-unread-dot"]')
  `));
  check('and offers both tabs', await eval_(`
    !! document.querySelector('[data-testid="notification-tab-all"]')
      && !! document.querySelector('[data-testid="notification-tab-unread"]')
  `));

  // The tab is a server round trip, so this is also the panel surviving a morph
  // while open — the failure mode a teleported slide-over actually has.
  await eval_(`document.querySelector('[data-testid="notification-tab-unread"]').click()`);
  await sleep(1200);
  const stillOpen = await eval_(`(() => {
    const p = document.querySelector('#wire-notifications');
    return !! p && getComputedStyle(p).display !== 'none';
  })()`);
  check('switching tabs keeps the panel open', stillOpen);
  const unread = await eval_(`document.querySelectorAll('[data-testid="notification-item"]').length`);
  check('the unread tab shows no more than the whole list did', unread <= items, `${unread} of ${items}`);
  await shot('02-unread-tab');

  // The badge has three states and only a browser can tell them apart on a real
  // page: the count while something is unread, a quiet dot once it is not, and
  // nothing at all when there is nothing.
  check('the bell shows a count while something is unread', await eval_(`
    !! document.querySelector('[data-testid="notification-bell-count"]')
      && ! document.querySelector('[data-testid="notification-bell-dot"]')
  `));
  check('and says the number in its accessible name, not only in the circle', await eval_(`
    document.querySelector('[data-testid="notification-bell"]').textContent.includes('—')
  `));

  // ── 4. The verbs ─────────────────────────────────────────────────────────
  // Back to the whole list, and read → unread → read on one row: reversible on
  // purpose, because the preview database is shared with every other driver and
  // with the screenshots. Delete is asserted as an affordance rather than
  // clicked for the same reason — a run that removed a seeded notification
  // would leave the bell with less to demonstrate for everyone after it.
  await eval_(`document.querySelector('[data-testid="notification-tab-all"]').click()`);
  await sleep(1200);

  const badgeCount = async () => eval_(`
    document.querySelector('[data-testid="notification-bell-count"]')?.textContent?.trim() ?? null
  `);
  const before = await badgeCount();

  check('a row offers both a way to mark it and a way to delete it', await eval_(`
    !! document.querySelector('[data-testid="notification-mark-read"]')
      && !! document.querySelector('[data-testid="notification-delete"]')
  `));

  await eval_(`document.querySelector('[data-testid="notification-mark-read"]').click()`);
  await waitFor(`(document.querySelector('[data-testid="notification-bell-count"]')?.textContent?.trim() ?? null) !== ${JSON.stringify(before)}`);
  const after = await badgeCount();
  check('marking one read moves the badge', after !== before, `${before} → ${after}`);

  // And back, which is the verb an inbox has and the one that restores the seed.
  await eval_(`document.querySelector('[data-testid="notification-mark-unread"]').click()`);
  await waitFor(`(document.querySelector('[data-testid="notification-bell-count"]')?.textContent?.trim() ?? null) === ${JSON.stringify(before)}`);
  check('and marking it unread again puts it back', (await badgeCount()) === before, `${await badgeCount()} vs ${before}`);
  await shot('03-verbs');

  // ── 5. Where a row goes, and what it lets you do ─────────────────────────
  // The seeded notifications carry a destination and a stored action button —
  // the two things that only mean anything once the payload survives the write.
  check('a row with a destination is a real link', await eval_(`
    !! document.querySelector('[data-testid="notification-open"][href]')
  `));
  check('a stored action renders as something clickable', await eval_(`
    !! document.querySelector('[data-testid="notification-action"]')
  `));

  // ── 6. The affordances that come and go ──────────────────────────────────
  // Deliberately NOT clicking mark-all: the preview database is shared with
  // every other driver and with the screenshots, and a run that marked the
  // seeded notifications read would leave the bell with nothing to demonstrate
  // for everyone after it. What the click does is a Livewire round trip, which
  // the tab switch above already proved in this browser and Pest proves for the
  // component; what only a browser can confirm is that the footer offers each
  // affordance exactly when it has something to do.
  const badge = await eval_(`!! document.querySelector('[data-testid="notification-bell-count"]')`);
  const markAll = await eval_(`!! document.querySelector('[data-testid="notification-mark-all"]')`);
  check('clear-read is offered exactly when something has been read', await eval_(`
    (!! document.querySelector('[data-testid="notification-clear-read"]'))
      === (document.querySelectorAll('[data-testid="notification-mark-unread"]').length > 0)
  `));
  check('mark-all is offered exactly when something is unread',
    badge === markAll, `badge=${badge} markAll=${markAll}`);

  // Absent rather than dead: wire-core routes nothing itself, so the link only
  // appears where a page package answers for the `notifications` key — which is
  // what installing wire-module-notifications does, and the workbench has.
  check('the panel links out to the full list', await eval_(`
    !! document.querySelector('[data-testid="notification-view-all"]')
  `));
  await shot('04-footer');

  check('no console errors', consoleErrors.length === 0, consoleErrors.join(' | '));
  console.log(`\nScreenshots: ${shotDir}`);
} finally {
  await close();
}

finish();
