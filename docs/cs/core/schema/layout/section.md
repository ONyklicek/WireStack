---
order: 10
summary: Sbalitelná sekce s nadpisem, popisem a ikonou, která seskupuje komponenty pod sebou.
---

# Section

Karta s nadpisem. `Section` je tahoun každého formuláře, který je dost dlouhý na
scrollování: dá skupině polí titulek, volitelnou větu, která ji vysvětlí, vlastní
sloupcový grid, tlačítka v hlavičce a — to, po čem lidi opravdu sahají —
skládání, aby části formuláře, do kterých uživatel chodí zřídka, mohly začít
z cesty.

```php
use NyonCode\WireCore\Foundation\Schema\Section;
```

## Jak to funguje

Sekce je **layoutová komponenta**: žádná hodnota, žádná state path, bezpečně se
přidá i odebere bez dotyku na data. Vykreslí orámovanou kartu s odsazením,
volitelný hlavičkový blok a pod ním děti v gridu.

**Hlavička existuje, jen když do ní něco jde.** Žádný label, žádný popis a žádné
hlavičkové akce znamená, že hlavičkový blok nevznikne vůbec — holá karta. Stojí za
to to vědět, protože `Section::make('billing')` samo o sobě ze svého jména nadpis
*neudělá* tak, jak to dělá pole. Napiš `->label('Fakturace')`.

**Skládání je Alpine, ne Livewire.** `collapsible()` dá na kartu
`x-data="{ open }"` a na tělo `x-show` + `x-collapse`, takže otevírání a zavírání
je okamžité a nestojí žádný round trip. Plynou z toho dvě věci:

- **Složení se neukládá.** Sekce se po `wire:navigate` nebo po plném načtení
  stránky vrátí do deklarovaného stavu. Když má uživatelova volba přežít, je to
  stav aplikace, ne nastavení sekce.
- **Přepínačem je celá hlavička** — nese `@click`, `role="button"` a
  `:aria-expanded`. Hlavičkové akce jsou zabalené v `@click.stop`, takže stisk
  jedné z nich spustí akci místo složení karty; přesně tuhle chybu to hlídá.

**`collapsed()` implikuje `collapsible()`.** Nastavit jen „začíná složená" by
uživateli dalo sekci, kterou nejde otevřít, takže ten druhý přepínač concern
zapne za tebe. Obojí bere closure, vyhodnocovanou při čtení — sekce, která se
skládá jedné roli a jiné ne, je podmínka, ne konstanta.

**Sloupce se vyhodnocují přes kanonický `ResponsiveGrid`**, přesně jako
u [`Gridu`](grid.md): int je mobile-first přeskládání na `md`, mapa
(`['default' => 1, 'lg' => 3]`) si každý breakpoint řekne sama, počty ořezané na
1–12. Výchozí je **1 sloupec**. (Právě tady se `Section` a
[`Fieldset`](fieldset.md) liší — fieldset si int variantu pořád řeší lokálně
a končí u čtyř.)

`aside()` z karty udělá od `md` nahoru třísloupcový grid: hlavička zabere jeden
sloupec, tělo dva. Pod `md` se to skládá pod sebe, takže je to tvar jen pro
desktop. Skládání, sloupcový grid i hlavičkové akce uvnitř fungují dál, protože
to obaluje dva bloky, které už existují, místo aby je nahrazovalo.

`compact()` je jen odsazení — `p-3` místo `p-4 sm:p-6`.

## Základní použití

```php
Section::make('personal')
    ->label('Osobní údaje')
    ->description('Základní informace o uživateli.')
    ->icon('outline:user')
    ->columns(2)
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
```

## Skládání

```php
Section::make('advanced')
    ->label('Pokročilá nastavení')
    ->collapsed()          // [tl! focus]
    ->schema([
        Toggle::make('debug_mode'),
        TextInput::make('webhook_url')->url(),
    ])
```

`collapsed()` je to, co napíšeš, když chceš mít sekci zpočátku z cesty. Samotné
`collapsible()` použij, když má začít otevřená, ale jít složit. Obojí bere closure:

```php
Section::make('internal')
    ->label('Interní poznámky')
    ->collapsed(fn (): bool => ! auth()->user()->isAdmin())   // [tl! focus]
    ->schema([...])
```

## Tlačítka v hlavičce

`headerActions()` dá [akce](../../actions/index.md) vedle nadpisu — „Vygenerovat
klíč znovu", „Poslat testovací e-mail", věci, které patří téhle skupině, ne
formuláři jako celku:

```php
Section::make('api')
    ->label('Přístup k API')
    ->headerActions([                                     // [tl! focus:start]
        Action::make('regenerate')
            ->label('Vygenerovat token znovu')
            ->requiresConfirmation()
            ->action(fn () => $this->regenerateToken()),
    ])                                                     // [tl! focus:end]
    ->schema([
        TextInput::make('api_token')->disabled(),
    ])
```

Je to alias sdíleného `actions()` se sémantikou hlavičkového slotu.

## Nadpis vedle polí

`aside()` přesune nadpis a popis do levého sloupce a pole doprava — tvar, který
používají obrazovky nastavení, když každá skupina potřebuje větu vysvětlení:

```php
Section::make('notifications')
    ->label('Notifikace')
    ->description('Vyber, jak a kdy tě máme kontaktovat.')
    ->aside()                                              // [tl! focus]
    ->schema([
        Toggle::make('email_notifications'),
        Toggle::make('sms_notifications'),
    ])
```

Třetina nadpis, dvě třetiny pole, od `md` nahoru; pod ním pod sebou.

## Rozšířený příklad

Obrazovka nastavení ve skutečném Livewire hostu: jedna obyčejná sekce, jedna
aside a jedna, která začíná složená.

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class AccountSettings extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Section::make('profile')                         // [tl! focus:start]
                    ->label('Profil')
                    ->icon('outline:user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                    ]),

                Section::make('notifications')
                    ->label('Notifikace')
                    ->description('Vyber, jak a kdy tě máme kontaktovat.')
                    ->aside()
                    ->schema([
                        Toggle::make('email_notifications'),
                        Toggle::make('sms_notifications'),
                    ]),

                Section::make('danger')
                    ->label('Pokročilé')
                    ->collapsed()
                    ->schema([
                        TextInput::make('webhook_url')->url(),
                    ]),                                           // [tl! focus:end]
            ])
            ->successMessage('Nastavení uloženo');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

## Section API

```php
->description(string|Closure|null $description)  // věta pod nadpisem
->icon(string|Icon|null $icon)                   // ikona před textem nadpisu
->columns(int|array $columns)                    // 1 (výchozí), nebo ['default' => 1, 'lg' => 3]
->collapsible(bool|Closure $condition = true)    // hlavička kartu skládá
->collapsed(bool|Closure $condition = true)      // začíná složená — implikuje collapsible()
->compact(bool $condition = true)                // těsnější odsazení
->aside(bool $condition = true)                  // nadpis vedle polí od md nahoru
->headerActions(array $actions)                  // ActionContract[] v hlavičce; alias actions()
->getDescription(): ?string
->getIcon(): ?string
->getColumns(): int|array
->getHeaderActions(): array
->isCompact(): bool
->isAside(): bool
->isCollapsible(): bool
->isCollapsed(): bool
```

Všechno ostatní — `label()`, `schema()`, `visible()`, `disabled()`,
`columnSpan()` — je společný layoutový povrch. Viz
[Společné API layoutů](../overview.md#spolecne-api-layoutu).

## Související

- [Schema](../overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Grid](grid.md) — týž sloupcový grid, bez karty
- [Fieldset](fieldset.md) — legenda místo nadpisu, když je seskupení sémantické
- [Tabs](tabs.md) — když jsou skupiny alternativy, ne posloupnost
- [Akce](../../actions/index.md) — co patří do `headerActions()`
