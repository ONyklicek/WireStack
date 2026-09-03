---
order: 90
---

# Resources

Resource váže jednu entitu na povrchy, které vystavuje — její seznam, formulář
a read-only pohled — takže žijí v jedné deklaraci místo ručního drátování do
každé Livewire komponenty, která je zrovna zobrazuje.

## Jak to funguje

Tabulka, formulář a infolist jsou samostatné primitivy a zůstávají jimi:
resource nemění nic na tom, jak fungují. Co přidává, je **vlastník** nad nimi —
jedna třída, která odpovídá „tohle je entita Order, takhle se vypisuje, takhle se
edituje" — a **registr**, který umí odpovědět „které resources existují" a „který
vlastní `App\Models\Order`", aniž by kterýkoli z těch povrchů postavil.

To rozdělení je důvod, proč je resource několik malých kontraktů místo jednoho
velkého:

| Kontrakt | Odpovídá na |
| --- | --- |
| `DescribesResource` | co je entita zač: klíč, model, jednotné a množné označení |
| `ProvidesResourceTable` | jak se vypisuje |
| `ProvidesResourceForm` | jak se zakládá a edituje |
| `ProvidesResourceInfolist` | jak se jeden záznam ukáže read-only |

Resource implementuje ty, které má. Read-only audit log implementuje identitu
a tabulku a nic dalšího, a stránka, která potřebuje formulář, ho omylem nedostane
— typ to řekne.

`DescribesResource` je **statický** a povrchy jsou **instanční** metody, a ten
důvod je mechanický, ne stylový. Menu se ptá na popisek a registr směruje model
na jeho vlastníka dřív, než se cokoli instancovalo; metadata tedy nesmí instanci
vyžadovat. Povrchy naopak skládají builder, který vlastní a na hostitele už
napojil volající — přesně jak to dělá `RelationManager` i každá `WithTable`
komponenta — takže ty instanci dostanou a vrátí.

## Co veze který balíček

Resource se deklaruje napříč balíčky, které vlastní typy, jež jmenuje — aplikace
tak instaluje jen to, co její resources opravdu používají:

| Co potřebuješ | Bydlí v | Protože jmenuje |
| --- | --- | --- |
| `DescribesResource`, `DescribesRecords`, `ResourceRegistry` | `wire-core` | nic než skaláry |
| `ProvidesResourceForm` | `wire-forms` | `Form` |
| `ProvidesResourceInfolist` | `wire-core` (vedle Infolistů) | `Infolist` |
| `ProvidesResourceTable`, `ProvidesRelationManagers` | `wire-panels` | `Table`, `RelationManager` |
| `ListPage` a ostatní stránky | `wire-panels` | `Table`, `Form`, host traity |

Praktický důsledek: **resource s formulářem a bez seznamu potřebuje `wire-forms`
a nic víc.** Identita je z `wire-core`, který si `wire-forms` už tak vyžaduje,
takže deklarace nikdy nepřitáhne tabulkový balíček — včetně jeho assetů, migrací,
konfigurace a Livewire synthesizeru.

`wire-panels` sedí nad všemi komponentovými balíčky a nic na něm nezávisí. Ten
směr je pointa: resource skládá primitivy, takže balíček, který resources vlastní,
je ten, který smí jmenovat všechny ostatní — a žádný z nich nesmí jmenovat jeho.


## Základní použití

```php
use App\Models\Order;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Table;

final class OrderResource implements DescribesResource, ProvidesResourceTable // [tl! focus]
{
    use DescribesRecords; // [tl! focus]

    public static function modelClass(): ?string // [tl! focus:3]
    {
        return Order::class;
    }

    public function table(Table $table): Table // [tl! focus]
    {
        return $table->columns([
            TextColumn::make('number'),
            TextColumn::make('customer.name'),
        ]);
    }
}
```

`DescribesRecords` dodá zbylé tři odpovědi z třídy modelu, takže deklarace výš je
kompletní: klíč `orders`, popisek `Order`, množné `Orders`.

## Pojmenování

Klíč není kosmetika. Je to konfigurační rukojeť, introspekční jméno a segment
routy, který stránka použije, takže musí přežít změnu popisku i přesun
namespace — proto se odvozuje od **modelu**, ne od třídy resource ani od
popisku:

```php
App\Models\OrderLine  →  key 'order-lines'  ·  label 'Order Line'  ·  plural 'Order Lines'
App\Models\Person     →  key 'people'       ·  label 'Person'      ·  plural 'People'
```

Množné číslo dělá Laravelův inflector, takže nepravidelná slova jsou správně bez
vypisování. Přebij jen tu odpověď, která je špatně:

```php
public static function pluralLabel(): string
{
    return 'Line items';
}
```

Resource bez modelu — postavený nad `DataSource` místo Eloquentu — vrací
`null` z `modelClass()` a jména si odvodí z vlastního názvu třídy, s useknutým
koncovým `Resource`. Registruje se a vypisuje jako každý jiný; jen ho nejde najít
*podle modelu*.

## Registrace

Resources se deklarují v konfiguraci:

```php
// config/wire-core.php
'resources' => [
    App\Resources\OrderResource::class,
    App\Resources\CustomerResource::class,
],
```

Je to registr, ne panel: drží názvy tříd a odpovídá na dvě otázky o nich.
Nevlastní routing, URL shell ani navigační strom.

Přidat jeden za běhu — což je přesně to, co by dělal scanner s atributy —
znamená vytáhnout registr a zaregistrovat přímo:

```php
use NyonCode\WireCore\Core\Resources\ResourceRegistry;

app(ResourceRegistry::class)->register(OrderResource::class);
```

Zaregistrovat tutéž třídu dvakrát je no-op, protože to dělá jak slučování
konfigurace, tak provider nabootovaný dvakrát. Dvě *různé* třídy hlásící se
k jednomu klíči naopak vyhodí výjimku: ta druhá by tiše převzala routing té
první.

## Čtení registru

```php
$registry = app(ResourceRegistry::class);

$registry->all();                       // ['orders' => OrderResource::class, …]
$registry->find('orders');              // OrderResource::class | null
$registry->has('orders');               // bool
$registry->forModel(Order::class);      // OrderResource::class | null
```

Každá z těch odpovědí vzniká jen ze statického kontraktu, takže sestavení menu
z `all()` nikdy neskládá tabulku.

## Stránky

`ListPage` je Livewire komponenta, která vykreslí seznam jednoho resource.
Skládá `WithTable`, takže je to obyčejný table host — polling, řádkové partialy,
gesta, exporty i všechno ostatní přichází beze změny, protože žádná z těch věcí
o resource neví.

```php
use NyonCode\WirePanels\Resources\Pages\ListPage;

final class ListOrders extends ListPage
{
    protected static ?string $resource = OrderResource::class;
}
```

To je celá stránka. Nadpis se bere z množného označení resource — proto je ten
popisek na *statickém* kontraktu: stránka ho ukáže, aniž by cokoli skládala.
Přebij ho nastavením `$title`.

Resource není povinný. Stránka si může tabulku napsat sama a žádný resource
nepoužít, přesně jako každá `WithTable` komponenta:

```php
final class ListOrders extends ListPage
{
    public function table(Table $table): Table
    {
        return $table->model(Order::class)->columns([
            TextColumn::make('number'),
        ]);
    }
}
```

Obě cesty jsou plnohodnotné. Co ale stránka *napůl* deklarovaná udělá, je
výjimka: stránka bez resource i bez `table()`, nebo mířící na resource, který
seznam nedeklaruje, se ozve nahlas místo aby vykreslila prázdnou tabulku — ta by
se totiž četla jako „žádné záznamy", ne jako chyba.

Stránky nevlastní routing. Namountuj si ji, kam aplikace chce, jako každou
Livewire komponentu.

### Založení, editace a detail

Zbylé tři stránky navazují na seznam. Založení a editace sdílejí jeden formulář —
resource ho deklaruje jednou:

```php
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

final class CreateOrder extends CreatePage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}

final class EditOrder extends EditPage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}

final class ViewOrder extends ViewPage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}
```

Editace a detail ukazují jeden záznam a ten přichází jako **klíč**:

```blade
@livewire(EditOrder::class, ['record' => $order->getKey()])
```

Ne model, a je to záměr. Mount argumenty Livewire komponenty končí v jejím
snapshotu, takže hydratovaný model je tam jednak větší než klíč, jednak zastaralý
v okamžiku, kdy dorazí další request. Cestuje klíč; záznam se resolvuje per
request. Přebij `resolveRecord()`, když ho chceš hledat jinak — soft-delete
scope, tenant guard, non-Eloquent zdroj.

**Perzistence zůstává formuláři.** `Form` už vlastní validate → mutate → hooky →
persist → notify; stránka jen naváže model a zavolá `save()`. Resource nad
non-Eloquent zdrojem si deklaruje `Form::using()` ve vlastním `form()` a tyhle
stránky se nemění.

Stránky navazují formulář na stavovou cestu `data` a deklarují odpovídající
veřejnou property, protože navázání formuláře na hostitele je práce stránky —
stejné dělení, díky kterému `table()` resource nic neví o komponentě, která ho
vykresluje. Resource, který potřebuje jinou cestu, si ji nastaví ve svém
`form()`, který běží potom, a tedy vyhraje.

Stránka detailu neskládá žádnou host traitu: read-only znamená žádný stav
k navázání a nic k odeslání, takže `Infolist` je celý povrch.

## Rozšířený příklad

Resource objednávky se všemi třemi povrchy — seznam, který stránka vykreslí,
formulář sdílený zakládáním i editací, a read-only pohled:

```php
use App\Models\Order;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\MoneyColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Table;

final class OrderResource implements
    DescribesResource,          // [tl! focus:4]
    ProvidesResourceTable,
    ProvidesResourceForm,
    ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Order::class;
    }

    public function table(Table $table): Table   // [tl! focus:8]
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('customer.name')->label('Customer'),
                MoneyColumn::make('total', 'Kč'),
            ])
            ->defaultSort('number', 'desc');
    }

    public function form(Form $form): Form        // [tl! focus:7]
    {
        return $form->schema([
            TextInput::make('number')->required(),
            Select::make('customer_id')
                ->relationship('customer', 'name')
                ->required(),
        ]);
    }

    public function infolist(Infolist $infolist): Infolist  // [tl! focus:6]
    {
        return $infolist->schema([
            TextEntry::make('number'),
            TextEntry::make('customer.name')->label('Customer'),
            TextEntry::make('total')->money('Kč'),
        ]);
    }
}
```

Jeden `form()` slouží zakládání i editaci záměrně — rozejít se formulář pro
založení a pro editaci je přesně ta chyba, které tenhle tvar předchází. Kde se
opravdu lišit musí, předá stránka formulář, který resource teprve tvaruje, místo
aby resource deklaroval dva.

Perzistence zůstává formuláři: `Form` už vlastní save lifecycle a resource nad
non-Eloquent zdrojem zapisuje přes `Form::using()`.

## Navigace a Workspace

Resource, který se má objevit v menu, implementuje `ProvidesNavigation`:

```php
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

public static function navigation(): NavigationItem   // [tl! focus:6]
{
    return NavigationItem::make('Objednávky')
        ->icon('outline:shopping-cart')
        ->group('sales')
        ->sort(10)
        ->badge(fn () => Order::whereNull('shipped_at')->count(), 'danger');
}
```

Statické, jako identita, a ze stejného důvodu: menu se staví ze všech
registrovaných resources naráz a instancovat každý jen kvůli tomu, jak se
jmenuje, by znamenalo složit tabulku a formulář na každou položku. Resource,
který tohle neimplementuje, je pořád registrovaný a routovatelný — jen se
neobjeví, což je přesně to, co chce interní nebo vnořený resource.

`NavigationItem` stojí na kanonických concernech `HasLabel` / `HasIcon` /
`HasVisibility`, ne na vlastních properties, takže mluví stejným slovníkem jako
každá jiná komponenta. Přidává jen to, co potřebuje *menu*: `group()`, `sort()`
a `badge()`. Closure v badge se vyhodnocuje při každém čtení, nikdy se necachuje
— počet neodeslaných objednávek je špatně v okamžiku, kdy se uloží.

Položka, která si sama nepojmenuje label, se jmenuje po svém resource:
`NavigationItem::make()` vedle `->icon()` a `->group()` je běžný tvar a menu
ukáže `pluralLabel()` — „Objednávky". Resource, který chce v menu jiný název než
svůj plurál, ho předá a ten vyhraje.

`group()` bere **klíč**, ne nadpis. Skupinu nevlastní žádný resource — sdílí ji
jich několik — takže co nadpis říká, jakou nese ikonu, kde sedí mezi ostatními
skupinami a jestli je vůbec vidět, patří `NavigationGroup`, deklarované tam, kde
si aplikace tuhle svou část skládá:

```php
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;

public function boot(): void
{
    $this->app->make(NavigationGroups::class)->registerMany([   // [tl! focus:start]
        NavigationGroup::make('sales')
            ->label(__('nav.sales'))
            ->icon('outline:banknotes')
            ->sort(10),
        NavigationGroup::make('admin')
            ->sort(90)
            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
    ]);                                                          // [tl! focus:end]
}
```

**Skupina, kterou nikdo nedeklaruje, funguje dál.** `Workspace` si z klíče udělá
implicitní, takže `->group('sales')` žádnou registraci nepotřebuje a nadpis
spadne na `Str::headline()` klíče. Registrace říká to, co holý klíč neumí:

| Metoda | K čemu |
| --- | --- |
| `NavigationGroup::make(string $key)` | Klíč, na který položky míří přes `group()` |
| `label(string\|Closure\|null)` | Nadpis. Oddělený od klíče schválně: přeložený nadpis se nesmí stát klíčem pole |
| `icon(string\|Icon\|Closure\|null)` | Ikona vedle nadpisu |
| `sort(int)` | Pořadí mezi skupinami; shody drží pořadí prvního výskytu |
| `visible(bool\|Closure)` / `hidden(bool\|Closure)` | Zobrazí nebo skryje **celou** skupinu — jedna podmínka místo téže podmínky na každém resource v ní |
| `getItems(): array<string, NavigationItem>` | Položky pod ní, klíčované klíčem resource |

Registrace téhož klíče podruhé přepíše, což je způsob, jak aplikace upraví
skupinu dodanou balíčkem, aniž by ten balíček editovala.

`Workspace` výsledek uspořádá:

```php
use NyonCode\WireCore\Core\Resources\Workspace;

$nav = app(Workspace::class)->navigation();
// ['sales' => NavigationGroup, '' => NavigationGroup]   bez skupiny je klíč ''
```

Skupiny se vrací v pořadí `sort()` a shodné drží pořadí, v jakém se registrovala
jejich první položka; uvnitř skupiny platí totéž pro položky. Skryté položky
vypadnou a skrytá skupina si své položky vezme s sebou.

Položky zůstávají klíčované **klíčem resource**, přes seskupení i přes řazení.
Ten klíč je druhá polovina řádku v menu: `NavigationItem` záměrně nedrží URL —
registr, který by držel URL, by byl panel — takže aplikace si klíč namapuje na
to, kam daný resource routuje:

```blade
@foreach($nav as $group)
    @if($group->hasVisibleLabel())
        <p>{!! icon($group->getIcon()) !!} {{ $group->getLabel() }}</p>
    @endif

    @foreach($group->getItems() as $key => $item)
        <a href="{{ route('admin.'.$key) }}" wire:navigate>   {{-- [tl! focus] --}}
            {!! icon($item->getIcon()) !!}
            {{ $item->getLabel() }}
            <x-wire::badge :color="$item->getBadgeColor() ?? 'gray'">{{ $item->getBadge() }}</x-wire::badge>
        </a>
    @endforeach
@endforeach
```

`Workspace::items()` odpovídá na tutéž otázku bez nadpisů: každá viditelná
položka, plochý seznam v pořadí `sort()`, klíčovaný klíčem resource — to, co
ukáže menu, které skupiny nekreslí. Položky ze skryté skupiny v něm taky nejsou.

`Workspace` neví, co je resource, a to je záměr. Jeho položky přicházejí
z libovolného počtu zdrojů `NavigationSource` — jedním je `ResourceRegistry`,
druhým `Widgets\DashboardRegistry` — takže menu míchá resources, dashboardy
a cokoli, co aplikace zaregistruje později, aniž by se o nich `Workspace`
dozvěděl. Dva zdroje hlásící se k jednomu klíči jsou odmítnuty, ne smířeny:
jedna položka by jinak zabrala místo druhé a menu, kterému tiše zmizel řádek, se
pozná až v den, kdy ten řádek byl potřeba.

Fallback labelu výše je resourcový, protože `pluralLabel()` je slovo resource.
Cokoli jiného v menu si položku pojmenuje samo.

Stejně jako registr nevlastní `Workspace` routing ani layout — menu vykresluje
aplikace.

## Routování

Registr nedrží žádný URL shell ani routu (ADR 0020 §5) a to se nemění: routy
zůstávají aplikaci. Co framework odebírá, je opakování — čtyři `Route::get()`
řádky na resource a vedle nich ručně psaná mapa klíč→URL pro menu.

Resource řekne, které stránky ho vykreslují:

```php
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourcePages;
use NyonCode\WirePanels\Routing\RoutePage;

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

Prefix, middleware i doména jsou tvoje — jsou to obyčejné Laravelí routy
registrované ve skupině, ve které jsi macro zavolal. Resource, který nedeklaruje
stránky, se přeskočí; tak zůstane interní nebo vnořený resource neroutovaný.
Jmenovat takový resource explicitně naopak vyhodí výjimku, protože to je chyba,
ne volba.

### Tvar URL

| Druh stránky | URL | Jméno routy |
| --- | --- | --- |
| `index` | `{prefix}` | `wire.{key}.index` |
| `create` | `{prefix}/create` | `wire.{key}.create` |
| `view` | `{prefix}/{record}` | `wire.{key}.view` |
| `edit` | `{prefix}/{record}/edit` | `wire.{key}.edit` |
| cokoli dalšího | `{prefix}/{druh}` | `wire.{key}.{druh}` |

`{prefix}` je klíč resource, takže klíč v menu a URL se shodují, aniž by se
kterýkoli z nich opakoval. `{record}` je **klíč**, ne navázaný model: stránky si
záznam resolvují samy, což nechává soft-delete scope, tenant guard i
non-Eloquent zdroj rozhodnutím stránky, ne routeru.

### Oprávnění, middleware a domény

`RoutePage::permission()` dosedne na routu jako Laravelí `can:` middleware. Nic
tady autorizaci neimplementuje znovu — odpovídá na ni Gate, přesně jako
u akcí, sloupců a widgetů, takže `spatie/laravel-permission`
i `nyoncode/laravel-permission-extended` fungují beze změny. Odmítnutí se stane
v routeru, dřív než se stránka vykreslí nebo padne dotaz.

Na úrovni resource přidává `ConfiguresResourceRoutes` tři věci, které patří
jednomu resource a ne celé skupině:

```php
public static function routeMiddleware(): array { return ['can:tenants.view']; }
public static function routeDomain(): ?string { return '{tenant}.example.com'; }
public static function routePrefix(): ?string { return 'billing/tenants'; }
```

Parametr domény se dostane do tvého `TenantResolver`u jako každý jiný parametr
routy. Samotná tenancy zůstává, kde je — globální scope nad každým dotazem, ne
záležitost routování; viz [Autorizace](../authorization.md).

### Jak na ně odkazovat

`ResourceRoutes::urlFor()` udělá z klíče v menu odkaz a vrátí `null` pro
resource, který routovaný není:

```php
ResourceRoutes::urlFor('orders');                          // /admin/orders
ResourceRoutes::urlFor('orders', 'edit', ['record' => 7]); // /admin/orders/7/edit
ResourceRoutes::urls();                                    // ['orders' => '/admin/orders', …]
```

Full-page Livewire komponenta potřebuje layout a framework ho nedodává — nastav
si `livewire.component_layout` na svůj vlastní.


## Relation managery

Resource může pojmenovat tabulky scopované na vztah, které patří vedle jeho
záznamu:

```php
use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;

public function relationManagers(): array   // [tl! focus:3]
{
    return [OrderItemsRelationManager::class];
}
```

`EditPage` a `ViewPage` je pak vloží pod formulář nebo infolist, namountované na
záznam. Na [`RelationManager`](../table/relation-managers.md) se nic nemění —
namountovat ho přímo funguje přesně jako dřív; tohle jen ruší nutnost opakovat to
drátování na každé stránce. Resource, který žádné nedeklaruje, žádné nevykreslí,
a to je normální stav, ne chyba.

## Introspekce

`describe-resource` hlásí, co resources aplikace deklarují — identitu, které
povrchy mají a navigační položku:

```text
describe-resource                  # všechny registrované resources
describe-resource orders           # jeden, podle klíče
describe-resource App\Resources\OrderResource   # nebo podle třídy
```

Povrchy se hlásí jako *deklarované / nedeklarované*, ne svým obsahem: složit je
by stálo přesně to, čemu se statická půlka vyhýbá, a `describe-table`
a `describe-form` na to už odpovídají za stránky, které je vykreslují.

## DescribesResource API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `static key(): string` | `string` | Stabilní identifikátor, unikátní v rámci registru |
| `static modelClass(): ?string` | `class-string\|null` | Vlastněný Eloquent model, nebo `null` u non-Eloquent zdroje |
| `static label(): string` | `string` | Jednotné označení |
| `static pluralLabel(): string` | `string` | Množné označení |

## API povrchových kontraktů

| Kontrakt | Metoda | Veze balíček |
| --- | --- | --- |
| `ProvidesResourceTable` | `table(Table $table): Table` | `wire-panels` |
| `ProvidesResourceForm` | `form(Form $form): Form` | `wire-forms` |
| `ProvidesResourceInfolist` | `infolist(Infolist $infolist): Infolist` | `wire-core` |
| `ProvidesRelationManagers` | `relationManagers(): array` | `wire-panels` |
| `ProvidesNavigation` | `static navigation(): NavigationItem` | `wire-core` |
| `GloballySearchable` | `static globallySearchableAttributes(): array`, `static toGlobalSearchResult(object): GlobalSearchResult` | `wire-core` |

## ResourceRegistry API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `register(string $resource): void` | `void` | Přidá třídu resource; vyhodí výjimku, když to resource není nebo když je klíč zabraný jinou třídou |
| `all(): array` | `array<string, class-string>` | Všechny registrované resources, klíčované klíčem |
| `find(string $key): ?string` | `class-string\|null` | Resource s tímhle klíčem |
| `has(string $key): bool` | `bool` | Jestli je klíč registrovaný |
| `forModel(string $model): ?string` | `class-string\|null` | Resource vlastnící danou třídu modelu |

## Související

- [Globální hledání](global-search.md) — příkazová paleta nad všemi registrovanými resources
- [Relation Managers](../table/relation-managers.md) — tabulka scopovaná na vztah, vlastník, který tenhle vzor zobecňuje
- [Tabulky](../table/overview.md) — seznamový povrch, který resource deklaruje
- [Konfigurace](../configuration.md) — kde se `resources` deklaruje
