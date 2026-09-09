---
order: 21
api_class: NyonCode\WireCore\Foundation\Schema\EmptyState
summary: "Co povrch ukáže, když není co ukázat: ikona, nadpis, věta a akce, které to napraví."
---

# Empty State

Obrazovka, kterou uživatel potká dřív, než cokoli vytvoří. `EmptyState` je
vycentrovaná ikona, nadpis, věta a — to podstatné — tlačítka, která ho z toho
dostanou. Sáhni po něm všude, kde seznam legitimně může být prázdný, a řekni, co
dělat dál, ne jenom to, že tu nic není.

```php
use NyonCode\WireCore\Foundation\Schema\EmptyState;
```

## Jak to funguje

Empty state je **layoutová komponenta**: žádná hodnota, žádná state path.
Vykreslí jeden vycentrovaný sloupec — jemné šedé kolečko s ikonou, pak nadpis, pak
popis a pak řádek akcí.

**Každá část je volitelná a vynechá se nezávisle.** Žádná ikona, žádné kolečko.
Ani nadpis, ani popis, a zmizí celý textový blok. Žádné akce, žádný řádek
tlačítek. `EmptyState::make()` bez čehokoli nastaveného vykreslí prázdný
vycentrovaný `div`, a proto je užitečné minimum nadpis plus jedna akce.

**Popis dostane horní odsazení jen tehdy, když je nad ním nadpis**, takže popis
sám o sobě sedí tam, kde by byl nadpis, místo aby se vznášel pod mezerou.

**Akce jsou předrenderované HTML, ne objekty akcí.** Tohle lidi překvapí a stojí
za to být přesný: setter je typovaný `Htmlable|string`, ale render cesta každou
položku přetypuje přes `(string) $action`. Objekt se tedy přijme jen tehdy, když
má `__toString()` — a `Action` z tohohle frameworku implementuje `Htmlable`
**bez** toho, aby byl `Stringable`, takže předání akce napřímo vyhodí:

```text
Error: Object of class NyonCode\WireCore\Actions\Action could not be converted to string
```

Předej ji vykreslenou — `->toHtml()`, nebo vlastní Blade řetězec:

```php
->actions([$action->toHtml()])
```

Stav „žádné záznamy" u tabulky i samostatný tag `<x-wire::empty-state>` se
vykreslují přes tentýž partial, takže cokoli se tady naučíš, platí ve všech třech.

## Základní použití

```php
EmptyState::make()
    ->icon('outline:inbox')
    ->heading('Zatím žádné objednávky')
    ->description('Objednávky se tu objeví, jakmile první zákazník dokončí nákup.')
```

## S tím, co se s tím dá dělat

```php
use NyonCode\WireCore\Actions\Action;

EmptyState::make()
    ->icon('outline:users')
    ->heading('Žádní členové týmu')
    ->description('Pozvi někoho ke spolupráci na tomhle projektu.')
    ->actions([
        Action::make('invite')->label('Pozvat kolegu')->toHtml(),   // [tl! focus]
    ])
```

Všimni si toho `->toHtml()`. Když předáš víc tlačítek, zalomí se na další řádky
a řádek je vycentrovaný pod popisem.

## Closure místo řetězce

`heading()` i `description()` berou closure, vyhodnocovanou při čtení, takže
zpráva může záviset na tom, na co se uživatel dívá:

```php
EmptyState::make()
    ->icon('outline:magnifying-glass')
    ->heading(fn (): string => "Ničemu neodpovídá \"{$this->search}\"")   // [tl! focus]
    ->description('Zkus kratší dotaz nebo zruš filtry.')
```

## Rozšířený příklad

Panel dashboardu, který ukáže buď svůj obsah, nebo empty state, uvnitř skutečného
Livewire hostu:

```php
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Schema\EmptyState;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class ProjectOverview extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('members')
                    ->label('Tým')
                    ->schema([
                        EmptyState::make()                                  // [tl! focus:start]
                            ->icon('outline:users')
                            ->heading('Zatím žádní členové týmu')
                            ->description('Pozvi někoho ke spolupráci na tomhle projektu.')
                            ->actions([
                                Action::make('invite')
                                    ->label('Pozvat kolegu')
                                    ->action(fn () => $this->invite())
                                    ->toHtml(),
                            ])
                            ->visible(fn (): bool => $this->members->isEmpty()),
                    ]),                                                      // [tl! focus:end]
            ]);
    }

    public function invite(): void
    {
        // ...
    }
}
```

`visible()` je ze sdíleného layoutového povrchu, takže empty state z markupu úplně
zmizí, jakmile je co ukazovat.

## Samostatný tag

Týž partial jako Blade tag, pro povrch, který žádné schéma nemá:

```blade
<x-wire::empty-state icon="outline:inbox" heading="Zatím žádné záznamy" />
```

Vlastní empty state tabulky se konfiguruje na tabulce, ne staví tady — viz
[Tabulky](../../table/overview.md).

## EmptyState API

```php
->icon(string|Icon|null $icon)                     // v jemném kolečku nad nadpisem
->heading(string|Closure|null $heading)            // hlavní řádek
->description(string|Closure|null $description)    // vedlejší řádek pod ním
->actions(array $actions)                          // předrenderované HTML řetězce — viz Jak to funguje
->getIcon(): ?string
->getHeading(): ?string
->getDescription(): ?string
->getActionsHtml(): array
```

Všechno ostatní — `visible()`, `hidden()`, `columnSpan()`, `schema()` — je
společný layoutový povrch. Viz [Společné API layoutů](overview.md#spolecne-api-layoutu).

## Související

- [Schema](overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Callout](callout.md) — druhá prime komponenta
- [Akce](../actions/index.md) — co patří do řádku tlačítek
- [Tabulky](../../table/overview.md) — vlastní stav „žádné záznamy" u tabulky
