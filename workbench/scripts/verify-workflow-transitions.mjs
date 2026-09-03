import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * V2.6 step 4: a workflow on a real table, driven in a browser.
 *
 * This driver exists because building it is what found the defect. `WorkflowState`
 * and `TransitionAction` were finished, tested and documented in V2.4 — and
 * nothing rendered either of them, so nobody had noticed that:
 *
 *   - every transition button appeared on every row, whatever the machine said,
 *     because what draws an action asks `isHidden($record)` and this type only
 *     answered `isAvailableFor()`, which nothing called; and
 *   - pressing one did nothing at all, because an action runs its
 *     `getActionCallback()` and this type set none.
 *
 * Both are invisible to a unit test of the class, and both are obvious the
 * moment a table draws it. Hence the rule this repository keeps rediscovering: a
 * new UI layer is proven by a prototype on a real entity, driven in a browser.
 *
 * The fixture is the workbench invoice table, whose statuses the seeder writes
 * and whose workflow is declared on InvoiceResource. The graph is a cycle —
 * paid can be reopened — because the workbench database is shared with 79 other
 * drivers and a one-way move would leave it one state further along every run.
 * This driver puts back whatever it moves.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/workspace/invoices`, shotPrefix: 'workflow-transitions', width: 1400, height: 900 });
const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  // Row state as the browser sees it: status text plus the transition buttons on offer.
  const rows = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="table-row"]')].map(tr => ({
    number: tr.querySelector('[data-testid="table-cell-number"]')?.innerText.trim(),
    status: tr.querySelector('[data-testid="table-cell-status"]')?.innerText.trim(),
    buttons: [...tr.querySelectorAll('button, a')].map(b => b.innerText.trim()).filter(t => ['Draft','Pending','Paid','Overdue'].includes(t)),
  })))`).then(JSON.parse);

  const rowFor = async (number) => (await rows()).find((r) => r.number === number);

  const clickTransition = async (number, label) => {
    const box = JSON.parse(await eval_(`(() => {
      const tr = [...document.querySelectorAll('[data-testid="table-row"]')]
        .find(tr => tr.querySelector('[data-testid="table-cell-number"]')?.innerText.trim() === ${JSON.stringify(number)});
      const el = [...tr.querySelectorAll('button, a')].find(b => b.innerText.trim() === ${JSON.stringify(label)});
      el.scrollIntoView({ block: 'center' });
      const r = el.getBoundingClientRect();
      return JSON.stringify({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    })()`));
    await page('Input.dispatchMouseEvent', { type: 'mousePressed', x: box.x, y: box.y, button: 'left', clickCount: 1 });
    await page('Input.dispatchMouseEvent', { type: 'mouseReleased', x: box.x, y: box.y, button: 'left', clickCount: 1 });
  };

  const statusOf = (number) => eval_(`(() => {
    const tr = [...document.querySelectorAll('[data-testid="table-row"]')]
      .find(tr => tr.querySelector('[data-testid="table-cell-number"]')?.innerText.trim() === ${JSON.stringify(number)});
    return tr?.querySelector('[data-testid="table-cell-status"]')?.innerText.trim() ?? '';
  })()`);

  await waitFor(`!! window.Alpine && document.querySelectorAll('[data-testid="table-row"]').length >= 3`);

  // ── 1. The machine decides what is on offer ──────────────────────────────
  const all = await rows();
  check('the invoice table rendered its rows', all.length >= 3, `${all.length} rows`);

  const paid = all.find((r) => r.status === 'paid');
  const pending = all.find((r) => r.status === 'pending');
  const overdue = all.find((r) => r.status === 'overdue');

  check('a paid invoice offers only the edge that exists from it', !! paid && JSON.stringify(paid.buttons) === JSON.stringify(['Pending']), JSON.stringify(paid?.buttons));
  check('a pending invoice offers both of its edges', !! pending && pending.buttons.includes('Paid') && pending.buttons.includes('Overdue') && ! pending.buttons.includes('Pending'), JSON.stringify(pending?.buttons));
  check('an overdue invoice offers only what it may become', !! overdue && JSON.stringify(overdue.buttons) === JSON.stringify(['Paid']), JSON.stringify(overdue?.buttons));
  await shot('01-offered');

  // ── 2. Pressing one actually moves the record ────────────────────────────
  const subject = pending?.number;
  check('there is a pending invoice to move', !! subject, subject ?? 'none');

  await clickTransition(subject, 'Paid');
  const moved = await waitFor(`(() => {
    const tr = [...document.querySelectorAll('[data-testid="table-row"]')]
      .find(tr => tr.querySelector('[data-testid="table-cell-number"]')?.innerText.trim() === ${JSON.stringify(subject)});
    return tr?.querySelector('[data-testid="table-cell-status"]')?.innerText.trim() === 'paid';
  })()`, { timeout: 8000 });
  check('pressing a transition moves the record', !! moved, await statusOf(subject));

  // ── 3. …and the buttons follow the new state ─────────────────────────────
  const after = await rowFor(subject);
  check('the offer is recomputed for the state it landed in', JSON.stringify(after?.buttons) === JSON.stringify(['Pending']), JSON.stringify(after?.buttons));
  await shot('02-moved');

  // ── 4. Put it back, so the next run starts where this one did ────────────
  await clickTransition(subject, 'Pending');
  const restored = await waitFor(`(() => {
    const tr = [...document.querySelectorAll('[data-testid="table-row"]')]
      .find(tr => tr.querySelector('[data-testid="table-cell-number"]')?.innerText.trim() === ${JSON.stringify(subject)});
    return tr?.querySelector('[data-testid="table-cell-status"]')?.innerText.trim() === 'pending';
  })()`, { timeout: 8000 });
  check('the reopen edge puts it back', !! restored, await statusOf(subject));

  const back = await rowFor(subject);
  check('and the original offer is back with it', back?.buttons.includes('Paid') && back?.buttons.includes('Overdue'), JSON.stringify(back?.buttons));
  await shot('03-restored');

  await sleep(200);
  finish({ consoleErrors, badResponses, shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await close();
}
