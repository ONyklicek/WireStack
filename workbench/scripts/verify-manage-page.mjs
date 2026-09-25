import { openPage, checker } from './lib/cdp.mjs';

/*
 * A whole resource on one page: the list, create and edit as modals over it.
 *
 * Pest drives the component; only a browser shows that the modal a header
 * action opens is really drawn by the table the page hosts, that its field
 * takes typing, and that the list re-renders with what the modal saved. The
 * driver makes a team, renames it and deletes it, so the workbench's own teams
 * are where it found them.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url: `${base}/resource-manage`, shotPrefix: 'manage-page', width: 1300, height: 900,
});

const name = `Driver team ${Date.now()}`;
const modalInput = `document.querySelector('[role="dialog"] input[type="text"], [data-testid="action-modal"] input[type="text"]')`;
const typeInModal = (value) => eval_(`(() => {
  const input = ${modalInput};
  input.value = ${JSON.stringify(value)};
  input.dispatchEvent(new Event('input', { bubbles: true }));
})()`);
const submitModal = () => eval_(`(() => {
  const buttons = [...document.querySelectorAll('button')].filter((b) => b.offsetParent !== null && /submit|save|confirm/i.test((b.getAttribute('wire:click') ?? '') + ' ' + b.type));
  const target = buttons.find((b) => (b.getAttribute('wire:click') ?? '').includes('submitActionModal')) ?? buttons.find((b) => b.type === 'submit');
  target?.click();
  return !! target;
})()`);
const rowOf = (text) => `[...document.querySelectorAll('[data-testid="table-row"]')].find((r) => r.innerText.includes(${JSON.stringify(text)}))`;

try {
  await waitFor(`!! window.Alpine && !! document.querySelector('[data-testid="action-create"]')`);
  check('the page offers New beside its heading', true);

  // ── create ──
  await eval_(`document.querySelector('[data-testid="action-create"]').click()`);
  await waitFor(`!! ${modalInput} && ${modalInput}.offsetParent !== null`);
  await shot('01-create-modal');
  check('New opens a modal with the resource form', true);

  await typeInModal(name);
  check('the modal submits', await submitModal());
  await waitFor(`!! ${rowOf(name)}`);
  check('the list shows the record the modal created', true);

  // ── edit ──
  await eval_(`${rowOf(name)}.querySelector('[data-testid="action-edit"]').click()`);
  await waitFor(`!! ${modalInput} && ${modalInput}.offsetParent !== null && ${modalInput}.value === ${JSON.stringify(name)}`);
  check('Edit opens the same form seeded from the row', true);
  await typeInModal(`${name} renamed`);
  await submitModal();
  await waitFor(`!! ${rowOf(`${name} renamed`)}`);
  await shot('02-edited');
  check('the list shows what the edit saved', true);

  // ── delete, putting the workbench back ──
  await eval_(`${rowOf(`${name} renamed`)}.querySelector('[data-testid="action-delete"]').click()`);
  await waitFor(`!! document.querySelector('[data-testid="confirmation-confirm"]') && document.querySelector('[data-testid="confirmation-confirm"]').offsetParent !== null`);
  await eval_(`document.querySelector('[data-testid="confirmation-confirm"]').click()`);
  await waitFor(`! ${rowOf(name)}`);
  check('Delete removes the row', true);
} catch (error) {
  check('the flow ran to the end', false, error.message);
  await shot('99-failed');
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
