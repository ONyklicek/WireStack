---
order: 55
summary: Tenant v URL — nalezený podle slugu, ověřený proti přihlášenému člověku, nastavený jako aktuální a přenesený do každého odkazu, který framework staví.
---

# Tenancy

Tenant je firma, kterou aplikace obsluhuje, a tenantová zóna je skupina stránek,
která pod ní žije: `/app/acme/orders`, nebo `acme.example.com/orders`. Tahle
stránka je polovina o routování — jak se tenant dostane z URL do requestu. Které
řádky tenant vidí, je polovina o datech, a ta je v
[Autorizaci § Multi-tenancy](../start/authorization.md#multi-tenancy).

## Jak to funguje

Tenant je parametr routy jménem `tenant`, v prefixu nebo před doménou, a
middleware `wire.tenant` z něj udělá aktuálního tenanta. Na každém requestu
uvnitř zóny udělá čtyři věci, v tomto pořadí:

1. **Najde** tenanta podle route key modelu — `getRouteKeyName()`, slug, pokud to
   model tak říká — bez globálních scopů modelu.
2. **Ověří** přihlášeného člověka: `HasTenants::canAccessTenant($tenant)`. Cizí
   člověk, neznámý slug i host dostanou stejné **404** — 403 by tomu, kdo napsal
   `/app/globex/`, prozradilo, že globex existuje.
3. **Vstoupí** do něj (`CurrentTenant`), takže každý model s `BelongsToTenant` je
   na něj scopovaný bez čehokoli dalšího.
4. **Přenese** ho: `URL::defaults(['tenant' => …])`, takže každé URL postavené od
   té chvíle — `urlFor()`, `OrderResource::url()`, menu, výsledky hledání,
   drobečky, přesměrování po uložení, vaše vlastní `route()` — míří do téhož
   tenanta. Parametr se pak z routy odebere, takže žádný `mount()` stránky
   nedostane parametr, o který si neřekl.

**Livewire round trip ho udrží.** Update request jde na `livewire/update`, kde
žádný `{tenant}` není; middleware je zaregistrovaný jako Livewire *persistent*
middleware, takže Livewire sestaví původní request stránky a spustí ho znovu.
Druhé vykreslení je scopované a prolinkované stejně jako první.

**Pasti.** Zóna round trip přežije stejně, na každé stránce zaregistrované přes
`Route::wireResources()`; komponenta na vaší vlastní routě si zónu přečtenou
v mountu nechá a předá ji ([Routování](routing.md#jak-na-ne-odkazovat)).
A uživatelský model, který
neimplementuje `HasTenants`, se při prvním requestu odmítne výjimkou — nikdy se
nečte jako „smí do každého tenanta“.

## Tenantová zóna

V souboru s routami je tenant součástí skupiny:

```php
Route::name('app.')
    ->prefix('app/{tenant}')                       // nebo ->domain('{tenant}.example.com')
    ->middleware(['web', 'auth', 'wire.tenant'])   // [tl! focus]
    ->group(fn () => Route::wireResources());
```

Z configu udělá obojí — parametr i middleware — jeden klíč u zóny:

```php
// config/wire-panels.php
'routes' => [
    'enabled' => true,
    'middleware' => ['web', 'auth'],
    'zones' => [                                                                // [tl! focus:start]
        'app' => ['prefix' => 'app', 'tenant' => 'path'],                        // app/{tenant}/…
        'portal' => ['domain' => 'example.com', 'tenant' => 'domain'],           // {tenant}.example.com
    ],                                                                          // [tl! focus:end]
],
```

Cesta nepotřebuje nic od DNS a dovolí mít dva tenanty otevřené ve dvou
záložkách; doména potřebuje wildcard DNS záznam a certifikát a doménu session
cookie, která pokryje subdomény (`SESSION_DOMAIN=.example.com`). Hodnota jiná
než `path` nebo `domain`, nebo `domain` u zóny bez `domain`, se odmítne při
registraci rout.

## Model tenanta

```php
// config/wire-core.php
'tenancy' => [
    'enabled' => true,
    'model' => App\Models\Company::class,   // [tl! focus]
    'members_table' => 'tenant_user',
],
```

```php
class Company extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';   // /app/acme/… místo /app/12/…
    }
}
```

## Kdo kam patří

Odpovídá uživatelský model, přes `HasTenants`:

```php
use NyonCode\WirePanels\Concerns\InteractsWithTenants;
use NyonCode\WirePanels\Contracts\HasTenants;

class User extends Authenticatable implements HasTenants
{
    use InteractsWithTenants;   // [tl! focus]
}
```

`InteractsWithTenants` čte členství z vazby many-to-many na model tenanta přes
`members_table`, se jmény sloupců pivotu podle konvence Laravelu — `user_id`
a pro `Company` `company_id`. Členství jiného tvaru přepíše `tenants()`, nebo
implementuje tři metody samo:

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `getTenants(): iterable` | `iterable<int, Model>` | Tenanti, do kterých člověk patří — co nabídne přepínač |
| `canAccessTenant(Model $tenant): bool` | `bool` | Jestli v tomhle smí pracovat; ptá se na každém requestu uvnitř |
| `getDefaultTenant(): ?Model` | `Model\|null` | Kam ho pošle vlastní adresa zóny |

## Související

- [Autorizace § Multi-tenancy](../start/authorization.md#multi-tenancy) — scope, fail-safe, `runAs()` pro joby a příkazy
- [Routování](routing.md) — zóny, jména rout a `url()`
- [Navigace](navigation.md) — menu, jehož odkazy teď nesou tenanta
