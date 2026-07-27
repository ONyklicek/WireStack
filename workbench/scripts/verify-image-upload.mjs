import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/*
 * FileUpload crop + resize WITHOUT the interactive frame, in a real browser
 * (/previews/field-file-upload-auto).
 *
 * imageCropAspectRatio() / imageResizeTargetWidth() were dead setters. The whole
 * point of them is that the browser rewrites the file *before* Livewire uploads
 * it, so nothing server-side can prove they work — only measuring the pixels the
 * picker produced can.
 *
 * It points at the *-auto preview deliberately. `imageCropAspectRatio('16:9')`
 * names a ratio, not a UI: without cropInteractively() the picked file is
 * cropped from the centre and lands in the wire:model input on its own. The
 * field this driver used to target later opted into the frame, at which point
 * the file stops arriving until the user confirms — so this measured nothing
 * and reported "nothing reached the wire:model input" instead. The interactive
 * path has its own driver (verify-image-crop.mjs).
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-image-upload.mjs
 *
 * Exit 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/field-file-upload-auto';
const chromeBin = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const port = Number(process.env.CHROME_PORT ?? 9461);

const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  `--remote-debugging-port=${port}`, `--user-data-dir=${join(tmpdir(), `img-${Date.now()}`)}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push(ok);
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};

let cdp;
try {
  const wsUrl = await waitForDevtools(port);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (m, p) => cdp.send(m, p, sessionId);
  const ev = async (expression) => {
    const { result, exceptionDetails } = await page('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'JS error');
    return result?.value;
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Page.navigate', { url });
  await sleep(3000);

  check('the processing bundle loaded', await ev(`typeof window.Alpine !== 'undefined'`) === true);

  const rootSel = `document.querySelector('[data-testid$="-picker"]').closest('[x-data]')`;
  const cfg = await ev(`(() => { const d = Alpine.$data(${rootSel}); return { hasPick: typeof d.onPick === 'function' }; })()`);
  check('the dropzone uses the image component', cfg.hasPick === true, JSON.stringify(cfg));

  // The point of this preview: no frame to place, so nothing waits for a user.
  check('this field crops from the centre, with no interactive frame',
    await ev(`(() => { const d = Alpine.$data(${rootSel}); return d.cropping === false || d.cropping === undefined; })()`) === true);

  // Build a real 1000x1000 PNG and push it through the picker exactly as a user would.
  const measured = await ev(`(async () => {
    const src = document.createElement('canvas');
    src.width = 1000; src.height = 1000;
    const ctx = src.getContext('2d');
    ctx.fillStyle = '#6366f1'; ctx.fillRect(0, 0, 1000, 1000);
    const blob = await new Promise(r => src.toBlob(r, 'image/png'));
    const file = new File([blob], 'square.png', { type: 'image/png' });

    const picker = document.querySelector('[data-testid$="-picker"]');
    const dt = new DataTransfer(); dt.items.add(file);
    picker.files = dt.files;
    picker.dispatchEvent(new Event('change', { bubbles: true }));

    // Wait for the async canvas work to land in the wire:model input.
    const wireInput = [...document.querySelectorAll('input[type=file]')].find(i => i !== picker);
    for (let i = 0; i < 60 && !wireInput.files.length; i++) await new Promise(r => setTimeout(r, 100));
    if (!wireInput.files.length) return { error: 'nothing reached the wire:model input' };

    const out = wireInput.files[0];
    const img = await createImageBitmap(out);
    return { name: out.name, type: out.type, w: img.width, h: img.height, originalBytes: file.size, outBytes: out.size };
  })()`);

  console.log('     ', JSON.stringify(measured));

  // 1000x1000 cropped to 16:9 => 1000x563, then fit into width 320 => 320x180.
  check('the image was cropped to 16:9', measured.w && Math.abs((measured.w / measured.h) - 16 / 9) < 0.02,
    `${measured.w}x${measured.h}`);
  check('the image was downscaled to the target width', measured.w === 320, `width=${measured.w}`);
  check('a PNG stayed a PNG', measured.type === 'image/png', measured.type);
  check('the processed file is what Livewire would upload', measured.outBytes > 0 && measured.outBytes < measured.originalBytes,
    `${measured.originalBytes}B -> ${measured.outBytes}B`);

  const failed = results.filter((r) => !r).length;
  console.log(`\n${results.length - failed}/${results.length} checks passed`);
  process.exitCode = failed ? 1 : 0;
} catch (e) {
  console.error('DRIVER ERROR:', e);
  process.exitCode = 2;
} finally {
  try { cdp?.close(); } catch {}
  chrome.kill();
}

async function waitForDevtools(port) {
  for (let i = 0; i < 60; i++) {
    try { const r = await fetch(`http://127.0.0.1:${port}/json/version`); const j = await r.json(); if (j.webSocketDebuggerUrl) return j.webSocketDebuggerUrl; } catch {}
    await sleep(250);
  }
  throw new Error('no devtools');
}
async function connect(wsUrl) {
  const ws = new WebSocket(wsUrl); const pending = new Map(); let id = 0;
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
  ws.onmessage = (e) => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { const { resolve, reject } = pending.get(m.id); pending.delete(m.id); m.error ? reject(new Error(JSON.stringify(m.error))) : resolve(m.result); } };
  return { send(method, params = {}, sessionId) { const i = ++id; return new Promise((resolve, reject) => { pending.set(i, { resolve, reject }); ws.send(JSON.stringify({ id: i, method, params, sessionId })); }); }, close() { ws.close(); } };
}
