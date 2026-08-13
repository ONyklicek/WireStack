import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying the quiet row-action style in the workbench preview
 * (/previews/table-actions-quiet): neutral at rest, colour on hover/focus,
 * a legible red Delete, a solid green Approve, and a visible focus ring.
 * Exit 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-actions-quiet`;
const chromeBin = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9336);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-quiet-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-quiet-verify-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${devtoolsPort}`,
  `--user-data-dir=${userDataDir}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};
// Tailwind 4 emits colours as oklch(L C H [/ a]); backgrounds still fall back to
// rgba() when transparent. Parse both into a normalised descriptor.
const parse = (s) => {
  const n = (s.match(/[\d.]+/g) ?? []).map(Number);
  if (s.startsWith('oklch')) return { model: 'oklch', L: n[0], C: n[1], H: n[2], a: n[3] ?? 1 };
  return { model: 'rgb', r: n[0], g: n[1], b: n[2], a: n[3] ?? 1 };
};
const isTransparent = (s) => { const c = parse(s); return c.a === 0; };
// Achromatic = near-zero chroma (oklch) or equal channels (rgb).
const isGrayish = (s) => { const c = parse(s); return c.model === 'oklch' ? c.C < 0.05 : Math.max(c.r, c.g, c.b) - Math.min(c.r, c.g, c.b) < 20; };
const hueIn = (s, lo, hi) => { const c = parse(s); return c.model === 'oklch' && c.C > 0.08 && c.H >= lo && c.H <= hi; };
const isReddish = (s) => hueIn(s, 15, 45);
const isGreenish = (s) => hueIn(s, 140, 180);

let cdp;
try {
  const wsUrl = await waitForDevtools(devtoolsPort);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (method, params) => cdp.send(method, params, sessionId);
  const eval_ = async (expression) => {
    const { result, exceptionDetails } = await page('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'JS error');
    return result?.value;
  };
  const shot = async (name) => {
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1200, height: 900, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  await eval_(`
    window.testid = (n) => document.querySelector('[data-testid="action-'+n+'"]');
    window.styleOf = (n) => { const el = testid(n); if (!el) return null; const s = getComputedStyle(el); return { color: s.color, bg: s.backgroundColor, shadow: s.boxShadow }; };
    window.centerOf = (n) => { const r = testid(n).getBoundingClientRect(); return { x: Math.round(r.x + r.width/2), y: Math.round(r.y + r.height/2) }; };
    true;
  `);

  const booted = await eval_(`!!testid('edit') && !!testid('delete') && !!testid('approve')`);
  check('quiet preview renders with row actions present', booted);
  if (!booted) throw new Error('row actions not found — preview did not boot');

  // ── LIGHT: resting state ──────────────────────────────────────────────
  await shot('01-light-rest');
  const editRest = await eval_(`styleOf('edit')`);
  const delRest = await eval_(`styleOf('delete')`);
  const approveRest = await eval_(`styleOf('approve')`);

  check('(1) Edit rests neutral gray with no solid fill',
    isGrayish(editRest.color) && isTransparent(editRest.bg),
    `color=${editRest.color} bg=${editRest.bg}`);
  check('(3) Delete is legible red at rest',
    isReddish(delRest.color),
    `color=${delRest.color}`);
  check('(4) Approve stays a solid green filled button',
    isGreenish(approveRest.bg),
    `bg=${approveRest.bg} color=${approveRest.color}`);

  // ── LIGHT: hover reveals colour ───────────────────────────────────────
  const c = await eval_(`centerOf('edit')`);
  await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x: c.x, y: c.y });
  await sleep(400);
  await shot('02-light-hover');
  const editHover = await eval_(`styleOf('edit')`);
  check('(2) Hovering Edit reveals colour + tinted background',
    !isTransparent(editHover.bg) || !isGrayish(editHover.color),
    `color=${editHover.color} bg=${editHover.bg}`);

  // ── LIGHT: keyboard focus ring ────────────────────────────────────────
  await eval_(`testid('edit').focus()`);
  await sleep(300);
  await shot('03-light-focus');
  const editFocus = await eval_(`styleOf('edit')`);
  check('(5) Focusing Edit shows a visible focus ring',
    editFocus.shadow && editFocus.shadow !== 'none',
    `boxShadow=${editFocus.shadow}`);

  // ── DARK: resting state ───────────────────────────────────────────────
  // Move the pointer off Edit and drop focus first, or it would still read its
  // hover/focus state from the steps above. Cover both dark strategies.
  await page('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 5, y: 5 });
  await eval_(`document.activeElement && document.activeElement.blur(); document.documentElement.classList.add('dark'); true;`);
  await page('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'dark' }] });
  await sleep(700);
  await shot('04-dark-rest');
  const editDark = await eval_(`styleOf('edit')`);
  const delDark = await eval_(`styleOf('delete')`);
  check('(dark) Edit neutral at rest, Delete legible red',
    isGrayish(editDark.color) && isTransparent(editDark.bg) && isReddish(delDark.color),
    `edit=${editDark.color}/${editDark.bg} delete=${delDark.color}`);

  console.log(`\nScreenshots: ${shotDir}`);
} catch (err) {
  console.error('DRIVER ERROR:', err.message);
  chrome.kill('SIGKILL');
  process.exit(2);
}

chrome.kill('SIGKILL');
const failed = results.filter((r) => !r.ok);
console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
process.exit(failed.length ? 1 : 0);

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
