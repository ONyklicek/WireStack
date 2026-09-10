---
summary: Boolean jako přepínač, s barvami, ikonami a popiskami, které nese každý stav.
---

# Toggle

Přepínač. `Toggle` drží týž boolean jako [`Checkbox`](checkbox.md) a rozdíl je
v tom, co říká uživateli: checkbox je tvrzení, se kterým souhlasíte, toggle je
nastavení, které zapínáte. Obrazovky nastavení chtějí toggly, obchodní podmínky
checkboxy.

```php
use NyonCode\WireForms\Components\Toggle;
```

## Jak to funguje

Toggle je **pole**: hodnota na své state path, validovaná jako kterákoli jiná.
Typ jeho stavu je `bool`, takže prázdné schéma ho naplní na `false`, ne na `null`.

**Samotný přepínač je Alpine.** Vykreslený prvek je `<button role="switch">`,
jehož stav žije v `x-data` provázaném s `wire:model` pole, takže se kolečko posune
hned po kliknutí, ještě před jakýmkoli round tripem. Právě to provázání nese změnu
zpátky na server a ctí `live()` stejně jako pole slideru a tagů.

**Barvy jsou barvy dráhy a vyhodnocují se na serveru.** `onColor()` má výchozí
`primary`, `offColor()` `gray` a obojí se převede na řetězce tříd, mezi kterými
Alpine přepíná. Funguje jakákoli barva z palety — viz
[Barvy](../../core/foundation/colors.md#barvy).

**Popisky on/off jsou jeden element, ne dva.** Span s popiskem se vykreslí, jen
když je nastavený `onLabel()` **nebo** `offLabel()`, a zobrazuje
`enabled ? onLabel : offLabel`. Nastavit jen jeden z nich tedy znamená prázdný
popisek v tom druhém stavu, ne žádný popisek — když nastavíte jeden, nastavte oba.

**Ikony jsou uvnitř kolečka** a na popiskách nezávisejí: `onIcon()` se ukazuje
v zapnutém stavu, `offIcon()` ve vypnutém, každá s `x-cloak`, aby ani jedna
neproblikla dřív, než naběhne Alpine.

**`inline()` tady dneska nedělá nic.** Je deklarované a výchozí hodnota je `true`,
ale `toggle.blade.php` `isInline()` nikdy nečte — konzumuje ho jen
[`Radio`](radio.md) a [`CheckboxList`](checkbox-list.md). Vlastní layout togglu je
pevné `flex items-center gap-3`.

## Základní použití

```php
Toggle::make('is_active')
    ->label('Aktivní')
    ->default(true)
```

## Barvy a ikony

```php
Toggle::make('notifications_enabled')
    ->label('Notifikace')
    ->onColor('success')       // [tl! focus:start]
    ->offColor('danger')
    ->onIcon('outline:check')
    ->offIcon('outline:x-mark')  // [tl! focus:end]
```

## Slova pro každý stav

Nastavte **oba**, nebo žádný:

```php
Toggle::make('visibility')
    ->label('Zveřejnění')
    ->onLabel('Veřejné')       // [tl! focus]
    ->offLabel('Soukromé')     // [tl! focus]
```

## Reakce na něj

```php
Toggle::make('advanced_mode')
    ->label('Pokročilý režim')
    ->live(),                                   // [tl! focus]

TextInput::make('webhook_url')
    ->url()
    ->visibleWhen('advanced_mode'),             // [tl! focus]
```

## Rozšířený příklad

Obrazovka nastavení notifikací ve skutečném Livewire hostu, kde jeden toggle řídí
zbytek:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class NotificationSettings extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Toggle::make('notifications_enabled')                 // [tl! focus:start]
                    ->label('Posílat mi notifikace')
                    ->onLabel('Zapnuto')
                    ->offLabel('Vypnuto')
                    ->onColor('success')
                    ->live(),

                Section::make('channels')
                    ->label('Kanály')
                    ->visibleWhen('notifications_enabled')
                    ->schema([
                        Toggle::make('notify_email')->label('E-mail'),
                        Toggle::make('notify_sms')->label('SMS'),
                        Toggle::make('notify_push')
                            ->label('Push')
                            ->disabled(fn (): bool => ! auth()->user()->hasDevice()),
                    ]),                                                // [tl! focus:end]
            ])
            ->successMessage('Nastavení uloženo');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Hlavní toggle je `live()`, protože sekce pod ním je podmíněná jeho hodnotou;
vnitřní toggly ne, protože při jejich změně se nic nepřekresluje.

## Toggle API

```php
->onLabel(string|Closure|null $label)     // slovo v zapnutém stavu — nastav ho spolu s offLabel
->offLabel(string|Closure|null $label)    // slovo ve vypnutém stavu
->onColor(string|Color $color)            // barva dráhy v zapnutém stavu — výchozí 'primary'
->offColor(string|Color $color)           // barva dráhy ve vypnutém stavu — výchozí 'gray'
->onIcon(string|Icon|null $icon)          // ikona v kolečku v zapnutém stavu
->offIcon(string|Icon|null $icon)         // ikona v kolečku ve vypnutém stavu
->inline(bool $condition = true)          // deklarované, ale view tohohle pole to nečte
->getOnLabel(): ?string
->getOffLabel(): ?string
->getOnColor(): string
->getOffColor(): string
->getOnIcon(): ?string
->getOffIcon(): ?string
->getOnColorClasses(): string
->getOffColorClasses(): string
->isInline(): bool
->getStateType(): string                  // 'bool'
```

Popisky, nápovědu, viditelnost, výchozí hodnoty, validaci a `live()` sdílí každé
pole — viz [Společné API pole](index.md#spolecne-api-pole).

## Související

- [Checkbox](checkbox.md) — týž boolean jako tvrzení, se kterým se souhlasí
- [Barvy](../../core/foundation/colors.md#barvy) — paleta, ze které `onColor()` čerpá
- [Ikony](../../core/foundation/icons.md) — názvy, které `onIcon()` bere
- [Reaktivní pole](../reactive-fields.md) — co dělá `live()` a `visibleWhen()`
