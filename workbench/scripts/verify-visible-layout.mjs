#!/usr/bin/env node
// Drives a stacked table that sends only the half the window shows: the first
// visit (no cookie) gets both halves and writes the cookie; a reload gets the
// table alone; narrowing the window past the breakpoint re-renders it as cards
// and widening it brings the table back; a stale cookie is corrected on load.
// Pest sees each response; only a browser sees the script that writes the
// cookie and asks for the other half.
//
// Usage: node workbench/scripts/verify-visible-layout.mjs
// Env: PREVIEW_URL, CHROME_BIN, CHROME_PORT, SHOT_DIR

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const BASE = process.env.PREVIEW_URL ?? process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const URL_PAGE = `${BASE}/previews/table-stacked-selection`;
const CHROME = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = Number(process.env.CHROME_PORT ?? 9251);
const SHOT_DIR = process.env.SHOT_DIR ?? '/tmp/visible-layout';

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
    return res.result?.result?.value;
  };
  const shot = async (name) => {
    const res = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, session);
    writeFileSync(`${SHOT_DIR}/${name}.png`, Buffer.from(res.result.data, 'base64'));
  };

  await send('Page.enable', {}, session);
  await send('Runtime.enable', {}, session);
  // Device emulation, not --window-size: headless clamps the window but honours this.
  await send('Page.enable', {}, session);
  await send('Runtime.enable', {}, session);
  await send('Network.enable', {}, session);

  const desktop = () => send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false }, session);
  const phone = () => send('Emulation.setDeviceMetricsOverride', { width: 390, height: 900, deviceScaleFactor: 2, mobile: true }, session);

  const state = () => evaluate(`(() => {
    const visible = (el) => !!el && !!el.offsetParent;
    const marker = document.querySelector('[data-wire-layout]');
    const cards = [...document.querySelectorAll('[data-testid=table-card]')];
    const table = document.querySelector('table');
    return {
      layout: marker?.dataset.wireLayout ?? null,
      cookie: document.cookie.split('; ').find((p) => p.startsWith('wire_viewport='))?.split('=')[1] ?? null,
      cards: cards.length,
      cardsVisible: cards.some(visible),
      table: !!table,
      tableVisible: visible(table),
    };
  })()`);

  const until = async (predicate, timeout = 6000) => {
    const end = Date.now() + timeout;
    let s = await state();
    while (! predicate(s) && Date.now() < end) {
      await sleep(150);
      s = await state();
    }
    return s;
  };

  // ── first visit: no cookie, both halves, the cookie gets written ──
  await desktop();
  await send('Page.navigate', { url: URL_PAGE }, session);
  await sleep(3000);
  let s = await state();
  check('first visit renders both halves', s.layout === 'both' && s.table && s.cards > 0, JSON.stringify(s));
  check('the table is the half on screen', s.tableVisible && ! s.cardsVisible);
  check('the script wrote the cookie', s.cookie === 'xl', `cookie=${s.cookie}`);
  check('a complete page is not re-rendered on load', s.layout === 'both');
  await shot('01-first-visit');

  // ── reload: only the table ──
  await send('Page.reload', {}, session);
  await sleep(3000);
  s = await state();
  check('a reload sends the table alone', s.layout === 'table' && s.table && s.cards === 0, JSON.stringify(s));
  check('and it is visible', s.tableVisible);
  await shot('02-table-only');

  // ── narrow past the breakpoint: the cards arrive ──
  await phone();
  s = await until((x) => x.layout === 'cards');
  check('narrowing re-renders as cards', s.layout === 'cards' && s.cards > 0 && ! s.table, JSON.stringify(s));
  check('the cards are visible', s.cardsVisible);
  check('the cookie followed the window', s.cookie === 'base', `cookie=${s.cookie}`);
  await shot('03-cards-only');

  // ── widen again: the table comes back ──
  await desktop();
  s = await until((x) => x.layout === 'table');
  check('widening brings the table back', s.layout === 'table' && s.table && s.cards === 0, JSON.stringify(s));

  // ── a stale cookie (a tablet turned since) is put right on load ──
  await evaluate(`document.cookie = 'wire_viewport=base; path=/'`);
  await send('Page.reload', {}, session);
  await sleep(1500);
  s = await until((x) => x.layout === 'table', 8000);
  check('a stale cookie is corrected after load', s.layout === 'table' && s.tableVisible, JSON.stringify(s));
  await shot('04-stale-corrected');

  const errors = events
    .filter((e) => (e.method === 'Runtime.consoleAPICalled' && e.params?.type === 'error') || e.method === 'Runtime.exceptionThrown')
    .map((e) => e.params.args?.map((a) => a.value ?? a.description).join(' ') ?? e.params.exceptionDetails?.exception?.description);
  check('no console errors', errors.length === 0, errors.slice(0, 2).join(' | '));

  console.log(`\nScreenshots: ${SHOT_DIR}`);
  ws.close();
} finally {
  chrome.kill();
}

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length === 0 ? 0 : 1);
