import { openPage, checker } from './lib/cdp.mjs';

/*
 * The role screens, which appear only where an installation has roles.
 *
 * "Where roles exist" is not a config flag — it is
 * `nyoncode/laravel-permission-extended` installed *and* the user model carrying
 * **its** `HasRoles` trait. A model on bare Spatie is deliberately not detected,
 * because these screens assume the wildcard matching and the super-admin gate
 * the extended package adds.
 *
 * What only a browser can answer:
 *
 *   - the role resource reached the **menu**. That registration happens once, at
 *     boot, while core spreads modules into the registries — so a workbench that
 *     pointed the module at its user model in `boot()` rather than `register()`
 *     had every role screen silently absent while `Roles::enabled()` answered
 *     true on every later call. A unit test asking the support class would have
 *     said yes.
 *   - permissions are edited as **names**, so a wildcard is just a name: the
 *     `invoices.*` row has to survive the round trip through the form.
 *   - the roles column on the users list is rendered from the relation, not from
 *     a column on the row.
 *   - the role detail page reads, and reads *split*: a role's wildcards are
 *     listed apart from the exact permissions they cover, which is the whole
 *     reason the page exists over the form that edits the same two facts.
 */

const base = process.env.PREVIEW_BASE ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews`;
const { check, finish } = checker();

const page_ = await openPage({ url: `${base}/routed/users`, shotPrefix: 'roles', width: 1300, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

const cellText = () => eval_(`[...document.querySelectorAll('[data-testid="admin-content"] tbody tr')].map(r => r.innerText).join(' | ')`);

try {
  await waitFor(`!! document.querySelector('[data-testid="admin-content"] tbody tr')`, 8000);

  // ── 1. The users list knows about roles ──────────────────────────────────
  const rows = await cellText();
  check('the users list shows the roles each person holds', /super-admin|manager|editor|viewer/.test(rows), rows.slice(0, 120));

  // ── The way out of the list ──────────────────────────────────────────────
  // Both lists shipped with no row action at all: the edit pages were routed
  // and unreachable from the screen in front of them.
  check('every row offers a way into the record', await eval_(`
    document.querySelectorAll('[data-testid="action-edit"]').length === document.querySelectorAll('[data-testid="admin-content"] tbody tr').length
  `));
  check('and a header action to add one', await eval_(`!! document.querySelector('[data-testid="header-action-create"]')`));

  // Not politeness: an administrator who removes their own row is signed out
  // mid-request into an application they can no longer reach.
  const deletes = await eval_(`document.querySelectorAll('[data-testid="action-delete"]').length`);
  const userRows = await eval_(`document.querySelectorAll('[data-testid="admin-content"] tbody tr').length`);
  check('but no way to delete the person reading the page', deletes === userRows - 1, `${deletes} of ${userRows} rows`);
  // The avatar column resolves through the same `StoredFileUrlResolver` the
  // chrome does, so a stored value that is already a complete source — the
  // `data:` URI the seeder writes — is drawn untouched.
  check('and a face beside them', await eval_(`!! document.querySelector('[data-testid="admin-content"] tbody img')`));
  await shot('01-users');

  // ── 2. The role resource is in the menu ──────────────────────────────────
  // The registration that put it there runs once, at boot. Absent from the menu
  // is what "roles are off" looks like, and it looks identical to a package that
  // does not ship them.
  const rolesLink = await eval_(`(() => {
    const link = [...document.querySelectorAll('[data-testid="admin-nav-item"]')].find(a => a.dataset.resource === 'roles');
    return link ? new URL(link.getAttribute('href'), location.href).pathname : null;
  })()`);
  check('the role resource reached the menu', !! rolesLink, String(rolesLink));

  await eval_(`[...document.querySelectorAll('[data-testid="admin-nav-item"]')].find(a => a.dataset.resource === 'roles').click()`);
  await waitFor(`location.pathname === ${JSON.stringify(String(rolesLink))}`, 8000);
  await waitFor(`!! document.querySelector('[data-testid="admin-content"] tbody tr')`, 8000);

  const roleRows = await cellText();
  check('and lists the roles this installation seeded', /super-admin/.test(roleRows) && /manager/.test(roleRows), roleRows.slice(0, 120));
  await shot('02-roles');

  // A wildcard is a permission in its own right — matched at check time, not
  // expanded when it is granted — so it has to survive as the literal name.
  check('and the wildcard reads as the name it is', /tasks\.\*/.test(roleRows), roleRows.slice(0, 80));

  // ── 3. The detail page ───────────────────────────────────────────────────
  // A role used to get Edit and Delete and no View, on the argument that the
  // edit form already showed everything a read-only page could. That holds only
  // while the permission list is short: forty chips inside a multi-select is a
  // control built for picking being used for reading.
  check('a role row offers all three actions', await eval_(`
    !! document.querySelector('[data-testid="action-view"]')
      && !! document.querySelector('[data-testid="action-edit"]')
      && !! document.querySelector('[data-testid="action-delete"]')
  `));

  await eval_(`(() => {
    const row = [...document.querySelectorAll('[data-testid="admin-content"] tbody tr')].find(r => r.innerText.includes('manager'));
    row.querySelector('[data-testid="action-view"]').click();
  })()`);

  await waitFor(`!! document.querySelector('[data-testid="admin-content"] .wire-infolist')`, 10000);

  const detail = await eval_(`document.querySelector('[data-testid="admin-content"]').innerText`);

  // The heading is the role, not the word "Role" — which is also the last
  // breadcrumb, so the resource singular put "Roles / Role" over a heading that
  // said it a third time.
  check('the detail page is headed by the role itself', await eval_(`
    document.querySelector('[data-testid="admin-content"] h1')?.textContent.trim() === 'manager'
  `));
  // The split is the point: a wildcard is a rule and an exact permission is an
  // entry, and one alphabetical row of chips hides the rule that makes half of
  // it redundant.
  // Case-insensitive: `innerText` returns text as CSS transformed it, and an
  // entry label is drawn uppercase.
  check('with its wildcards apart from the permissions they cover', /Wildcards[\s\S]*tasks\.\*[\s\S]*Granted permissions/i.test(detail), detail.replace(/\s+/g, ' ').slice(0, 160));
  await shot('03-role-view');

  // ── 4. And on to the form that edits the same two facts ──────────────────
  await eval_(`history.back()`);
  await waitFor(`!! document.querySelector('[data-testid="admin-content"] tbody tr')`, 10000);

  await eval_(`(() => {
    const row = [...document.querySelectorAll('[data-testid="admin-content"] tbody tr')].find(r => r.innerText.includes('manager'));
    row.querySelector('[data-testid="action-edit"]').click();
  })()`);

  await waitFor(`!! document.querySelector('[data-testid="admin-content"] form input')`, 10000);

  check('and clicking it opens that role', await eval_(`
    document.querySelector('[data-testid="admin-content"] form input')?.value === 'manager'
  `));
  check('with its permissions on it, wildcard and all', await eval_(`
    document.querySelector('[data-testid="admin-content"]').innerText.includes('invoices')
  `));
  await shot('04-role-edit');

  console.log(`Screenshots: ${shotDir}`);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
