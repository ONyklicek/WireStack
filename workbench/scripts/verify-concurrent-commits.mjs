import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for two inline-edit commits racing each other
 * (/previews/table-editable-fill).
 *
 * This is the driver the Livewire 4 migration owed itself. On v3 the commit bus
 * serialised everything for one component — `CommitBus.add()` refused to open a
 * second pool while one was in flight for that component — and a wireStack table
 * is ONE component, so rows, toolbar, selection and every inline edit strictly
 * queued. On v4 they can be in flight together.
 *
 * That matters here because every editable cell captures the record's
 * optimistic-lock version at render time, and the cells of one record share it
 * (verified below as the premise). When one cell commits, the winner broadcasts
 * `wire-editable-committed` so its siblings can adopt the new version. Serialised,
 * that broadcast always landed before the next commit left. In parallel it need
 * not, so a sibling can send a version it read before the other cell moved the
 * record — and take the conflict branch, which is the least-exercised path in the
 * editable cell.
 *
 * What this driver does NOT assert, and why it would be wrong to:
 *
 *   `RecordVersion::stamp()` is `updated_at` as a UNIX timestamp in WHOLE SECONDS
 *   (packages/core/src/Foundation/Support/RecordVersion.php:36-40), and
 *   `conflicts()` compares that string. Two commits in the same tick therefore
 *   almost always produce the SAME stamp, so they cannot conflict — the lock has
 *   one-second granularity by construction. A driver asserting "the conflict UI
 *   appears when two cells race" would be asserting a bug, and would go red the
 *   moment the two commits happened to straddle a second boundary.
 *
 * So the race check asserts the properties that actually have to hold, and the
 * conflict branch is also exercised deterministically, by handing the server a
 * version that is definitely stale.
 *
 * WHAT THIS DRIVER FOUND, AND NOW GUARDS
 *
 * Two same-tick commits are not two requests. Livewire bundles them into ONE
 * (`requests: 1, maxInFlight: 1` — printed below), and the server then runs them
 * in order within it. The first write moves the record's `updated_at`; the second
 * arrived carrying the version read at RENDER time, no longer matched, and was
 * refused as "modified by another user" — when in fact it was the same user, in
 * the same request, one call earlier. The second edit was lost.
 *
 * `wire-editable-committed` could never have covered it: it is dispatched from
 * the commit's RESPONSE handler (dropdown.js:697), so it fires long after a
 * sibling bundled into the same request was serialised into the payload. It was
 * not a Livewire 4 regression either — v3 squashed same-tick calls into a single
 * commit inside its 5 ms buffer too.
 *
 * Fixed in RecordVersion, which now remembers the stamp each record carried when
 * the request first looked at it and accepts that baseline however many times the
 * request has written since. A version matching neither the current stamp nor the
 * baseline is still a conflict, so the cross-client guarantee is unchanged — and
 * the forced-conflict checks at the end of this driver are what hold that line.
 *
 * So BOTH commits must now land, and this driver is the only thing that runs the
 * bundling for real: the Pest coverage drives two calls through one component,
 * but only a browser actually puts them in one Livewire request.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-concurrent-commits.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-editable-fill`;

const { page, eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'concurrent-commits', width: 1400, height: 1000, settle: 4000,
});

const { check, finish } = checker();

const stamp = Date.now();
const newEmail = `race-${stamp}@example.test`;

try {
  await eval_(`
    window.$cells = () => [...document.querySelectorAll('tbody [data-record-key][data-column-name]')];
    window.$cell = (key, col) => window.$cells().find(
      (c) => c.dataset.recordKey === key && c.dataset.columnName === col
    );
    window.$data = (key, col) => Alpine.$data(window.$cell(key, col));
    window.$host = () => window.Livewire.find(
      document.querySelector('[wire\\\\:id]').getAttribute('wire:id')
    );

    // Count Livewire roundtrips and how many were ever in flight at once. This is
    // reported, not asserted: whether v4 bundles two same-tick calls into one
    // request or overlaps two is its decision to make, and both are correct. What
    // must hold is the outcome, below.
    window.__net = { requests: 0, maxInFlight: 0, inFlight: 0 };
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
      const target = typeof args[0] === 'string' ? args[0] : (args[0]?.url ?? '');
      const isLivewire = target.includes('/livewire');
      if (isLivewire) {
        window.__net.requests++;
        window.__net.inFlight++;
        window.__net.maxInFlight = Math.max(window.__net.maxInFlight, window.__net.inFlight);
      }
      return originalFetch.apply(this, args).finally(() => {
        if (isLivewire) window.__net.inFlight--;
      });
    };
    true;
  `);

  // ── The premise ────────────────────────────────────────────────────
  // If these stop holding, the race this driver describes is not the race the
  // page runs, and every check below is measuring something else.
  const premise = JSON.parse(await eval_(`(() => {
    const email = window.$data('1', 'email');
    const role  = window.$data('1', 'role');
    return JSON.stringify({
      bothCommit: typeof email?.commit === 'function' && typeof role?.commit === 'function',
      sameVersion: email?.recordVersion === role?.recordVersion,
      sameComponent: email?.componentId === role?.componentId,
      version: email?.recordVersion,
    });
  })()`));

  check('two editable cells of one record are live and commit-capable', premise.bothCommit, JSON.stringify(premise));
  check('…they share one optimistic-lock version', premise.sameVersion, `version=${premise.version}`);
  check('…and one Livewire component, so on v3 they would have queued',
    premise.sameComponent, `component=${premise.sameComponent}`);

  await shot('01-initial');

  // ── The race ───────────────────────────────────────────────────────
  // Both commits are started in the same tick, with no await between them, which
  // is what a user produces by tabbing out of one cell straight into another.
  const raced = JSON.parse(await eval_(`(async () => {
    const email = window.$data('1', 'email');
    const role  = window.$data('1', 'role');
    const nextRole = role.value === 'admin' ? 'editor' : 'admin';

    const both = Promise.all([
      email.commit(${JSON.stringify(newEmail)}),
      role.commit(nextRole),
    ]);

    const inFlightAtStart = window.__net.inFlight;
    await both;
    await new Promise((r) => setTimeout(r, 900));

    const e = window.$data('1', 'email');
    const r = window.$data('1', 'role');
    return JSON.stringify({
      nextRole,
      inFlightAtStart,
      net: window.__net,
      email: { value: e.value, serverValue: e.serverValue, saving: e.saving, error: e.error, version: e.recordVersion },
      role:  { value: r.value, serverValue: r.serverValue, saving: r.saving, error: r.error, version: r.recordVersion },
    });
  })()`));

  console.log('raced →', JSON.stringify(raced.net), `(inFlight when the second started: ${raced.inFlightAtStart})`);

  check('both racing commits settle', raced.email.saving === false && raced.role.saving === false,
    JSON.stringify({ email: raced.email.saving, role: raced.role.saving }));

  check('the first of the two commits is accepted',
    raced.email.serverValue === newEmail && ! raced.email.error,
    JSON.stringify(raced.email));

  // The regression this driver exists for: the second call rides in the same
  // request as the first, so it carries a version the first has already
  // superseded. It must still be accepted — it is the same request, not another
  // user.
  check('the second is accepted too, though the first already moved the record',
    raced.role.serverValue === raced.nextRole && ! raced.role.error,
    JSON.stringify(raced.role));

  check('…and neither cell shows a value it did not write',
    raced.email.value === raced.email.serverValue && raced.role.value === raced.role.serverValue,
    JSON.stringify({ email: raced.email.value, role: raced.role.value }));

  // The sibling broadcast is what stops the SECOND edit of a record from carrying
  // a version the FIRST one already invalidated. Parallel requests are exactly the
  // case where it can arrive late, so this is the assertion that earns the driver.
  check('…and both cells hold the same version afterwards, so neither is stale',
    raced.email.version === raced.role.version,
    JSON.stringify({ email: raced.email.version, role: raced.role.version }));

  await shot('02-after-race');

  // ── Both writes actually persisted ─────────────────────────────────
  // Client state agreeing with itself is not evidence the database moved. Reload
  // and read what the server renders.
  await page('Page.navigate', { url });
  await sleep(4000);

  const persisted = JSON.parse(await eval_(`(() => {
    const e = window.$data ? window.$data('1', 'email') : null;
    return JSON.stringify({ ready: !!e });
  })()`).catch(() => '{"ready":false}'));

  // The page is fresh, so the helpers have to be re-installed.
  await eval_(`
    window.$cells = () => [...document.querySelectorAll('tbody [data-record-key][data-column-name]')];
    window.$cell = (key, col) => window.$cells().find(
      (c) => c.dataset.recordKey === key && c.dataset.columnName === col
    );
    window.$data = (key, col) => Alpine.$data(window.$cell(key, col));
    true;
  `);

  const reloaded = JSON.parse(await eval_(`(() => {
    const e = window.$data('1', 'email');
    const r = window.$data('1', 'role');
    return JSON.stringify({ email: e?.serverValue, role: r?.serverValue, version: e?.recordVersion });
  })()`));

  check('the first write survives a reload — it reached the database',
    reloaded.email === newEmail, `email=${reloaded.email}`);
  check('…and so does the second, so no edit was lost to a false conflict',
    reloaded.role === raced.nextRole, `role=${reloaded.role}, wrote=${raced.nextRole}`);

  await shot('03-after-reload');

  // ── The conflict branch, forced ────────────────────────────────────
  // Deterministic where the race is not: '1' is a real-looking stamp from 1970, so
  // it can never match. This is the path the v4 parallelism makes more reachable,
  // and nothing else in the sweep executes it.
  const conflicted = JSON.parse(await eval_(`(async () => {
    const cell = window.$data('1', 'email');
    cell.recordVersion = '1';
    await cell.commit('stale-writer-${stamp}@example.test');
    await new Promise((r) => setTimeout(r, 900));
    const after = window.$data('1', 'email');
    return JSON.stringify({
      value: after.value,
      serverValue: after.serverValue,
      error: after.error,
      version: after.recordVersion,
      saving: after.saving,
    });
  })()`));

  console.log('conflict →', JSON.stringify(conflicted));

  check('a commit carrying a stale version is refused', !! conflicted.error, JSON.stringify(conflicted));
  check('…the cell adopts the server value rather than keeping the rejected one',
    conflicted.value === reloaded.email && conflicted.serverValue === reloaded.email,
    JSON.stringify({ shown: conflicted.value, server: reloaded.email }));
  check('…and adopts a usable version, so the next edit is not refused too',
    conflicted.version && conflicted.version !== '1', `version=${conflicted.version}`);
  check('…and the cell is left editable, not stuck saving', conflicted.saving === false,
    `saving=${conflicted.saving}`);

  await shot('04-after-conflict');

  // The recovery is the point of adopting the version: the same cell must now be
  // able to write. A conflict that leaves the cell permanently unable to save is
  // the failure this pairs against.
  const recovered = JSON.parse(await eval_(`(async () => {
    const cell = window.$data('1', 'email');
    await cell.commit('recovered-${stamp}@example.test');
    await new Promise((r) => setTimeout(r, 900));
    const after = window.$data('1', 'email');
    return JSON.stringify({ serverValue: after.serverValue, error: after.error });
  })()`));

  check('…after which the cell can commit again',
    recovered.serverValue === `recovered-${stamp}@example.test` && ! recovered.error,
    JSON.stringify(recovered));

  await shot('05-after-recovery');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
