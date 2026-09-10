---
summary: Jeden checkbox pro jeden boolean, s inline popiskem a stavem, který zapisuje.
---

# Checkbox

Jeden box, jeden boolean. Sáhni po `Checkboxu`, když je otázka „ano, nebo ne“
a odpověď patří vedle svého znění — souhlas s podmínkami, přihlášení k newsletteru.
Když je ten přepínač spíš nastavení, které uživatel překlápí, než tvrzení, se
kterým souhlasí, čte se líp [`Toggle`](toggle.md). Na několik booleanů ze seznamu
použijte [`CheckboxList`](checkbox-list.md).

```php
use NyonCode\WireForms\Components\Checkbox;
```

## Jak to funguje

Checkbox je **pole**: nese hodnotu na své state path a účastní se validace jako
kterékoli jiné.

**Jeho typ stavu je `bool`.** Na to se formulář ptá, když plní prázdné schéma,
takže checkbox, kterého se nikdo nedotkl, začíná jako `false`, ne `null` — a proto
`->required()` na checkboxu znamená „musí být zaškrtnutý“, ne „musí být přítomný“.

**Popisek kreslí checkbox, ne obal pole.** Na rozdíl od každého jiného pole dostane
obal pokyn popisek skrýt a checkbox si ho vykreslí sám, vedle boxu, s povinnou
hvězdičkou za ním. Plynou z toho dvě věci:

- Popisek checkboxu je **vždycky** vedle boxu, nikdy nad ním.
- `description()` je druhý, menší řádek pod tím popiskem — patří checkboxu a je
  něco jiného než sdílený `helperText()`, který obal vykresluje pod celým polem.

**`inline()` tady dneska nedělá nic.** Metoda je deklarovaná a přijme se, ale
`checkbox.blade.php` ji nikdy nečte — `isInline()` konzumuje jen [`Radio`](radio.md)
a [`CheckboxList`](checkbox-list.md). Popisek je vedle boxu tak jako tak, takže se nic
nerozbije; jen to na tomhle poli dneska není volba, kterou byste měli.

## Základní použití

```php
Checkbox::make('agree_terms')
    ->label('Souhlasím s podmínkami')
    ->required()
```

## Druhý řádek vysvětlení

```php
Checkbox::make('agree_terms')
    ->label('Souhlasím s podmínkami')
    ->description('Bez souhlasu nejde pokračovat.')      // [tl! focus]
    ->required()
```

## Reakce na něj

`live()` pošle změnu na server, a to je to, co nechává jiné části formuláře
objevovat se a mizet podle zaškrtnutí:

```php
Checkbox::make('has_company')
    ->label('Nakupuji na firmu')
    ->live(),                                            // [tl! focus]

TextInput::make('vat_number')
    ->visibleWhen('has_company'),                        // [tl! focus]
```

Bez `live()` by se pole s DIČ neobjevilo až do dalšího round tripu z nějakého
jiného důvodu — což vypadá, jako by byl checkbox rozbitý.

## Rozšířený příklad

Registrační formulář ve skutečném Livewire hostu, kde jeden checkbox podmiňuje
druhý:

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class Register extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),

                Checkbox::make('agree_terms')                       // [tl! focus:start]
                    ->label('Souhlasím s podmínkami služby')
                    ->description('Přečíst si je můžeš na /terms.')
                    ->required()
                    ->validationMessages([
                        'accepted' => 'Bez souhlasu s podmínkami nejde pokračovat.',
                    ])
                    ->rules(['accepted']),

                Checkbox::make('newsletter')
                    ->label('Posílejte mi novinky o produktu')
                    ->default(true),                                 // [tl! focus:end]
            ])
            ->successMessage('Vítej');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Všimněte si `rules(['accepted'])` u podmínek: samotné `required()` odmítne chybějící
klíč, kdežto `accepted` je laravelí pravidlo pro „tohle musí být opravdu true“,
a to je přesně, co checkbox se souhlasem znamená.

## Checkbox API

```php
->description(string|Closure|null $description)   // malý řádek pod labelem, uvnitř bloku checkboxu
->inline(bool $condition = true)                  // deklarované, ale view tohohle pole to nečte
->getDescription(): ?string
->isInline(): bool
->getStateType(): string                          // 'bool'
```

Popisky, nápovědu, viditelnost, výchozí hodnoty, validaci a `live()` sdílí každé
pole — viz [Společné API polí](index.md#spolecne-api-pole).

## Související

- [Toggle](toggle.md) — týž boolean jako přepínač
- [CheckboxList](checkbox-list.md) — několik booleanů z jednoho seznamu možností
- [Radio](radio.md) — jedna volba z několika místo ano/ne
- [Reaktivní pole](../reactive-fields.md) — co dělá `live()` a `visibleWhen()`
