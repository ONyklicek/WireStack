import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * CDP driver for the two editors whose Alpine component is written inline as an
 * x-data attribute — where the HTML parser reads the code before JavaScript
 * does, and Pest sees only the markup that went in.
 *
 * Both bugs this covers were invisible to PHP tests:
 *  - a RAW double quote inside x-data ends the attribute there, so a regex
 *    literal truncated the MarkdownEditor mid-function and Alpine threw
 *    "Invalid regular expression: missing /" — no tabs, no preview, no entangle;
 *  - an entity is DECODED, so the preview's sanitiser, written once, arrived as
 *    replace(& with &) and let raw HTML through to x-html.
 *
 * The RichEditor half guards its link prompt, which is rendered into x-data too.
 *
 * Usage:
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # background
 *   node workbench/scripts/verify-markdown-editor.mjs
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9339);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-markdown-editor-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-markdown-editor-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (method, params) => cdp.send(method, params, sessionId);
  const eval_ = async (expression) => {
    const { result, exceptionDetails } = await page('Runtime.evaluate', {
      expression, returnByValue: true, awaitPromise: true,
    });
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'JS error');
    return result?.value;
  };
  const shot = async (name) => {
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  // An x-data that does not parse surfaces as an Alpine *warning*, not an error,
  // so both levels are collected — a warning here is a dead component.
  const problems = [];
  await page('Page.enable');
  await page('Runtime.enable');
  await page('Console.enable');
  cdp.on('Console.messageAdded', (p) => {
    const m = p.params?.message;
    if (!m) return;
    if (m.level === 'error' || m.level === 'warning') problems.push(`${m.level}: ${m.text.split('\n')[0]}`);
  });
  await page('Emulation.setDeviceMetricsOverride', { width: 1200, height: 1000, deviceScaleFactor: 1, mobile: false });

  // ─────────────── MarkdownEditor ───────────────
  await page('Page.navigate', { url: `${base}/field-markdown-editor` });
  await sleep(4000);

  const booted = await eval_(`(() => {
    const root = [...document.querySelectorAll('[x-data]')].find(e => e.getAttribute('x-data').includes('renderMd'));
    if (!root) return { found: false };
    const d = window.Alpine.$data(root);
    return { found: true, tab: d.tab, hasRender: typeof d.renderMd === 'function' };
  })()`);
  check('Alpine initialises the markdown component (x-data parses whole)',
    booted.found && booted.hasRender && booted.tab === 'write', JSON.stringify(booted));

  // Type markdown that carries raw HTML, switch to Preview, read what rendered.
  const preview = await eval_(`(() => {
    const root = [...document.querySelectorAll('[x-data]')].find(e => e.getAttribute('x-data').includes('renderMd'));
    const d = window.Alpine.$data(root);
    d.content = '## Nadpis\\n\\n**tučně** a [odkaz](https://example.com) plus <img src=x onerror=alert(1)>';
    d.tab = 'preview';
    return new Promise(r => setTimeout(() => {
      const pane = root.querySelector('[x-html]');
      r({ tab: d.tab, html: pane ? pane.innerHTML : null, imgs: root.querySelectorAll('img').length });
    }, 400));
  })()`);
  check('the Preview tab renders the markdown',
    !!preview.html && /<h2 class="text-lg/.test(preview.html) && /<strong>tučně<\/strong>/.test(preview.html),
    (preview.html ?? 'no preview pane').slice(0, 100));
  check('links are built with quoted attributes', /<a href="https:\/\/example\.com"/.test(preview.html ?? ''));
  check('raw HTML is escaped rather than injected (the sanitiser is not a no-op)',
    preview.imgs === 0 && (preview.html ?? '').includes('&lt;img'), `img elements: ${preview.imgs}`);
  await shot('01-markdown-preview');

  // ─────────────── RichEditor ───────────────
  await page('Page.navigate', { url: `${base}/field-rich-editor` });
  await sleep(3000);
  const rich = await eval_(`(() => {
    const root = [...document.querySelectorAll('[x-data]')].find(e => e.getAttribute('x-data').includes('insertLink('));
    if (!root) return { found: false };
    const d = window.Alpine.$data(root);
    return {
      found: true,
      initialised: typeof d.insertLink === 'function',
      titles: [...document.querySelectorAll('button[title]')].map(b => b.title),
    };
  })()`);
  check('Alpine initialises the rich editor (its @js() link prompt parses)',
    rich.found && rich.initialised, JSON.stringify({ found: rich.found, initialised: rich.initialised }));
  check('the rich editor toolbar carries its translated titles',
    (rich.titles ?? []).includes('Bold') && (rich.titles ?? []).includes('Code block'),
    (rich.titles ?? []).join(', '));
  await shot('02-rich-editor');

  check('no Alpine errors or warnings during the run', problems.length === 0, problems.slice(0, 3).join(' | '));

  console.log(`\nScreenshots: ${shotDir}`);
  const failed = results.filter((r) => !r.ok);
  chrome.kill();
  process.exit(failed.length ? 1 : 0);
} catch (err) {
  console.error('DRIVER ERROR:', err);
  chrome.kill();
  process.exit(2);
}

async function waitForDevtools(port) {
  for (let i = 0; i < 50; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      const j = await res.json();
      if (j.webSocketDebuggerUrl) return j.webSocketDebuggerUrl;
    } catch {}
    await sleep(200);
  }
  throw new Error('DevTools endpoint never came up');
}

async function connect(wsUrl) {
  const { WebSocket } = await import('ws').catch(() => ({ WebSocket: globalThis.WebSocket }));
  const ws = new WebSocket(wsUrl, { perMessageDeflate: false });
  const pending = new Map();
  const listeners = [];
  let id = 0;
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
  ws.onmessage = (ev) => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
    } else if (msg.method) {
      listeners.forEach((fn) => fn(msg));
    }
  };
  return {
    send(method, params = {}, sessionId) {
      return new Promise((resolve, reject) => {
        const mid = ++id;
        pending.set(mid, { resolve, reject });
        ws.send(JSON.stringify({ id: mid, method, params, sessionId }));
      });
    },
    on(_evt, fn) { listeners.push(fn); },
  };
}
