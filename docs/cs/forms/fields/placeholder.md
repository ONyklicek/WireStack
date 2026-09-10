---
summary: "Statický text v toku schématu: čte se jako pole a nedrží žádná data."
---

# Placeholder

Řádek textu, který sedí ve schématu a vypadá jako pole — popisek nahoře, hodnota
pod ním — ale nic nedrží. Sáhni po `Placeholderu`, když chcete vedle editovatelných
vstupů ukázat spočítanou hodnotu nebo hodnotu jen ke čtení: celkovou částku
faktury, vygenerovanou referenci, „naposledy přihlášen před třemi dny“.

```php
use NyonCode\WireForms\Components\Display\Placeholder;
```

## Jak to funguje

Placeholder je **zobrazovací komponenta**: vykresluje výstup a nikdy se neváže na
stav formuláře. Žádná state path, žádná validace, žádné `live()` — a nic z toho,
co ukazuje, se s formulářem neodesílá.

**Půjčuje si tvar pole, ne jeho chování.** Vykreslený markup je `label`, pokud je
nastavený, pod ním obsah a pod tím `helperText()`. Placeholder se tak zarovná se
vstupy kolem sebe, aniž by jedním z nich byl.

**Obsah se ve výchozím stavu escapuje.** `content('<strong>Pozor</strong>')`
vykreslí ty tagy jako viditelný text. Vypnout to jde dvěma způsoby, které jsou
týž přepínač:

- `->allowHtml()` zapne surový výstup pro to, co drží `content()`.
- `->html($content)` nastaví obsah **a** zároveň to zapne, jedním voláním.

Ani jedno nic nesanitizuje. To escapování je jediné, co stojí mezi hodnotou
a stránkou, takže cokoli, co jde do `allowHtml()`, musí být už důvěryhodné —
řetězec, který jste složili vy, ne ten, který napsal někdo jiný.

**Obsah může být closure**, vyhodnocovaná při každém čtení, a právě to dělá
z placeholderu užitečnou věc pro hodnotu, která závisí na zbytku formuláře, ne na
uloženém sloupci.

## Základní použití

```php
Placeholder::make('reference')
    ->label('Reference')
    ->content('INV-2026-0184')
```

## Hodnota spočítaná z formuláře

Protože se obsah vyhodnocuje při čtení, může se dívat na cokoli, co host ví:

```php
Placeholder::make('total')
    ->label('Celkem')
    ->content(fn (): string => number_format($this->lineTotal(), 2).' Kč')   // [tl! focus]
```

Aby se hodnota přepočítávala, jak uživatel píše, potřebují pole, na kterých závisí,
`live()` — samotný placeholder nemá čím reagovat.

## Propuštění markupu

```php
Placeholder::make('status')
    ->label('Stav')
    ->html('<span class="text-red-600 font-medium">Po splatnosti</span>')   // [tl! focus]
```

`html()` je `content()` plus `allowHtml()`. Používejte ho jen s řetězcem, který jste
složil sám.

## Rozšířený příklad

Formulář faktury ve skutečném Livewire hostu, kde dva placeholdery ukazují
hodnoty, které uživatel nemůže editovat, ale potřebuje je vidět:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditInvoice extends Component
{
    use WithForms;

    public Invoice $record;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Invoice::class)
            ->schema([
                Grid::make()->columns(2)->schema([
                    Placeholder::make('number')                       // [tl! focus:start]
                        ->label('Číslo faktury')
                        ->content(fn (): string => $this->record->number)
                        ->helperText('Přiděleno při vystavení faktury.'),

                    Placeholder::make('issued_at')
                        ->label('Vystaveno')
                        ->content(fn (): string => $this->record->issued_at->format('j. n. Y')),
                                                                       // [tl! focus:end]
                    TextInput::make('customer_reference')
                        ->label('Reference zákazníka')
                        ->maxLength(60),

                    TextInput::make('due_days')
                        ->label('Splatnost (dny)')
                        ->numeric(),
                ]),
            ])
            ->successMessage('Faktura upravena');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Ani jeden placeholder se v uložených datech neobjeví — nejsou to pole, takže pro
ně v `$data` není klíč a validace na ně nedosáhne.

## Placeholder API

```php
->content(string|Closure|null $content)   // text; escapuje se, dokud není zapnuté allowHtml()
->allowHtml(bool $condition = true)       // vykreslit obsah jako surové HTML — výchozí false
->html(string|Closure $content)           // content() a allowHtml() jedním voláním
->getContent(): ?string
->isHtmlContent(): bool
```

Popisky, pomocný text, viditelnost a `columnSpan()` jsou sdílený povrch komponent —
viz [Společné API pole](index.md#spolecne-api-pole). Validace, `live()` a výchozí
hodnoty neplatí: placeholder žádný stav nedrží.

## Související

- [Html](html.md) — markup bez popisku a bez tvaru pole kolem
- [ViewField](view-field.md) — když je tou věcí celý Blade partial
- [Alert](alert.md) — tatáž myšlenka uvnitř barevného boxu
- [Hidden](hidden.md) — opak: hodnota, která se veze, ale nikdy neukazuje
