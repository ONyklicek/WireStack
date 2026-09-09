---
summary: Seznam, jehož každá položka si volí vlastní typ bloku a vlastní schéma — kde repeater opakuje jeden tvar, builder vybírá z několika.
---

# Builder

Blokový editor pro heterogenní obsah: seznam položek, kde si každá volí vlastní
typ bloku a edituje se schématem toho bloku. Zatímco [Repeater](repeater.md)
opakuje *jedno* schéma, Builder vybírá z několika — je to tvar, na kterém stojí
page builder nebo pole s bohatým obsahem.

## Základní použití

```php
use NyonCode\WireForms\Components\Block;
use NyonCode\WireForms\Components\Builder;

Builder::make('content')
    ->blocks([
        Block::make('heading')->icon('star')->schema([
            TextInput::make('text')->rules(['required']),
        ]),
        Block::make('paragraph')->schema([
            Textarea::make('body'),
        ]),
        Block::make('image')->schema([
            FileUpload::make('file'),
            TextInput::make('alt'),
        ]),
    ])
    ->reorderable()
```

Tlačítko „přidat" otevře nabídku všech deklarovaných bloků; výběrem se přidá
položka daného typu.

## Uložený tvar

Každá položka se ukládá jako svůj typ plus data:

```php
[
    ['type' => 'heading',   'data' => ['text' => 'Ahoj']],
    ['type' => 'paragraph', 'data' => ['body' => 'Světe']],
]
```

Pole se váží pod `<statePath>.<index>.data`, takže schéma bloku nemusí vědět nic
o své pozici — a pole pojmenované `type` uvnitř bloku nemůže kolidovat s vlastním
diskriminátorem položky. Na modelu atribut castujte na `array` (nebo `json`).

## Je to Repeater

`Builder` rozšiřuje `Repeater`, takže sdílí přidávání/mazání/přeuspořádání,
reaktivitu po položkách, limity počtu i to, jak s opakovaným podstromem zachází
runtime formulářů:

```php
Builder::make('content')
    ->blocks([...])
    ->minItems(1)
    ->maxItems(20)
    ->reorderable()
    ->cloneable()
    ->collapsible()
    ->expandLast()
    ->itemLabel(fn (array $state) => $state['text'] ?? null)
    ->addButtonLabel('Přidat blok')
```

Všechno, co umí řádek repeateru, umí i blok: táhne se stejným controllerem,
přesouvá stejnými klávesovými tlačítky, duplikuje stejným endpointem a skládá
podle stejné politiky rozbalení. Dvě věci jsou Builderu vlastní — `itemLabel()`
dostane obálku `data` bloku, ne položku, takže closure vidí vlastní pole bloku;
a jméno se vykreslí *vedle* popisku bloku, ne místo něj, protože to, kterým
blokem řádek je, zůstává první informací, kterou čtenář potřebuje.

Dvě věci neplatí. `relationship()`: smíšené typy bloků nemají jeden společný
model, takže se builder ukládá jako pole, ne přes relaci — a bez relace není co
odstraňovat za klíč, takže duplikovaný blok je prostá kopie. A `table()`, které
vyhodí výjimku: tabulka rozkládá do sloupců *jedno* schéma a položky builderu
nesou každá jiné.

## Validace

Pravidla polí bloků se montují pod obálku `data` položky, tedy na
`<path>.*.data.<field>`. Protože resolver validuje podle wildcard cesty, bloky
sdílející *název* pole sdílejí i jeho pravidla — pravidla jsou tak přísná jako
nejvolnější blok, který ten název deklaruje. Kde se bloky musí validovat jinak,
pojmenujte pole odlišně.

## Bloky, které už neexistují

Uložený obsah přežije kód, který ho deklaroval. Položka, jejíž uložený typ
neodpovídá žádnému deklarovanému bloku, vykreslí svůj typ v hlavičce a žádná
pole — místo aby znemožnila vykreslení celého formuláře. Obsah tak jde pořád
poznat, přesunout i smazat.

## Deklarace bloku

```php
Block::make(string $name)
->label(string|Closure $label)     // popisek v hlavičce (odvozen z názvu)
->icon(string|Icon $icon)          // v nabídce i v hlavičce položky
->schema(array $components)        // pole, kterými se blok edituje
```

`Block` je definice, ne vykreslovaný povrch: vložení přímo do schématu formuláře
vyhodí `FormConfigurationException`.

## Builder API

```php
->blocks(array $blocks)            // typy bloků, které builder umí vložit
->getBlocks(): array
->getBlock(string $name): ?Block
->getItemType(mixed $item): ?string
->table(bool $condition = true)    // vyhazuje výjimku, viz níže
// plus celé Repeater API: addable, deletable, reorderable,
// collapsible, collapsed, minItems, maxItems, addButtonLabel
```

`table()` je jediná část Repeater API, která sem nepřechází. Tabulkový layout
rozloží do sloupců *jedno* schéma, jenže položky builderu nesou každá schéma
jiného bloku, takže neexistuje společná sada sloupců, kterou by šlo nadepsat.
Volání vyhodí `FormConfigurationException`, místo aby příznak přijalo a
vykreslilo obyčejný builder.

## Související dokumentace

- [Repeater](repeater.md) — opakuje jedno schéma místo výběru z několika
- [Validace](../validation.md)
