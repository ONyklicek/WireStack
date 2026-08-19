import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * CDP driver for `Form::fieldPartials()` (/previews/forms-field-partials).
 *
 * Pest can see which regions the server queued. It cannot see the thing the
 * feature is for: whether the response carried a view at all. The view is
 * skipped by `PartialRenderHook::call()`, which needs a call to run — and
 * `Testable::set()` sends updates with `calls: []`. A browser never does:
 * `wire:model.live` sends Livewire's synthetic `$commit` alongside its updates
 * (livewire.esm.js `component.$wire.$commit()`), so only here does the skip
 * actually happen.
 *
 * Three commits, three different answers:
 *   - typing in `note`, which nothing reads, moves no markup anywhere (a
 *     TextInput renders no `value` attribute; the value rides wire:model), so the
 *     response carries neither a view nor a region;
 *   - `summary` reads `name` in its helperText closure, so its markup moves and
 *     comes back as a region while the view stays skipped;
 *   - typing `b` into `kind` makes `extra` appear, which changes the SET of
 *     fields — a shape change no region describes — and falls back to a full
 *     render.
 *
 * The heading outside the form is the witness for the documented trade: it reads
 * the same state and must not update on a covered commit.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-field-partials.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/forms-field-partials`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'field-partials', width: 1200, height: 900, settle: 3000,
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
              htmlBytes: bodies.reduce((n, c) => n + String(c?.effects?.html ?? '').length, 0),
              partials: Object.keys(partials),
              partialBytes: Object.values(partials).reduce((n, h) => n + String(h).length, 0),
            });
          }).catch(() => {});
        }).catch(() => {});
      }

      return promise;
    };

    window.$input = (path) => document.querySelector('[data-field="data.' + path + '"] input');
    window.$anchors = () => Array.from(document.querySelectorAll('[wire\\\\:partial]'))
      .map((el) => el.getAttribute('wire:partial'));
    window.$helper = () => document.querySelector('[data-field="data.summary"]')?.textContent ?? '';

    window.$type = async (path, value) => {
      const el = window.$input(path);
      el.value = value;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      el.blur();
      await new Promise((r) => setTimeout(r, 1600));
    };
    true;
  `);

  const start = await eval_(`(() => JSON.stringify({ anchors: window.$anchors() }))()`);
  check('every field carries its own anchor', JSON.parse(start).anchors.length >= 3, start);

  await shot('01-before');

  // ── A commit that establishes the baseline stamps ──────────────────
  await eval_(`window.__wire = []; true`);
  await eval_(`window.$type('note', 'Baseline')`);

  // ── A plain commit: nothing reads `note`, so nothing moved ─────────
  await eval_(`window.__wire = []; true`);
  await eval_(`window.$type('note', 'Grace')`);

  const plain = JSON.parse(await eval_(`(() => JSON.stringify({
    wire: window.__wire,
    helper: window.$helper(),
  }))()`));
  console.log('plain commit →', JSON.stringify(plain.wire));

  check('a plain commit reaches the server', plain.wire.length > 0, JSON.stringify(plain.wire));
  check('…and answers with no view',
    plain.wire.every((r) => r.hasHtml === false), JSON.stringify(plain.wire));
  check('…and with no region either, because nothing moved',
    plain.wire.every((r) => r.partials.length === 0), JSON.stringify(plain.wire));

  // ── A commit a sibling depends on ──────────────────────────────────
  await eval_(`window.__wire = []; true`);
  await eval_(`window.$type('name', 'Ada')`);
  await sleep(600);

  const dependent = JSON.parse(await eval_(`(() => JSON.stringify({
    wire: window.__wire,
    helper: window.$helper(),
  }))()`));
  console.log('dependent commit →', JSON.stringify(dependent.wire));

  // helperText reads `name`, so its markup moves — and the region must actually
  // reach the DOM, which is the half only a browser can answer.
  check('a sibling that reads the field comes back as a region',
    dependent.wire.some((r) => r.partials.includes('field-data.summary')),
    JSON.stringify(dependent.wire));

  check('…with the view still skipped',
    dependent.wire.every((r) => r.hasHtml === false), JSON.stringify(dependent.wire));

  check('…and the region was applied to the page',
    dependent.helper.includes('Summary for Ada'), dependent.helper.trim().slice(0, 80));

  await shot('02-after-dependent');

  // ── A commit that changes the shape ────────────────────────────────
  await eval_(`window.__wire = []; true`);
  await eval_(`window.$type('kind', 'b')`);
  await sleep(600);

  const shape = JSON.parse(await eval_(`(() => JSON.stringify({
    wire: window.__wire,
    anchors: window.$anchors(),
  }))()`));
  console.log('shape commit →', JSON.stringify(shape.wire));

  check('a field appearing falls back to a full render',
    shape.wire.some((r) => r.hasHtml === true), JSON.stringify(shape.wire));

  check('…and the new field is really on the page',
    shape.anchors.includes('field-data.extra'), shape.anchors.join(', '));

  await shot('03-after-shape');

  check('no console errors during the run', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));

  const realFailures = badResponses.filter((r) => ! r.endsWith('/favicon.ico'));
  check('no failed requests', realFailures.length === 0, realFailures.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${shotDir}`);
} finally {
  await close();
}

finish();
