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

## Základní použití

```php
use App\Models\Order;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Resources\Concerns\DescribesRecords;
use NyonCode\WireTable\Resources\Contracts\DescribesResource;
use NyonCode\WireTable\Resources\Contracts\ProvidesResourceTable;
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
// config/wire-table.php
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
use NyonCode\WireTable\Managers\ResourceRegistry;

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
use NyonCode\WireTable\Resources\Concerns\DescribesRecords;
use NyonCode\WireTable\Resources\Contracts\DescribesResource;
use NyonCode\WireTable\Resources\Contracts\ProvidesResourceForm;
use NyonCode\WireTable\Resources\Contracts\ProvidesResourceInfolist;
use NyonCode\WireTable\Resources\Contracts\ProvidesResourceTable;
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

## DescribesResource API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `static key(): string` | `string` | Stabilní identifikátor, unikátní v rámci registru |
| `static modelClass(): ?string` | `class-string\|null` | Vlastněný Eloquent model, nebo `null` u non-Eloquent zdroje |
| `static label(): string` | `string` | Jednotné označení |
| `static pluralLabel(): string` | `string` | Množné označení |

## API povrchových kontraktů

| Kontrakt | Metoda |
| --- | --- |
| `ProvidesResourceTable` | `table(Table $table): Table` |
| `ProvidesResourceForm` | `form(Form $form): Form` |
| `ProvidesResourceInfolist` | `infolist(Infolist $infolist): Infolist` |

## ResourceRegistry API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `register(string $resource): void` | `void` | Přidá třídu resource; vyhodí výjimku, když to resource není nebo když je klíč zabraný jinou třídou |
| `all(): array` | `array<string, class-string>` | Všechny registrované resources, klíčované klíčem |
| `find(string $key): ?string` | `class-string\|null` | Resource s tímhle klíčem |
| `has(string $key): bool` | `bool` | Jestli je klíč registrovaný |
| `forModel(string $model): ?string` | `class-string\|null` | Resource vlastnící danou třídu modelu |

## Související

- [Relation Managers](relation-managers.md) — tabulka scopovaná na vztah, vlastník, který tenhle vzor zobecňuje
- [Tabulky](overview.md) — seznamový povrch, který resource deklaruje
- [Konfigurace](../configuration.md) — kde se `resources` deklaruje
