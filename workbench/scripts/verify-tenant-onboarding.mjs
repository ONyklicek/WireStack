import { openPage, checker, sleep } from './lib/cdp.mjs';

/*
 * wire-module-tenants over the workbench's `tenants` zone (ADR 0040 step 8).
 * The demo user owns Acme beside Mason, and is a plain member of Globex.
 *
 * What only a browser can say:
 *  - registering a company lands in it, and the switcher offers it at once;
 *  - a reserved slug is refused on the form, not after a redirect;
 *  - changing the slug lands on the new address, and deleting asks for the
 *    name rather than a yes/no;
 *  - the members screen is the company's members only, with an owner's actions
 *    on everybody but the last owner, and none at all for a plain member.
 *
 * The company it registers is deleted again at the end, so a second run finds
 * the workbench where the first did. An invitation it sends stays behind, which
 * changes nothing any driver reads.
 */

const origin = process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const zone = '/previews/tenants';
const { check, finish } = checker();

const { page, eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = await openPage({
  url: `${origin}${zone}/acme/members`, shotPrefix: 'tenant-onboarding', width: 1400, height: 1000,
});

const slug = `wayne${Date.now().toString(36)}`;
const go = async (path, ready) => {
  await page('Page.navigate', { url: `${origin}${path}` });
  return waitFor(`location.pathname === '${path}' && !! window.Alpine && (${ready})`);
};
const type = (selector, value) => eval_(`(() => {
  const el = document.querySelector(${JSON.stringify(selector)});
  el.value = ${JSON.stringify(value)};
  el.dispatchEvent(new Event('input', { bubbles: true }));
})()`);
const rowOf = (text) => `[...document.querySelectorAll('[data-testid="table-row"]')].find((r) => r.innerText.includes(${JSON.stringify(text)}))`;
const rows = () => eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="table-row"]')].map(r => r.innerText.replace(/\\s+/g, ' ').trim()))`).then(JSON.parse);
const switcherItems = async () => {
  // The address changes before the new page is swapped in; wait for its bar.
  await waitFor(`!! document.querySelector('[data-testid="panels-tenant-switcher-trigger"]')`);
  await eval_(`document.querySelector('[data-testid="panels-tenant-switcher-trigger"]').click()`);
  await sleep(300);
  const items = JSON.parse(await eval_(`JSON.stringify([...document.querySelectorAll('[data-testid="panels-tenant-switcher-item"]')].map(a => a.textContent.trim()))`));
  await eval_(`document.body.click()`);
  return items;
};

try {
  // ── 1. An owner's members screen ────────────────────────────────────────
  await waitFor(`!! window.Alpine && document.querySelectorAll('[data-testid="table-row"]').length > 0`);
  const acme = await rows();
  check('the members screen lists the company\'s members, and only those', acme.length === 2 && acme.some((r) => r.includes('Mason')) && ! acme.some((r) => r.includes('Sofia')), JSON.stringify(acme));
  check('an owner is offered Invite', await eval_(`!! document.querySelector('[data-testid="action-invite"]')`));
  check('another member can be made owner or removed', await eval_(`!! ${rowOf('Mason')}?.querySelector('[data-testid="action-makeOwner"]') && !! ${rowOf('Mason')}?.querySelector('[data-testid="action-removeMember"]')`));
  check('the last owner cannot be removed or demoted', await eval_(`! ${rowOf('Amelia')}?.querySelector('[data-testid^="action-"]')`));
  await shot('01-owner-members');

  // ── 2. Inviting somebody ─────────────────────────────────────────────────
  await eval_(`document.querySelector('[data-testid="action-invite"]').click()`);
  const modalEmail = `document.querySelector('[role="dialog"] input[type="email"], [data-testid="action-modal"] input[type="email"]')`;
  await waitFor(`!! ${modalEmail} && ${modalEmail}.offsetParent !== null`);
  await eval_(`(() => { const el = ${modalEmail}; el.value = 'new.colleague@example.com'; el.dispatchEvent(new Event('input', { bubbles: true })); })()`);
  await eval_(`[...document.querySelectorAll('button')].find((b) => b.offsetParent !== null && (b.getAttribute('wire:click') ?? '').includes('submitActionModal'))?.click()`);
  const invited = await waitFor(`document.body.innerText.includes('An invitation is on its way to new.colleague@example.com')`);
  check('Invite sends the invitation and says so', !! invited);
  await shot('02-invited');

  // ── 3. A plain member's view ─────────────────────────────────────────────
  await go(`${zone}/globex/members`, `document.querySelectorAll('[data-testid="table-row"]').length > 0`);
  check('a plain member sees the members', (await rows()).some((r) => r.includes('Sofia')), JSON.stringify(await rows()));
  check('and is offered nothing to change', await eval_(`! document.querySelector('[data-testid="action-invite"], [data-testid="table-row"] [data-testid^="action-"]')`));
  await go(`${zone}/globex/company`, `!! document.querySelector('[data-testid="tenants-profile"]')`);
  check('the company page has no save and no delete for a member', await eval_(`! document.querySelector('[data-testid="tenants-profile-save"], [data-testid="tenants-delete"]')`));

  // ── 4. Registering, and a reserved slug ──────────────────────────────────
  await go('/tenants/register', `!! document.getElementById('data.name')`);
  await type('#data\\.name', 'Wayne Enterprises');
  const proposed = await waitFor(`document.getElementById('data.slug').value === 'wayne-enterprises'`);
  check('the name proposes the address', !! proposed, await eval_(`document.getElementById('data.slug').value`));

  await type('#data\\.slug', 'admin');
  await sleep(400);
  await eval_(`document.querySelector('[data-testid="tenants-register-submit"]').click()`);
  const refused = await waitFor(`!! document.querySelector('[data-testid="form-field-data.slug"] p.text-red-600')`);
  check('a reserved address is refused on the form, saying why', !! refused
    && await eval_(`location.pathname === '/tenants/register'`)
    && await eval_(`document.querySelector('[data-testid="form-field-data.slug"] p.text-red-600').textContent.includes('belongs to the application')`));
  await shot('03-reserved');

  await type('#data\\.slug', slug);
  await sleep(400);
  await eval_(`document.querySelector('[data-testid="tenants-register-submit"]').click()`);
  const landed = await waitFor(`location.pathname.startsWith('${zone}/${slug}/')`, { timeout: 15000 });
  check('registering lands in the new company', !! landed, await eval_('location.pathname'));
  const offered = await switcherItems();
  check('and the switcher offers it beside the others', offered.includes('Wayne Enterprises') && offered.includes('Acme'), JSON.stringify(offered));

  await go(`${zone}/${slug}/members`, `document.querySelectorAll('[data-testid="table-row"]').length > 0`);
  const own = await rows();
  check('whoever registered it is its one member, as owner', own.length === 1 && own[0].includes('Amelia') && own[0].includes('Owner'), JSON.stringify(own));

  // ── 5. A new address, then deleting it ──────────────────────────────────
  await go(`${zone}/${slug}/company`, `!! document.getElementById('data.slug')`);
  await type('#data\\.slug', `${slug}-co`);
  await sleep(400);
  await eval_(`document.querySelector('[data-testid="tenants-profile-save"]').click()`);
  const moved = await waitFor(`location.pathname === '${zone}/${slug}-co/company' && !! document.querySelector('[data-testid="tenants-delete"]')`, { timeout: 15000 });
  check('a new address lands on the new address', !! moved, await eval_('location.pathname'));

  await type('[data-testid="tenants-delete-confirmation"]', 'yes');
  await eval_(`document.querySelector('[data-testid="tenants-delete-submit"]').click()`);
  await sleep(1200);
  check('deleting with anything but the name keeps the company', await eval_(`location.pathname === '${zone}/${slug}-co/company'`));
  await shot('04-delete-refused');

  await type('[data-testid="tenants-delete-confirmation"]', 'Wayne Enterprises');
  await eval_(`document.querySelector('[data-testid="tenants-delete-submit"]').click()`);
  const gone = await waitFor(`! location.pathname.startsWith('${zone}/${slug}')`, { timeout: 15000 });
  check('deleting with the name takes it away', !! gone, await eval_('location.pathname'));
  const status = await eval_(`fetch('${zone}/${slug}-co/company').then(r => r.status)`);
  check('and its address is a 404 afterwards', status === 404, String(status));

  await sleep(200);
  finish({ consoleErrors, badResponses: badResponses.filter((r) => ! r.includes(`/${slug}-co/`)), shotDir });
} catch (e) {
  console.error('DRIVER ERROR:', e.message);
  await shot('99-failed');
  process.exitCode = 2;
} finally {
  await close();
}
