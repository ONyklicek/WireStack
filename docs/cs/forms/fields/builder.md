---
summary: Seznam, jehož každá položka si volí vlastní typ bloku a vlastní schéma — kde repeater opakuje jeden tvar, builder vybírá z několika.
---

# Builder

Blokový editor pro heterogenní obsah: seznam položek, kde si každá volí vlastní
typ bloku a edituje se schématem toho bloku. Zatímco [Repeater](repeater.md)
opakuje *jedno* schéma, Builder vybírá z několika — je to tvar, na kterém stojí
page builder, landing page nebo tělo článku složené z nadpisů, odstavců,
obrázků a zvýrazněných boxů.

```php
use NyonCode\WireForms\Components\Builder;
```

## Jak to funguje

**Každá položka je typ plus data.** Stav builderu je obyčejný seznam:

```php
[
    ['type' => 'heading',   'data' => ['text' => 'Ahoj']],
    ['type' => 'paragraph', 'data' => ['body' => 'Světe']],
]
```

`type` pojmenovává jeden z deklarovaných [bloků](#deklarace-bloku), `data` drží
pole toho bloku. Nic dalšího se neukládá — žádné popisky, ikony ani schéma. Právě
proto obsah přečte i kód, který o formuláři nic neví: stránce, která ho
[vykresluje](#vykresleni-ulozeneho-obsahu), stačí tyhle dva klíče.

**Pole položky se volí podle jejího typu, při každém renderu.** Pro položku `$i`
najde `getItemSchema($i, $type)` uložený typ přes `getBlock()`, naklonuje schéma
toho bloku a převáže ho pod `<statePath>.<i>.data`, takže
`TextInput::make('text')` v položce 0 se váže na `content.0.data.text`. Reaktivita
se řídí pravidly repeateru: `$get`/`$set`, `afterStateUpdated()` i živá validace
se vyhodnocují vůči té jedné položce.

**Přidání je Livewire volání, které nese zvolený blok.** Tlačítko „přidat“ jen
otevře nabídku — to je Alpine, žádný roundtrip. Výběr bloku zavolá
`addBuilderItem($statePath, $block)`, které na serveru připojí `['type' => $block,
'data' => []]`, a nový render vykreslí položku se schématem jejího bloku.
Mazání, duplikace a přesun jsou vlastní endpointy repeateru, beze změny.

**Nová položka začíná prázdná.** `addBuilderItem()` připojí `data => []` a pole
přidané do bloku později v položkách uložených dřív prostě chybí. Kód, který obsah
čte, musí každý klíč v `data` brát jako volitelný.

**Past: uložený typ není deklarovaný typ.** `addBuilderItem()` připojí jakýkoli
název bloku, který dostane, a uložený obsah přežije kód, který ho deklaroval —
blok přejmenovaný nebo odebraný příští měsíc pořád sedí v řádcích z minulého
měsíce. Formulář si poradí: [neznámý typ](#bloky-ktere-uz-neexistuji) vykreslí
hlavičku a žádná pole. Váš vykreslovací kód si musí poradit taky, a proto každý
příklad níže mapuje *známé* typy a ostatní přeskočí, místo aby z `type` rovnou
udělal název view.

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

Tlačítko „přidat“ otevře nabídku všech deklarovaných bloků; výběrem se přidá
položka daného typu. Na modelu atribut castujte na `array` (nebo `json`) —
builder se ukládá jako jeden JSON sloupec, nikdy přes relaci.

## Deklarace bloků

Blok je název, popisek, volitelná ikona a schéma, kterým se jeho položky
editují. Název se ukládá jako `type`, takže s ním zacházejte jako s názvem
sloupce: přejmenováním osiříte všechny položky uložené pod starým názvem.

```php
Block::make('callout')
    ->label('Zvýrazněný box')           // položka nabídky i hlavička — výchozí: z názvu
    ->icon('information-circle')        // zobrazí se v obou
    ->schema([
        Select::make('tone')->options(['info' => 'Informace', 'warning' => 'Varování']),
        TextInput::make('title'),
        Textarea::make('body')->rows(2),
    ])
```

Schéma bloku smí použít jakékoli pole i layoutovou komponentu — `Grid` se dvěma
inputy, `Section` uvnitř bloku — a jeho pravidla se přes layout sbírají stejně
jako u formuláře.

`Block` je definice, ne vykreslovaný povrch: vložení přímo do schématu formuláře
vyhodí `FormConfigurationException`.

## Pojmenování položek

```php
Builder::make('content')
    ->blocks([...])
    ->itemLabel(fn (array $state) => $state['text'] ?? $state['title'] ?? null)
```

`itemLabel()` dostane obálku `data` bloku, ne celou položku, takže closure vidí
vlastní pole bloku. Jméno se vykreslí *vedle* popisku bloku, ne místo něj — to,
kterým blokem řádek je, zůstává první informací, kterou čtenář potřebuje, takže
sbalený seznam čte „Heading · Release notes“.

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
    ->addButtonLabel('Přidat blok')
```

Všechno, co umí řádek repeateru, umí i blok: táhne se stejným controllerem,
přesouvá stejnými klávesovými tlačítky, duplikuje stejným endpointem a skládá
podle stejné politiky rozbalení.

Dvě věci neplatí. `relationship()`: smíšené typy bloků nemají jeden společný
model, takže se builder ukládá jako pole, ne přes relaci — a bez relace není co
odstraňovat za klíč, takže duplikovaný blok je prostá kopie. A `table()`, které
vyhodí výjimku: tabulka rozkládá do sloupců *jedno* schéma a položky builderu
nesou každá jiné.

## Validace

Pravidla polí bloků se montují pod obálku `data` položky, tedy na
`<path>.*.data.<field>`. Protože resolver validuje podle wildcard cesty, bloky
sdílející *název* pole sdílejí i jeho pravidla — vyhrává první blok, který ten
název deklaruje, takže pravidla jsou jen tak přísná jako jeho. Kde se bloky musí
validovat jinak, pojmenujte pole odlišně:

```php
Block::make('heading')->schema([
    TextInput::make('heading_text')->rules(['required', 'max:120']),
]),
Block::make('quote')->schema([
    TextInput::make('quote_text')->rules(['required', 'max:500']),
]),
```

Pole bez pravidel dostane `nullable`, takže jeho hodnota ve validovaných datech
zůstane, místo aby tiše zmizela.

## Bloky, které už neexistují

Položka, jejíž uložený typ neodpovídá žádnému deklarovanému bloku, vykreslí svůj
typ v hlavičce a žádná pole — místo aby znemožnila vykreslení celého formuláře.
Obsah tak jde pořád poznat, přesunout i smazat. Pokud ho redaktoři ještě
potřebují upravovat, nechte vyřazený blok deklarovaný, dokud obsah nezmigrujete.

## Vykreslení uloženého obsahu

Builder obsah edituje; ukázat ho návštěvníkovi je práce vaší stránky a nepotřebuje
k tomu nic z balíčku formulářů. Model vrátí pole a view ho projde a markup volí
podle `type`.

Pro pár bloků stačí jedno view s `@switch`:

```blade
{{-- resources/views/pages/show.blade.php --}}
<article class="prose">
    @foreach ($page->content ?? [] as $item)
        @php($data = $item['data'] ?? [])

        @switch($item['type'] ?? null) {{-- [tl! focus:start] --}}
            @case('heading')
                <h2>{{ $data['text'] ?? '' }}</h2>
                @break
            @case('paragraph')
                <p>{{ $data['body'] ?? '' }}</p>
                @break
            @case('image')
                @isset($data['file'])
                    <img src="{{ Storage::url($data['file']) }}" alt="{{ $data['alt'] ?? '' }}">
                @endisset
                @break
        @endswitch {{-- [tl! focus:end] --}}
    @endforeach
</article>
```

Jak bloků přibývá, dejte každému vlastní partial a smyčku nechte malou. Seznam
známých typů je whitelist — neznámý typ se přeskočí, nikdy se z něj nestane cesta
k view:

```blade
{{-- resources/views/pages/show.blade.php --}}
@foreach ($page->content ?? [] as $item)
    @if (in_array($item['type'] ?? null, ['heading', 'paragraph', 'image', 'callout'], true))
        @include('blocks.'.$item['type'], ['data' => $item['data'] ?? []])
    @endif
@endforeach
```

```blade
{{-- resources/views/blocks/callout.blade.php --}}
<aside @class(['callout', 'callout-warning' => ($data['tone'] ?? null) === 'warning'])>
    <strong>{{ $data['title'] ?? '' }}</strong>
    <p>{{ $data['body'] ?? '' }}</p>
</aside>
```

Tři věci, které v partialech hlídat:

- **Každý klíč je volitelný.** Nová položka začíná s prázdným `data` a pole
  přidané do bloku později ve starších položkách chybí. Používejte `?? ''` /
  `@isset`, ne holé `$data['x']`.
- **Uložené hodnoty jsou surové.** `FileUpload` ukládá cestu na svém disku, takže
  ji převeďte přes `Storage::url()` (nebo `Storage::disk('s3')->url()`); `Select`
  ukládá klíč volby, ne její popisek.
- **Escapujte ve výchozím stavu.** `{{ }}` escapuje, a má. Markup tiskněte přes
  `{!! !!}` jen u pole, jehož obsah sami vytváříte a kterému věříte — blok s
  `RichEditor`, jehož výstup sanitizujete — nikdy u obyčejného textového inputu.

Read-only strana administrace nemá infolist entry, které by builderu rozumělo:
[`RepeatableEntry`](../../core/infolists/index.md) opakuje jedno schéma, takže
neukáže položky s různými poli. Vykreslete tam stejné partialy.

## Rozšířený příklad

Model stránky, formulář, který ji edituje, a view, které ji ukazuje — tři místa,
kterými obsah prochází.

```php
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'content'];

    protected function casts(): array
    {
        return [
            'content' => 'array', // [tl! focus] jeden JSON sloupec drží všechny bloky
        ];
    }
}
```

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Block;
use NyonCode\WireForms\Components\Builder;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditPage extends Component
{
    use WithForms;

    public Page $page;

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->page->only(['title', 'content']));
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->page)
            ->statePath('data')
            ->schema([
                TextInput::make('title')->required(),

                Builder::make('content')                    // [tl! focus:start]
                    ->blocks([
                        Block::make('heading')
                            ->icon('bars-3-bottom-left')
                            ->schema([TextInput::make('text')->rules(['required', 'max:120'])]),
                        Block::make('paragraph')
                            ->icon('bars-3')
                            ->schema([Textarea::make('body')->rows(4)]),
                        Block::make('image')
                            ->icon('photo')
                            ->schema([
                                FileUpload::make('file')->image()->disk('public'),
                                TextInput::make('alt')->label('Alternativní text'),
                            ]),
                        Block::make('callout')
                            ->icon('information-circle')
                            ->schema([
                                Select::make('tone')->options(['info' => 'Informace', 'warning' => 'Varování']),
                                TextInput::make('title'),
                                Textarea::make('body')->rows(2),
                            ]),
                    ])
                    ->itemLabel(fn (array $state) => $state['text'] ?? $state['title'] ?? null)
                    ->minItems(1)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->expandLast()
                    ->addButtonLabel('Přidat blok'),       // [tl! focus:end]
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

Při uložení se celý seznam zapíše do `content` jako JSON, v pořadí, v jakém je
zobrazený. Veřejná stránka ho pak vykreslí smyčkou s partialy z
[Vykreslení uloženého obsahu](#vykresleni-ulozeneho-obsahu):

```blade
{{-- resources/views/pages/show.blade.php --}}
<h1>{{ $page->title }}</h1>

@foreach ($page->content ?? [] as $item)
    @if (in_array($item['type'] ?? null, ['heading', 'paragraph', 'image', 'callout'], true))
        @include('blocks.'.$item['type'], ['data' => $item['data'] ?? []])
    @endif
@endforeach
```

## Builder API

Povrch pro výběr bloků. Všechno ostatní — `addable`, `deletable`,
`reorderable`, `cloneable`, `collapsible`, `collapsed`, `expandAll`/`expandFirst`/
`expandLast`/`collapseAll`, `itemLabel`, `emptyLabel`, `minItems`, `maxItems` —
je [Repeater API](repeater.md#repeater-api), beze změny.

```php
->blocks(array $blocks)            // array<int, Block> — typy bloků, které builder umí vložit
->addButtonLabel(?string $label)   // výchozí __('Add block')
->table(bool $condition = true)    // vyhodí FormConfigurationException — viz níže
->getBlocks(): array
->getBlock(string $name): ?Block   // null pro název, který žádný blok nedeklaruje
->getItemType(mixed $item): ?string // uložený typ, nebo null, když chybí nebo je prázdný
```

`table()` je jediná část Repeater API, která sem nepřechází. Tabulkový layout
rozloží do sloupců *jedno* schéma, jenže položky builderu nesou každá schéma
jiného bloku, takže neexistuje společná sada sloupců, kterou by šlo nadepsat.
Volání vyhodí `FormConfigurationException`, místo aby příznak přijalo a
vykreslilo obyčejný builder.

## Block API

```php
Block::make(string $name)          // uložený `type` — přejmenujte ho a staré položky osiří
->label(string|Closure $label)     // položka nabídky i hlavička — výchozí: odvozen z názvu
->icon(string|Icon $icon)          // v nabídce i v hlavičce položky
->schema(array $components)        // pole, kterými se blok edituje
```

## Související

- [Repeater](repeater.md) — opakuje jedno schéma místo výběru z několika
- [Formulářová pole](index.md) — sdílené API polí
- [File Upload](file-upload.md) — co obrázkový blok ukládá a na jaký disk
- [Validace](../validation.md) — wildcard cesty a pravidla po položkách
