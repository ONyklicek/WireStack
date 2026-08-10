import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver verifying the layering of teleported floating surfaces
 * (/previews/actions-modal-stacking + /previews/core-dropdown).
 *
 * Every panel is teleported to <body> so an ancestor's overflow can never clip
 * it — but that also drops it into the ROOT stacking context, where its `z-50`
 * class alone loses to a stacked action modal (ModalStack z-index 60 at depth 1)
 * and the panel renders BEHIND the modal that owns it.
 *
 * The trap this guards: a buried panel still reports `offsetParent !== null` and
 * still passes every server-side and naive DOM assertion. Only a hit test at the
 * panel's own coordinates catches it, which is why this lives here and not in
 * Pest.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   npm run build:core-assets                                 # if dropdown.js changed
 *   node workbench/scripts/verify-modal-layering.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9342);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-layering-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-layering-verify-${Date.now()}`);
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

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });

  const helpers = `
    window.$qa = (s) => [...document.querySelectorAll(s)];
    window.byWire = (frag) => $qa('[wire\\\\:click]').find(b => (b.getAttribute('wire:click')||'').includes(frag));
    window.byText = (t) => $qa('button, a').find(b => (b.innerText||'').trim().toLowerCase().includes(t.toLowerCase()));
    window.fixedChild = (text) => [...document.body.children].find(el =>
      getComputedStyle(el).position === 'fixed' && (el.innerText||'').includes(text));
    // What the user's pointer actually lands on at an element's own coordinates.
    window.hitAt = (el, dy) => {
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height) return null;
      return document.elementFromPoint(r.left + r.width / 2, r.top + Math.min(dy ?? 24, r.height / 2));
    };
    true;
  `;

  // ---- 1. a panel opened inside a stacked (depth 1) action modal -------------
  await page('Page.navigate', { url: `${base}/actions-modal-stacking` });
  await sleep(3200);
  await eval_(helpers);

  await eval_(`byWire('stackBasic').click()`);
  await sleep(2000);
  await eval_(`(byText('Add internal note') || byWire('stackNote')).click()`);
  await sleep(2400);
  check('a nested action modal opens (depth 1)', await eval_(`!!fixedChild('Add note')`));

  await eval_(`fixedChild('Add note').querySelector('[data-testid="select-trigger"]').click()`);
  await sleep(1400);
  await shot('01-select-in-stacked-modal');

  const panel = await eval_(`(() => {
    const opt = $qa('[data-testid^="select-option-"]').find(o => (o.innerText||'').includes('Turing'));
    if (!opt) return { found: false };
    let p = opt;
    while (p && !['absolute', 'fixed'].includes(getComputedStyle(p).position)) p = p.parentElement;
    const modal = fixedChild('Add note');
    const hit = hitAt(p, 24);
    return {
      found: true,
      reportedVisible: opt.offsetParent !== null,
      panelZ: parseInt(getComputedStyle(p).zIndex, 10),
      modalZ: parseInt(getComputedStyle(modal).zIndex, 10),
      hitInsidePanel: hit ? p.contains(hit) : false,
      hitTestid: hit ? hit.getAttribute('data-testid') : null,
    };
  })()`);

  check('the select panel renders inside the stacked modal', panel.found);
  check(
    'the panel outranks the modal that owns it',
    panel.found && panel.panelZ > panel.modalZ,
    panel.found ? `panel z=${panel.panelZ}, modal z=${panel.modalZ}` : '',
  );
  check(
    'the panel is actually hit-testable, not just "visible"',
    panel.found && panel.hitInsidePanel === true,
    panel.found ? `elementFromPoint → ${panel.hitTestid ?? 'outside the panel'}` : '',
  );

  // ---- 2. the create-option modal opened FROM that panel ---------------------
  await eval_(`byText('create option').click()`);
  await sleep(2500);
  await shot('02-create-option-modal');

  const optionModal = await eval_(`(() => {
    const m = [...document.body.children].find(el =>
      getComputedStyle(el).position === 'fixed' && /create option/i.test(el.innerText||'') && el.querySelector('input'));
    const note = fixedChild('Add note');
    if (!m) return { found: false };
    const input = m.querySelector('input:not([type=hidden])');
    const hit = input ? hitAt(input, 8) : null;
    return {
      found: true,
      z: parseInt(getComputedStyle(m).zIndex, 10),
      noteZ: note ? parseInt(getComputedStyle(note).zIndex, 10) : null,
      hitIsInput: hit === input,
    };
  })()`);

  check('the create-option modal opens from a panel inside a stacked modal', optionModal.found);
  check(
    'the create-option modal outranks the modal it was opened from',
    optionModal.found && optionModal.z > optionModal.noteZ,
    optionModal.found ? `option z=${optionModal.z}, note z=${optionModal.noteZ}` : '',
  );
  check(
    'its form field is reachable',
    optionModal.found && optionModal.hitIsInput === true,
  );

  // ---- 3. no regression for a page-level dropdown ---------------------------
  // A trigger on the page owns no layer, so the panel must keep the z-index its
  // view's own class gives it. Resolving a layer must only ever RAISE a panel:
  // a trigger inside low-z page chrome (a sticky toolbar at z-10) must not drag
  // its panel down to z-11.
  for (const slug of ['core-dropdown', 'table-actions-group']) {
    await page('Page.navigate', { url: `${base}/${slug}` });
    await sleep(3000);
    await eval_(helpers);
    await eval_(`$qa('[x-ref="trigger"], [data-testid="select-trigger"]')[0].click()`);
    await sleep(1000);
    await shot(`03-page-dropdown-${slug}`);

    const pagePanel = await eval_(`(() => {
      const p = $qa('[x-ref="panel"]').filter(e => e.offsetParent !== null)[0];
      if (!p) return { found: false };
      const hit = hitAt(p, 16);
      return { found: true, inlineZ: p.style.zIndex || null, z: parseInt(getComputedStyle(p).zIndex, 10), hitInside: hit ? p.contains(hit) : false };
    })()`);

    check(`[${slug}] a page-level dropdown opens`, pagePanel.found);
    check(
      `[${slug}] keeps its own layer (no inline override)`,
      pagePanel.found && pagePanel.inlineZ === null && pagePanel.z === 50,
      pagePanel.found ? `z=${pagePanel.z}, inline=${pagePanel.inlineZ ?? '(none)'}` : '',
    );
    check(`[${slug}] is hit-testable`, pagePanel.found && pagePanel.hitInside === true);
  }

  console.log(`\nscreenshots: ${shotDir}`);
} catch (e) {
  console.error('DRIVER ERROR', e);
  process.exitCode = 2;
} finally {
  try { cdp?.close(); } catch {}
  chrome.kill();
}

if (process.exitCode !== 2) {
  const failed = results.filter((r) => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
  process.exitCode = failed.length ? 1 : 0;
}

async function waitForDevtools(port) {
  for (let i = 0; i < 60; i++) {
    try {
      const r = await fetch(`http://127.0.0.1:${port}/json/version`);
      const j = await r.json();
      if (j.webSocketDebuggerUrl) return j.webSocketDebuggerUrl;
    } catch {}
    await sleep(250);
  }
  throw new Error('devtools never came up');
}

async function connect(wsUrl) {
  const ws = new globalThis.WebSocket(wsUrl);
  await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });
  let id = 0;
  const pending = new Map();
  ws.onmessage = (ev) => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      msg.error ? reject(new Error(JSON.stringify(msg.error))) : resolve(msg.result);
    }
  };
  return {
    send(method, params, sessionId) {
      const msgId = ++id;
      ws.send(JSON.stringify({ id: msgId, method, params, sessionId }));
      return new Promise((resolve, reject) => pending.set(msgId, { resolve, reject }));
    },
    close() { ws.close(); },
  };
}
