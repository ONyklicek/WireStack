/*
 * FileUpload interactive crop, in a real browser (/previews/field-file-upload).
 *
 * The frame is placed by hand and the crop happens on a canvas before Livewire
 * uploads, so only measuring the resulting pixels proves it: a red/blue image is
 * pushed through the picker and the crop is asserted to follow the frame.
 *
 * Usage (see .claude/skills/verify-preview/SKILL.md):
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085   # in background
 *   node workbench/scripts/verify-image-crop.mjs
 *
 * Exit 0 = all checks passed; 1 = a check failed; 2 = driver error.
 */
import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { until } from './lib/cdp.mjs';
const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/field-file-upload`;
const port = Number(process.env.CHROME_PORT ?? 9481);
const chrome = spawn(process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ['--headless=new','--disable-gpu','--no-first-run','--disable-background-timer-throttling', '--disable-backgrounding-occluded-windows', '--disable-renderer-backgrounding', `--remote-debugging-port=${port}`,`--user-data-dir=${join(tmpdir(),`cr-${Date.now()}`)}`,'about:blank'], { stdio:'ignore' });
let cdp; const R=[]; const chk=(n,ok,d='')=>{R.push(ok);console.log(`${ok?'PASS':'FAIL'}  ${n}${d?` — ${d}`:''}`)};
try {
  const wsUrl = await waitForDevtools(port); cdp = await connect(wsUrl);
  const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
  const page=(m,p)=>cdp.send(m,p,sessionId);
  const ev=async e=>{const{result,exceptionDetails}=await page('Runtime.evaluate',{expression:e,returnByValue:true,awaitPromise:true});if(exceptionDetails)throw new Error(exceptionDetails.exception?.description);return result?.value};
  await page('Page.enable'); await page('Runtime.enable');
  await page('Emulation.setDeviceMetricsOverride',{width:1200,height:1000,deviceScaleFactor:1,mobile:false});
  await page('Page.navigate',{url}); await sleep(2500);
  const root = `document.querySelector('[data-testid$="-picker"]').closest('[x-data]')`;

  // Nasypej do pickeru vysoký obrázek: 400x1000, horní půlka červená, spodní modrá.
  const opened = await ev(`(async () => {
    const c = document.createElement('canvas'); c.width=400; c.height=1000;
    const x = c.getContext('2d');
    x.fillStyle='#ff0000'; x.fillRect(0,0,400,500);
    x.fillStyle='#0000ff'; x.fillRect(0,500,400,500);
    const blob = await new Promise(r=>c.toBlob(r,'image/png'));
    const f = new File([blob],'tall.png',{type:'image/png'});
    const p = document.querySelector('[data-testid$="-picker"]');
    const dt = new DataTransfer(); dt.items.add(f); p.files = dt.files;
    p.dispatchEvent(new Event('change',{bubbles:true}));
    for (let i=0;i<40 && !Alpine.\$data(${root}).cropping;i++) await new Promise(r=>setTimeout(r,100));
    return Alpine.\$data(${root}).cropping;
  })()`);
  chk('picking an image opens the frame instead of uploading', opened === true, String(opened));

  // Read primitives, not the reactive objects: CDP cannot serialise an Alpine
  // Proxy and silently hands back {} — which reads as a failure that is not one.
  const fw = await ev(`Number(Alpine.\$data(${root}).frame.width)`);
  const fh = await ev(`Number(Alpine.\$data(${root}).frame.height)`);
  const oy = await ev(`Number(Alpine.\$data(${root}).cropOffset.y)`);
  chk('the frame is sized to the ratio and starts centred',
    fw > 0 && Math.abs(fw / fh - 16 / 9) < 0.05 && Math.abs(oy - 0.5) < 0.01, `${fw}x${fh} offsetY=${oy}`);

  // Přetáhni rámeček úplně nahoru → ořez musí být z ČERVENÉ půlky.
  await ev(`Alpine.\$data(${root}).moveFrame({x:0.5, y:0})`); await sleep(150);
  const top = await ev(`(async () => { const d = Alpine.\$data(${root}); await d.confirmCrop();
    const wi = [...document.querySelectorAll('input[type=file]')].find(i => !i.dataset.testid?.endsWith('-picker'));
    for (let i=0;i<40 && !wi.files.length;i++) await new Promise(r=>setTimeout(r,100));
    const bmp = await createImageBitmap(wi.files[0]);
    const cv = document.createElement('canvas'); cv.width=bmp.width; cv.height=bmp.height;
    cv.getContext('2d').drawImage(bmp,0,0);
    const px = cv.getContext('2d').getImageData(Math.floor(bmp.width/2), Math.floor(bmp.height/2), 1, 1).data;
    return { w: bmp.width, h: bmp.height, rgb: [px[0],px[1],px[2]] };
  })()`);
  console.log('     ', JSON.stringify(top));
  chk('cropping at the top yields the red half', top.rgb[0] > 200 && top.rgb[2] < 50, `rgb=${top.rgb}`);
  chk('the frame still honours the 16:9 ratio', Math.abs(top.w/top.h - 16/9) < 0.05, `${top.w}x${top.h}`);

  // The discriminating check: cropping at the TOP would also pass if the offset
  // were ignored and the top always taken. The bottom must give the other half.
  const bottom = await ev(`(async () => {
    const c = document.createElement('canvas'); c.width=400; c.height=1000;
    const x = c.getContext('2d');
    x.fillStyle='#ff0000'; x.fillRect(0,0,400,500);
    x.fillStyle='#0000ff'; x.fillRect(0,500,400,500);
    const blob = await new Promise(r=>c.toBlob(r,'image/png'));
    const p = document.querySelector('[data-testid$="-picker"]');
    const dt = new DataTransfer(); dt.items.add(new File([blob],'tall.png',{type:'image/png'})); p.files = dt.files;
    p.dispatchEvent(new Event('change',{bubbles:true}));
    const d = Alpine.$data(${root});
    for (let i=0;i<40 && !d.cropping;i++) await new Promise(r=>setTimeout(r,100));
    d.moveFrame({x:0.5, y:1});
    const wi = [...document.querySelectorAll('input[type=file]')].find(i => !i.dataset.testid?.endsWith('-picker'));
    const before = wi.files[0];
    await d.confirmCrop();
    for (let i=0;i<40 && wi.files[0] === before;i++) await new Promise(r=>setTimeout(r,100));
    const bmp = await createImageBitmap(wi.files[0]);
    const cv = document.createElement('canvas'); cv.width=bmp.width; cv.height=bmp.height;
    cv.getContext('2d').drawImage(bmp,0,0);
    const px = cv.getContext('2d').getImageData(Math.floor(bmp.width/2), Math.floor(bmp.height/2), 1, 1).data;
    return [px[0],px[1],px[2]];
  })()`);
  chk('cropping at the bottom yields the blue half', bottom[2] > 200 && bottom[0] < 50, `rgb=${bottom}`);

  // ── The cropper is a dialog, so it has to behave like one ──────────────
  // It covers the page; before it carried the focus trap, opening it left the
  // focus on <body> and Tab reached the file picker BEHIND the overlay.
  await ev(`(async () => {
    const c = document.createElement('canvas'); c.width=200; c.height=400;
    const x = c.getContext('2d'); x.fillStyle='red'; x.fillRect(0,0,200,400);
    const blob = await new Promise(r=>c.toBlob(r,'image/png'));
    const p = document.querySelector('[data-testid$="-picker"]');
    const dt = new DataTransfer(); dt.items.add(new File([blob],'tall.png',{type:'image/png'})); p.files = dt.files;
    p.dispatchEvent(new Event('change',{bubbles:true}));
    const d = Alpine.$data(${root});
    for (let i=0;i<40 && !d.cropping;i++) await new Promise(r=>setTimeout(r,100));
  })()`);

  const box = '[data-testid$="-cropper"]';

  // `cropping` flips before the overlay is in the DOM with the focus moved into
  // it, so this used to be a blind settle. A sweep runs 74 Chromes over one PHP
  // dev server and that guess stopped holding — the two checks below then failed
  // for a reason that had nothing to do with the cropper. Waiting for the state
  // they assert against costs nothing when it is already true.
  await until(() => ev(`(() => {
    const b = document.querySelector('${box}');
    return !! b && b.contains(document.activeElement);
  })()`));
  chk('the cropper announces itself as a modal dialog',
    await ev(`(() => { const b = document.querySelector('${box}'); return b?.getAttribute('role') === 'dialog' && b?.getAttribute('aria-modal') === 'true'; })()`));

  chk('opening it moves the focus inside',
    await ev(`(() => { const b = document.querySelector('${box}'); return !!b && b.contains(document.activeElement); })()`),
    await ev(`document.activeElement?.getAttribute?.('data-testid') ?? document.activeElement?.tagName`));

  const tabbed = [];
  const focused = () => ev(`document.activeElement?.getAttribute?.('data-testid') ?? document.activeElement?.tagName ?? ''`);

  for (let i = 0; i < 3; i++) {
    const before = await focused();

    await cdp.send('Input.dispatchKeyEvent',{type:'rawKeyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9},sessionId);
    await cdp.send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9},sessionId);

    // Wait for the focus to MOVE, not for a guessed number of milliseconds.
    // Reading too early is the dangerous direction here: the focus is inside the
    // trap before Tab as well, so a stale read passes the check without the trap
    // having done anything.
    await until(async () => (await focused()) !== before);

    tabbed.push(await ev(`(() => { const b = document.querySelector('${box}'); return !!b && b.contains(document.activeElement); })()`));
  }
  chk('Tab stays inside it instead of reaching the picker behind it',
    tabbed.every(Boolean), JSON.stringify(tabbed));

  const returned = await ev(`(async () => {
    const before = document.activeElement;
    Alpine.$data(${root}).cancelCrop();
    await new Promise(r => setTimeout(r, 600));
    const b = document.querySelector('${box}');
    return { closed: !b || b.getClientRects().length === 0, focus: document.activeElement?.tagName, stillInside: !!b && b.contains(document.activeElement) };
  })()`);
  chk('cancelling closes it and takes the focus back out',
    returned.closed && ! returned.stillInside, JSON.stringify(returned));

  const f=R.filter(x=>!x).length; console.log(`\n${R.length-f}/${R.length} checks passed`); process.exitCode=f?1:0;
} catch(e){console.error('DRIVER ERROR:',e);process.exitCode=2} finally { try{cdp?.close()}catch{} chrome.kill(); }
async function waitForDevtools(port){for(let i=0;i<60;i++){try{const r=await fetch(`http://127.0.0.1:${port}/json/version`);const j=await r.json();if(j.webSocketDebuggerUrl)return j.webSocketDebuggerUrl}catch{}await sleep(250)}throw new Error('no devtools')}
async function connect(wsUrl){const ws=new WebSocket(wsUrl);const pending=new Map();let id=0;await new Promise((res,rej)=>{ws.onopen=res;ws.onerror=rej});ws.onmessage=(e)=>{const m=JSON.parse(e.data);if(m.id&&pending.has(m.id)){const{resolve,reject}=pending.get(m.id);pending.delete(m.id);m.error?reject(new Error(JSON.stringify(m.error))):resolve(m.result)}};return{send(method,params={},sessionId){const i=++id;return new Promise((resolve,reject)=>{pending.set(i,{resolve,reject});ws.send(JSON.stringify({id:i,method,params,sessionId}))})},close(){ws.close()}}}
