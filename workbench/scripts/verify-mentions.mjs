import { openPage, checker, until, sleep } from './lib/cdp.mjs';

/*
 * Mentions in the TipTap editor: `@` for people, `#` for two models at once.
 *
 * Pest sees the field's config and the endpoint's answer. What it cannot see is
 * the half that only exists in a browser:
 *
 *   - the mention chunk is a third ESM entry, injected through @assets only when
 *     a field declares mentions — Livewire hoists those tags out of the
 *     component's own HTML, so no server-side render can show whether the script
 *     actually arrived and registered window.WireTiptapMentions;
 *   - the suggestion list is drawn by our own popup (no popup library), fed by a
 *     $wire round-trip, and dismissed by keys;
 *   - what the editor writes into the document is a ProseMirror node, and the
 *     whole design rests on that node serialising to an identity — a morph type
 *     and an id — rather than to the name that was on screen.
 *
 * That last check is the one worth having: if the node ever serialises the label
 * as the truth again, every rename in the application silently stops
 * propagating, and nothing else in the suite would notice.
 *
 * See .claude/skills/verify-preview.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

let session;
try {
  session = await openPage({ url: `${base}/field-tiptap-mentions`, shotPrefix: 'mentions' });
  const { eval_, shot, shotDir, consoleErrors, badResponses, close } = session;

  // Type into ProseMirror the way TipTap's suggestion plugin expects: real input
  // events through the editor's own commands, so the plugin's decorations run.
  const type = async (text) => {
    await eval_(`(() => {
      const mount = document.querySelector('[data-testid="form-editor-data.bio"]');
      const view = mount.querySelector('.ProseMirror');
      view.focus();
      document.execCommand('insertText', false, ${JSON.stringify(text)});
    })()`);
  };

  const popupItems = async () => JSON.parse(await eval_(`(() => {
    const box = document.querySelector('.wire-mention-suggestions');
    if (!box) return JSON.stringify(null);
    return JSON.stringify({
      groups: [...box.querySelectorAll('.wire-mention-group')].map(e => e.textContent),
      items: [...box.querySelectorAll('.wire-mention-item')].map(e => e.textContent),
      selected: box.querySelector('.wire-mention-item.is-selected')?.textContent ?? null,
    });
  })()`));

  const key = async (k) => {
    await eval_(`(() => {
      const view = document.querySelector('[data-testid="form-editor-data.bio"] .ProseMirror');
      view.dispatchEvent(new KeyboardEvent('keydown', { key: ${JSON.stringify(k)}, bubbles: true, cancelable: true }));
    })()`);
    await sleep(120);
  };

  const storedHtml = async () => eval_(`document.querySelector('[data-testid="form-editor-data.bio"] .ProseMirror').innerHTML`);

  // ── The chunk arrived at all ────────────────────────────────────────
  const registered = await until(
    async () => (await eval_('typeof window.WireTiptapMentions')) === 'object',
  );
  check('the mention chunk is injected and registers its node', registered === true,
    `typeof window.WireTiptapMentions = ${await eval_('typeof window.WireTiptapMentions')}`);

  check('and the editor booted with it',
    (await eval_(`!!document.querySelector('[data-testid="form-editor-data.bio"] .ProseMirror')`)) === true);

  // ── One trigger, two models ─────────────────────────────────────────
  await type('#a');

  const hash = await until(async () => {
    const p = await popupItems();
    return p && p.items.length > 0 ? p : null;
  }, { timeout: 6000 });

  check('typing # opens a suggestion list', hash !== null && hash.items.length > 0,
    JSON.stringify(hash));
  check('and it is grouped, because one trigger stands for several models',
    hash !== null && hash.groups.length >= 1, JSON.stringify(hash?.groups));
  await shot('01-hash-list');

  // ── Keyboard ────────────────────────────────────────────────────────
  const first = (await popupItems())?.selected;
  await key('ArrowDown');
  const second = (await popupItems())?.selected;
  check('arrow keys move the selection', first !== null && second !== null && first !== second,
    `${first} → ${second}`);

  await key('Escape');
  check('escape closes the list', (await popupItems()) === null);

  // ── What lands in the document ──────────────────────────────────────
  // Re-open, then commit the highlighted row with Enter.
  await eval_(`(() => {
    const view = document.querySelector('[data-testid="form-editor-data.bio"] .ProseMirror');
    view.focus();
  })()`);
  await type(' #a');
  await until(async () => (await popupItems())?.items.length > 0, { timeout: 6000 });
  await key('Enter');
  await sleep(300);

  const html = await storedHtml();

  check('the document stores a mention node', /data-type="mention"/.test(html), html.slice(0, 400));
  check('it stores what the mention points AT — a morph type and an id',
    /data-mention-type="[^"]+"/.test(html) && /data-id="[^"]+"/.test(html), html.slice(0, 400));
  check('and the trigger, so the label can be rebuilt on render',
    /data-mention-trigger="#"/.test(html), html.slice(0, 400));
  // The label is in the document as text — a fallback for a record that is gone,
  // never an attribute the renderer would be tempted to trust.
  check('the label is text, not a stored attribute', !/data-label=/.test(html), html.slice(0, 400));
  await shot('02-inserted');

  // ── The other trigger is a separate list ────────────────────────────
  await type(' @a');
  const at = await until(async () => {
    const p = await popupItems();
    return p && p.items.length > 0 ? p : null;
  }, { timeout: 6000 });

  check('@ opens its own list with its own source', at !== null && at.items.length > 0,
    JSON.stringify(at));
  await shot('03-at-list');

  finish({ consoleErrors, badResponses, shotDir });
  await close();
  session = null;
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  process.exitCode = 2;
} finally {
  await session?.close();
}
