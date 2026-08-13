import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * ImageColumn gallery in a real browser (/previews/table-image-gallery).
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-image-gallery.mjs
 *
 * Exit 0 = all checks passed; 1 = a check failed; 2 = driver error.
 * Server-side tests assert the markup;
 * only this can show the images actually paint, that a stack really overlaps
 * (computed geometry, not just a class name), and that the "+N" chip lands
 * where a user would see it.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-image-gallery`;
const chromeBin = process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const port = Number(process.env.CHROME_PORT ?? 9421);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-gallery-shots');
await mkdir(shotDir, { recursive: true });

const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', '--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${port}`,
  `--user-data-dir=${join(tmpdir(), `gal-${Date.now()}`)}`, 'about:blank',
], { stdio: 'ignore' });

const results = [];
const check = (name, ok, detail = '') => {
  results.push({ name, ok });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ` — ${detail}` : ''}`);
};

let cdp;
try {
  const wsUrl = await waitForDevtools(port);
  cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page = (m, p) => cdp.send(m, p, sessionId);
  const eval_ = async (expression) => {
    const { result, exceptionDetails } = await page('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (exceptionDetails) throw new Error(exceptionDetails.exception?.description ?? 'JS error');
    return result?.value;
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 900, deviceScaleFactor: 2, mobile: false });
  await page('Page.navigate', { url });
  await sleep(2500);
  await eval_(`window.$q=s=>document.querySelector(s); window.$qa=s=>[...document.querySelectorAll(s)];
    window.cells = () => $qa('tbody tr')[0]?.querySelectorAll('td'); true;`);

  const { data } = await page('Page.captureScreenshot', { format: 'png' });
  await writeFile(join(shotDir, 'gallery.png'), Buffer.from(data, 'base64'));

  // 1. Every image actually painted (naturalWidth > 0 means the browser decoded it).
  const painted = await eval_(`(() => {
    const imgs = $qa('tbody img');
    return { total: imgs.length, broken: imgs.filter(i => !i.complete || i.naturalWidth === 0).length };
  })()`);
  check('every image decoded (none broken)', painted.total > 0 && painted.broken === 0, JSON.stringify(painted));

  // 2. Per-column counts in the first row: single=1, gallery=3, stacked=3 (capped from 6).
  const counts = await eval_(`(() => {
    const tds = [...cells()];
    return tds.map(td => td.querySelectorAll('img').length);
  })()`);
  check('single column paints 1, gallery 3, stack capped to 3', JSON.stringify(counts) === JSON.stringify([0, 1, 3, 3]), JSON.stringify(counts));

  // 3. The "+N" chip exists, shows the hidden count, and is really visible.
  const chip = await eval_(`(() => {
    const el = $q('[data-testid="image-stack-overflow"]');
    if (!el) return { found: false };
    const r = el.getBoundingClientRect();
    const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    return { found: true, text: el.innerText.trim(), w: Math.round(r.width), h: Math.round(r.height), hitIsChip: el.contains(hit) || el === hit };
  })()`);
  check('"+3" chip is rendered and hit-testable', chip.found && chip.text === '+3' && chip.hitIsChip && chip.w > 0, JSON.stringify(chip));

  // 4. Stacking is real geometry, not just a class: images must overlap.
  const geo = await eval_(`(() => {
    const tds = [...cells()];
    const rects = (td) => [...td.querySelectorAll('img')].map(i => i.getBoundingClientRect());
    const gap = (rs) => rs.length < 2 ? null : Math.round(rs[1].left - rs[0].right);
    return { stacked: gap(rects(tds[3])), gallery: gap(rects(tds[2])) };
  })()`);
  check('stacked images overlap (negative gap)', geo.stacked !== null && geo.stacked < 0, `gap=${geo.stacked}px`);
  check('unstacked gallery does not overlap', geo.gallery !== null && geo.gallery >= 0, `gap=${geo.gallery}px`);

  // 5. The stack sits on one line — a wrapped "stack" would mean the CSS lost.
  const oneLine = await eval_(`(() => {
    const imgs = [...[...cells()][3].querySelectorAll('img')];
    const tops = new Set(imgs.map(i => Math.round(i.getBoundingClientRect().top)));
    return tops.size;
  })()`);
  check('the stack renders on a single line', oneLine === 1, `distinct tops=${oneLine}`);

  console.log(`\nScreenshot: ${join(shotDir, 'gallery.png')}`);
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
  process.exitCode = failed.length ? 1 : 0;
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
  ws.onmessage = (ev) => { const m = JSON.parse(ev.data); if (m.id && pending.has(m.id)) { const { resolve, reject } = pending.get(m.id); pending.delete(m.id); m.error ? reject(new Error(JSON.stringify(m.error))) : resolve(m.result); } };
  return { send(method, params = {}, sessionId) { const msgId = ++id; return new Promise((resolve, reject) => { pending.set(msgId, { resolve, reject }); ws.send(JSON.stringify({ id: msgId, method, params, sessionId })); }); }, close() { ws.close(); } };
}
