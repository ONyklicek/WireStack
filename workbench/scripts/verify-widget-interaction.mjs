import { openPage, checker, until } from './lib/cdp.mjs';

/*
 * Interactive CDP driver for the three widget behaviours a browser has to prove
 * (/previews/widgets-interactive).
 *
 * Pest sees the markup a widget produces. It cannot see whether the browser then
 * does anything with it, and all three of these live exactly there:
 *
 *  1. **A deferred widget replaces its own skeleton.** `wire:init` has to fire,
 *     the response has to arrive as a `wire:partial` region, and the applier has
 *     to morph it over the placeholder. A missing bundle, a wrong anchor or a
 *     second element answering to the same name all leave a skeleton on screen
 *     and nothing in the console.
 *
 *  2. **A filter re-resolves on the server.** The list's closure is PHP; the
 *     select is HTML. The only proof the two are connected is the row text
 *     changing after a change event.
 *
 *  3. **A header action's whole route works.** A Blade button, a Livewire call
 *     the Widgets module owns, an `Action` callback run through a Foundation
 *     contract because `Widgets` and `Actions` may not import each other, and
 *     the one widget re-rendered. Every link in that chain is green in PHP on
 *     its own; only a click proves they are joined.
 *
 *  4. **The chart is rebuilt, not patched.** This is the one that cannot be
 *     tested any other way. Alpine never re-evaluates `x-data` on an element it
 *     has already initialised, so a morph that *patches* the chart wrapper hands
 *     the new datasets to nobody and Chart.js keeps drawing the old series —
 *     with correct markup, a correct response and an empty console. The
 *     `wire:key` carrying the active filter is what turns the patch into a
 *     replacement; this driver reads Chart.js's own instance to prove it.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-widget-interaction.mjs
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/widgets-interactive`;

/*
 * Every Livewire response this page makes, so a targeted answer can be told from
 * a full render.
 *
 * As a preload rather than an eval_ after settle, and that is the whole reason
 * this file needed a second pass: `wire:init` fires during component
 * initialisation, so the deferred widget's load is over before a driver that
 * installs its hook afterwards has been given a turn. The DOM assertions were
 * green while the network record was an empty array.
 */
const RECORD_LIVEWIRE = `
  window.__wire = [];
  const originalFetch = window.fetch;
  window.fetch = function (...args) {
    const target = typeof args[0] === 'string' ? args[0] : (args[0]?.url ?? '');
    const promise = originalFetch.apply(this, args);

    if (target.includes('/livewire')) {
      promise.then((response) => {
        response.clone().json().then((body) => {
          for (const component of body?.components ?? []) {
            window.__wire.push({
              hasHtml: component?.effects?.html !== undefined,
              partials: Object.keys(component?.effects?.wirePartials ?? {}),
            });
          }
        }).catch(() => {});
      }).catch(() => {});
    }

    return promise;
  };
`;

const { eval_, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url, shotPrefix: 'widget-interaction', width: 1280, height: 1100, settle: 3000,
  preload: RECORD_LIVEWIRE,
});

const { check, finish } = checker();

const json = async (expr) => JSON.parse(await eval_(expr));

try {
  await eval_(`
    window.$grid = () => document.querySelector('.wire-widget-grid');
    window.$cell = (key) => document.querySelector('[wire\\\\:partial="widget-' + key + '"]');
    window.$chart = () => {
      const canvas = document.querySelector('.wire-chart-widget canvas');

      return canvas && window.Chart ? window.Chart.getChart(canvas) : null;
    };
    true;
  `);

  // ─── 1. The deferred widget ────────────────────────────────────────────────

  // wire:init fires on component initialisation, so by settle time the swap has
  // usually happened already. Poll rather than sleep: a fixed wait reports a
  // false failure on a throttled machine.
  const loaded = await until(async () => {
    const state = await json(`(() => {
      const cell = window.$cell('quota');
      return JSON.stringify({
        anchored: !! cell,
        placeholder: !! document.querySelector('.wire-widget-placeholder'),
        real: !! document.querySelector('.wire-progress-widget'),
      });
    })()`);

    return state.real ? state : null;
  }, { timeout: 8000 });

  check('the deferred widget is anchored as its own region', loaded?.anchored === true, JSON.stringify(loaded));
  check('…and has replaced its skeleton with itself',
    loaded?.real === true && loaded?.placeholder === false, JSON.stringify(loaded));

  const deferred = await json(`JSON.stringify(window.__wire)`);
  check('…answered as that widget alone, not as the page',
    deferred.length > 0
      && deferred.every((r) => r.hasHtml === false)
      && deferred.some((r) => r.partials.includes('widget-quota')),
    JSON.stringify(deferred));

  // The eager widgets beside it were never deferred.
  const eager = await json(`(() => JSON.stringify({
    list: !! document.querySelector('.wire-list-widget'),
    chart: !! document.querySelector('.wire-chart-widget'),
    skeletons: document.querySelectorAll('.wire-widget-placeholder').length,
  }))()`);

  check('the widgets beside it were drawn eagerly',
    eager.list === true && eager.chart === true && eager.skeletons === 0, JSON.stringify(eager));

  await shot('01-loaded');

  // ─── 2. The list filter ───────────────────────────────────────────────────

  const before = await eval_(`document.querySelector('.wire-list-widget').textContent.includes('range: week')`);
  check('the list starts on its default filter key', before === true, String(before));

  await eval_(`(() => {
    const select = document.querySelector('.wire-list-widget select');
    window.__wire = [];
    select.value = 'month';
    select.dispatchEvent(new Event('change', { bubbles: true }));

    return true;
  })()`);

  const filtered = await until(async () => {
    const state = await json(`(() => {
      const list = document.querySelector('.wire-list-widget');
      return JSON.stringify({
        month: list.textContent.includes('range: month'),
        week: list.textContent.includes('range: week'),
        wire: window.__wire,
      });
    })()`);

    return state.month ? state : null;
  }, { timeout: 8000 });

  check('choosing a filter re-resolves the closure on the server',
    filtered?.month === true && filtered?.week === false, JSON.stringify(filtered));

  check('…and answers with that widget alone',
    (filtered?.wire ?? []).some((r) => r.hasHtml === false && r.partials.includes('widget-orders')),
    JSON.stringify(filtered?.wire));

  // ─── 3. The chart is rebuilt, not patched ─────────────────────────────────

  const chartBefore = await json(`(() => {
    const chart = window.$chart();
    return JSON.stringify({
      present: !! chart,
      labels: chart ? chart.data.labels.length : null,
      series: chart ? chart.data.datasets[0].label : null,
    });
  })()`);

  check('Chart.js drew the chart', chartBefore.present === true, JSON.stringify(chartBefore));
  check('…on the first filter key', chartBefore.labels === 4 && chartBefore.series === 'Orders q1',
    JSON.stringify(chartBefore));

  await eval_(`(() => {
    const select = document.querySelector('.wire-chart-widget select');
    window.__wire = [];
    select.value = 'q2';
    select.dispatchEvent(new Event('change', { bubbles: true }));

    return true;
  })()`);

  const chartAfter = await until(async () => {
    const state = await json(`(() => {
      const chart = window.$chart();
      return JSON.stringify({
        labels: chart ? chart.data.labels.length : null,
        series: chart ? chart.data.datasets[0].label : null,
        key: (document.querySelector('.wire-chart-widget') || {}).getAttribute
          ? document.querySelector('.wire-chart-widget').getAttribute('wire:key')
          : null,
      });
    })()`);

    return state.series === 'Orders q2' ? state : null;
  }, { timeout: 8000 });

  // The whole reason the wrapper carries the filter in its wire:key. Without the
  // replacement this reads "Orders q1" against a perfectly correct response.
  check('the chart is rebuilt over the new series rather than left on the old one',
    chartAfter?.series === 'Orders q2' && chartAfter?.labels === 2, JSON.stringify(chartAfter));

  check('…and its key moved with the filter',
    typeof chartAfter?.key === 'string' && chartAfter.key.endsWith('-q2'), JSON.stringify(chartAfter));

  // ─── 4. A header action runs and re-renders its widget ────────────────────

  const actionBefore = await eval_(
    `document.querySelector('.wire-progress-widget').textContent.includes('recounts: 0')`,
  );

  check('the deferred widget starts with its action never run', actionBefore === true, String(actionBefore));

  await eval_(`(() => {
    window.__wire = [];
    document.querySelector('[data-testid="widget-action-recount"]').click();

    return true;
  })()`);

  const acted = await until(async () => {
    const state = await json(`(() => {
      const progress = document.querySelector('.wire-progress-widget');
      return JSON.stringify({
        text: progress.textContent.includes('recounts: 1'),
        wire: window.__wire,
      });
    })()`);

    return state.text ? state : null;
  }, { timeout: 8000 });

  // The whole route: a Blade button, a Livewire call the Widgets module owns, an
  // Action callback run through a Foundation contract because the two modules
  // may not import each other, and the one widget re-rendered afterwards.
  check('clicking a header action runs its callback', acted?.text === true, JSON.stringify(acted));

  check('…and answers with that widget alone',
    (acted?.wire ?? []).some((r) => r.hasHtml === false && r.partials.includes('widget-quota')),
    JSON.stringify(acted?.wire));

  await shot('03-action');
} catch (err) {
  check('driver ran to completion', false, err?.message ?? String(err));
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}
