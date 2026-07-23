#!/usr/bin/env node
// Drives the sub-rows preview in headless Chrome to prove the expansion
// controls actually work in the browser: the master chevron in the expander
// column header, ⌥/Alt-click promoting a row chevron to that master toggle,
// and the view-menu baseline (the only bulk control a phone gets).
//
// Usage: node workbench/scripts/verify-subrows-expansion.mjs
// Env: PREVIEW_URL, CHROME_BIN, CHROME_PORT, SHOT_DIR

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const BASE = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085';
const URL_SUBROWS = `${BASE}/previews/table-subrows`;
const CHROME = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = Number(process.env.CHROME_PORT ?? 9222);
const SHOT_DIR = process.env.SHOT_DIR ?? '/tmp/subrows-expansion';

mkdirSync(SHOT_DIR, { recursive: true });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? `  — ${detail}` : ''}`);
};

async function waitForDevtools() {
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) return await res.json();
    } catch {}
    await sleep(250);
  }
  throw new Error('Chrome DevTools never came up');
}

async function connect(wsUrl) {
  const { WebSocket } = await import('node:worker_threads').then(() => globalThis);
  const ws = new WebSocket(wsUrl);
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
  let id = 0;
  const pending = new Map();
  const events = [];
  ws.onmessage = (m) => {
    const msg = JSON.parse(m.data);
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
    else events.push(msg);
  };
  const send = (method, params = {}, sessionId) =>
    new Promise((resolve) => {
      const msgId = ++id;
      pending.set(msgId, resolve);
      ws.send(JSON.stringify({ id: msgId, method, params, sessionId }));
    });
  return { send, events, close: () => ws.close() };
}

const chrome = spawn(CHROME, [
  '--headless=new',
  `--remote-debugging-port=${PORT}`,
  '--no-first-run',
  '--no-default-browser-check',
  '--disable-gpu',
  '--window-size=1440,1000',
  'about:blank',
], { stdio: 'ignore' });

try {
  const version = await waitForDevtools();
  const browser = await connect(version.webSocketDebuggerUrl);

  const { result: target } = await browser.send('Target.createTarget', { url: 'about:blank' });
  const { result: attached } = await browser.send('Target.attachToTarget', { targetId: target.targetId, flatten: true });
  const session = attached.sessionId;

  const evaluate = async (expression) => {
    const res = await browser.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, session);
    if (res.result?.exceptionDetails) throw new Error(JSON.stringify(res.result.exceptionDetails));
    return res.result?.result?.value;
  };
  const shot = async (name) => {
    const res = await browser.send('Page.captureScreenshot', { format: 'png' }, session);
    writeFileSync(`${SHOT_DIR}/${name}.png`, Buffer.from(res.result.data, 'base64'));
  };

  await browser.send('Page.enable', {}, session);
  await browser.send('Runtime.enable', {}, session);
  await browser.send('Page.navigate', { url: URL_SUBROWS }, session);
  await sleep(3500);
  await shot('01-initial');

  // ── the master chevron exists, and the old toolbar does not ──
  const chrome0 = await evaluate(`(() => {
    const q = (sel) => document.querySelector(sel);
    return {
      master: !!q('[data-testid="subrows-master-toggle"]'),
      masterExpanded: q('[data-testid="subrows-master-toggle"]')?.getAttribute('aria-expanded'),
      masterInHeaderCell: q('[data-testid="subrows-master-toggle"]')?.closest('th') !== null,
      oldExpandAll: !!q('[data-testid="subrows-expand-all"]'),
      oldCollapseAll: !!q('[data-testid="subrows-collapse-all"]'),
      oldScopeToggle: !!q('[data-testid="subrows-scope-toggle"]'),
      openPanels: document.querySelectorAll('[wire\\\\:key^="sub-rows-"]').length,
    };
  })()`);

  check('master chevron renders', chrome0.master);
  check('master chevron sits in a <th> (expander column header)', chrome0.masterInHeaderCell);
  check('master starts collapsed', chrome0.masterExpanded === 'false', `aria-expanded=${chrome0.masterExpanded}`);
  check('old three-button toolbar is gone', !chrome0.oldExpandAll && !chrome0.oldCollapseAll && !chrome0.oldScopeToggle);
  // The preview seeds one open invoice for its screenshot state.
  check('preview opens its seeded row only', chrome0.openPanels === 1, `${chrome0.openPanels} open`);

  // ── master chevron expands every row ──
  await evaluate(`document.querySelector('[data-testid="subrows-master-toggle"]').click()`);
  await sleep(2000);
  await shot('02-master-expanded');

  const afterMaster = await evaluate(`(() => ({
    panels: document.querySelectorAll('[wire\\\\:key^="sub-rows-"]').length,
    rows: document.querySelectorAll('[data-testid="table-row-expand"]').length,
    masterExpanded: document.querySelector('[data-testid="subrows-master-toggle"]').getAttribute('aria-expanded'),
    firstRowExpanded: document.querySelector('[data-testid="table-row-expand"]').getAttribute('aria-expanded'),
  }))()`);

  check('master chevron opened every row', afterMaster.panels === afterMaster.rows && afterMaster.rows > 0,
    `${afterMaster.panels} panels / ${afterMaster.rows} rows`);
  check('master reports expanded', afterMaster.masterExpanded === 'true');
  check('row chevrons follow the baseline', afterMaster.firstRowExpanded === 'true');

  // ── master chevron collapses again (the old flatten no-op bug) ──
  await evaluate(`document.querySelector('[data-testid="subrows-master-toggle"]').click()`);
  await sleep(2000);
  await shot('03-master-collapsed');

  const afterCollapse = await evaluate(`document.querySelectorAll('[wire\\\\:key^="sub-rows-"]').length`);
  check('master chevron collapses every row again', afterCollapse === 0, `${afterCollapse} panels left`);

  // ── plain click opens just one row ──
  await evaluate(`document.querySelector('[data-testid="table-row-expand"]').click()`);
  await sleep(2000);
  const afterPlain = await evaluate(`document.querySelectorAll('[wire\\\\:key^="sub-rows-"]').length`);
  check('plain click opens exactly one row', afterPlain === 1, `${afterPlain} panels`);
  await shot('04-single-row');

  // ── ⌥/Alt-click promotes to the master toggle ──
  await evaluate(`(() => {
    const el = document.querySelector('[data-testid="table-row-expand"]');
    el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, altKey: true }));
  })()`);
  await sleep(2000);
  await shot('05-alt-click');

  const afterAlt = await evaluate(`(() => ({
    panels: document.querySelectorAll('[wire\\\\:key^="sub-rows-"]').length,
    rows: document.querySelectorAll('[data-testid="table-row-expand"]').length,
    master: document.querySelector('[data-testid="subrows-master-toggle"]').getAttribute('aria-expanded'),
  }))()`);
  check('alt-click expands every row via the master toggle', afterAlt.panels === afterAlt.rows && afterAlt.rows > 0,
    `${afterAlt.panels} panels / ${afterAlt.rows} rows`);
  check('alt-click moved the baseline, not one row', afterAlt.master === 'true');

  // ── view menu carries the baseline ──
  await evaluate(`document.querySelector('[data-testid="subrows-master-toggle"]').click()`);
  await sleep(1800);
  await evaluate(`document.querySelector('[data-testid="table-column-toggle"]').click()`);
  await sleep(900);
  await shot('06-view-menu');

  const menu = await evaluate(`(() => {
    const box = document.querySelector('[data-testid="subrows-expand-all-rows"]');
    if (!box) return { present: false };
    const label = box.closest('label');
    const rect = label?.getBoundingClientRect();
    return {
      present: true,
      checked: box.checked,
      visible: !!rect && rect.width > 0 && rect.height > 0,
      text: label?.innerText?.trim(),
    };
  })()`);
  check('view menu offers the expansion baseline', menu.present && menu.visible, menu.text ?? 'not rendered');
  check('baseline checkbox reflects collapsed state', menu.checked === false);

  await evaluate(`document.querySelector('[data-testid="subrows-expand-all-rows"]').click()`);
  await sleep(2200);
  await shot('07-menu-expanded');

  const afterMenu = await evaluate(`(() => ({
    panels: document.querySelectorAll('[wire\\\\:key^="sub-rows-"]').length,
    rows: document.querySelectorAll('[data-testid="table-row-expand"]').length,
    master: document.querySelector('[data-testid="subrows-master-toggle"]').getAttribute('aria-expanded'),
  }))()`);
  check('menu item expands every row', afterMenu.panels === afterMenu.rows && afterMenu.rows > 0,
    `${afterMenu.panels} panels / ${afterMenu.rows} rows`);
  check('menu and master chevron share one state', afterMenu.master === 'true');

  // ── console must stay clean ──
  const errors = browser.events
    .filter((e) => e.method === 'Runtime.consoleAPICalled' && e.params?.type === 'error')
    .map((e) => e.params.args.map((a) => a.value ?? a.description).join(' '));
  check('no console errors', errors.length === 0, errors.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${SHOT_DIR}`);
  browser.close();
} finally {
  chrome.kill();
}

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length === 0 ? 0 : 1);
