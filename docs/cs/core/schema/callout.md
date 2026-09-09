---
order: 20
summary: Jemný barevný box s upozorněním, nadpisem, ikonou a volitelným zavřením — tělo ze schématu nebo z prostého řetězce.
---

# Callout

Barevné upozornění uvnitř formuláře nebo infolistu — „tohle nejde vzít zpět",
„zkušební období končí za tři dny". Sáhni po něm, když zpráva patří k jedné části
obrazovky a má tam zůstat. Na něco, co se objeví jako reakce na to, co uživatel
právě udělal, a zase zmizí, chceš [notifikaci](../notifications/index.md).

```php
use NyonCode\WireCore\Foundation\Schema\Callout;
```

## Jak to funguje

Callout je **layoutová komponenta**: žádná hodnota, žádná state path, bezpečně se
přidá i odebere bez dotyku na data. Vykreslí jemný orámovaný box s `role="alert"`,
volitelnou ikonou vlevo a volitelným tučným nadpisem nad tělem.

**Tělo má dva zdroje a nejsou si rovné.** Vyhrávají dětské komponenty:

1. Každé viditelné dítě ze `schema()` se vykreslí a spojí dohromady.
2. **Jen když z toho nevzniklo nic**, použije se `content()`.

Callout, který má schéma i řetězec v `content()`, tedy ukáže schéma a řetězec
tiše zahodí. A je mezi nimi ještě druhý rozdíl: děti jsou `Htmlable`, takže se
jejich markup vypíše tak, jak je, kdežto `content()` se **escapuje**.
`content('<strong>Pozor</strong>')` vykreslí ty tagy jako viditelný text. Markup
v calloutu jde přes dětskou komponentu, ne přes `content()`.

**Barevný slovník je kratší, než vypadá.** `getAlertColorClasses()` je uzavřený
`match` nad `success`/`emerald`, `green`, `warning`/`amber`, `yellow`,
`danger`/`red`, `black` a `white` — a **všechno, co nepozná, propadne na
informační modrou**. `->color('primary')` i `->color('purple')` jsou modré, bez
jediné chyby. Prakticky: používej ty čtyři zkratky a cokoli jiného ber jako
přání, které nemusí být vyslyšeno.

**Zavření je Alpine a nepamatuje se.** `dismissible()` obalí box do
`x-data="{ show: true }"` a skryje ho přes `x-show`, takže zavření nestojí žádný
round trip — a při dalším renderu, po `wire:navigate` nebo po round tripu
formuláře, je callout zpátky. Upozornění, které má zůstat zavřené, je stav
aplikace.

Nadpis, ikona i tělo jsou každý volitelný a každý se z markupu nezávisle vynechá,
když chybí; callout bez všech tří je prázdný barevný box.

## Základní použití

```php
Callout::make()
    ->warning()
    ->icon('outline:exclamation-triangle')
    ->heading('Pozor')
    ->content('Tuhle akci nejde vzít zpět.')
```

`title()` je alias `heading()`, když se to tam, kde jsi, čte líp.

## Barvy

```php
Callout::make()->info();      // výchozí — totéž co ->color('info')
Callout::make()->success();
Callout::make()->warning();
Callout::make()->danger();
```

Ty čtyři zkratky jsou celý praktický slovník. `->color()` bere ještě `emerald`,
`green`, `yellow`, `red`, `amber`, `black` a `white`; každá jiná hodnota se
vykreslí jako informační modrá.

## Tělo s markupem

Protože se `content()` escapuje, cokoli bohatšího než věta patří do schématu:

```php
Callout::make()
    ->info()
    ->heading('Fakturace')
    ->schema([                                          // [tl! focus:start]
        Placeholder::make('plan')->content('Pro — obnova 1. března'),
        Placeholder::make('seats')->content('12 z 20 využito'),
    ])                                                   // [tl! focus:end]
```

Pamatuj, že schéma, jakmile něco vykreslí, `content()` úplně nahradí.

## Zavíratelný

```php
Callout::make()
    ->danger()
    ->dismissible()          // [tl! focus]
    ->heading('Platba selhala')
    ->content('Nepodařilo se nám strhnout částku z karty.')
```

## Rozšířený příklad

Callout nahoře ve formuláři, zobrazený jen dokud není účet ověřený:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Callout;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditProfile extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Callout::make()                                   // [tl! focus:start]
                    ->warning()
                    ->icon('outline:exclamation-triangle')
                    ->heading('Tvůj e-mail není ověřený')
                    ->content('Některé funkce zůstanou zamčené, dokud ho nepotvrdíš.')
                    ->visible(fn (): bool => ! auth()->user()->hasVerifiedEmail()),
                                                                   // [tl! focus:end]
                Section::make('profile')
                    ->label('Profil')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

`visible()` je ze sdíleného layoutového povrchu, takže callout zmizí úplně —
i s markupem — jakmile podmínka přestane platit.

## Samostatný tag

Týž markup je i Blade tag, na callout mimo jakékoli schéma:

```blade
<x-wire::callout color="warning" heading="Pozor">
    Tuhle akci nejde vzít zpět.
</x-wire::callout>
```

Ve formuláři je [Alert](../../forms/fields/alert.md) polní alias téhle komponenty
a [modal](../actions/modals.md) používá tutéž paletu.

## Callout API

```php
->heading(string|Closure|null $heading)   // tučný řádek nad tělem
->title(string|Closure|null $title)       // alias heading()
->content(string|Closure|null $content)   // prostý text těla, ESCAPOVANÝ, ignorovaný když se vykreslí schéma
->color(string|Color $color)              // 'info' (výchozí)|'success'|'warning'|'danger'|'emerald'|'green'|'amber'|'yellow'|'red'|'black'|'white' — cokoli jiného propadne na informační modrou
->info()                                  // zkratka pro color('info')
->success()                               // zkratka pro color('success')
->warning()                               // zkratka pro color('warning')
->danger()                                // zkratka pro color('danger')
->icon(string|Icon|null $icon)            // úvodní ikona
->dismissible(bool $condition = true)     // klientské zavření, nepamatuje se — výchozí false
->getHeading(): ?string
->getContent(): ?string
->getColor(): string
->getIcon(): ?string
->isDismissible(): bool
```

Všechno ostatní — `schema()`, `visible()`, `hidden()`, `columnSpan()` — je
společný layoutový povrch. Viz [Společné API layoutů](overview.md#spolecne-api-layoutu).

## Související

- [Schema](overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Empty State](empty-state.md) — druhá prime komponenta
- [Alert](../../forms/fields/alert.md) — týž box jako zobrazovací pole formuláře
- [Notifikace](../notifications/index.md) — na zprávu, která odpovídá na akci
