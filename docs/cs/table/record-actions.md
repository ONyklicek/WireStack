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

Klávesová navigace se zapne automaticky u každé tabulky, kterou klávesnice
ovládá řádek po řádku — u té s akcemi nad záznamem stejně jako u té, která je
`->selectable()` nebo má hromadné akce — a taková tabulka se ohlásí jako ARIA
grid:

| Klávesa | Akce |
|---------|------|
| `↑` / `↓` | Posun aktivního řádku |
| `Home` / `End`, `PageUp` / `PageDown` | Skok na okraj, nebo posun o obrazovku |
| `Enter` | Primární record action (binding dvojkliku, jinak kliku) |
| `Shift` + `Enter` | Sekundární record action (druhý pointer binding) |
| `Space` | Přepnout výběr aktivního řádku (a nastavit kotvu) při selectable, jinak primární akce |
| `Shift` + `↑` / `↓` | Rozšířit výběr od kotvy |
| `mod` + `A` | Vybrat všechny řádky na stránce |
| Menu klávesa, `Shift` + `F10` | Otevřít kontextové menu řádku |
| `?` | Zobrazit zkratky, na které tabulka reaguje |
| `Delete`, `mod+d`, … | Vlastní `->onKey()` / `->keyboardShortcut()` akce |

Vazba `->onKey('Delete')` reaguje i na `Backspace` — na klávesnici Macu je to
tatáž klávesa pod jiným jménem.

Výběrová gesta — `Space`, rozsahy, `mod`+`A` — popisuje
[Výběr řádků](selection.md), včetně gest myší a toho, co rozsah znamená, když je
vybráno „vše odpovídající".

Myš i klávesnice sdílejí jeden aktivní řádek: **klik na řádek ho označí** a šipky
pokračují odtud, takže se tabulka nikdy neovládá ze dvou míst zároveň. Označení
zůstane vidět i pod kurzorem, přežije roundtrip vyvolaný akcí a drží se svého
záznamu i po přeřazení (když záznam ze stránky zmizí, tabstop se vrátí na první
řádek).

Klávesy dosáhnou na grid jen tehdy, když má fokus **samotný řádek**: stisk uvnitř
tlačítka akce, inline editovatelné buňky nebo dropdownu patří tomu prvku. Dokud
je otevřený modal akce, grid je inertní — žádná šipka neposune označení za
dialogem a žádná zkratka nespustí druhou akci — a po zavření modalu se fokus
vrátí na aktivní řádek, takže šipky dál fungují.

Vynuť vypnutí (či zapnutí), pokud potřebuješ:

```php
->recordActionKeyboard(false)
```

Protože Enter vždy dosáhne na primární akci, každá record action zůstává
dostupná klávesnicí — behavior-only akce nikdy není past jen pro myš.

### Klávesy, které si grid vyhrazuje

Klávesy, kterými grid naviguje, nejde navázat na akci — vazba by nikdy
nevystřelila. Místo tichého zahození proto `->onKey()` vyhodí výjimku už při
konfiguraci:

```text
Enter  Space  ArrowUp  ArrowDown  Home  End  PageUp  PageDown  ContextMenu  F10  ?
```

`keyboardShortcut()` nastavený přímo na akci se jen přeskočí a nikdy není
fatální — taková akce může legitimně sloužit i toolbaru nebo paletě.

## Kombinace s výběrem a hromadnými akcemi

Když je tabulka `->selectable()`, výchozím triggerem record akce se stává
**dvojklik**, takže jednoklik zůstává volný pro práci s výběrem — jen označí
řádek, na který dopadne (aktivní řádek pro klávesnici a kotva dalšího
`Shift`+rozsahu). Prostý klik zaškrtávátko nikdy nezaškrtne; ty s modifikátorem
záměrně ano, protože přesně to `Shift` a `mod` znamenají všude jinde (viz
[Výběr řádků](selection.md)). Klik s modifikátorem je výběrové gesto a nikdy
nespustí navázanou akci nad záznamem. Hromadné akce zůstávají nedotčené:

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
->activeRowClass('bg-amber-100')  // přepis označení aktivního řádku (klik i klávesnice)
```

Aktivní řádek po dobu označení shazuje svůj hover tint, takže označení nikdy
nepřebije `hover:bg-*`, když na něm spočine kurzor.

Ve výchozím stavu jsou označením dva signály, ne jeden: podbarvení a pruh u
náběžné hrany řádku. Samotné podbarvení má vůči prostému řádku kontrast asi
1,1:1 — pod hranicí 3:1 a neviditelné pro každého, kdo ty dva odstíny nerozliší.
`activeRowClass()` nahrazuje **obě** poloviny, takže přepis si ručí za vlastní
kontrast:

```php
->activeRowClass('bg-amber-100 [&>td:first-of-type]:before:bg-amber-600')
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

## Související dokumentace

- [Výběr řádků](selection.md) — výběrová gesta, se kterými akce nad záznamem
  sdílejí řádek
- [Akce](actions.md) — řádkové, hromadné a hlavičkové akce
