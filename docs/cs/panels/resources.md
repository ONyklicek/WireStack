---
order: 20
summary: Jedna entita svázaná s povrchy, které vystavuje — kontrakty, které implementuje, jak se jmenuje, jak se registruje a na co registr odpoví, aniž by kterýkoli z nich postavil.
---


# Resources

Resource váže jednu entitu na povrchy, které vystavuje — její seznam, formulář
a read-only pohled — takže žijí v jedné deklaraci místo ručního drátování do
každé Livewire komponenty, která je zrovna zobrazuje.

## Jak to funguje

Tabulka, formulář a infolist jsou samostatné primitivy a zůstávají jimi:
resource nemění nic na tom, jak fungují. Co přidává, je **vlastník** nad nimi —
jedna třída, která odpovídá „tohle je entita Order, takhle se vypisuje, takhle se
edituje“ — a **registr**, který umí odpovědět „které resources existují“ a „který
vlastní `App\Models\Order`“, aniž by kterýkoli z těch povrchů postavil.

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
vypisování. Přebijte jen tu odpověď, která je špatně:

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

Perzistence zůstává formuláři: `Form` už vlastní životní cyklus ukládání a resource nad
non-Eloquent zdrojem zapisuje přes `Form::using()`.

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
| `ProvidesBreadcrumbs` | `breadcrumbs(): array` | `wire-core` |
| `ProvidesPages` | `static pages(): array` | `wire-core` |
| `ConfiguresRoutes` | `static routeMiddleware(): array`, `static routeDomain(): ?string`, `static routePrefix(): ?string` | `wire-core` |
| `GloballySearchable` | `static globallySearchableAttributes(): array`, `static toGlobalSearchResult(object): GlobalSearchResult` | `wire-core` |

## ResourceRegistry API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `register(string $resource): void` | `void` | Přidá třídu resource; vyhodí výjimku, když to resource není nebo když je klíč zabraný jinou třídou |
| `registerMany(mixed $resources): void` | `void` | Zaregistruje, co bylo v configu. Chybějící nebo poškozená položka se přeskočí, ne fatal — publikovaný config se zbloudilou hodnotou nesmí položit aplikaci při bootu |
| `all(): array` | `array<string, class-string>` | Všechny registrované resources, klíčované klíčem |
| `registeredClasses(): array` | `array<string, class-string>` | Slib `RegistrySource`, ze kterého čte menu, router i paleta. Táž odpověď jako `all()`, schválně jiná metoda, aby `all()` mohla nabírat další konzumenty, aniž by se tím slibem stala |
| `find(string $key): ?string` | `class-string\|null` | Resource s tímhle klíčem |
| `has(string $key): bool` | `bool` | Jestli je klíč registrovaný |
| `forModel(string $model): ?string` | `class-string\|null` | Resource vlastnící danou třídu modelu |

## Související

- [Stránky](pages.md) — komponenty, které tyhle povrchy vykreslují
- [Navigace](navigation.md) — jak resource dostat do menu
- [Routování](routing.md) — jak jeho stránkám dát URL
- [Moduly](modules.md) — deklarace resourců celé byznysové oblasti najednou
- [Globální vyhledávání](../core/global-search.md) — command palette nad každým zaregistrovaným resourcem
- [Relation managery](../table/relation-managers.md) — relací omezená tabulka, vlastník, který tenhle vzor zobecňuje
- [Konfigurace](../start/configuration.md) — kde se deklaruje `resources`
