---
order: 55
summary: Companies over tenant zones — registering one, its profile, and inviting and managing its members, with a company that always keeps an owner.
---

# The Companies Module

A [tenant zone](../panels/tenancy.md) knows which company a URL is about and who
may enter it. What it does not have is the screens around a company: a way to
create one, to rename it, to bring people in and to take them out. This module is
those screens.

```bash
composer require nyoncode/wire-module-tenants
php artisan wire-module-tenants:install
php artisan migrate
```

## How It Works

The module ships a `Tenant` model over a `tenants` table, the `tenant_user` pivot
membership is read from — with a `role`, `owner` or `member`, beside it — and a
table of invitations. Installing it fills the two settings a tenant zone reads when
the application has not set them: `wire-core.tenancy.model` becomes its `Tenant`,
and `wire-panels.routes.tenant_entry.view` becomes its page for somebody in no
company. Anything the application already named, it keeps.

Its screens live in two places, because they are about two different moments:

| Where | Route | What it is |
| --- | --- | --- |
| Outside any company | `tenants/register` | Registering a company |
| Outside any company | `tenants/invitations/{invitation}` (signed) | Accepting an invitation |
| Inside the tenant zone | `company` | The company's profile — name, address, deleting it |
| Inside the tenant zone | `members` | Its members, their roles, invitations |

The second pair are two resources of the `tenants` module, so they appear wherever
the application's tenant zone routes them — `Route::wireResources()`, or a zone's
`only` list naming `company` and `members`. Outside a company both stay out of the
menu.

**Every member may look; only an owner changes anything.** Each action asks
`Membership` again when it runs, since a button that was drawn is not a
permission. And one rule holds whoever asks: **a company always keeps an owner** —
the last one cannot be removed or made a member, the rule the users module holds
for the last super-admin.

**The traps.** A company's slug is a URL segment, so it may not be one the
application's own routes answer — `reserved_slugs` lists them. Changing the slug
changes every link to the company; the profile page lands on the new address after
the save. Deleting is soft and asks for the company's name rather than a yes/no.

## Registering A Company

Whoever registers a company becomes its owner, and lands on its first page:
`home` — `app/{tenant}` by default — with the new slug in it. Who may register is
one setting:

```php
// config/wire-module-tenants.php
'registration' => 'anyone',              // 'anyone' | 'ability' | false
'registration_ability' => 'tenants.create',
'home' => 'app/{tenant}',                // or '//{tenant}.example.com'
'reserved_slugs' => ['admin', 'api', 'app', 'login', 'logout', 'register', 'tenants'],
```

`anyone` is a SaaS — any signed-in person may start a company. `ability` asks the
Gate, for an internal system where only an administrator creates one. `false` takes
the screen away; companies are then created by the application itself.

Under [database isolation](../start/authorization.md#a-database-per-tenant),
registering also queues `ProvisionTenantDatabase`, which creates the new company's
database and runs the tenant migrations in it.

## Somebody In No Company

The tenant zone's own address — `/app` — sends a person to their company. Somebody
in none gets the module's page, which offers registration when the setting allows
it and says to ask a colleague for an invitation when it does not. It is drawn
inside the layout the application gave its Livewire pages
(`livewire.component_layout`).

## Members And Invitations

An owner invites by e-mail address and chooses the role. The e-mail carries a
signed link that works until the invitation expires — `invitations.expire_days`,
seven by default — and only for the address it was sent to: a forwarded link is a
403 for whoever it was forwarded to. Accepting it adds the person in the invited
role, or leaves an existing member's role as it is, and lands them in the company.

The members list shows the company's members and nobody else — never every account
of the application — with the actions an owner has: make owner, make member, and
remove from the company, each refused for the last owner.

```php
use NyonCode\WireModuleTenants\Actions\InviteMember;
use NyonCode\WireModuleTenants\Enums\MemberRole;

(new InviteMember)($company, 'ada@example.com', MemberRole::Member, auth()->user());
```

The actions are plain invokable classes — `RegisterTenant`, `InviteMember`,
`AcceptInvitation`, `ChangeMemberRole`, `RemoveMember`, `DeleteTenant` — for an
application that wants the same rules behind a screen of its own.

## Projects Inside A Company

The module does not manage teams. With the [users module](users.md) installed and
its team model tenant-owned, a company's projects are that module's teams, scoped to
the company — see [Teams inside a company](teams-and-two-factor.md#teams-inside-a-company).

## Related

- [Tenancy](../panels/tenancy.md) — the tenant zone these screens live in
- [Authorization § Multi-tenancy](../start/authorization.md#multi-tenancy) — the scope, the isolations and queued work
- [Users](users.md) — accounts, roles and teams
- [Modules](index.md) — the other ready-made areas
