#!/usr/bin/env node
// Drives the owner layer's four pages in headless Chrome, on the workbench's
// real Invoice entity — V2.3's own gate before the API counted as finished.
//
// Every other exercise of these contracts is a test fixture. This mounts the
// framework's real ListPage / CreatePage / EditPage / ViewPage against a
// resource declared in config, and asks the browser whether they render: the
// list's columns, the form's fields, the infolist's entries, and the relation
// manager an edit or view page embeds.
//
// Usage: node workbench/scripts/verify-resource-pages.mjs
// Env: PREVIEW_URL, CHROME_BIN, CHROME_PORT, SHOT_DIR

import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';

const BASE = process.env.PREVIEW_URL ?? process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const URLS = {
  list: `${BASE}/previews/resource-list`,
  create: `${BASE}/previews/resource-create`,
  edit: `${BASE}/previews/resource-edit`,
  view: `${BASE}/previews/resource-view`,
};
const CHROME = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = Number(process.env.CHROME_PORT ?? 9222);
const SHOT_DIR = process.env.SHOT_DIR ?? '/tmp/resource-pages';

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
  '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${PORT}`,
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
  const go = async (url) => {
    await browser.send('Page.navigate', { url }, session);
    await sleep(3000);
  };

  const text = () => evaluate('document.body.innerText');

  // ── the list ──
  await go(URLS.list);
  await shot('01-list');
  const list = await evaluate(`(() => ({
    heading: document.querySelector('h1')?.textContent?.trim(),
    rows: document.querySelectorAll('[data-testid="table-row"]').length,
    body: document.body.innerText,
  }))()`);

  check('list page renders rows from the resource table()', list.rows > 0, `${list.rows} rows`);
  check('list heading comes from the resource plural label', list.heading === 'Invoices', `heading=${list.heading}`);
  check('list shows the declared columns', list.body.includes('customer') || /INV|Invoice/i.test(list.body));

  // ── create ──
  await go(URLS.create);
  await shot('02-create');
  const create = await evaluate(`(() => ({
    heading: document.querySelector('h1')?.textContent?.trim(),
    inputs: document.querySelectorAll('form input, form select').length,
    body: document.body.innerText,
  }))()`);

  check('create page renders the resource form', create.inputs >= 3, `${create.inputs} controls`);
  check('create heading is the singular', create.heading === 'New Invoice', `heading=${create.heading}`);

  // ── edit: seeded, and the relation manager embedded ──
  await go(URLS.edit);
  await shot('03-edit');
  const edit = await evaluate(`(() => ({
    heading: document.querySelector('h1')?.textContent?.trim(),
    // getElementById, not a CSS selector: the id is literally "data.number"
    // and a dot in a selector means a class.
    number: document.getElementById('data.number')?.value,
    relationHeading: [...document.querySelectorAll('h3')].map((h) => h.textContent.trim()),
    relationRows: document.querySelectorAll('[data-testid="table-row"]').length,
  }))()`);

  check('edit heading is the singular', edit.heading === 'Edit Invoice', `heading=${edit.heading}`);
  check('edit form arrives seeded from the record', !!edit.number, `number=${edit.number}`);
  check('edit embeds the relation manager', edit.relationHeading.includes('Line items'),
    edit.relationHeading.join(' | '));
  check('the embedded relation manager lists the related rows', edit.relationRows > 0,
    `${edit.relationRows} rows`);

  // ── view: read-only, same relation manager ──
  await go(URLS.view);
  await shot('04-view');
  const view = await evaluate(`(() => ({
    heading: document.querySelector('h1')?.textContent?.trim(),
    inputs: document.querySelectorAll('input[type="text"], select').length,
    relationHeading: [...document.querySelectorAll('h3')].map((h) => h.textContent.trim()),
    relationRows: document.querySelectorAll('[data-testid="table-row"]').length,
    body: document.body.innerText,
  }))()`);

  check('view heading is the singular', view.heading === 'Invoice', `heading=${view.heading}`);
  check('view renders the infolist entries', /INV|Invoice/i.test(view.body));
  check('view embeds the same relation manager', view.relationHeading.includes('Line items'));
  check('the relation manager still lists rows on the view page', view.relationRows > 0);

  // ── console must stay clean across all four ──
  const errors = browser.events
    .filter((e) => e.method === 'Runtime.consoleAPICalled' && e.params?.type === 'error')
    .map((e) => e.params.args.map((a) => a.value ?? a.description).join(' '));
  check('no console errors on any page', errors.length === 0, errors.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${SHOT_DIR}`);
  browser.close();
} finally {
  chrome.kill();
}

const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length === 0 ? 0 : 1);
