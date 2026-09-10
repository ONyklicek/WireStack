---
order: 10
summary: Indikátor kroků nad sadou panelů s Předchozí a Další — samostatný protějšek wizardu v modalu akce.
---

# Wizard

Posloupnost. `Wizard` jsou [`Tabs`](tabs.md) pro případ, kdy panely mají pořadí
a chcete, aby jimi uživatel procházel po jednom — registrační tok, import, cokoli
dost dlouhého na to, že ukázat to celé naráz by lidi odradilo. Je to samostatný
protějšek [wizardu v modalu akce](../../modals.md#vicekrokovy-wizard).

```php
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
```

## Jak to funguje

`Wizard` je indikátor a navigace, každý `Step` je jeden panel. Obojí jsou
layoutové komponenty — žádná hodnota, žádná state path.

**Každý krok zůstává v DOM**, přesně jako záložka, takže se vnořená pole při
finálním odeslání odesílají a validují dohromady bez ohledu na to, který krok je
vidět. Nic se nemusí nejdřív navštívit, aby data platila.

**Vykreslují se jen děti typu `Step`.** `getSteps()` profiltruje schéma na
viditelné instance `Step`, takže pole vložené přímo do
`Wizard::make()->schema([...])` se tiše zahodí. Pole patří dovnitř kroku.

**Pojmenujte wizard, když můžou být dva na obrazovce naráz.** Kroky se adresují
jménem wizardu, takže `Wizard::make('signup')` validuje nezávisle na jiném vedle
sebe; nepojmenovaný wizard se vyhodnotí na první ve schématu a dva nepojmenované
sdílejí prázdný scope.

Na desktopu nese každé kolečko indikátoru popisek a popis svého kroku; na mobilu se
indikátor scvrkne na číslovaná kolečka a popisek i popis aktivního kroku jsou pod
ním. Vlastní děti kroku se rozkládají do gridu, jehož int varianta rozumí **1 až
4** sloupcům — na víc předejte mapu breakpointů.

## Základní použití

```php
Wizard::make()->schema([
    Step::make('Account')->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ]),
    Step::make('Company')->schema([
        TextInput::make('company_name'),
    ]),
])
```

## Popisky kroků

```php
Wizard::make()->schema([
    Step::make('Account')
        ->description('Jak se k tobě dostaneme')     // [tl! focus]
        ->icon('outline:user')
        ->columns(2)
        ->schema([
            TextInput::make('first_name')->required(),
            TextInput::make('last_name')->required(),
        ]),
    Step::make('Company')
        ->description('Fakturační údaje')            // [tl! focus]
        ->icon('outline:building-office')
        ->schema([
            TextInput::make('vat_number'),
        ]),
])
```

## Validace po krocích

**Další validuje dřív, než postoupí — na serveru.** Uvnitř Livewire hostu
(`WithForms` nebo modal tabulkové akce) spustí stisk Další pravidla, která
deklarují pole aktuálního kroku — `rules()`, `required()`, pravidla položek
repeateru — zúžená na ten krok. Při neúspěchu wizard zůstane, kde je, chyby se
vykreslí v aktivním panelu a kroky, na které uživatel ještě nedošel, se nikdy
neoznačí předčasně.

Plynou z toho tři chování, která byste jinak museli postavit sami:

- **Neúspěšné odeslání skočí na první krok s chybou**, takže hláška z prvního
  kroku nikdy neuvízne v panelu, na který se nikdo nedívá.
- **Dynamické kroky drží krok.** Když podmínka `visible()` uprostřed formuláře
  krok přidá nebo odebere — po round tripu pole s `live()` — indikátor i navigace
  se přerovnají a aktivní krok se ořízne do vykresleného rozsahu.
- **Skok přes indikátor u `skippable()` validaci přeskočí**, stejně jako
  u Filamentu. Skippable znamená „nech mě podívat se dopředu“, ne „nech mě
  odeslat neúplné“.

Vykreslený **mimo** Livewire host spadne Další na obyčejnou klientskou navigaci
a formulář se validuje při odeslání jako dřív.

## Začít jinde a nechat lidi přeskakovat

```php
Wizard::make('onboarding')
    ->activeStep(1)     // otevřít na druhém kroku — [tl! focus]
    ->skippable()       // indikátor je klikatelný, bez validace — [tl! focus]
    ->schema([...])
```

`activeStep()` je od nuly a počítá jen viditelné kroky.

## Předání navigace jinam

`navigation(false)` vykreslí wizard bez jeho řádku Předchozí / Další — pro
povrch, který chce tyhle prvky ve vlastním rámu, třeba patičku modalu nebo
toolbar stránky — aby na obrazovce neseděly dvě navigace naráz:

```php
Wizard::make('category')
    ->navigation(false)          // [tl! focus]
    ->schema([
        Step::make('Name')->schema([TextInput::make('label')->required()]),
        Step::make('Detail')->schema([TextInput::make('note')]),
    ])
```

Stav kroku pořád vlastní wizard; vnější povrch ho zrcadlí a posouvá přes dvě
window události, protože řídící patička je *sourozenecký* podstrom a bublající
událost by se k ní nikdy nedostala:

- `wire-wizard-state` — wizard ji publikuje, kdykoli se změní krok, počet nebo
  příznak validace: `{ wizard, step, total, validating }`.
- `wire-wizard-navigate` — posílá se wizardu, aby se pohnul: `{ wizard, direction }`,
  kde direction je `'next'` nebo `'previous'`. `'next'` spustí tutéž validaci
  kroku jako vestavěné tlačítko, takže externí ovládání hlídá stejně.

Obojí je zúžené přes `wizard` — jméno wizardu, `null` u nepojmenovaného.

[Modal s možnostmi u `Selectu`](../../../forms/fields/select.md#plnohodnotny-formular-ne-seznam-poli)
tohle dělá za vás: dejte wizard s `navigation(false)` do `createOptionForm()`
a patička modalu se toho ujme — ukazuje Zpět / Další až do posledního kroku
a odesílací tlačítko až tam.

## Rozšířený příklad

Onboardingový tok ve skutečném Livewire hostu, s krokem, který se objeví jen
u firemních účtů:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class Onboarding extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Account::class)
            ->schema([
                Wizard::make('onboarding')->schema([              // [tl! focus:start]
                    Step::make('Account')
                        ->description('Jak se k tobě dostaneme')
                        ->icon('outline:user')
                        ->columns(2)
                        ->schema([
                            TextInput::make('name')->required(),
                            TextInput::make('email')->email()->required(),
                            Select::make('type')
                                ->options(['personal' => 'Osobní', 'business' => 'Firemní'])
                                ->live()
                                ->columnSpanFull(),
                        ]),

                    Step::make('Company')
                        ->description('Fakturační údaje')
                        ->icon('outline:building-office')
                        ->visible(fn (array $get): bool => $get('type') === 'business')
                        ->schema([
                            TextInput::make('company_name')->required(),
                            TextInput::make('vat_number')->required(),
                        ]),

                    Step::make('Done')
                        ->description('Zkontrolovat a dokončit')
                        ->schema([
                            TextInput::make('referral_code'),
                        ]),
                ]),                                                // [tl! focus:end]
            ])
            ->successMessage('Vítej');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Select typu je `live()`, takže volba „Firemní“ firemní krok hned přidá; „Osobní“
ho odebere a indikátor se přečísluje. Další nepustí z kroku účtu dřív, než jsou
jméno a e-mail platné.

## Wizard API

```php
->activeStep(int $index)             // od nuly, jen viditelné kroky — výchozí 0
->skippable(bool $condition = true)  // indikátor přeskakuje kroky bez validace — výchozí false
->navigation(bool $condition = true) // false nevykreslí řádek Předchozí/Další — výchozí true
->getActiveStep(): int
->isSkippable(): bool
->hasNavigation(): bool
->getSteps(): array                  // viditelné děti typu Step, přeindexované
```

## Step API

```php
->description(string|Closure|null $description)  // druhý řádek pod labelem kroku
->icon(string|Icon|null $icon)                   // ikona v indikátoru
->columns(int|array $columns)                    // 1 (výchozí); int rozumí 1–4, nebo mapa breakpointů
->getDescription(): ?string
->getIcon(): ?string
->getColumns(): int|array
```

Obojí nese společný layoutový povrch — `label()`, `schema()`, `visible()`,
`hidden()`, `disabled()`. Viz
[Společné API layoutů](../overview.md#spolecne-api-layoutu).

## Související

- [Schema](../overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Tabs](tabs.md) — tytéž panely, když jsou alternativy, ne posloupnost
- [Modaly — vícekrokový wizard](../../modals.md#vicekrokovy-wizard) — týž layout uvnitř akce
- [Section](section.md) — seskupení, které nic neskrývá
