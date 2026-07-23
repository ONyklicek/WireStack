#!/usr/bin/env node
// Drives the stacked-card selection in a phone-sized headless Chrome: the
// always-visible select-all strip, the escalation to "everything the filter
// matches", exclusions inside that mode, and the mobile sort control — none of
// which the desktop table exercises, since its own controls live in a header
// row that is hidden at this width.
//
// Usage: node workbench/scripts/verify-mobile-selection.mjs
// Env: PREVIEW_URL, CHROME_BIN, CHROME_PORT, SHOT_DIR

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const BASE = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085';
const URL_PAGE = `${BASE}/previews/table-stacked-selection`;
const CHROME = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = Number(process.env.CHROME_PORT ?? 9244);
const SHOT_DIR = process.env.SHOT_DIR ?? '/tmp/mobile-selection';

mkdirSync(SHOT_DIR, { recursive: true });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? `  — ${detail}` : ''}`);
};

async function waitForDevtools() {
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) return res.json();
    } catch {}
    await sleep(250);
  }
  throw new Error('Chrome DevTools never came up');
}

const chrome = spawn(CHROME, [
  '--headless=new', `--remote-debugging-port=${PORT}`,
  '--no-first-run', '--no-default-browser-check', '--disable-gpu', 'about:blank',
], { stdio: 'ignore' });

try {
  const version = await waitForDevtools();
  const ws = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });

  let id = 0;
  const pending = new Map();
  const events = [];
  ws.onmessage = (m) => {
    const msg = JSON.parse(m.data);
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
    else events.push(msg);
  };
  const send = (method, params = {}, sessionId) => new Promise((resolve) => {
    const msgId = ++id;
    pending.set(msgId, resolve);
    ws.send(JSON.stringify({ id: msgId, method, params, sessionId }));
  });

  const { result: target } = await send('Target.createTarget', { url: 'about:blank' });
  const { result: attached } = await send('Target.attachToTarget', { targetId: target.targetId, flatten: true });
  const session = attached.sessionId;

  const evaluate = async (expression) => {
    const res = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, session);
    return res.result?.result?.value;
  };
  const shot = async (name) => {
    const res = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, session);
    writeFileSync(`${SHOT_DIR}/${name}.png`, Buffer.from(res.result.data, 'base64'));
  };

  await send('Page.enable', {}, session);
  await send('Runtime.enable', {}, session);
  // Device emulation, not --window-size: headless clamps the window but honours this.
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 900, deviceScaleFactor: 2, mobile: true }, session);
  await send('Page.navigate', { url: URL_PAGE }, session);
  await sleep(3500);

  const state = () => evaluate(`(() => {
    const q = (s) => document.querySelector(s);
    const vis = (el) => !!el && !!el.offsetParent;
    const strip = q('[data-testid=table-card-select-all]');
    const scope = q('[data-testid=table-selection-scope]');
    return {
      strip: !!strip,
      stripState: strip?.getAttribute('aria-checked'),
      cards: document.querySelectorAll('[data-testid=table-card]').length,
      checked: [...document.querySelectorAll('[data-testid=table-card-select]')].map(c => c.checked),
      count: q('[data-testid=table-card-select-count]')?.innerText.trim().replace(/\\s+/g, ' '),
      bulkCount: q('[data-testid=table-bulk-bar] .rounded-full span')?.innerText.trim(),
      scopeText: vis(scope) ? scope.innerText.trim().replace(/\\s+/g, ' ') : null,
      canSelectAllMatching: vis(q('[data-testid=table-select-all-matching]')),
      canSelectOnlyPage: vis(q('[data-testid=table-select-only-page]')),
      mobileSort: vis(q('[data-testid=table-mobile-sort]')),
      sortLabel: q('[data-testid=table-mobile-sort]')?.innerText.trim(),
    };
  })()`);

  await shot('01-initial');
  let s = await state();
  check('select-all strip renders on the card view', s.strip && s.stripState !== null, `aria-checked=${s.stripState}`);
  check('page holds fewer rows than the filter matches', s.cards === 2, `${s.cards} cards`);
  check('the scope line is present, not hidden until discovered', s.scopeText !== null, s.scopeText ?? 'absent');

  // ── strip selects the page ──
  await evaluate(`document.querySelector('[data-testid=table-card-select-all]').click()`);
  await sleep(1200);
  s = await state();
  check('strip selects every card on the page', s.checked.every(Boolean) && s.stripState === 'true', JSON.stringify(s.checked));
  check('escalation to the whole filtered set is offered', s.canSelectAllMatching, s.scopeText ?? '');
  await shot('02-page-selected');

  // ── escalate to everything matching ──
  await evaluate(`document.querySelector('[data-testid=table-select-all-matching]').click()`);
  await sleep(2000);
  s = await state();
  check('count reports the whole filtered set, not the page', /4/.test(s.bulkCount ?? ''), `bulk count=${s.bulkCount}`);
  check('the way back to a page selection is offered', s.canSelectOnlyPage, s.scopeText ?? '');
  await shot('03-all-matching');

  // ── an exclusion inside "all" mode ──
  await evaluate(`document.querySelectorAll('[data-testid=table-card-select]')[0].click()`);
  await sleep(1500);
  s = await state();
  check('unticking one row excludes it without dropping the mode', s.checked[0] === false && s.canSelectOnlyPage, JSON.stringify(s.checked));
  check('count drops by exactly one', s.bulkCount === '3', `bulk count=${s.bulkCount}`);
  await shot('04-exclusion');

  // ── changing the filter must not leave "everything" pointing elsewhere ──
  await evaluate(`(() => {
    const input = document.querySelector('[data-testid=table-search]');
    input.value = 'sofia';
    input.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await sleep(2500);
  s = await state();
  check('a search resets the selection scope', s.scopeText === null || !s.canSelectOnlyPage, s.scopeText ?? 'cleared');
  await shot('05-after-search');

  // ── mobile sort ──
  const sorted = await evaluate(`(() => {
    const trigger = document.querySelector('[data-testid=table-mobile-sort]');
    trigger.click();
    return !!document.querySelector('[data-testid^=table-mobile-sort-]');
  })()`);
  await sleep(800);
  check('mobile sort control exists and opens', s.mobileSort && sorted === true, s.sortLabel ?? '');
  await shot('06-sort-sheet');

  const errors = events
    .filter((e) => e.method === 'Runtime.consoleAPICalled' && e.params?.type === 'error')
    .map((e) => e.params.args.map((a) => a.value ?? a.description).join(' '));
  check('no console errors', errors.length === 0, errors.slice(0, 2).join(' | '));

  console.log(`\nScreenshots: ${SHOT_DIR}`);
  ws.close();
} finally {
  chrome.kill();
}

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length === 0 ? 0 : 1);
