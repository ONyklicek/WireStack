---
order: 50
---

# Pole formulářů

Reference pro vestavěné Wire Forms field a layoutové komponenty.

## Vyberte podle případu užití

| Případ užití | Komponenta |
|----------|-----------|
| Jednořádkový text | [TextInput](text-input.md) |
| Víceřádkový text | [Textarea](textarea.md) |
| Vybrat jednu možnost | [Select](select.md) |
| Přepnout boolean hodnotu | [Toggle](toggle.md) nebo [Checkbox](checkbox.md) |
| Vybrat více možností | [CheckboxList](checkbox-list.md) |
| Vybrat jednu viditelnou možnost | [Radio](radio.md) |
| Volné tagy / chipy | [Tags](tags.md) |
| Slider číselného rozsahu | [Slider](slider.md) |
| Editor párů klíč-hodnota | [KeyValue](key-value.md) |
| Hvězdičkové hodnocení | [Rating](rating.md) |
| Vybrat datum nebo datum/čas | [DateTimePicker](date-time-picker.md) |
| Vybrat barvu | [ColorPicker](color-picker.md) |
| Nahrát soubory | [FileUpload](file-upload.md) |
| Rich text editace | [RichEditor](rich-editor.md) nebo [TiptapEditor](tiptap-editor.md) |
| Markdown editace | [MarkdownEditor](markdown-editor.md) |
| Vstup kódu / skriptu | [CodeEditor](code-editor.md) |
| OTP / PIN kód | [OtpInput](otp-input.md) |
| Skrytá metadata formuláře | [Hidden](hidden.md) |
| Spustit akci ze schématu | [Button](button.md) |
| Vybrat související záznam | [BelongsToSelect](belongs-to-select.md) |
| Vybrat polymorfní cíl | [MorphToSelect](morph-to-select.md) |
| Spravovat opakované skupiny nebo dětské řádky | [Repeater](repeater.md) |

## Layoutové komponenty

Layoutové komponenty (Grid, Flex, Section, Fieldset, Tabs, Wizard) žijí ve
sdílené sekci [Schema](../../core/schema/overview.md) — stejný slovník používají
formuláře, infolisty i modály.

## Zobrazovací komponenty

| Komponenta | Účel |
|-----------|---------|
| [Placeholder](placeholder.md) | Read-only zobrazení hodnoty |
| [Alert](alert.md) | Kontextová zpráva uvnitř formuláře |
| [Html](html.md) | Vykreslit důvěryhodný HTML obsah |
| [ViewField](view-field.md) | Vykreslit vlastní Blade partial jako pole |

## Postavte si vlastní

Potřebujete pole, které zde není? Stavbu vlastních polí a zobrazovacích komponent,
znovupoužitelné presety a balíčkování polí do pluginu viz [Rozšíření formulářů](../custom-fields.md).

<a id="common-field-api"></a>
## Společné API pole

Každé pole dědí následující metody ze sdílené základní třídy `Field`. Jednotlivé dokumentace polí se soustředí na volby specifické pro dané pole; pro cokoli tam neuvedeného se vraťte sem.

### Label a nápověda

| Metoda | Příklad |
|--------|---------|
| `label(string\|Closure)` | `->label('Full name')` |
| `helperText(string\|Closure)` | `->helperText('Used for login')` |
| `hint(string\|Closure)` | `->hint('Optional')` |
| `hintIcon(string)` | `->hintIcon('information-circle')` |
| `hintColor(string)` | `->hintColor('warning')` |
| `tooltip(string\|Closure)` | `->tooltip('Shown on hover')` |

### Viditelnost a stav

| Metoda | Příklad |
|--------|---------|
| `visible(bool\|Closure)` | `->visible(fn () => $this->showField)` |
| `hidden(bool\|Closure)` | `->hidden()` |
| `disabled(bool\|Closure)` | `->disabled()` |
| `readOnly(bool\|Closure)` | `->readOnly()` |

### Výchozí hodnota

```php
TextInput::make('status')->default('draft')
TextInput::make('user_id')->default(fn () => auth()->id())
```

Při naplnění formuláře se každé pole ze schématu naseeduje automaticky: jeho
`->default()`, pokud je nastaven, jinak **typově správná prázdná hodnota**
(`''`/`null` u textu, `[]` u polí typu pole jako `CheckboxList`/`Tags`/
multi-select, `false` u přepínačů). Nikdy nemusíš pole předvyplňovat jen proto,
aby jeho klíč existoval — pole typu pole zejména začínají jako `[]` místo aby
zkolabovala.

Výchozí hodnoty doplní jen klíče, které příchozí data nedodala, takže se
uplatní při vytváření a u nových/virtuálních polí a **nikdy nepřepíšou uloženou
hodnotu záznamu — ani záměrný `null`.** Pro předvyplnění z recordu nebo kontextu
nad rámec výchozích hodnot použij `fillFormUsing()` na akci (viz
[Akce](../../core/actions.md)).

### Validace

| Metoda | Příklad |
|--------|---------|
| `required()` | `->required()` |
| `rules(array\|string)` | `->rules(['min:2', 'max:255'])` |
| `validationMessages(array)` | `->validationMessages(['required' => 'Povinné pole'])` |

### Live aktualizace

| Metoda | Chování |
|--------|-----------|
| `live()` | Spustí Livewire update při každé input události (s 250 ms debounce) |
| `lazy()` | Spustí Livewire update při blur |
| `debounce(int $ms)` | Přepíše debounce prodlevu pro `live()` |

### Prefix a suffix

Dostupné na TextInput, Textarea a Select.

| Metoda | Příklad |
|--------|---------|
| `prefix(string)` | `->prefix('CZK')` |
| `suffix(string)` | `->suffix('%')` |
| `prefixIcon(string)` | `->prefixIcon('magnifying-glass')` |
| `suffixIcon(string)` | `->suffixIcon('check')` |
| `prefixAction(Action)` | `->prefixAction(Action::make('lookup')->action(fn ($get, $set) => …))` |
| `suffixAction(Action)` | `->suffixAction(Action::make('verify')->action(fn ($get, $set) => …))` |
| `hintAction(Action)` | `->hintAction(Action::make('help'))` |

Affix a hint akce běží na serveru s reaktivním `$get` / `$set` kontextem pole — viz
[Field akce a tlačítka](../reactive-fields.md#field-actions-and-buttons).

### Ostatní

| Metoda | Příklad |
|--------|---------|
| `placeholder(string\|Closure)` | `->placeholder('Enter value...')` |
| `autofocus()` | `->autofocus()` |
| `extraAttributes(array)` | `->extraAttributes(['data-testid' => 'name'])` |
| `columnSpan(int\|string)` | `->columnSpan(2)` — rozpětí sloupců uvnitř [Gridu](../../core/schema/layout/grid.md) |

## Běžné vzory

### Základní create nebo edit formulář

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Toggle;

->schema([
    TextInput::make('name')
        ->required()
        ->maxLength(255),

    TextInput::make('email')
        ->email()
        ->required(),

    Select::make('role')
        ->options([
            'admin' => 'Admin',
            'editor' => 'Editor',
            'viewer' => 'Viewer',
        ])
        ->required(),

    Toggle::make('active'),
])
```

### Seskupení polí do sekcí

```php
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;

->schema([
    Section::make('User')
        ->schema([
            Grid::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ]),
        ]),
])
```

## Související dokumentace

- [Přehled formulářů](../overview.md)
- [Validace](../validation.md)
- [Životní cyklus ukládání](../save-lifecycle.md)
```
