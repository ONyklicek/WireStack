import { mkdir, writeFile, rm } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/*
 * Capture preview screenshots with headless Chrome over the DevTools Protocol.
 * No Safari WebDriver required: clips each capture to its [data-preview-root]
 * element at 2x scale for crisp, framed previews.
 */

const previewBase = process.env.PREVIEW_BASE_URL ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const outputDir = new URL('../assets/previews/', import.meta.url);
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9333);

// Component / variant previews captured from the runtime.
const fieldSlugs = [
  'text-input', 'textarea', 'select', 'checkbox', 'checkbox-list', 'radio',
  'toggle', 'color-picker', 'slider', 'tags', 'rating', 'otp-input',
  'key-value', 'date-time-picker', 'file-upload',
];

const captures = [
  { slug: 'forms-overview', path: 'forms-overview' },
  { slug: 'forms-repeater', path: 'forms-repeater' },
  { slug: 'table-overview', path: 'table-overview' },
  { slug: 'table-selection', path: 'table-selection' },
  { slug: 'table-subrows', path: 'table-subrows' },
  { slug: 'table-summary', path: 'table-summary' },
  { slug: 'table-subrows-flatten', path: 'table-subrows-flatten' },
  { slug: 'table-subrows-limit', path: 'table-subrows-limit' },
  { slug: 'table-subrows-filter', path: 'table-subrows-filter' },
  { slug: 'sortable-overview', path: 'sortable-overview' },
  { slug: 'sortable-detail', path: 'sortable-detail' },
  { slug: 'core-overview', path: 'core-overview' },
  { slug: 'core-modal', path: 'core-modal', selector: '[role="dialog"] .transform', pad: 88 },
  { slug: 'widgets-overview', path: 'widgets-overview' },
  { slug: 'widgets-chart', path: 'widgets-chart' },
  ...fieldSlugs.map((slug) => ({ slug: `field-${slug}`, path: `field-${slug}` })),
];

// Optionally capture only a subset, e.g. ONLY=table-subrows,table-summary
const onlySlugs = (process.env.ONLY ?? '').split(',').map((s) => s.trim()).filter(Boolean);
const activeCaptures = onlySlugs.length > 0
  ? captures.filter((capture) => onlySlugs.includes(capture.slug))
  : captures;

await mkdir(outputDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-docs-chrome-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new',
  '--disable-gpu',
  '--no-first-run',
  '--no-default-browser-check',
  '--hide-scrollbars',
  `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`,
  'about:blank',
], { stdio: 'ignore' });

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);

  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (method, params) => cdp.send(method, params, sessionId);

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', {
    width: 1600, height: 1600, deviceScaleFactor: 2, mobile: false,
  });

  for (const capture of activeCaptures) {
    const url = `${previewBase}/${capture.path}`;
    const selector = capture.selector ?? '[data-preview-root]';

    await page('Page.navigate', { url });
    await waitForLoad(cdp, sessionId);
    await sleep(2200);

    const { result } = await page('Runtime.evaluate', {
      expression: `(() => {
        const el = document.querySelector(${JSON.stringify(selector)});
        if (!el) return null;
        el.scrollIntoView();
        const r = el.getBoundingClientRect();
        return JSON.stringify({ x: r.x, y: r.y, width: r.width, height: r.height });
      })()`,
      returnByValue: true,
    });

    if (!result?.value) {
      throw new Error(`Unable to find ${selector} for ${capture.path}.`);
    }

    const rect = JSON.parse(result.value);
    const pad = capture.pad ?? 0;
    const { data } = await page('Page.captureScreenshot', {
      format: 'png',
      captureBeyondViewport: true,
      clip: {
        x: Math.max(0, rect.x - pad),
        y: Math.max(0, rect.y - pad),
        width: Math.ceil(rect.width + pad * 2),
        height: Math.ceil(rect.height + pad * 2),
        scale: 1,
      },
    });

    await writeFile(new URL(`${capture.slug}.png`, outputDir), Buffer.from(data, 'base64'));
    console.log(`captured ${capture.slug}`);
  }
} finally {
  cdp?.close();
  chrome.kill('SIGTERM');
  await rm(userDataDir, { recursive: true, force: true }).catch(() => {});
}

console.log('Captured preview screenshots into docs-site/assets/previews');

/* ---------- CDP helpers ---------- */

async function waitForDevtools(port) {
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      const json = await res.json();
      if (json.webSocketDebuggerUrl) return json.webSocketDebuggerUrl;
    } catch {}
    await sleep(250);
  }
  throw new Error('Chrome DevTools endpoint did not become available.');
}

function connect(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl);
    const pending = new Map();
    let nextId = 1;
    const listeners = [];

    ws.addEventListener('open', () => resolve({
      send(method, params = {}, sessionId) {
        const id = nextId++;
        const message = { id, method, params };
        if (sessionId) message.sessionId = sessionId;
        return new Promise((res, rej) => {
          pending.set(id, { res, rej });
          ws.send(JSON.stringify(message));
        });
      },
      on(fn) { listeners.push(fn); },
      close() { ws.close(); },
    }));

    ws.addEventListener('error', (err) => reject(err));
    ws.addEventListener('message', (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && pending.has(msg.id)) {
        const { res, rej } = pending.get(msg.id);
        pending.delete(msg.id);
        msg.error ? rej(new Error(msg.error.message)) : res(msg.result);
      } else {
        listeners.forEach((fn) => fn(msg));
      }
    });
  });
}

function waitForLoad(cdp, sessionId) {
  return new Promise((resolve) => {
    const timer = setTimeout(resolve, 8000);
    cdp.on((msg) => {
      if (msg.method === 'Page.loadEventFired' && msg.sessionId === sessionId) {
        clearTimeout(timer);
        resolve();
      }
    });
  });
}
