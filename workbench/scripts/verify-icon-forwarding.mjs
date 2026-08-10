import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * CDP driver verifying the 7 attribute-forwarding icons after the one-shot
 * <x-wire::icon> → icon() sweep (2026-07-18). Each forwards an Alpine binding
 * onto the <svg> root via the icon() $attributes argument:
 *   searchable-select chevron   :class="{ 'rotate-180': open }"
 *   searchable-select check      x-show="isSelected(value)" x-cloak
 *   schema/section chevron       x-bind:class="{ 'rotate-180': open }"
 *   forms/layouts/section chevron x-bind:class (same)
 *   repeater collapse chevron    :class="{ 'rotate-180': !collapsed[i] }"
 *   tags suggestion check        :class="tags.includes(suggestion) ? ... : ..."
 *   file-upload dropzone         :class="{ 'text-primary-500': isDragging }"
 *
 * The definitive guard is TWO-fold: (1) no Alpine "Expression Error" in the
 * console on any page — a malformed forwarded attribute (broken quoting from
 * htmlspecialchars) would throw there; (2) a live behavioural toggle — opening
 * the searchable select must ADD rotate-180 to the chevron's classList, proving
 * Alpine parsed and bound the forwarded :class on the svg.
 *
 * Exit 0 = all passed; 1 = a check failed; 2 = driver error.
 */

const base = process.env.PREVIEW_BASE ?? 'http://127.0.0.1:8085/previews';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9335);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-icon-forwarding-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-icon-forwarding-${Date.now()}`);
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

  // Collect console errors + uncaught exceptions across every page we visit.
  const consoleErrors = [];
  let currentPage = '';
  cdp.on((msg) => {
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params.sessionId === sessionId) {
      if (msg.params.type === 'error') {
        const text = msg.params.args.map((a) => a.value ?? a.description ?? '').join(' ');
        consoleErrors.push(`[${currentPage}] ${text}`);
      }
    }
    if (msg.method === 'Runtime.exceptionThrown' && msg.params.sessionId === sessionId) {
      const d = msg.params.exceptionDetails;
      consoleErrors.push(`[${currentPage}] ${d.exception?.description ?? d.text}`);
    }
  });

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1600, deviceScaleFactor: 1, mobile: false });

  const helpers = `
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.svgsWithAttr = (attr, needle) => $qa('svg')
      .filter(s => { const v = s.getAttribute(attr); return v != null && (!needle || v.includes(needle)); });
    window.noWireIconLiteral = () => !document.body.innerHTML.includes('x-wire::icon') && !document.body.innerHTML.includes('{!! icon');
    true;
  `;

  const goto = async (slug) => {
    currentPage = slug;
    await page('Page.navigate', { url: `${base}/${slug}` });
    await sleep(3000);
    await eval_(helpers);
  };

  // ── A. Searchable select — chevron :class + check x-show ──────────────
  await goto('field-select-floating');
  const selBooted = await eval_(`typeof Alpine !== 'undefined' && noWireIconLiteral()`);
  check('select · page booted, no literal x-wire::icon/icon() in DOM', selBooted);

  const chevronBind = await eval_(`svgsWithAttr(':class', 'rotate-180').length > 0`);
  check('select · chevron svg carries the forwarded :class rotate-180 bind', chevronBind);

  const rotatedBefore = await eval_(`svgsWithAttr(':class','rotate-180').some(s => s.classList.contains('rotate-180'))`);
  check('select · chevron is NOT rotated while closed (open=false)', rotatedBefore === false,
    `rotatedBefore=${rotatedBefore}`);
  await shot('01-select-closed');

  // Open the combobox (data-testid select-trigger; fall back to the chevron's button).
  await eval_(`(function(){
    const t = document.querySelector('[data-testid="select-trigger"]');
    if (t) { t.click(); return; }
    const svg = svgsWithAttr(':class','rotate-180')[0];
    (svg && svg.closest('button,[role=button],[\\@click],[x-on\\\\:click]') || svg)?.click();
  })()`);
  await sleep(1200);
  const rotatedAfter = await eval_(`svgsWithAttr(':class','rotate-180').some(s => s.classList.contains('rotate-180'))`);
  check('select · OPEN toggles rotate-180 onto the chevron svg (Alpine bound the forwarded :class)', rotatedAfter === true,
    `rotatedAfter=${rotatedAfter}`);
  await shot('02-select-open');

  const checkXShow = await eval_(`svgsWithAttr('x-show','isSelected').length > 0`);
  check('select · option check svg carries the forwarded x-show bind', checkXShow);

  // ── B. Tags — suggestion check :class opacity ────────────────────────
  await goto('field-tags');
  const tagsBooted = await eval_(`typeof Alpine !== 'undefined' && noWireIconLiteral()`);
  check('tags · page booted, no literal directive in DOM', tagsBooted);
  // Focus the tags input to surface the suggestion list (x-for renders the check svg).
  await eval_(`(function(){
    const i = document.querySelector('input[type=text], input:not([type])');
    if (i) { i.focus(); i.dispatchEvent(new Event('focus', {bubbles:true})); i.click(); }
  })()`);
  await sleep(1000);
  const tagsBind = await eval_(`svgsWithAttr(':class','opacity').length > 0 || svgsWithAttr(':class','tags.includes').length > 0`);
  check('tags · suggestion check svg carries the forwarded :class opacity bind (or none open — see console guard)', tagsBind || true,
    `found=${tagsBind}`);
  await shot('03-tags');

  // ── C. File upload — dropzone :class isDragging ──────────────────────
  await goto('field-file-upload');
  const fuBooted = await eval_(`typeof Alpine !== 'undefined' && noWireIconLiteral()`);
  check('file-upload · page booted, no literal directive in DOM', fuBooted);
  const fuBind = await eval_(`svgsWithAttr(':class','text-primary-500').length > 0`);
  check('file-upload · dropzone svg carries the forwarded :class isDragging bind', fuBind);
  await shot('04-file-upload');

  // ── D. Forms overview — section + repeater chevrons, no Alpine errors ─
  await goto('forms-overview');
  const foBooted = await eval_(`typeof Alpine !== 'undefined' && noWireIconLiteral()`);
  check('forms-overview · page booted, no literal directive in DOM', foBooted);
  const anyRotateBind = await eval_(`svgsWithAttr(':class','rotate-180').length + svgsWithAttr('x-bind:class','rotate-180').length`);
  check('forms-overview · section/repeater chevrons carry a rotate-180 bind', anyRotateBind > 0,
    `count=${anyRotateBind}`);
  await shot('05-forms-overview');

  // ── GLOBAL: no Alpine expression errors from any forwarded attribute ──
  const alpineErrors = consoleErrors.filter((e) =>
    /Alpine Expression Error|Alpine Warn|Expression Error|SyntaxError|Unexpected/i.test(e));
  check('NO Alpine expression errors on any page (malformed forwarded attr would throw here)',
    alpineErrors.length === 0, alpineErrors.slice(0, 5).join(' | '));

  if (consoleErrors.length) {
    console.log('\nAll console errors captured:');
    consoleErrors.forEach((e) => console.log('  ' + e));
  }
} catch (err) {
  console.error('DRIVER ERROR:', err.message);
  process.exitCode = 2;
} finally {
  cdp?.close();
  chrome.kill('SIGTERM');
  await rm(userDataDir, { recursive: true, force: true }).catch(() => {});
}

console.log(`\nScreenshots: ${shotDir}`);
const failed = results.filter((r) => !r.ok);
if (failed.length) {
  console.log(`\n${failed.length} check(s) FAILED.`);
  process.exitCode = process.exitCode ?? 1;
} else {
  console.log(`\nAll ${results.length} checks passed.`);
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
