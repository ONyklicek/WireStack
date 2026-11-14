---
order: 23
summary: Inline rozbalovací seznam, který uloží v okamžiku výběru — stejný combobox jako Select ve formuláři, nebo nativní select prohlížeče — s možnostmi a zápisovou cestou za ním.
---

# SelectColumn

Inline rozbalovací seznam, který uloží v okamžiku výběru. Buňka je stejný ovládací prvek
jako [`Select`](../../forms/fields/select.md) ve formuláři: sdílený combobox s vyhledáváním,
více hodnotami a řádkem „vytvořit položku“, když o ně požádáte — nebo na požádání nativní
`<select>` prohlížeče.

```php
use NyonCode\WireTable\Columns\SelectColumn;
```

## Základní použití

```php
SelectColumn::make('status')
    ->options([
        'draft' => 'Draft',
        'review' => 'In Review',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
```

## Jak buňka funguje

Každá buňka drží hodnotu ve vlastní komponentě editovatelné buňky a každý výběr jde na
server přes `updateTableCell()` — se stejnou ochranou, validací, optimistickým zámkem a
hooky jako jakákoli inline úprava (viz [Inline editace](editing.md)). Combobox je navázaný
na tuto komponentu, ne na Livewire property, takže se nic neprovazuje po řádcích.

Dva náklady zůstávají mimo řádky:

- **Jeden seznam na sloupec.** Seznam možností se vykreslí jednou za tabulku, vedle řádků.
  Buňka nese jen svůj spouštěč; při prvním otevření si seznam sloupce naklonuje do stránky
  pod svým řádkem. Stránka se stovkou řádků nese možnosti jednou.
- **Žádný render view na řádek.** Buňka se vykreslí jednou za sloupec jako kostra a klíč,
  hodnota a verze každého řádku se do ní doplní.

Server nevěří seznamu, který prohlížeč ukázal: hodnota, která není mezi možnostmi, se
odmítne a buňka se vrátí zpět se zprávou pod sebou.

## Možnosti z relace

```php
SelectColumn::make('category_id')
    ->relationship('category', 'name')   // načíst options ze souvisejícího modelu
```

Související seznam je pro každý řádek stejný, takže se načte **jednou za render**
a znovu použije — ne jednou za buňku. Explicitní `->options()` má přednost.

```php
// Naplnit seznam sám ze známého záznamu (zřídka potřeba).
SelectColumn::make('category_id')
    ->relationship('category', 'name')
    ->loadRelationshipOptions($record)
```

## Možnosti z enumu

Předejte třídu PHP enumu pro rozvinutí jeho case na možnosti `value => label`. Popisky pocházejí z
`getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`, jinak se
z názvu case udělá headline. Kontrakty viz [Enum a JSON casty](casts.md).

```php
SelectColumn::make('status')->options(OrderStatus::class)
```

## Vyhledávání v seznamu

```php
SelectColumn::make('owner_id')
    ->relationship('owner', 'name')
    ->searchableOptions()
```

`searchableOptions()` dá nahoru do seznamu vyhledávací pole. Jmenuje se jinak než
`searchable()`, které u každého sloupce znamená, že na sloupec dosáhne vyhledávání tabulky.

## Více hodnot

```php
SelectColumn::make('labels')
    ->multiple()
    ->options(['bug' => 'Bug', 'feature' => 'Feature', 'docs' => 'Docs'])
```

Buňka s více hodnotami drží seznam — atribut castujte na `array`. Zaškrtávání možností
zatím nic nezapisuje; seznam se zapíše **jednou, při zavření**, a jen pokud se změnil.
Needitovatelná buňka ukáže popisky spojené čárkou.

## Vytvoření položky

```php
SelectColumn::make('owner_name')
    ->options(fn () => Owner::query()->pluck('name', 'name')->all())
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
    ->createOptionUsing(fn (array $data) => Owner::create($data)) // vrací klíč, nebo model
```

Seznam končí řádkem „Vytvořit položku“. Otevře modal s formulářem; po odeslání
`createOptionUsing()` položku uloží a její klíč se zapíše do řádku, ze kterého byl seznam
otevřen — přes `updateTableCell()`, stejně jako výběr. Všechny ostatní řádky sloupce ji
mohou hned vybrat. Nikdo nevytvoří položku pro buňku, kterou by nemohl nastavit: akce se
řídí stejným oprávněním a pravidlem `disabled()` jako buňka.

## Nativní ovládací prvek

```php
SelectColumn::make('priority')->options([...])->native()          // nativní <select> všude
SelectColumn::make('priority')->options([...])->nativeOnMobile()  // nativní jen na telefonu
SelectColumn::make('status')->options([...])->touchOnMobile()     // dotykový seznam přes celou výšku na telefonu
```

Stejné tři přepínače jako u každé select plochy, přes sdílený koncept nativního prvku;
globální výchozí hodnoty jsou `wire-core.mobile.native` a `wire-core.mobile.touch`.
Přednost: `native()` > `touchOnMobile()` > `nativeOnMobile()`. Vyhledávání potřebuje
combobox, takže `searchableOptions()` zruší dřívější `native()`.

## Vyprázdnění buňky

```php
SelectColumn::make('assignee_id')->options([...])->selectablePlaceholder()
```

Ve výchozím stavu buňku vyprázdnit nejde: atribut, který nesmí být null, by zápis
neprovedl. `selectablePlaceholder()` přidá do seznamu (i do nativního selectu) prázdný
řádek a server pak přijme `null`.

## Vkládání a kopírování

S [fill handle](fill-handle.md) se blok vložený z tabulkového procesoru čte podle popisku
i podle klíče — `Paid` se zapíše jako `paid` — a buňka s více hodnotami přijme seznam
oddělený čárkami. Kopírování řádků dá do schránky popisky, které se stejně vloží zpět.

## Podmíněné disabled

```php
SelectColumn::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->disabled(fn ($record) => $record->is_super_admin)  // super admina nelze změnit
```

## Rozšířený příklad

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\SelectColumn;

public function table(Table $table): Table
{
    return $table
        ->model(Task::class)
        ->columns([
            TextColumn::make('title'),
            SelectColumn::make('status')
                ->options(TaskStatus::class)
                ->searchableOptions() // [tl! focus]
                ->touchOnMobile(), // [tl! focus]
            SelectColumn::make('owner_id')
                ->relationship('owner', 'name')
                ->searchableOptions()
                ->createOptionForm([TextInput::make('name')->required()]) // [tl! focus]
                ->createOptionUsing(fn (array $data) => Owner::create($data)), // [tl! focus]
            SelectColumn::make('labels')
                ->multiple() // [tl! focus]
                ->selectablePlaceholder()
                ->options(['bug' => 'Bug', 'feature' => 'Feature', 'docs' => 'Docs']),
            SelectColumn::make('priority')
                ->options(['low' => 'Low', 'high' => 'High'])
                ->native() // [tl! focus]
                ->disabled(fn (Task $record): bool => $record->completed),
        ]);
}
```

## API SelectColumn

```php
->options(array|string|Closure $options): static          // ['value' => 'Label', ...], třída enumu, nebo closure spuštěná jednou
->getOptions(): array                                      // array<string, string>
->relationship(?string $name, ?string $titleAttribute = null): static // options z relace, načtené jednou za render
->loadRelationshipOptions(Model $record): static           // naplnit seznam explicitně
->searchableOptions(bool $searchable = true): static       // vyhledávání v seznamu
->hasSearchableOptions(): bool
->multiple(bool $multiple = true): static                  // atribut je seznam
->isMultiple(): bool
->selectablePlaceholder(bool $selectable = true): static   // dovolit vyprázdnění buňky
->isPlaceholderSelectable(): bool
->placeholder(string|Closure|null $placeholder): static    // text prázdného spouštěče
->createOptionForm(array|Closure $schema): static          // řádek „Vytvořit položku“ a formulář v modalu
->createOptionUsing(Closure $callback): static             // fn (array $data): klíč|Model
->createOptionModalHeading(?string $heading): static
->hasCreateOptionForm(): bool
->native(bool $native = true): static                      // nativní <select>
->nativeOnMobile(bool $condition = true): static
->touchOnMobile(bool $condition = true): static
->disabled(bool|Closure $disabled = true): static
->isDisabled(Model $record): bool
->validate(mixed $value, Model $record): array             // ['valid' => bool, 'errors' => list<string>]
```
