import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver for a wizard living inside a create-option modal with
 * its navigation handed to the modal footer — /previews/forms-option-wizard.
 *
 * Two things here are invisible to Pest and are the whole point of this driver:
 *
 *   1. The footer and the wizard are *sibling* Alpine scopes inside the modal.
 *      They agree on the current step only because the wizard broadcasts
 *      `wire-wizard-state` on window and the footer answers with
 *      `wire-wizard-navigate`. Markup assertions cannot show that the bridge is
 *      live; only clicking the footer and watching the panel move can.
 *   2. Footer "Next" gates on server-side per-step validation of the *option*
 *      form, which only resolves because the mounted option form is enumerated
 *      as a host form.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-option-wizard.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 * Screenshots of every stage land in SHOT_DIR (path printed at the end) — look
 * at them, don't only trust the assertions.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/forms-option-wizard';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9346);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-option-wizard-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-option-wizard-${Date.now()}`);
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
  // Poll rather than sleep a fixed span: a fixed wait is both slower than it
  // needs to be and a false failure on a loaded machine.
  const until = async (expression, timeoutMs = 8000) => {
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
      if (await eval_(`!!(${expression})`)) return true;
      await sleep(100);
    }
    return false;
  };
  const shot = async (name) => {
    const { data } = await page('Page.captureScreenshot', { format: 'png' });
    await writeFile(join(shotDir, `${name}.png`), Buffer.from(data, 'base64'));
  };

  await page('Page.enable');
  await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });

  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.tid = (name) => $q('[data-testid="' + name + '"]');
    window.shown = (el) => !!el && el.offsetParent !== null;
    window.wizardData = () => Alpine.$data(tid('wizard'));
    window.footerData = () => Alpine.$data(tid('select-create-next'));
    window.labelInput = () => $q('[role="dialog"] input[type="text"], [role="dialog"] input:not([type])');
    window.setInput = (el, value) => { el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); };
    window.trigger = () => $qa('button[aria-haspopup="listbox"]')[0];
    true;
  `;
  await until(`typeof Alpine !== 'undefined' && document.querySelector('button[aria-haspopup="listbox"]')`);
  await eval_(helpers);

  // ── 0. Preview booted ──────────────────────────────────────────────────
  check('option-wizard preview renders with Alpine booted', await eval_(`!!trigger() && typeof Alpine !== 'undefined'`));
  await shot('01-initial');

  // ── 1. Open the combobox and the create-option modal ───────────────────
  await eval_(`trigger().click()`);
  await until(`$qa('button').some(b => b.innerText.includes('Create option'))`);
  await eval_(`$qa('button').find(b => b.innerText.includes('Create option')).click()`);
  const modalOpen = await until(`$q('[role="dialog"]') && tid('wizard')`);
  check('create-option modal opened with the wizard inside it', modalOpen);
  await shot('02-modal-open');

  // ── 2. The wizard gave its navigation to the footer ────────────────────
  const nav = JSON.parse(await eval_(`JSON.stringify({
    ownNext: !!tid('wizard-next'),
    ownBack: !!tid('wizard-back'),
    footerNext: shown(tid('select-create-next')),
    footerBack: shown(tid('select-create-back')),
    footerSave: shown(tid('select-create-save')),
    indicator: $qa('[role="dialog"] ol li').length,
  })`));
  check('the wizard renders no navigation row of its own', !nav.ownNext && !nav.ownBack, JSON.stringify(nav));
  check('the footer shows Next on the first step, no Back, no submit',
    nav.footerNext && !nav.footerBack && !nav.footerSave, JSON.stringify(nav));
  check('the step indicator still belongs to the wizard', nav.indicator === 2, `li=${nav.indicator}`);

  // ── 3. Footer Next gates on the option form's per-step validation ───────
  const beforeInvalid = await eval_(`wizardData().step`);
  await eval_(`tid('select-create-next').click()`);
  // The gate is a Livewire roundtrip: wait for it to settle either way.
  await until(`footerData().validating === false && $q('[role="dialog"] [class*="text-red"]')`, 8000);
  const invalid = JSON.parse(await eval_(`JSON.stringify({
    step: wizardData().step,
    footerStep: footerData().step,
    err: !!$q('[role="dialog"] [class*="text-red"]'),
  })`));
  check('footer Next with an empty required field stays on step 0 and shows its error',
    beforeInvalid === 0 && invalid.step === 0 && invalid.err, JSON.stringify(invalid));
  await shot('03-blocked-next');

  // ── 4. Valid step → footer Next advances, and the footer swaps controls ─
  await eval_(`setInput(labelInput(), 'Culture')`);
  await eval_(`tid('select-create-next').click()`);
  const advanced = await until(`wizardData().step === 1`);
  check('footer Next advances the wizard once the step is valid', advanced);
  // The footer only knows this because the wizard broadcast it across scopes.
  const mirrored = await until(`footerData().step === 1 && footerData().total === 2`);
  check('the footer mirrors the wizard step across the two Alpine scopes', mirrored,
    await eval_(`JSON.stringify({ wizard: wizardData().step, footer: footerData().step })`));
  const swapped = await until(`! shown(tid('select-create-next')) && shown(tid('select-create-back')) && shown(tid('select-create-save'))`);
  check('on the last step the footer swaps Next for the submit button', swapped,
    await eval_(`JSON.stringify({
      next: shown(tid('select-create-next')),
      back: shown(tid('select-create-back')),
      save: shown(tid('select-create-save')),
    })`));
  await shot('04-last-step');

  // ── 5. Footer Back steps the wizard the other way ──────────────────────
  await eval_(`tid('select-create-back').click()`);
  const wentBack = await until(`wizardData().step === 0 && footerData().step === 0`);
  check('footer Back steps the wizard back', wentBack);
  // Poll the rendered controls, not the state: Alpine applies x-show on its own
  // flush, so reading the DOM in the same tick as the state change is a race —
  // and one that reports a passing bridge as broken.
  const backRestored = await until(`shown(tid('select-create-next')) && ! shown(tid('select-create-save'))`);
  check('stepping back restores Next and hides the submit', backRestored,
    await eval_(`JSON.stringify({
      next: shown(tid('select-create-next')),
      save: shown(tid('select-create-save')),
      step: footerData().step,
      total: footerData().total,
    })`));
  await shot('05-stepped-back');

  // ── 6. Submit from the last step still creates and selects the option ───
  await eval_(`tid('select-create-next').click()`);
  await until(`wizardData().step === 1`);
  await eval_(`tid('select-create-save').click()`);
  const created = await until(`!$q('[role="dialog"]') && trigger().innerText.includes('Culture')`, 10000);
  check('submitting from the last step creates the option and selects it', created,
    await eval_(`trigger().innerText.trim()`));
  await shot('06-created');

  console.log('\nSummary: ' + results.filter(r => r.ok).length + '/' + results.length + ' checks passed');
  console.log('Screenshots: ' + shotDir);
  if (results.some(r => !r.ok)) process.exitCode = 1;
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
