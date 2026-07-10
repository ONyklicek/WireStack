import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Screenshots the responsive action-modal variants so the mobile presentation
 * (bottom-sheet vs. full-screen vs. desktop slide-over) can be eyeballed.
 * See .claude/skills/verify-preview/SKILL.md.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9335);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-modal-mobile-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-modal-mobile-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

// name, preview slug, viewport
const shots = [
  ['01-slideover-mobile-390', 'table-modal-slideover-mobile', 390, 844, true],
  ['02-fullscreen-mobile-390', 'table-modal-fullscreen-mobile', 390, 844, true],
  ['03-compose-mobile-390', 'table-modal-slideover-compose', 390, 844, true],
  ['04-compose-desktop-1400', 'table-modal-slideover-compose', 1400, 900, false],
  ['05-default-dialog-390', 'table-modal-form', 390, 844, true],
];

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

  await page('Page.enable');
  await page('Runtime.enable');

  for (const [name, slug, width, height, mobile] of shots) {
    await page('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 2, mobile });
    await page('Page.navigate', { url: `${base}/${slug}` });
    await sleep(2600);
    const info = await eval_(`JSON.stringify({
      dialog: !!document.querySelector('[role="dialog"]'),
      heading: (document.querySelector('#modal-title, #slide-over-title')||{}).innerText || null,
      panel: (() => {
        const p = document.querySelector('[role="dialog"] .shadow-xl');
        if (!p) return null;
        const r = p.getBoundingClientRect();
        return { top: Math.round(r.top), bottom: Math.round(r.bottom), left: Math.round(r.left), right: Math.round(r.right), w: Math.round(r.width), h: Math.round(r.height) };
      })(),
      viewport: { w: window.innerWidth, h: window.innerHeight },
    })`);
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
    console.log(`${name}  ${info}`);
  }

  console.log('\nScreenshots: ' + shotDir);
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  cdp?.close();
  chrome.kill('SIGTERM');
  await rm(userDataDir, { recursive: true, force: true }).catch(() => {});
}

async function waitForDevtools(port) {
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      const json = await res.json();
      if (json.webSocketDebuggerUrl) return json.webSocketDebuggerUrl;
    } catch {}
    await sleep(250);
  }
  throw new Error('DevTools endpoint never came up');
}

function connect(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl);
    const pending = new Map();
    const listeners = [];
    let nextId = 1;
    ws.addEventListener('open', () => resolve({
      send(method, params = {}, sessionId) {
        const id = nextId++;
        return new Promise((res, rej) => {
          pending.set(id, { res, rej });
          ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
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
