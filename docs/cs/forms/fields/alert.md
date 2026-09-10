---
summary: "Callout uvnitř formuláře: zpráva s barvou, ikonou a volitelným zavřením, vykreslená tam, kde by bylo pole."
---

# Alert

Barevné upozornění, které sedí ve schématu tam, kde by bylo pole. Sáhni po
`Alertu`, když formulář potřebuje něco říct na místě — „tenhle účet je
pozastavený“, „změny tady ovlivní každého uživatele“ — a ne až po akci, na což je
[notifikace](../../core/notifications/index.md).

```php
use NyonCode\WireForms\Components\Display\Alert;
```

## Jak to funguje

Alert je **zobrazovací komponenta**: vykresluje výstup a nikdy se neváže na stav
formuláře. Nemá state path, neúčastní se validace a dá se přidat i odebrat bez
dotyku na data.

**Je to polní alias [`Calloutu`](../../core/schema/callout.md)**, a to není
obrazné vyjádření — obojí vykresluje `wire-core::partials.callout`, takže box,
místo pro ikonu, `role="alert"` i tlačítko zavření jsou doslova týž markup.
`Alert` používejte uvnitř schématu formuláře, `Callout` ve sdíleném schema
slovníku; cokoli platí o jednom, platí o druhém.

**`message()` je `content()`.** Volá přímo skrz, takže jsou zaměnitelné a když
nastavíte obojí, vyhraje to druhé. Ve formuláři se `message()` čte líp.

**Tělo se escapuje.** View vypisuje `e($field->getContent())`, takže
`message('<strong>Pozor</strong>')` vykreslí ty tagy jako viditelný text. Alert je
na větu; markup patří do [`Html`](html.md) nebo [`ViewFieldu`](view-field.md).

**Barevný slovník je kratší, než vypadá**, a tohle je ta past, kterou je dobré
znát. Barvy se řeší přes sdílenou alert paletu, uzavřený `match` nad
`success`/`emerald`, `green`, `warning`/`amber`, `yellow`, `danger`/`red`,
`black` a `white`. **Všechno ostatní propadne na informační modrou** —
`->color('primary')` i `->color('purple')` se vykreslí modře, bez jediné chyby.
Používejte ty čtyři zkratky a cokoli jiného ber jako přání, které nemusí být
vyslyšeno.

**Zavření je Alpine a nepamatuje se.** Zavření skryje box na klientovi bez round
tripu a při dalším renderu je alert zpátky — po `wire:navigate` nebo po jakémkoli
round tripu formuláře. Upozornění, které má zůstat zavřené, je stav aplikace, ne
nastavení alertu.

## Základní použití

```php
Alert::make('irreversible')
    ->warning()
    ->icon('outline:exclamation-triangle')
    ->title('Tohle nejde vzít zpět')
    ->message('Smazáním projektu zmizí i každý úkol v něm.')
```

## Barvy

```php
Alert::make('a')->info();      // výchozí
Alert::make('a')->success();
Alert::make('a')->warning();
Alert::make('a')->danger();
```

`->color()` bere ještě `emerald`, `green`, `yellow`, `red`, `amber`, `black`
a `white`. Cokoli mimo ten seznam je informační modrá.

## Zavíratelný

```php
Alert::make('tip')
    ->info()
    ->dismissible()          // [tl! focus]
    ->message('Řádky můžeš přeskládat tažením.')
```

## Zobrazení, jen když to platí

`visible()` je sdílený povrch, který nese každá komponenta, takže se alert objeví
a zmizí podle stavu formuláře místo aby se vykresloval prázdný:

```php
Alert::make('suspended')
    ->danger()
    ->title('Účet pozastaven')
    ->message('Tenhle uživatel se nepřihlásí, dokud ho správce neobnoví.')
    ->visible(fn (): bool => $this->record?->is_suspended ?? false)   // [tl! focus]
```

## Rozšířený příklad

Editační formulář ve skutečném Livewire hostu, s jedním alertem, který je tam
vždycky, a jedním, který se objeví jen u pozastavených účtů:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\Display\Alert;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditUser extends Component
{
    use WithForms;

    public User $record;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Alert::make('suspended')                            // [tl! focus:start]
                    ->danger()
                    ->icon('outline:no-symbol')
                    ->title('Účet pozastaven')
                    ->message('Tenhle uživatel se nepřihlásí, dokud ho neobnovíš.')
                    ->visible(fn (): bool => $this->record->is_suspended),

                Alert::make('scope')
                    ->info()
                    ->dismissible()
                    ->message('Změny se projeví při příštím přihlášení uživatele.'),
                                                                     // [tl! focus:end]
                Section::make('profile')
                    ->label('Profil')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                        Toggle::make('is_suspended')->label('Pozastaven')->live(),
                    ]),
            ])
            ->successMessage('Uživatel upraven');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Toggle je `live()`, takže se alert nad ním objeví ve chvíli, kdy je účet
pozastavený, ne až při dalším round tripu.

## Alert API

```php
->message(string|Closure|null $message)   // tělo — alias content(), a escapuje se
->content(string|Closure|null $content)   // totéž pod sdíleným názvem
->title(string|Closure|null $title)       // tučný řádek nad tělem
->color(string|Color $color)              // 'info' (výchozí)|'success'|'warning'|'danger'|'emerald'|'green'|'amber'|'yellow'|'red'|'black'|'white' — cokoli jiného je informační modrá
->info()                                  // zkratka pro color('info')
->success()                               // zkratka pro color('success')
->warning()                               // zkratka pro color('warning')
->danger()                                // zkratka pro color('danger')
->icon(string|Icon|null $icon)            // úvodní ikona
->dismissible(bool $condition = true)     // klientské zavření, nepamatuje se — výchozí false
->getTitle(): ?string
->getContent(): ?string
->getColor(): string
->getColorClasses(): string
->getIcon(): ?string
->isDismissible(): bool
```

Popisky, pomocný text, viditelnost a `columnSpan()` jsou sdílený povrch komponent —
viz [Společné API pole](index.md#spolecne-api-pole). Validace, `live()` a výchozí
hodnoty neplatí: alert žádný stav nedrží.

## Související

- [Callout](../../core/schema/callout.md) — týž box ve sdíleném schema slovníku
- [Placeholder](placeholder.md) — řádek textu bez boxu kolem
- [Html](html.md) — když zpráva potřebuje markup
- [Notifikace](../../core/notifications/index.md) — na zprávu, která odpovídá na akci
