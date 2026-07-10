import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { mkdir, rm, writeFile } from 'node:fs/promises';

/*
 * Interactive CDP driver verifying the reactive wizard stack in the workbench
 * preview (/previews/forms-wizard-live): per-step validation on Next, dynamic
 * step count sync on morph, live select with a visibleWhen sibling, and the
 * create-option modal merging into the combobox without a refresh.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-wizard-live.mjs
 *
 * Exit code 0 = all checks passed; 1 = a check failed; 2 = driver error.
 * Screenshots of every stage land in SHOT_DIR (defaults to a temp dir, path
 * printed at the end) — look at them, don't only trust the assertions.
 */

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/forms-wizard-live';
const chromeBin = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const devtoolsPort = Number(process.env.CHROME_PORT ?? 9334);
const shotDir = process.env.SHOT_DIR ?? join(tmpdir(), 'wire-wizard-verify-shots');
await mkdir(shotDir, { recursive: true });

const userDataDir = join(tmpdir(), `wire-wizard-verify-${Date.now()}`);
const chrome = spawn(chromeBin, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--hide-scrollbars', `--remote-debugging-port=${devtoolsPort}`,
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
  await page('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1400, deviceScaleFactor: 1, mobile: false });
  await page('Page.navigate', { url });
  await sleep(3000);

  // Helpers evaluated in-page.
  const helpers = `
    window.$q = (sel) => document.querySelector(sel);
    window.$qa = (sel) => [...document.querySelectorAll(sel)];
    window.wizardRoot = () => $qa('[x-data]').find(el => (el.getAttribute('x-data')||'').includes('wizard:'));
    window.wizardData = () => Alpine.$data(wizardRoot());
    window.visibleText = () => wizardRoot().innerText;
    window.stepButtons = () => $qa('ol button');
    window.nextBtn = () => $qa('button').find(b => b.innerText.trim().startsWith('Next') || b.innerText.trim().startsWith('Další'));
    window.setInput = (sel, value) => { const el = $q(sel); el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); };
    true;
  `;
  await eval_(helpers);

  // ── 0. Page + wizard rendered ─────────────────────────────────────────
  const booted = await eval_(`!!wizardRoot() && typeof Alpine !== 'undefined'`);
  check('wizard preview renders with Alpine booted', booted);
  await shot('01-initial');

  // ── 1. Next with empty required fields → stays on step 0, errors shown ─
  await eval_(`nextBtn().click()`);
  await sleep(1500);
  const afterInvalidNext = await eval_(`JSON.stringify({
    step: wizardData().step,
    hasError: visibleText().toLowerCase().includes('required') || !!$q('.text-red-500, .text-red-600, [class*="text-red"]'),
  })`);
  const s1 = JSON.parse(afterInvalidNext);
  check('invalid Next keeps step 0 and shows errors', s1.step === 0 && s1.hasError, afterInvalidNext);
  await shot('02-invalid-next');

  // ── 2. live select: pick "Sport" → visibleWhen sibling appears in same roundtrip
  await eval_(`$qa('button[aria-haspopup="listbox"]')[0].click()`);
  await sleep(400);
  const sportClicked = await eval_(`
    (() => {
      const opt = $qa('[role="option"] button').find(b => b.innerText.trim() === 'Sport');
      if (!opt) return false; opt.click(); return true;
    })()
  `);
  check('combobox opened and Sport option clicked', sportClicked === true);
  await sleep(1500);
  const whySport = await eval_(`!!$qa('label').find(l => l.innerText.includes('Why sport?'))`);
  check('live select reveals visibleWhen sibling without extra interaction', whySport);
  await shot('03-live-select-visiblewhen');

  // ── 3. create-option: open panel footer → modal → create → selected + in options
  await eval_(`$qa('button[aria-haspopup="listbox"]')[0].click()`);
  await sleep(400);
  const createOpened = await eval_(`
    (() => {
      const btn = $qa('button').find(b => b.innerText.includes('Create option') || b.innerText.includes('Vytvořit'));
      if (!btn) return false; btn.click(); return true;
    })()
  `);
  check('create-option footer button present and clicked', createOpened === true);
  await sleep(1500);
  const modalOpen = await eval_(`!!$q('[role="dialog"]')`);
  check('create-option modal opened', modalOpen);
  await shot('04-create-modal');

  await eval_(`
    (() => {
      const input = $q('[role="dialog"] input[type="text"], [role="dialog"] input:not([type])');
      input.value = 'Culture'; input.dispatchEvent(new Event('input', { bubbles: true }));
    })()
  `);
  await sleep(600);
  await eval_(`$qa('[role="dialog"] button').find(b => b.innerText.trim() === 'Create' || b.innerText.trim() === 'Vytvořit').click()`);
  await sleep(2000);
  const afterCreate = await eval_(`JSON.stringify({
    modalClosed: !$q('[role="dialog"]'),
    triggerLabel: $qa('button[aria-haspopup="listbox"]')[0].innerText.trim(),
  })`);
  const s3 = JSON.parse(afterCreate);
  check('created option selected in trigger without refresh', s3.modalClosed && s3.triggerLabel.includes('Culture'), afterCreate);
  await shot('05-created-option');

  // Also confirm the new option is in the (client-side) dropdown list now.
  await eval_(`$qa('button[aria-haspopup="listbox"]')[0].click()`);
  await sleep(400);
  const inList = await eval_(`$qa('[role="option"] button').some(b => b.innerText.trim() === 'Culture')`);
  check('created option merged into the open dropdown list', inList);
  await eval_(`document.body.click()`);
  await sleep(300);

  // ── 4. fill step 0 validly → Next advances ─────────────────────────────
  await eval_(`setInput('input[id*="name"]', 'Amelia Stone')`);
  // the "Why sport?" field is required? no — only visible; leave it
  await sleep(600);
  await eval_(`nextBtn().click()`);
  await sleep(1800);
  const step2 = await eval_(`wizardData().step`);
  check('valid Next advances to step 1', step2 === 1, `step=${step2}`);
  await shot('06-step-1');

  // ── 5. dynamic step: toggle "Add extras step" → total 3 → 4 ────────────
  const totalBefore = await eval_(`wizardData().total`);
  await eval_(`$q('button[role="switch"]').click()`);
  await sleep(2000);
  const totalAfter = await eval_(`wizardData().total`);
  check('live toggle adds the Extras step (total sync on morph)', totalBefore === 3 && totalAfter === 4, `total ${totalBefore} -> ${totalAfter}`);
  const indicatorCount = await eval_(`$qa('ol li').length`);
  check('indicator shows 4 steps', indicatorCount === 4, `li=${indicatorCount}`);
  await shot('07-extras-step-added');

  // ── 6. per-step validation on the revealed step ─────────────────────────
  await eval_(`setInput('input[id*="email"]', 'amelia@example.com')`);
  await sleep(600);
  await eval_(`nextBtn().click()`); // step 1 -> 2 (Extras)
  await sleep(1500);
  const stepExtras = await eval_(`wizardData().step`);
  check('advances into the dynamic Extras step', stepExtras === 2, `step=${stepExtras}`);
  await eval_(`nextBtn().click()`); // Extras has required extra_note → must stay
  await sleep(1500);
  const afterExtrasNext = await eval_(`JSON.stringify({ step: wizardData().step, err: !!$q('[class*="text-red"]') })`);
  const s6 = JSON.parse(afterExtrasNext);
  check('empty Extras step blocks Next with its own error', s6.step === 2 && s6.err, afterExtrasNext);
  await shot('08-extras-validation');

  // ── 7. toggle extras off from Extras step → step clamps back into range ─
  await eval_(`setInput('input[id*="extra_note"]', 'done')`);
  await sleep(400);
  await eval_(`nextBtn().click()`);
  await sleep(1500);
  const reviewStep = await eval_(`JSON.stringify({ step: wizardData().step, total: wizardData().total })`);
  check('valid Extras advances to Review', JSON.parse(reviewStep).step === 3, reviewStep);
  await shot('09-review');

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
