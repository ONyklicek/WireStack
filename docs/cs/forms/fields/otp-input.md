---
summary: Kód psaný do samostatných políček, uložený jako jeden prostý řetězec.
---

# OtpInput

Jednorázový kód nebo PIN, psaný do samostatných políček, která se sama posouvají.
Sáhněte po něm, když je hodnota *kód známé délky* — ověřovací kód, PIN, záložní
kód — kde počet políček řekne uživateli, kolik se čeká, ještě než začne psát.

```php
use NyonCode\WireForms\Components\OtpInput;
```

## Jak to funguje

**Políčka jsou prezentace; hodnota je jeden řetězec.** N jednoznakových vstupů se
v prohlížeči spojí a do stavu se zapíše `'283041'`, takže validace, uložení
i každý další konzument vidí obyčejný řetězec.

**Fokus se pohybuje s kódem.** Psaní posune na další políčko, Backspace
v prázdném políčku skočí zpět do předchozího a šipky projdou řadu — kód nikdy
neuvízne v políčku, ze kterého by uživatel klávesnicí neodešel.

**Vložený kód vyplní celou řadu.** Bílé znaky se odstraní, kód se ořízne na délku
pole a kurzor skončí na posledním vyplněném políčku — to je, co dělá „zkopírovat
z SMS a vložit“ jedním gestem místo šesti.

**Na formuláři, který odesílá sám prohlížeč, jsou políčka jen nadstavba.**
Nenesou `name` — šest inputů by odeslalo šest hodnot — takže
v [režimu nativního odeslání](../overview.md#rendering) pole vykreslí vedle nich
jeden skutečný textový input se jménem pole a s hodnotou, kterou políčka spojila.
Alpine ten input schová (`x-show="false"`, ne `type="hidden"`) a políčka drží pod
`x-cloak`, dokud nenaběhne — prohlížeč bez JavaScriptu tedy ukáže obyčejný input
a odešle z něj. Právě to drží dvoufázovou výzvu zodpověditelnou i tam, kde Alpine
nikdy nedorazí, a takhle vykresluje kód z autentizační aplikace
[modul přihlášení](../../modules/auth.md#pole). První políčko nese
`autocomplete="one-time-code"`, takže platforma nabídne kód rovnou ze zprávy.

**Délka je struktura, ne validační pravidlo.** `length()` rozhoduje, kolik
políček se vykreslí, a pole odmítne délku menší než 1 — nulová délka nevykreslí
žádné políčko, což vypadá jako chyba stylů, ne konfigurace. Stejná pojistka
platí pro `separator()`.

**`numericOnly()` filtruje a odmítá bez mazání.** Nastaví
`inputmode="numeric"`, aby telefon otevřel číselnou klávesnici, a zahazuje
nečíslice, jakmile dorazí — napsané i schované ve vloženém řetězci, ze kterého si
nechá číslice a zbytek zahodí. Odmítnutý stisk nechá číslici, která už v políčku
byla, být: políčko se při fokusu označí, takže by ji překlep jinak přepsal. Co je
*platný kód*, zůstává pravidlem, které napíšete (`->rules(['digits:6'])`).

## Základní použití

```php
OtpInput::make('code')
    ->length(6)
```

Uložená hodnota je prostý řetězec: `'283041'`.

## Jen číslice

```php
OtpInput::make('pin')
    ->length(4)
    ->numericOnly()
```

## Maskovaný

```php
OtpInput::make('pin')
    ->length(4)
    ->masked()      // znaky se vykreslí jako u pole s heslem
```

## Vizuální oddělovač

```php
OtpInput::make('code')
    ->length(6)
    ->separator(3)  // [x][x][x] — [x][x][x]
```

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class ConfirmLogin extends Component
{
    use WithForms;

    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                OtpInput::make('verification_code')      // [tl! focus:start]
                    ->label('Kód z SMS')
                    ->length(6)
                    ->numericOnly()
                    ->separator(3)
                    ->required()
                    ->rules(['digits:6'])
                    ->live(),                            // [tl! focus:end]
            ]);
    }

    public function confirm(): void
    {
        $data = $this->form->validate();

        // $data['verification_code'] === '283041'
    }
}
```

`live()` je to, co hostiteli dovolí zareagovat na poslední stisk klávesy —
zkontrolovat kód, jakmile je řada plná, místo čekání na tlačítko odeslat.

## API OtpInput

Část pro kód. Popisek, hint, `required()`, `rules()`, `disabled()`, `live()`
a zbytek jsou sdílené API pole, zdokumentované ve [Formulářová pole](index.md).

```php
->length(int $length)                    // počet políček — výchozí 6, nejméně 1
->numericOnly(bool $condition = true)    // inputmode="numeric", jen číslice
->masked(bool $condition = true)         // vykreslí znaky jako heslo
->separator(int $after)                  // pomlčka po každých N políčkách, nejméně 1
->getLength(): int
->isNumericOnly(): bool
->isMasked(): bool
->getSeparator(): ?int
```

## Související

- [Formulářová pole](index.md) — sdílené API pole
- [TextInput](text-input.md) — pro kód, jehož délka není pevná
- [Validace](../validation.md) — kam patří `digits:6` a spol.
