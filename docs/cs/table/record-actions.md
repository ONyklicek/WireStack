---
order: 45
---

# Celořádkové akce (Record Actions)

Celořádkové akce promění celý řádek v ovládací prvek: dvojklik otevře záznam,
pravé tlačítko zobrazí menu, šipky přesouvají mezi řádky a Enter spustí hlavní
akci — jako v desktopové aplikaci. Jsou samostatnou skupinou vedle řádkových
`->actions()`, `->bulkActions()` a `->headerActions()` a běží přes stejnou
exekuci jako každá jiná akce, takže autorizace, potvrzovací modaly i formuláře
fungují beze změny.

## Základní použití

Naváž akci na celořádkové gesto. Trigger je součástí bindingu, takže fluent
metody se čtou jako to gesto:

```php
use NyonCode\WireCore\Actions\Action;

->recordActions([
    Action::make('view')->onClick(),

    Action::make('edit')
        ->icon('pencil')
        ->onDoubleClick()
        ->action(fn (User $record) => $this->edit($record)),
])
```

`Action::make(...)->onDoubleClick()` vrací **record action**, takže patří do
`recordActions()`, nikdy do `->actions()`.

### Reference na existující akci

Pokud akce už existuje v `->actions()`, jen ji pojmenuj místo nové definice:

```php
->actions([
    Action::make('edit')->action(fn (User $record) => /* … */),
])
->recordAction('edit') // dvojklik spustí tutéž akci 'edit'
```

Samotné jméno má výchozí trigger dvojklik.

## Triggery

| Trigger | Metoda | Typické použití |
|---------|--------|-----------------|
| Klik | `->onClick()` | Otevřít záznam, když tabulka nemá výběr |
| Dvojklik | `->onDoubleClick()` | Otevřít / editovat — doporučený primární |
| Pravé tlačítko | `->onContextMenu()` | Kontextové menu řádku |
| Klávesa | `->onKey('Delete')` | Klávesová zkratka proti aktivnímu řádku |

`->onKey()` je cukr nad kanonickým `->keyboardShortcut()`, takže `mod+d`, `ctrl+c`
i jednotlivé klávesy se resolvují stejně (⌘ na Macu, Ctrl jinde). Jeden binding
může nést víc triggerů:

```php
Action::make('edit')->onDoubleClick()->onKey('Enter')
```

## Jen chování vs. i tlačítko

Record action se ve výchozím stavu **nerenderuje jako tlačítko** — je to čisté
chování řádku. Právě to dělá z tabulky appku místo mřížky tlačítek:

```php
Action::make('open')
    ->onDoubleClick()
    ->behaviorOnly() // výchozí; řádek je jediný ovládací prvek
```

Opt-in pro *zároveň* zobrazení ve sloupci akcí:

```php
Action::make('edit')->onDoubleClick()->alsoInRowActions()
```

## Více celořádkových akcí

```php
->recordActions([
    Action::make('view')->onClick(),
    Action::make('edit')->onDoubleClick(),
    Action::make('preview')->onContextMenu(),
])
```

## Ovládání klávesnicí

Jakmile má tabulka libovolnou record action, klávesová navigace se zapne
automaticky a tabulka se ohlásí jako grid:

| Klávesa | Akce |
|---------|------|
| `↑` / `↓` | Posun aktivního řádku |
| `Enter` | Primární record action (binding dvojkliku, jinak kliku) |
| `Shift` + `Enter` | Sekundární record action (druhý pointer binding) |
| `Space` | Přepnout výběr aktivního řádku (a nastavit kotvu) při selectable, jinak primární akce |
| `Shift` + `↑` / `↓` | Rozšířit souvislý výběr od kotvy (desktopový range-select) |
| `mod` + `A` | Vybrat všechny řádky na stránce |
| Menu klávesa | Otevřít kontextové menu řádku |
| `Delete`, `mod+d`, … | Vlastní `->onKey()` / `->keyboardShortcut()` akce |

Klávesnicový výběr řídí **stejný** stav výběru jako checkboxy a bulk bar — šipkou
na řádek, `Space` pro výběr, `Shift`+šipka pro rozšíření bloku — pak spusť
hromadnou akci z baru.

Vynuť vypnutí (či zapnutí), pokud potřebuješ:

```php
->recordActionKeyboard(false)
```

Protože Enter vždy dosáhne na primární akci, každá record action zůstává
dostupná klávesnicí — behavior-only akce nikdy není past jen pro myš.

## Kombinace s výběrem a hromadnými akcemi

Když je tabulka `->selectable()`, jednoklik dál vybírá řádek, takže výchozí
trigger record akce se stává **dvojklik** — nekolidují spolu. Klik na checkbox
jen přepne výběr a hromadné akce zůstávají nedotčené:

```php
->selectable()
->bulkActions([DeleteBulkAction::make()])
->recordActions([
    Action::make('open')->onDoubleClick()->action(/* … */),
])
```

## Styling

Řádek ukáže kurzor ruky, když je klikatelný. Nech neutrální hover, nebo ho
obarvi pro silnější náznak „tento řádek je klikatelný":

```php
->recordActionHover('primary')   // obarvený hover místo neutrální šedé
->activeRowClass('bg-amber-100')  // přepis zvýraznění klávesnicově aktivního řádku
```

## Doporučené UX

- **App-like tabulka** — `->recordAction('open')->behaviorOnly()` plus kontextové
  menu; zmenši nebo vypusť sloupec akcí. Řádky se chovají jako položky v
  Průzkumníku.
- **Klasická tabulka + zkratka** — nech plný sloupec akcí a přidej
  `->recordAction('view')->onDoubleClick()->alsoInRowActions()` jen jako
  zrychlení.
- **Read-heavy** — dvojklik otevře detail; pravé tlačítko nabídne
  editaci / smazání.

## Nejčastější chyby

- **Jednoklik jako primární u selectable tabulky** — krade klik určený výběru.
  Použij dvojklik (výchozí při selectable).
- **Vložení `Action::make()->onDoubleClick()` do `->actions()`** — vrací record
  action a tam je odmítnuta; předej ji do `recordActions()`.
- **Očekávání record akcí na mobilní kartě nebo sub-rows** — record akce jsou
  desktop pointer koncept na hlavních řádcích; touch karty používají viditelná
  tlačítka akcí a sub-rows jsou záměrně vyloučené.

## Migrace z `rowContextMenu()`

`Table::rowContextMenu([...])` je deprecated. Naváž místo toho trigger pravého
tlačítka:

```php
// dříve
->rowContextMenu([Action::make('edit'), Action::make('delete')])

// nyní
->recordActions([
    Action::make('edit')->onContextMenu(),
    Action::make('delete')->onContextMenu(),
])
```
