#!/usr/bin/env node
// Proves the interactive sub-row filter bar actually filters the children — the
// bug it fixes was invisible on the server (state was set by hand in tests) and
// only shows in a browser, where the input's wire:model decides where the typed
// value lands. Before the fix it wrote to the parent table's columnFilters; now
// it writes to rows.subRowFilters and narrows the child list.
//
// Usage: node workbench/scripts/verify-subrow-filter.mjs
// Env: PREVIEW_URL, CHROME_BIN, CHROME_PORT, SHOT_DIR

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const BASE = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085';
const URL_PAGE = `${BASE}/previews/table-subrows-filter`;
const CHROME = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = Number(process.env.CHROME_PORT ?? 9266);
const SHOT_DIR = process.env.SHOT_DIR ?? '/tmp/subrow-filter';

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
  '--headless=new', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${PORT}`,
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
    if (res.result?.exceptionDetails) throw new Error(JSON.stringify(res.result.exceptionDetails));
    return res.result?.result?.value;
  };
  const shot = async (name) => {
    const res = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, session);
    writeFileSync(`${SHOT_DIR}/${name}.png`, Buffer.from(res.result.data, 'base64'));
  };

  await send('Page.enable', {}, session);
  await send('Runtime.enable', {}, session);
  // Desktop width: the interactive sub-row filter bar lives in the table
  // sub-rows partial, which the stacked card layout hides below the breakpoint.
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false }, session);
  await send('Page.navigate', { url: URL_PAGE }, session);
  await sleep(3500);
  await shot('01-initial');

  const state = () => evaluate(`(() => {
    const childRows = document.querySelectorAll('[wire\\\\:key^="sub-row-"]');
    const products = [...childRows].map(r => r.querySelector('td')?.nextElementSibling?.innerText?.trim()).filter(Boolean);
    const input = document.querySelector('[data-testid^="subrows-filter"] input, [wire\\\\:model*="subRowFilters"]')
      || [...document.querySelectorAll('input')].find(i => (i.getAttribute('wire:model.live.debounce.300ms') || i.getAttribute('wire:model') || '').includes('subRowFilters'));
    return {
      parentRows: document.querySelectorAll('[data-testid="table-row-expand"]').length,
      childRows: childRows.length,
      products,
      hasSubRowInput: !!input,
      inputModel: input?.getAttribute('wire:model.live.debounce.300ms') || input?.getAttribute('wire:model') || null,
      resetVisible: !!document.querySelector('[data-testid="subrows-reset-filters"]')?.offsetParent,
    };
  })()`);

  let s = await state();
  check('the sub-row filter input binds to the sub-row slot, not columnFilters',
    s.hasSubRowInput && /rows\.subRowFilters/.test(s.inputModel ?? '') && !/columnFilters/.test(s.inputModel ?? ''),
    s.inputModel ?? 'no input found');
  check('all children are shown before filtering', s.childRows > 0, `${s.childRows} children`);
  check('reset is hidden while no filter is active', s.resetVisible === false);

  const parentsBefore = s.parentRows;
  const childrenBefore = s.childRows;
  const firstProduct = s.products[0] ?? '';
  const term = firstProduct.slice(0, 3);

  // Type into the sub-row product filter.
  await evaluate(`(() => {
    const input = [...document.querySelectorAll('input')].find(i =>
      (i.getAttribute('wire:model.live.debounce.300ms') || i.getAttribute('wire:model') || '').includes('subRowFilters'));
    input.value = ${JSON.stringify(term)};
    input.dispatchEvent(new Event('input', { bubbles: true }));
  })()`);
  await sleep(2500);
  await shot('02-filtered');

  s = await state();
  check('typing narrows the children', s.childRows < childrenBefore && s.childRows > 0,
    `${childrenBefore} → ${s.childRows}`);
  check('the parent rows are NOT filtered by a sub-row filter', s.parentRows === parentsBefore,
    `${parentsBefore} → ${s.parentRows}`);
  check('every surviving child matches the term', s.products.every(p => p.toLowerCase().includes(term.toLowerCase())),
    s.products.join(', '));
  check('reset appears once a filter is active', s.resetVisible === true);

  // Reset brings the children back.
  await evaluate(`document.querySelector('[data-testid="subrows-reset-filters"]').click()`);
  await sleep(2000);
  s = await state();
  check('reset restores every child', s.childRows === childrenBefore, `${s.childRows} / ${childrenBefore}`);
  check('reset hides itself again', s.resetVisible === false);
  await shot('03-reset');

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
