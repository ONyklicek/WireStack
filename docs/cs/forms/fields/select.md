# Select

Dropdown select se statickými nebo dynamickými options, hledáním a multi-selectem.

```php
use NyonCode\WireForms\Components\Select;
```

> **Mobil.** Dropdown/hledací panel se otevře jako bottom sheet pod
> nakonfigurovaným breakpointem (searchable selecty zůstanou plovoucím panelem ve výchozím stavu,
> aby vyhledávací box zůstal použitelný). Přepište per pole pomocí `->sheetOnMobile()` /
> `->mobileBreakpoint('md')` — viz [mobilní prezentace](../../configuration.md#mobil).

## Základní použití

```php
Select::make('role')
    ->options([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'user' => 'User',
    ])
```

## Dynamické options

```php
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->placeholder('Choose category')
```

<a id="enum-options"></a>
## Options z enumu

Předejte třídu PHP enumu přímo místo pole — case se rozvinou na
mapu `value => label`. Klíč je backing hodnota (nebo název case pro unit enumy)
a label pochází z `getLabel()` enumu, když implementuje kontrakt
`Foundation\Contracts\Enum\HasLabel`, s fallbackem na headline z názvu case.

```php
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum Status: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }
}

Select::make('status')->options(Status::class)
// → ['draft' => 'Draft', 'published' => 'Published']
```

Enum bez `HasLabel` stále funguje — z názvu case se udělá headline pro label
(`LowPriority` → `Low Priority`). Closura vracející třídu enumu se rozvine také.

**Automatická validace.** Jednohodnotový `Select` (nebo [`Radio`](radio.md)), jehož options pocházejí
z enumu, je automaticky omezen na tyto hodnoty pravidlem `in:` — odeslání
mimo enum je odmítnuto, aniž byste ho museli znovu uvádět. Přeskočí se pro `multiple()` selecty
(stav pole) a když deklarujete vlastní pravidlo `in:` / `Rule::in()` / `Rule::enum()`.

> Stejná zkratka `->options(Enum::class)` funguje na [`Radio`](radio.md),
> [`CheckboxList`](checkbox-list.md), table `SelectColumn` a table
> [`SelectFilter`](../../table/filters/index.md).

<a id="searchable"></a>
## Zrušení výběru

Zvolení prázdné (placeholder) položky uloží **null**, ne prázdný řetězec — což
je podstatné hlavně u sloupce castovaného na enum, kde `''` není platná hodnota
a cast by při ukládání vyhodil chybu:

```php
Select::make('status')
    ->options(Status::class)      // sloupec castovaný na enum
    ->placeholder('Bez stavu')    // zvolením se uloží null
```

Vícenásobný výběr to nemění: jeho prázdný stav je `[]`, což cast na pole uloží
tak, jak je.

## Vyhledávatelné

```php
Select::make('user_id')
    ->options(fn () => User::pluck('name', 'id')->toArray())
    ->searchable()
    ->noSearchResultsMessage('No users found')
    ->searchPrompt('Type to search...')
    ->loadingMessage('Loading...')
```

## Remote hledání

Místo filtrování přednačteného seznamu v prohlížeči resolvujte shody na
serveru, jak uživatel píše:

```php
Select::make('author_id')
    ->getSearchResultsUsing(fn (string $search) =>
        User::where('name', 'like', "%{$search}%")->limit(50)->pluck('name', 'id')->all()
    )
    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
```

- `getSearchResultsUsing()` implikuje `searchable()` a vrací mapu `value => label`.
- `getOptionLabelUsing()` (single) / `getOptionLabelsUsing()` (multiple) resolvují
  label(y) pro aktuální výběr, takže trigger zůstane čitelný i když
  vybraná option nebyla nikdy přednačtena.
- `preload()` dychtivě naplní remote seznam při renderu (spustí search callback s
  prázdným výrazem) místo čekání na první stisk klávesy.

Hostitel musí vystavit search endpoint — jakákoli `WithForms` komponenta nebo table
action modal to dělá. [`BelongsToSelect`](belongs-to-select.md) dostane remote hledání
řízené relací automaticky.

<a id="create--edit-options"></a>
## Create & Edit options

Nechte uživatele vytvořit novou option — nebo editovat vybranou — z modalu bez
opuštění formuláře:

```php
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->all())
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
    ->editOptionForm([
        TextInput::make('name')->required(),
    ])
    ->fillEditOptionUsing(fn ($value) => Category::find($value)->only('name'))
    ->updateOptionUsing(fn ($value, array $data) => Category::find($value)->update($data))
```

Prvek „+ Create" (a pro vybranou hodnotu „Edit") se objeví v patičce panelu comboboxu
a otevře izolovaný modal. Validace drží modal otevřený s
chybami; při úspěchu se nová hodnota vybere (přidá u multi-selectu).

- `createOptionUsing()` vrací hodnotu nové option — skalární klíč nebo model,
  jehož klíč se použije.
- Editace cílí na jedinou vybranou option, takže není dostupná na `multiple()`.
- Funguje v samostatných `WithForms` komponentách **i** uvnitř table action modalů.
- Aby nově vytvořená hodnota vykreslila label, spárujte s `getOptionLabelUsing()`
  nebo přednačteným seznamem options.
- Vytvořená/editovaná option se okamžitě sloučí do otevřeného comboboxu (hostitel
  odesílá browser události `select-option-created` / `select-option-updated`) — žádné
  obnovení stránky není potřeba.

### Plnohodnotný formulář, ne seznam polí

Option schéma je běžné formulářové schéma a namountovaný option form je
plnohodnotný formulář hostitele, takže věci, které potřebují, aby hostitel našel
pole podle state path, uvnitř něj fungují stejně jako kdekoli jinde:

```php
Select::make('category_id')
    ->createOptionForm([
        Wizard::make('category')->schema([                       // [tl! focus:start]
            Step::make('Základ')->schema([
                TextInput::make('name')->required(),
            ]),
            Step::make('Zařazení')->schema([
                Select::make('parent_id')
                    ->getSearchResultsUsing(fn (string $search) =>
                        Category::where('name', 'like', "%{$search}%")->pluck('name', 'id')->all()
                    ),
            ]),
        ]),                                                      // [tl! focus:end]
    ])
    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
```

- [`Wizard`](../../core/schema/layout/wizard.md) gatuje jednotlivé kroky: „Next"
  validuje jen pole daného kroku a při neúspěchu zůstane stát, přičemž chyby
  přistanou na option bagu (`createOptionFormData.*`), kde je modal už zobrazuje.
- Vnořený `Select` dosáhne na endpoint remote searche a field actions
  (`suffixAction()`, `hintAction()`, `Button`) se resolvnou a proběhnou.
- Otevření *druhého* option modalu zevnitř option formu je odmítnuto, ne vnořeno:
  na každý druh je jedna mounted path a jeden data bag, takže vyhovět by znamenalo
  zahodit rozepsaný formulář.

### Konfigurace option modalu

Ani jeden option modal není zvláštní případ: oba se konfigurují přes stejný objekt
`Modal`, jaký používají action modaly, takže nadpis, popis, ikona, šířka, chování
při zavírání, sticky chrome i popisky obou tlačítek žijí na jednom místě.

```php
use NyonCode\WireCore\Modals\Modal;

Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->all())
    ->createOptionForm([TextInput::make('name')->required()])
    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey())
    ->createOptionModal(fn (Modal $modal) => $modal   // [tl! focus:start]
        ->heading('Nová kategorie')
        ->description('Bude vybratelná, jakmile ji uložíte.')
        ->icon('outline:folder-plus')
        ->width('2xl')
        ->closeOnClickAway(false)
        ->stickyFooter()
        ->submitLabel('Vytvořit kategorii')
        ->cancelLabel('Zahodit'))                     // [tl! focus:end]
    ->editOptionModal(fn (Modal $modal) => $modal->width('xl'))
```

Callback konfiguruje modal na místě; vrácený `Modal` ho nahradí celý. Běží při
definici schématu, ne jednou za render, takže text závislý na stavu jde přes
closure podporu samotného configu — `$modal->heading(fn (Select $field) => …)`,
vyhodnocenou s polem jako kontextem.

`createOptionModalHeading()` / `createOptionModalWidth()` a jejich `editOption…`
dvojčata zůstávají jako zkratky a zapisují do téhož objektu, takže se dva způsoby
nastavení nadpisu nemůžou rozejít. Šířka bere case `ModalWidth` nebo jeho token
(`sm`…`7xl`, `full`); neznámý token spadne na `md` a nenakonfigurovaný modal
následuje `wire-core.modals.default_width` jako každý jiný modal.

`id` modalu, `wire:model` a zavírací akce konfigurovatelné záměrně **nejsou**.
Klíčují teleport, podle kterého Livewire morfuje, a oba option modaly můžou být
namountované najednou — `id` nastavené volajícím by nechalo jejich obsah prohodit.

## Reaktivita

Combobox se váže deferred ve výchozím stavu. Přidejte `live()`, když na výběr reagují jiná pole
— `afterStateUpdated()`, `visibleWhen()` souseda nebo `Form::live()` —
aby se výběr option synchronizoval na server při kliknutí místo čekání na další
roundtrip:

```php
Select::make('type')
    ->options([...])
    ->live()
    ->afterStateUpdated(fn ($state, $set) => $set('label', ucfirst((string) $state)))
```

## Multi-select

```php
Select::make('tags')
    ->multiple()
    ->maxItems(5)
    ->minItems(1)
    ->options([...])
```

## Relace

```php
Select::make('author_id')
    ->relationship('author', 'name')
    ->searchable()
```

## Nativní vs vlastní

Každý `Select` se ve výchozím stavu vykresluje přes vlastní combobox, takže searchable a
non-searchable selecty sdílejí jeden design — [`searchable()`](#vyhledavatelne) jen přidá
in-panel search input. Použijte `native()` pro nativní
`<select>` element prohlížeče.

```php
Select::make('country')
    ->searchable()      // combobox se search inputem
    ->native()          // vynutit nativní <select> prohlížeče
```

## Boolean select

```php
Select::make('active')
    ->boolean()         // Yes/No options
```

## Disabled options

Vykreslit konkrétní options jako nevybíratelné:

```php
Select::make('status')
    ->options([
        'draft'     => 'Draft',
        'review'    => 'In Review',
        'published' => 'Published',
        'archived'  => 'Archived',
    ])
    ->disabledOptions(['archived'])
```

Dynamické disabled options:

```php
Select::make('tier')
    ->options(Plan::pluck('name', 'id')->toArray())
    ->disabledOptions(fn () => Plan::unavailable()->pluck('id')->toArray())
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `options(array\|string\|Closure)` | array | Statické, dynamické nebo enum-class options (`value => label`) |
| `searchable()` | bool | Zapnout hledání options |
| `multiple()` | bool | Povolit více výběrů |
| `native(bool $native = true)` | bool | Použít nativní `<select>` prohlížeče místo comboboxu (výchozí: `false`) |
| `maxItems(int\|null)` | int | Maximum vybraných položek (multi-select) |
| `minItems(int\|null)` | int | Minimum vybraných položek (multi-select) |
| `disabledOptions(array\|Closure)` | array | Klíče options vykreslené jako disabled |
| `noSearchResultsMessage(string\|null)` | string | Zpráva, když hledání nic nenajde |
| `loadingMessage(string\|null)` | string | Zpráva během načítání options |
| `searchPrompt(string\|null)` | string | Prompt zobrazený v hledacím boxu |
| `boolean()` | — | Zkratka pro Yes/No options |
| `relationship(string, string)` | — | Načíst options z relace |
| `getSearchResultsUsing(Closure)` | — | Remote hledání: resolvovat shody na serveru (implikuje `searchable()`) |
| `getOptionLabelUsing(Closure)` / `getOptionLabelsUsing(Closure)` | — | Resolvovat label(y) pro aktuální výběr |
| `preload()` | bool | Dychtivě naplnit remote seznam options při renderu |
| `createOptionForm(array\|Closure)` / `createOptionUsing(Closure)` | — | Vytvořit novou option z modalu |
| `editOptionForm(array\|Closure)` / `fillEditOptionUsing(Closure)` / `updateOptionUsing(Closure)` | — | Editovat vybranou option z modalu |
| `createOptionModal(Closure)` / `editOptionModal(Closure)` | — | Konfigurace option modalu přes kanonický objekt `Modal` |
| `createOptionModalHeading(string)` / `editOptionModalHeading(string)` | string | Nadpisy modalu (zkratka) |
| `createOptionModalWidth(string\|ModalWidth\|null)` / `editOptionModalWidth(string\|ModalWidth\|null)` | string | Šířky modalu (`sm`…`7xl`, `full`; výchozí `md`) (zkratka) |
| `placeholder(string\|Closure)` | string | Label prázdné/blank option |
| `disabled(bool\|Closure)` | bool | Znepřístupnit select |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).
