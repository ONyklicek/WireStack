---
order: 50
summary: Jak z deklarovaných stránek vzniknou skutečné URL — makro, tvar URL, middleware pro každý resource, několik zón nad jednou sadou resourců a cesta přes config.
---

# Routování

Registr nevlastní žádný URL shell ani routu: routy zůstávají aplikaci, v její
vlastní skupině, s jejím prefixem a middlewarem. Co framework odstraňuje, je
opakování — čtyři řádky `Route::get()` na každý resource a vedle nich ručně psaná
mapa klíč→URL pro menu.

## Jak to funguje

Resource řekne, které stránky ho vykreslují — a stejně tak cokoli dalšího, co
aplikace zaregistrovala, včetně dashboardu: router čte tentýž katalog jako menu,
takže routovatelnost je věcí deklarace stránek, ne toho, jaký druh věci to je.

```php
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;

public static function pages(): array   // [tl! focus:start]
{
    return [
        'index' => ListOrders::class,
        'create' => CreateOrder::class,
        'view' => ViewOrder::class,
        'edit' => RoutePage::make(EditOrder::class)->permission('orders.update'),
    ];
}   // [tl! focus:end]
```

a aplikace je zaregistruje **uvnitř své vlastní skupiny**:

```php
// routes/web.php
Route::prefix('admin')
    ->middleware(['auth', 'verified'])
    ->domain(config('app.admin_domain'))
    ->group(function () {
        Route::wireResources();                        // [tl! focus]
        Route::wireResource(OrderResource::class);     // nebo po jednom
    });
```

Prefix, middleware i doména jsou vaše — jsou to obyčejné Laravelí routy
registrované ve skupině, ve které jste macro zavolali. Resource, který nedeklaruje
stránky, se přeskočí; tak zůstane interní nebo vnořený resource neroutovaný.
Jmenovat takový resource explicitně naopak vyhodí výjimku, protože to je chyba,
ne volba.

## Tvar URL

| Druh stránky | URL | Jméno routy |
| --- | --- | --- |
| `index` | `{prefix}` | `wire.{key}.index` |
| `create` | `{prefix}/create` | `wire.{key}.create` |
| `view` | `{prefix}/{record}` | `wire.{key}.view` |
| `edit` | `{prefix}/{record}/edit` | `wire.{key}.edit` |
| cokoli dalšího | `{prefix}/{druh}` | `wire.{key}.{druh}` |

`{prefix}` je registrovaný klíč, takže klíč v menu a URL se shodují, aniž by se
kterýkoli z nich opakoval. `{record}` je **klíč**, ne navázaný model: stránky si
záznam resolvují samy, což nechává soft-delete scope, tenant guard i
non-Eloquent zdroj rozhodnutím stránky, ne routeru.

## Oprávnění, middleware a domény

`RoutePage::permission()` dosedne na routu jako Laravelí `can:` middleware. Nic
tady autorizaci neimplementuje znovu — odpovídá na ni Gate, přesně jako
u akcí, sloupců a widgetů, takže `spatie/laravel-permission`
i `nyoncode/laravel-permission-extended` fungují beze změny. Odmítnutí se stane
v routeru, dřív než se stránka vykreslí nebo padne dotaz.

Na úrovni resource přidává `ConfiguresRoutes` tři věci, které patří
jednomu resource a ne celé skupině:

```php
public static function routeMiddleware(): array { return ['can:tenants.view']; }
public static function routeDomain(): ?string { return '{tenant}.example.com'; }
public static function routePrefix(): ?string { return 'billing/tenants'; }
```

Parametr domény se dostane do vašeho `TenantResolver`u jako každý jiný parametr
routy. Samotná tenancy zůstává, kde je — globální scope nad každým dotazem, ne
záležitost routování; viz [Autorizace](../start/authorization.md).

## Zóny

Víc mount pointů nad jednou sadou resources — `admin`, `business`, `production`.
Resource může být v jedné z nich, ve víc, nebo ve všech: zóna násobí, kde je
stránka dosažitelná, ne kolikrát je zaregistrovaná.

Zóna je **jméno** route skupiny a nic víc:

```php
Route::name('admin.')->prefix('admin')->middleware(['web','auth','can:admin'])
    ->group(fn () => Route::wireResources());                        // [tl! focus]

Route::name('business.')->prefix('business')->middleware(['web','auth','can:business'])
    ->group(fn () => Route::wireResources(only: ['orders']));        // [tl! focus]
```

```
admin.wire.orders.index      →  admin/orders
business.wire.orders.index   →  business/orders
```

Rozděluje je to volání `name()`. Vynech ho na druhé skupině a obě zóny
zaregistrují `wire.orders.index`, kde pozdější tiše vyhraje každý lookup — proto
je [cesta přes config](#registrace-z-configu-misto-route-souboru) níž bezpečnější
způsob, jak zóny deklarovat: tam je zóna klíčem pole a zapomenout se nedá.

Které resources zóna obsahuje, říká `only` / `except` a nic jiného — žádný druhý
seznam, který by se musel držet v souladu s routami.

**Odkazování uvnitř zóny.** Každá otázka na URL zní „kde je tenhle klíč *v téhle
zóně*“, takže zóna cestuje s ní:

```php
ResourceRoutes::urlFor('orders', zone: 'business');   // /business/orders
ResourceRoutes::urls(zone: 'business');               // jen to, co business routuje
app(Workspace::class)->navigation(zone: 'business');  // položky odkazující do business
```

Klíč, který zóna neroutuje, odpoví `null` a vykreslí se bez odkazu — přesně jako
neroutovaný resource. Když má menu obsahovat jen to, na co tahle zóna opravdu
dosáhne, řekni si o to:

```php
app(Workspace::class)->navigation(zone: 'business', linkedOnly: true);
```

Volitelné, ne pravidlo, protože důvody, proč položka nemá URL, jsou dva různé
a `Workspace` je nerozliší: jedna může být routovaná v *jiné* zóně, druhá nikde.
A shell s vlastním URL schématem má tady bez odkazu úplně všechno a stejně chce
všechny položky — je to volající, kdo ví, ve kterém případě je. Skupina, které
vypadnou všechny položky, zmizí celá místo prázdného nadpisu.

**Landing page zóny.** `/business` samo neroutuje nic, dokud si to něco
nenárokuje — a nárokuje se to jednou metodou: prázdný prefix nepřidá segment,
takže `index` té stránky sedne na vlastní cestu skupiny:

```php
final class BusinessOverview extends Dashboard implements ConfiguresRoutes, ProvidesPages
{
    public static function pages(): array { return ['index' => ShowBusinessOverview::class]; }

    public static function routePrefix(): ?string { return self::ROOT; }   // [tl! focus]
}
```

```
business.wire.business-overview.index   →  business
business.wire.orders.index              →  business/orders
```

Která zóna přistane kde, říká `only` / `except` — jako každá jiná otázka na
členství: dejte každé zóně vlastní dashboard a vypište ho tam. Dvě stránky, které si
nárokují kořen **jedné** skupiny, jsou odmítnuty — Laravel klíčuje routy podle
URI, takže by druhá tu první nahradila i se jménem routy a zůstala by položka
menu, která vypadá zaroutovaně a tiše nikam neodkazuje.

Zóna, která chce cíl a ne vlastní stránku, napíše vedle skupiny obyčejný
redirect:

```php
Route::redirect('business', 'business/orders');
```

**Odkud se zóna bere.** `Zone::current()` ji přečte z routy, která se právě
vykresluje, a je to volání **pro plný render stránky**:

```php
public ?string $zone = null;      // [tl! focus:start]

public function mount(): void
{
    $this->zone = Zone::current();
}                                 // [tl! focus:end]
```

`Route::currentRouteName()` během Livewire round tripu odpoví `livewire.update`,
takže komponenta, která se zeptá znovu uprostřed updatu, nedostane nic — a paleta,
která hledá při každém stisku klávesy, by odkazovala mimo svoji zónu a přitom
vypadala bezvadně. Přečtěte to jednou, uložte do public property a nechte to Livewire
přenášet. Command paleta to přesně tak dělá, takže paleta v zónovaném layoutu
nepotřebuje žádnou konfiguraci.

## Registrace z configu místo route souboru

Macro výše zůstává referenční cestou. Aplikace, která chce konvenci a nechce si
kvůli ní držet route soubor, předá tytéž argumenty skupiny jednou:

```php
// config/wire-panels.php
'routes' => [
    'enabled' => true,                    // [tl! focus]
    'prefix' => 'admin',
    'middleware' => ['web', 'auth'],
    'domain' => null,
    'only' => [],
    'except' => [],
],
```

Zóny jsou klíč `zones` a klíč pole je ta zóna:

```php
'routes' => [
    'enabled' => true,
    'middleware' => ['web', 'auth'],          // dědí každá zóna
    'zones' => [                              // [tl! focus:start]
        'admin' => [
            'prefix' => 'admin',
            'middleware' => ['web', 'auth', 'can:admin'],
        ],
        'business' => [
            'prefix' => 'business',
            'only' => ['orders', 'customers'],
        ],
    ],                                        // [tl! focus:end]
],
```

Každá zóna zdědí hodnoty mimo `zones` a přepíše to, co pojmenuje. **Klíč se stane
prefixem jména routy**, což je důvod dát tomuhle přednost před ručně psanými
skupinami, ne jen alternativa k nim: v route souboru je `->name('business.')`
řádek, který se dá vynechat, a vynechání znamená, že jedna zóna tiše převezme
odkazy druhé. Klíč pole vynechat nejde a opakovat se nemůže.

Bez klíče `zones` je to jedna nepojmenovaná skupina, což je to, co chce
jednozónová aplikace.

Ve výchozím stavu vypnuté, a to záměrně: providery balíčků bootují dřív než vaše
vlastní, takže tyhle routy se matchují **před** vším v `routes/web.php`. Aplikace
s catch-all routou pod stejným prefixem dnes vyhraje a přestala by — to je
rozhodnutí, které se dělá, ne default, který se zdědí.

Zapnout tohle *a zároveň* volat `Route::wireResources()` by zaregistrovalo každou
stránku dvakrát pod jedním jménem routy; je to odmítnuto, ne smířeno, se zprávou,
která pojmenuje obě místa, kde stačí smazat řádek.

## Jak na ně odkazovat

URL už nemusí nikdo psát ručně. Položka menu nese URL stránky svého klíče
a výsledek hledání nese URL svého záznamu:

```php
$item->getUrl();          // /admin/orders — doplní Workspace, null když neroutováno
$result->url;             // /admin/orders/7 — z klíče a klíče záznamu
```

Obojí přichází z `ResolvesPageUrls`, na které odpovídá `wire-panels` a na které
`wire-core` odpovídá `null`, když routing nevlastní žádný balíček. `null` je
plnohodnotná odpověď: položka menu bez `href` se vykreslí a resource, který
nedeklaruje stránky, je neodkazovaný záměrně. Položka nebo výsledek, který si URL
pojmenuje sám, vždy vyhraje — externí odkaz nebo aplikace s vlastním URL schématem
shellu.

Sáhnout po tom přímo je totéž volání:

```php
ResourceRoutes::urlFor('orders');                          // /admin/orders
ResourceRoutes::urlFor('orders', 'edit', ['record' => 7]); // /admin/orders/7/edit
ResourceRoutes::urls();                                    // ['orders' => '/admin/orders', …]
```

Full-page Livewire komponenta potřebuje layout a framework ho nedodává — nastavte
si `livewire.component_layout` na svůj vlastní.

## Routing API

```php
ResourceRoutes::all(array $only = [], array $except = []): array   // každý klíč, který deklaruje
ResourceRoutes::for(string $class): array                          // jeden, nebo vyhodí výjimku
ResourceRoutes::urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
ResourceRoutes::urls(string $page = 'index', ?string $zone = null): array

Zone::current(): ?string          // zóna právě vykreslované stránky — jen při plném renderu
Zone::of(?string $routeName): ?string
Zone::prefix(?string $zone): string
```

`urlFor()` odpoví `null` ve dvou případech: když klíč nic neroutuje, a když routa
potřebuje parametr, který tohle volání nedalo — třeba resource na doméně
`{tenant}`. Obojí se vykreslí jako „bez odkazu“, místo aby to shodilo menu.

Z `wire-core` na to sáhni přes `ResolvesPageUrls`, na které odpovídá `wire-panels`
a které odpoví `null`, když routing nevlastní žádný balíček. `RegistersPageRoutes`
je druhá půlka toho seamu: `wire-core` ho zavolá ve chvíli, kdy jsou registry plné,
což je jediný okamžik, kdy [routy z configu](#registrace-z-configu-misto-route-souboru)
můžou přečíst kompletní katalog.

## Související

- [Stránky](pages.md) — komponenty, na které tyhle routy vedou
- [Navigace](navigation.md) — odkud se bere URL položky menu
- [Autorizace](../start/authorization.md) — gates, na které se ptá `permission()` a `can:`
- [Konfigurace](../start/configuration.md) — celý blok `wire-panels.routes`
- [Admin shell](../admin/overview.md) — layout pro stránky, na které tyhle routy vedou
