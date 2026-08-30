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
| `ProvidesResourceTable` | `wire-panels` | `Table` |
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

- [Relation Managers](../table/relation-managers.md) — tabulka scopovaná na vztah, vlastník, který tenhle vzor zobecňuje
- [Tabulky](../table/overview.md) — seznamový povrch, který resource deklaruje
- [Konfigurace](../configuration.md) — kde se `resources` deklaruje
