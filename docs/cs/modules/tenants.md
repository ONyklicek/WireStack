---
order: 55
summary: Firmy nad tenantovými zónami — registrace firmy, její profil a pozvánky a správa členů, s firmou, která si vždy ponechá vlastníka.
---

# Modul firem

[Tenantová zóna](../panels/tenancy.md) ví, o které firmě URL mluví a kdo do ní smí.
Nemá ale obrazovky kolem firmy: jak ji založit, přejmenovat, přivést do ní lidi
a zase je odebrat. Tento modul jsou právě ty obrazovky.

```bash
composer require nyoncode/wire-module-tenants
php artisan wire-module-tenants:install
php artisan migrate
```

## Jak to funguje

Modul dodává model `Tenant` nad tabulkou `tenants`, pivot `tenant_user`, ze kterého
se čte členství — s `role` vedle, `owner` nebo `member` —, a tabulku pozvánek.
Instalace doplní dvě nastavení, která tenantová zóna čte, pokud je aplikace
nenastavila: `wire-core.tenancy.model` se stane jeho `Tenant`
a `wire-panels.routes.tenant_entry.view` jeho stránkou pro někoho bez firmy. Cokoli
aplikace už pojmenovala, zůstává.

Aplikace s vlastním modelem firmy ho pojmenuje ve `wire-core.tenancy.model`
a modul registruje do něj: každá obrazovka i akce se ptá stejného resolveru,
nejdřív nastavení jádra a modulové `model` jen tehdy, když jádro žádné neurčuje.
Pivot je `wire-core.tenancy.members_table`, migrovaný pod tímto jménem, a jeho dva
sloupce se řídí konvencí Laravelu pro oba modely — `company_id` a `user_id` pro
`Company` — což je to, co čte `InteractsWithTenants`.

Jeho obrazovky žijí na dvou místech, protože patří ke dvěma různým chvílím:

| Kde | Routa | Co to je |
| --- | --- | --- |
| Mimo jakoukoli firmu | `tenants/register` | Registrace firmy |
| Mimo jakoukoli firmu | `tenants/invitations/{invitation}` (podepsaná) | Přijetí pozvánky |
| Uvnitř tenantové zóny | `company` | Profil firmy — název, adresa, smazání |
| Uvnitř tenantové zóny | `members` | Její členové, jejich role, pozvánky |

**Každou z nich umisťuje aplikace**, nikdy provider modulu
([Routování](../panels/routing.md#popis-skupin-v-configu)). První dvojice je skupina
rout `tenants` — v `routes/web.php`, který zapíše `wire:install`, když je tenancy
zapnutá:

```php
// routes/web.php
Route::middleware(['web', 'auth'])->prefix('tenants')->group(function () {
    Route::wire('tenants');   // [tl! focus]
});
```

nebo jako záznam `wire-core.routes.groups`, který začíná od `tenants/` za
`['web', 'auth']`:

```php
'groups' => ['tenants' => ['routes' => ['register' => ['can' => 'tenants.create']]]],
```

Skupina je vaše: prefix, doména, middleware, `can:`. Routy se vrátí s klíči —
`register` a `accept` — pro cokoli, co potřebuje jen jedna z nich,
`Route::wire('tenants')['register']->middleware('can:tenants.create')`. Skupina
s `name()` se odmítne, protože e-mail s pozvánkou na tyhle routy odkazuje jejich
jmény. Bez té skupiny žádná obrazovka registrace není a stránka pro někoho bez
firmy žádnou nenabídne.

Druhá dvojice jsou dva resource modulu `tenants`, takže se objeví všude, kde je
tenantová zóna aplikace routuje — `Route::wire('panel', tenant: 'path')`, nebo
seznam `only` zóny, který jmenuje `company` a `members` — a jen tam: v zóně bez
firmy se neroutují vůbec. Mimo firmu obě zůstávají mimo menu.

**Dívat se smí každý člen; měnit smí jen vlastník.** Každá akce se při spuštění
znovu zeptá `Membership`, protože vykreslené tlačítko není oprávnění. A jedno
pravidlo platí, ať se ptá kdokoli: **firma si vždy ponechá vlastníka** — posledního
nejde odebrat ani udělat členem, stejné pravidlo, jaké drží modul uživatelů pro
posledního super-admina.

**Pasti.** Slug firmy je segment URL, takže nesmí být takový, na který odpovídají
vlastní routy aplikace — vyjmenovává je `reserved_slugs`. Změna slugu změní každý
odkaz na firmu; stránka profilu po uložení přejde na novou adresu. Mazání je
měkké a místo ano/ne žádá název firmy.

## Registrace firmy

Kdo firmu zaregistruje, stane se jejím vlastníkem a přistane na její první
stránce: `home` — ve výchozím stavu `app/{tenant}` — s novým slugem. Kdo smí
registrovat, určuje jedno nastavení:

```php
// config/wire-module-tenants.php
'registration' => 'anyone',              // 'anyone' | 'ability' | false
'registration_ability' => 'tenants.create',
'home' => 'app/{tenant}',                // nebo '//{tenant}.example.com'
'reserved_slugs' => ['admin', 'api', 'app', 'login', 'logout', 'register', 'tenants'],
```

`anyone` je SaaS — firmu může založit kdokoli přihlášený. `ability` se ptá Gate,
pro interní systém, kde firmu zakládá jen administrátor. `false` obrazovku vezme;
firmy pak zakládá aplikace sama.

Pod [izolací databází](../start/authorization.md#databaze-pro-kazdeho-tenanta)
registrace navíc zařadí `ProvisionTenantDatabase`, který nové firmě vytvoří
databázi a spustí v ní migrace tenantů.

## Někdo bez firmy

Vlastní adresa tenantové zóny — `/app` — pošle člověka do jeho firmy. Kdo nemá
žádnou, dostane stránku modulu, která nabídne registraci, když to nastavení
dovolí, a jinak poradí požádat kolegu o pozvánku. Vykreslí se uvnitř layoutu, který
aplikace dala svým Livewire stránkám (`livewire.component_layout`).

## Členové a pozvánky

Vlastník zve e-mailovou adresou a volí roli. E-mail nese podepsaný odkaz, který
platí, dokud pozvánka nevyprší — `invitations.expire_days`, ve výchozím stavu
sedm dní —, a jen pro adresu, na kterou šel: přeposlaný odkaz je pro toho, komu
byl přeposlán, 403. Přijetí přidá člověka v pozvané roli, nebo existujícímu
členovi roli ponechá, a přenese ho do firmy.

Seznam členů ukazuje členy firmy a nikoho jiného — nikdy všechny účty aplikace —
s akcemi, které má vlastník: udělat vlastníkem, udělat členem a odebrat z firmy,
každá odmítnutá pro posledního vlastníka.

```php
use NyonCode\WireModuleTenants\Actions\InviteMember;
use NyonCode\WireModuleTenants\Enums\MemberRole;

(new InviteMember)($company, 'ada@example.com', MemberRole::Member, auth()->user());
```

Akce jsou obyčejné invokovatelné třídy — `RegisterTenant`, `InviteMember`,
`AcceptInvitation`, `ChangeMemberRole`, `RemoveMember`, `DeleteTenant` — pro
aplikaci, která chce stejná pravidla za vlastní obrazovkou.

## Projekty uvnitř firmy

Modul týmy nespravuje. S nainstalovaným [modulem uživatelů](users.md) a jeho
modelem týmu vlastněným tenantem jsou projekty firmy týmy toho modulu, omezené na
firmu — viz [Týmy uvnitř firmy](teams-and-two-factor.md#tymy-uvnitr-firmy).

## Související

- [Tenancy](../panels/tenancy.md) — tenantová zóna, ve které tyto obrazovky žijí
- [Autorizace § Multi-tenancy](../start/authorization.md#multi-tenancy) — scope, izolace a práce ve frontě
- [Uživatelé](users.md) — účty, role a týmy
- [Moduly](index.md) — ostatní hotové oblasti
