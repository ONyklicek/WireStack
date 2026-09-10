---
order: 10
summary: Lišta záložek nad sadou panelů, přepínaná v prohlížeči — každý panel zůstává v DOM, takže je validace vidí všechny.
---

# Tabs

Lišta záložek nad sadou panelů. Sáhni po `Tabs`, když jsou skupiny dlouhého
formuláře **alternativy** — Profil / Předvolby / Zabezpečení — a ne posloupnost,
kterou od někoho čekáte projít po pořádku. Když *je* to posloupnost, použijte
[`Wizard`](wizard.md).

```php
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
```

## Jak to funguje

`Tabs` je lišta, každý `Tab` je jeden panel. Obojí jsou layoutové komponenty —
žádná hodnota, žádná state path — takže se záložky dají přidávat, odebírat
i přeskládávat bez dotyku na data.

**Přepínání je Alpine, ne Livewire.** Aktivní index žije v `x-data` na obalu
a každý panel se přepíná přes `x-show`, takže změna záložky je okamžitá a nestojí
žádný round trip. Server se nikdy nedozví, která záložka je otevřená.

**Každý panel zůstává v DOM.** Tohle je ten důležitý důsledek a důvod, proč je
tenhle layout ve formuláři bezpečný: vnořená pole se odesílají a validují
dohromady bez ohledu na to, která záložka je vidět. Povinné pole na třetí záložce
pořád blokuje odeslání a jeho chybová hláška se vykreslí v panelu, kam patří. Nic
se nemusí nejdřív „aktivovat“.

**Vykreslují se jen děti typu `Tab`.** `getTabs()` profiltruje schéma na viditelné
instance `Tab` — cokoli jiného, co dáte přímo do `Tabs::make()->schema([...])`,
třeba holý `TextInput`, se **tiše zahodí**. Pole patří dovnitř záložky, nikdy
vedle ní.

**Skryté záložky se odeberou a zbytek přeindexuje**, takže `activeTab(1)` vždycky
znamená „druhá záložka, která se opravdu vykresluje“. Záložka skrytá podmínkou
nikdy nenechá v liště díru ani neposune aktivní panel na špatný obsah.

Popisek záložky spadne na `Str::headline()` jejího jména, takže `Tab::make('Profile')`
žádné `->label()` nepotřebuje. Na úzkých obrazovkách se lišta scrolluje vodorovně
místo aby se zalamovala — zalomené záložky přirozené šířky vypadají jako dvě
roztřepené řady.

`Tab` rozkládá vlastní děti do gridu, jehož int varianta rozumí **1 až 4**
sloupcům; na cokoli dalšího předejte mapu breakpointů (`['default' => 1, 'lg' => 6]`).

## Základní použití

```php
Tabs::make()->schema([
    Tab::make('Profile')->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ]),
    Tab::make('Preferences')->schema([
        Toggle::make('newsletter'),
    ]),
])
```

## Ikony, sloupce a výchozí záložka

```php
Tabs::make()
    ->activeTab(1)                          // první otevřená je druhá — [tl! focus]
    ->schema([
        Tab::make('Profile')
            ->icon('outline:user')
            ->columns(2)
            ->schema([
                TextInput::make('first_name'),
                TextInput::make('last_name'),
            ]),
        Tab::make('Security')
            ->icon('outline:lock-closed')
            ->schema([
                TextInput::make('password')->password(),
            ]),
    ])
```

`activeTab()` je od nuly a počítá jen viditelné záložky.

## Skrytí záložky

Záložka bere sdílené podmínky `visible()` / `hidden()`, takže celý panel může
patřit jedné roli a jiné ne — jedna podmínka místo téže podmínky na každém poli
uvnitř:

```php
Tab::make('Billing')
    ->visible(fn (): bool => auth()->user()->can('manageBilling'))   // [tl! focus]
    ->schema([...])
```

## Rozšířený příklad

Obrazovka účtu ve skutečném Livewire hostu. Tři panely, jeden z nich podmíněný,
všechny se při odeslání validují dohromady:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditAccount extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Tabs::make()->schema([                              // [tl! focus:start]
                    Tab::make('Profile')
                        ->icon('outline:user')
                        ->columns(2)
                        ->schema([
                            TextInput::make('first_name')->required(),
                            TextInput::make('last_name')->required(),
                            TextInput::make('email')->email()->required()->columnSpanFull(),
                        ]),

                    Tab::make('Preferences')
                        ->icon('outline:adjustments-horizontal')
                        ->schema([
                            Toggle::make('newsletter'),
                            Toggle::make('product_updates'),
                        ]),

                    Tab::make('Billing')
                        ->icon('outline:credit-card')
                        ->visible(fn (): bool => auth()->user()->can('manageBilling'))
                        ->schema([
                            TextInput::make('vat_number'),
                        ]),
                ]),                                                  // [tl! focus:end]
            ])
            ->successMessage('Účet upraven');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Uživatel bez oprávnění na fakturaci vidí dvě záložky a pole té třetí se ani
nevykreslí, ani nevaliduje.

## Tabs API

```php
->activeTab(int $index)      // od nuly, počítá jen viditelné záložky — výchozí 0
->getActiveTab(): int
->getTabs(): array           // viditelné děti typu Tab, přeindexované
```

## Tab API

```php
->icon(string|Icon|null $icon)    // vykreslí se v liště před labelem
->columns(int|array $columns)     // 1 (výchozí); int rozumí 1–4, nebo mapa breakpointů
->getIcon(): ?string
->getColumns(): int|array
```

Obojí nese společný layoutový povrch — `label()`, `schema()`, `visible()`,
`hidden()`, `disabled()`. Viz
[Společné API layoutů](../overview.md#spolecne-api-layoutu).

## Související

- [Schema](../overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Wizard](wizard.md) — tytéž panely jako posloupnost, s validací po krocích
- [Section](section.md) — když jsou skupiny vidět všechny naráz
- [Grid](grid.md) — sloupcový grid, do kterého záložka rozkládá vlastní pole
