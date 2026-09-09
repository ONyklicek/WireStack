---
summary: Kolekce řádků editovaných na místě — přidávaných, duplikovaných, přetahovaných, skládaných a ukládaných přes relaci.
---

# Repeater

Opakovaná skupina polí: položky objednávky, seznam kontaktů, otevírací doba.
Jedno schéma se vykreslí jednou za řádek a řádky se váží na pole — nebo se
s `relationship()` zapisují rovnou do `hasMany` / `belongsToMany` jako podřízené
záznamy.

```php
use NyonCode\WireForms\Components\Repeater;
```

## Jak to funguje

**Každý řádek je totéž schéma, naklonované a znovu navázané.**
`getItemSchema($index)` naklonuje šablonové komponenty a přepíše každé z nich
stavovou cestu na `<path>.<index>.<field>`, takže `TextInput::make('email')`
v řádku 2 váže `contacts.2.email`. Reaktivita to následuje:
`afterStateUpdated()`, `$get`/`$set`, `visibleWhen()` i živá validace se řeší
proti stavu *toho* řádku, takže přepnutí selectu v řádku 2 se řádku 1 nedotkne.

**Přidání, odebrání, duplikace a přesun jsou volání Livewire, ne stav
v prohlížeči.** `addRepeaterItem`, `removeRepeaterItem`, `cloneRepeaterItem`,
`moveRepeaterItem` a `reorderRepeaterItems` žijí v `InteractsWithRepeaters`,
jednom vlastníkovi pro každého hostitele, který vykresluje formulář — pro
samostatnou komponentu i pro modal akce v tabulce. Každé je round trip a
autoritou nad obsahem seznamu je serverový re-render.

**Tažení se před dotazem vrátí zpět.** SortableJS nechá DOM v pořadí, do kterého
jste pustili; controller uzel vrátí tam, kde byl, a zavolá
`reorderRepeaterItems`. Karta nenese `wire:key`, kdežto blok Builderu je klíčovaný
indexem — takže tentýž puštěný DOM by v jednom rozvržení dokonvergoval a ve
druhém se překlopil zpět. Nechat řádek umístit server ten rozpor odstraní.
Viditelný výsledek je stejný; mechanismus je jeden zdroj pravdy místo dvou.

**Pořadí se neuloží, dokud si o to neřeknete.** Bez
[`orderColumn()`](#ulozeni-poradi) tažení přeuspořádá navázané pole a nic víc:
`hasMany` se vrátí v tom pořadí, v jakém ho vydá databáze, takže tažení přežije
do dalšího načtení a dál ne.

**Duplikace odstraní klíč řádku.** Řádek relace nese primární klíč potomka a save
handler podle něj páruje — dva řádky se stejným klíčem by oba dělaly
`fill()->save()` nad týmž záznamem, druhý by přepsal první a jeden ze dvou by po
načtení zmizel. `cloneable()` proto z kopie odstraní
[`itemKeyName()`](#duplikace-radku) (výchozí `id`), aby se uložila jako nový
potomek.

**Politika rozbalení rozhoduje o tom, jak se řádek vykreslí *poprvé*.**
Nevynucuje se znovu při každém re-renderu: s `expandLast()` přidání řádku otevře
ten nový a řádek, na který se právě díváte, nechá být. Cokoli jiného by
zaklaplo řádek pod rukama někomu uprostřed editace.

**Vlastní klíč repeateru s relací se nikdy nezapisuje jako sloupec.**
`isDehydrated()` vrací false, když je nastaveno `relationship()` — klíč
pojmenovává relaci, ne sloupec, a jeho zápis by skončil fatální chybou. Řádky
zapisuje po rodičovském záznamu `RelationshipSaveHandler`.

**Past.** Šablony dlouho posílaly značkování `x-sortable` proti direktivě, kterou
v tomto repozitáři nikdo neregistroval: úchyt se vykreslil, kurzor hlásil `grab`
a tažení nedělalo vůbec nic. Všechny testy se k reorder endpointu dostávaly přes
`->call(...)` a jediná kontrola v prohlížeči ověřovala, že úchyt *existuje*.
Chování tažení nyní pokrývá `workbench/scripts/verify-repeater-reorder.mjs`,
který provede skutečné gesto ukazatelem a ověří stav po round tripu.

## Základní použití

```php
Repeater::make('contacts')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
```

## Režim relace

`relationship()` naváže řádky na související záznamy místo na JSON sloupec.
Vlastněná relace (`hasMany`, `morphMany`) své řádky vytváří, aktualizuje a maže;
`belongsToMany` synchronizuje pivot, přičemž každé pole kromě klíče souvisejícího
modelu se považuje za data pivotu.

```php
Repeater::make('contacts')
    ->relationship('contacts')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
    ->mutateRelationshipDataBeforeSaveUsing(fn (array $row) => [
        ...$row,
        'source' => 'admin',   // orazítkuje každý řádek cestou do databáze
    ])
```

Naplnění formuláře zůstává na vás: řádky předejte v tom pořadí, v jakém je chcete.

## Uložení pořadí

`orderColumn()` zapíše pozici každého řádku (od nuly) do sloupce souvisejícího
modelu — nebo, u `belongsToMany`, do sloupce pivotu. Přečtení zpět je vaše
polovina a je to ta polovina, na kterou se zapomíná:

```php
Repeater::make('lines')
    ->relationship('lines')
    ->reorderable()
    ->orderColumn('sort_order')   // zapisuje se při uložení
    ->schema([TextInput::make('description')])
```

```php
// …a seřazené na vstupu, jinak se řádky vrátí v pořadí databáze a sloupec
// vypadá rozbitě, přestože se zapisuje správně.
public function lines(): HasMany
{
    return $this->hasMany(Line::class)->orderBy('sort_order');
}
```

## Přeuspořádání

`reorderable()` dá každému řádku úchyt pro tažení **a** dvojici tlačítek pro
přesun. Tlačítka nejsou ozdoba: úchyt pro tažení se bez ukazatele ovládat nedá,
takže bez nich je `reorderable()` pro každého, kdo pracuje z klávesnice,
nepoužitelné. Na koncích seznamu jsou zakázaná.

```php
Repeater::make('contacts')
    ->reorderable()
    ->schema([TextInput::make('name')])
```

## Duplikace řádku

`cloneable()` přidá ke každému řádku tlačítko duplikace; kopie přistane přímo pod
originálem. Podléhá stejným přepínačům jako přidávání — `addable(false)`,
zakázaný repeater i plný `maxItems()` ho odeberou, takže se jím nedá limit obejít.

```php
Repeater::make('contacts')
    ->relationship('contacts')
    ->cloneable()
    ->itemKeyName('uuid')   // odstraněno z kopie; výchozí 'id'
    ->schema([TextInput::make('name')])
```

## Které řádky začínají otevřené

`collapsible()` umožní řádek složit. To, které řádky začínají složené, je
politika — a booleanem se nedají zapsat dva tvary, které lidé opravdu chtějí,
takže jsou čtyři:

```php
Repeater::make('contacts')->collapsible()->expandAll();    // výchozí
Repeater::make('contacts')->collapsible()->expandFirst();  // otevřený jen první
Repeater::make('contacts')->collapsible()->expandLast();   // otevřený jen nejnovější
Repeater::make('contacts')->collapsed();                   // každý řádek složený
```

`expandFirst()`, `expandLast()` a `collapseAll()` implikují `collapsible()` —
seznam, který řádky skládá a nenabízí způsob, jak je otevřít, je past, ne funkce.
Poslední volání vyhrává v obou směrech, takže `->expandFirst()->collapsed()`
složí všechno. Od dvou řádků výš se vedle popisku objeví přepínač **Collapse all
/ Expand all**.

## Pojmenované řádky

Každý řádek je nadepsaný svým číslem. `itemLabel()` vedle něj dá jméno —
statický řetězec, nebo closure nad stavem řádku a jeho indexem. Spárujte ho
s polem `live()` a jméno bude sledovat, co se píše.

```php
Repeater::make('contacts')
    ->collapsible()
    ->itemLabel(fn (array $state, int $index) => $state['name'] ?? "Contact #{$index}")
    ->schema([TextInput::make('name')->live()])
```

V [tabulkovém rozvržení](#tabulkove-rozvrzeni) dostane jméno vlastní sloupec
nadepsaný jednou — a jen tehdy, když bylo `itemLabel()` vůbec nastaveno, aby
closure, která se pro jeden řádek nevyhodnotí, nezpůsobila mizení a objevování
záhlaví.

## Limity a prázdnota

```php
Repeater::make('contacts')
    ->minItems(1)                     // na spodní hranici zmizí tlačítko odebrání
    ->maxItems(10)                    // na horní hranici zmizí tlačítko přidání
    ->addButtonLabel('Přidat kontakt')
    ->emptyLabel('Zatím žádné kontakty')   // zobrazí se místo řádků, když žádné nejsou
```

## Tabulkové rozvržení

Krátké, jednotné řádky — položky faktury, dvojice klíč/hodnota — se čtou lépe
jako tabulka než jako karta za každou. `table()` rozloží řádky pod jedno záhlaví:
stejné stavové cesty, stejné napojení přidání/odebrání/duplikace/přeuspořádání,
liší se jen uspořádání.

```php
Repeater::make('lines')
    ->table()
    ->reorderable()
    ->cloneable()
    ->schema([
        TextInput::make('description')->label('Co'),
        TextInput::make('amount')->label('Za kolik'),
    ])
```

Z každého pole schématu se stane sloupec nadepsaný vlastním popiskem a popisek
u buňky je skrytý, aby se neopakoval na každém řádku. Skládání po položkách se
na řádek nevztahuje, takže `collapsible()` se v tomto rozvržení ignoruje.

## Rozšířený příklad

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Component;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditInvoice extends Component
{
    use WithForms;

    public Invoice $invoice;

    public array $data = [];

    public function mount(): void
    {
        // Seřazeno na vstupu — orderColumn() pozici zapisuje, zpět ji
        // nečte.
        $this->form->fill([
            'lines' => $this->invoice->lines()->orderBy('sort_order')->get()->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->invoice)
            ->statePath('data')
            ->schema([
                Repeater::make('lines')            // [tl! focus:start]
                    ->relationship('lines')
                    ->table()
                    ->reorderable()
                    ->cloneable()
                    ->orderColumn('sort_order')
                    ->itemLabel(fn (array $state) => $state['description'] ?? null)
                    ->minItems(1)
                    ->maxItems(50)
                    ->emptyLabel('Tato faktura zatím nemá položky')
                    ->schema([
                        TextInput::make('description')->label('Popis')->required(),
                        TextInput::make('quantity')->label('Ks')->numeric(),
                        TextInput::make('amount')->label('Částka')->numeric(),
                    ]), // [tl! focus:end]
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render(): string
    {
        return '<form wire:submit="save">{{ $this->form }}<button>Uložit</button></form>';
    }
}
```

## Repeater API

Plocha opakované kolekce. Slovník skládání (`collapsible()`, `collapsed()`) je
sdílený se `Section` a je zdokumentovaný v [Section](../../core/schema/layout/section.md);
zbytek sdílené plochy polí je v [Pole formuláře](index.md).

```php
->relationship(?string $name)                          // hasMany / morphMany / belongsToMany — řádky ukládané jako podřízené záznamy
->schema(array $components)                            // schéma opakované jednou za řádek
->addable(bool $condition = true)                      // výchozí true
->deletable(bool $condition = true)                    // výchozí true
->reorderable(bool $condition = true)                  // úchyt tažení + klávesová tlačítka přesunu — výchozí false
->cloneable(bool $condition = true)                    // tlačítko duplikace u řádku — výchozí false
->table(bool $condition = true)                        // řádky pod jedním záhlavím místo karty za každou
->orderColumn(?string $column = 'sort_order')          // zapíše pozici každého řádku při uložení; null = vypnuto (výchozí)
->itemKeyName(string $name)                            // klíč odstraněný z duplikátu — výchozí 'id'
->itemLabel(string|Closure|null $label)                // string | fn(array $state, int $index): ?string
->addButtonLabel(?string $label)                       // výchozí __('Add item')
->emptyLabel(?string $label)                           // výchozí __('No items yet')
->minItems(?int $count)
->maxItems(?int $count)
->disabled(bool|Closure $condition = true)             // vypne přidání, mazání, duplikaci i přeuspořádání
->expandAll()                                          // každý řádek otevřený — výchozí
->expandFirst()                                        // otevřený jen první řádek; implikuje collapsible()
->expandLast()                                         // otevřený jen poslední řádek; implikuje collapsible()
->collapseAll()                                        // každý řádek složený; implikuje collapsible()
->mutateRelationshipDataBeforeSaveUsing(?Closure $fn)  // fn(array $row): array
->getRelationship(): ?string
->getOrderColumn(): ?string
->getItemKeyName(): string
->getItemLabel(array $itemState, int $index): ?string
->hasItemLabel(): bool
->getEmptyLabel(): string
->getItemExpansion(): ItemExpansion                    // All|None|First|Last
->isItemCollapsedByDefault(int $index, int $count): bool
->isAddable(): bool
->isDeletable(): bool
->isReorderable(): bool
->isCloneable(): bool
->isTable(): bool
->getItemSchema(int $index): array
```

## Kdy ho použít

Použijte `Repeater`, když jeden formulář vlastní malou až střední kolekci
souvisejících podřízených záznamů a uživatel je má spravovat na místě. Pokud
potomci potřebují vlastní filtrování, stránkování nebo těžké workflow, dejte jim
vlastní tabulku nebo obrazovku.

## Související

- [Builder](builder.md) — tentýž seznam, kde si každý řádek volí vlastní typ bloku
- [Pole formuláře](index.md) — sdílené API polí
- [Reaktivní pole](../reactive-fields.md) — jak se `$get`/`$set` zužují uvnitř řádku
- [Validace](../validation.md) — pravidla po řádcích a zástupné cesty
- [Přehled formulářů](../overview.md)
