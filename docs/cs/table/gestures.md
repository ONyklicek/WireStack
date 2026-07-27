---
order: 48
---

# Vrstva gest

Tabulka wire-table se umí chovat jako desktopová aplikace: šipky procházejí
řádky, `Shift` roztahuje rozsah, myš označuje tažením přes checkboxový sloupec,
pravý klik otevře menu řádku, `?` vysvětlí zkratky a fill handle roztáhne jednu
hodnotu přes mnoho buněk.

Pro back office je to přesně ono. Pro veřejný výpis je to obvykle špatně —
zvýrazněný řádek a zabavený pravý klik tam v lepším případě jen ruší.

Celá vrstva je proto jeden vypínač:

```php
->gestures(false)
```

Dostanete obyčejnou webovou tabulku. Nic není navázané, nic se neoznačuje
a controllery, které by to řídily, se vůbec nevykreslí.

## Co se počítá jako gesto

Šest schopností, každá zvlášť přepínatelná:

| Schopnost | Co pokrývá |
|-----------|------------|
| `keyboard` | Navigaci v mřížce: putovní `tabindex`, šipky, `Home`/`End`, `PageUp`/`PageDown`, `Enter` / `Shift`+`Enter` pro primární a sekundární record action, `Space` pro přepnutí výběru a každou vlastní `keyboardShortcut()` / `onKey()` proti aktivnímu řádku. Zároveň je to to, co z tabulky dělá ARIA `grid`. |
| `rangeSelection` | `Shift`+klik, `mod`+klik a `mod`+`Shift`+klik na řádek, plus `Shift`+šipka, `Shift`+`Home` a `Shift`+`End` z klávesnice. |
| `dragSelect` | Označování tažením: stisknout v checkboxovém sloupci a táhnout přes blok řádků. |
| `contextMenu` | Kontextové menu řádku pod pravým tlačítkem — jak `rowContextMenu()`, tak libovolnou `onContextMenu()` record action. |
| `shortcutHelp` | Nápovědu zkratek pod `?`. |
| `fillHandle` | Fill handle nad editovatelnými buňkami ve stylu Excelu. |

`mod` je `Ctrl` na Windows a `⌘` na macOS.

## Kombinování

Předejte closure. Dostane gesta téhle tabulky a nastaví je na místě — návratová
hodnota se ignoruje, takže funguje jak fluent řetězec, tak víceřádkové tělo.

```php
->gestures(fn (TableGestures $g) => $g
    ->keyboard()          // šipky, Enter, zkratky
    ->dragSelect(false)   // ale žádné označování tažením
    ->rangeSelection())   // Shift+klik rozsah pořád dělá
```

Každý setter bere `bool`, takže `->contextMenu(false)` se čte stejně dobře jako
`->contextMenu()`.

Můžete taky předat hotovou sadu, což se hodí, když má víc tabulek sdílet jeden
domácí styl:

```php
use NyonCode\WireTable\Support\TableGestures;

$readOnly = TableGestures::none()->contextMenu();

// …a pak v každé tabulce:
->gestures($readOnly)
```

`TableGestures::all()` a `TableGestures::none()` jsou dva výchozí body.

## Povolení není zapnutí

Každá schopnost je **povolení**, nikdy spouštěč. Zapnout ji neznamená vyrobit to,
co řídí:

- `dragSelect` a `rangeSelection` pořád potřebují `->selectable()` (nebo
  `->bulkActions()`, které ho implikují) — rozsah musí mít v čem růst.
- `fillHandle` pořád potřebuje `->fillHandle()` na tabulce a editovatelné sloupce.
- `shortcutHelp` pořád potřebuje klávesovou vrstvu, protože právě ta na klávesu
  poslouchá.

Takže `->gestures(fn ($g) => $g->dragSelect())` na tabulce bez `selectable()`
nezmění nic. Je to záměr: vrstva gest rozhoduje, co tabulka *smí*, a zbytek API
tabulky rozhoduje, co *má*.

## `keyboard()` má tři stavy

Ostatních pět schopností jsou prosté booleany. `keyboard` je třístavová, protože
musí umět vyjádřit i „zapni to natvrdo pro tabulku, která by se jinak
nekvalifikovala":

| Hodnota | Význam |
|---------|--------|
| `null` (výchozí) | Rozhoduje tabulka — zapnuto pro tabulku s record actions nebo pro selectable |
| `true` | Zapnout natvrdo, i pro tabulku, která by se nekvalifikovala |
| `false` | Vypnout |

```php
->gestures(fn (TableGestures $g) => $g->keyboard(null))   // vrátit rozhodnutí tabulce
```

## Co vrstva neřídí

**Výslovně deklarovaná record action funguje dál.** Vazba jako

```php
->recordAction(RecordAction::make(Action::make('view'))->onClick())
```

je vědomé rozhodnutí o téhle tabulce, ne implicitní afordance, kterou si tabulka
zapnula sama — takže `gestures(false)` přežije. Vrstva gest řídí jen to, co by si
tabulka jinak zapnula sama od sebe.

Jedinou výjimkou je `->onKey()`, které potřebuje klávesovou vrstvu, aby měla čím
poslouchat. S vypnutou `keyboard` nemá vazba `onKey()` odkud vystřelit.

Samotný výběr zůstává taky nedotčený. I s vypnutými gesty fungují checkboxy, oba
ovladače „vybrat vše" i bulk bar přesně jako dřív — přijdete o zkratky k nim, ne
o funkci.

## Označení aktivního řádku

Řádky nesou marker aktivního řádku tehdy, když nějaké gesto potřebuje odkud růst
— tedy když tabulka používá grid semantiku, rozsahový výběr nebo označování
tažením.

Tabulka, které zbyla jen deklarovaná klik akce, neoznačuje nic. Klik tam otevře
záznam a jde se dál; zvýrazněný řádek, který po něm zůstane, by byl aplikační
afordance na stránce, která o žádnou nežádala.

## Výchozí nastavení pro celý projekt

Nastavte jednou pro všechny tabulky:

```php
// config/wire-table.php
'defaults' => [
    'gestures' => false,
],
```

`true` (nebo chybějící klíč) povolí všechno, `false` nic a mapa kombinuje:

```php
'gestures' => ['keyboard' => true, 'drag_select' => false],
```

Klíče schopností se párují volně — `drag_select`, `drag-select`, `dragSelect`
i `dragselect` jsou tentýž klíč. **Neznámý** klíč vyhodí
`TableConfigurationException`, místo aby tiše nedělal nic: překlep v povolení je
přesně ten druh chyby, která se projeví až za půl roku jako „proč tohle
nefunguje".

Per-table `->gestures(...)` vždy přebije výchozí hodnotu z configu.

## Vypnuto je i na serveru

Vypnutí schopnosti není věc toho, že klient ignoruje eventy. Jde s tím i markup
a endpointy:

- Delegované Alpine controllery se nevykreslí. Tabulka, které zbylo jen vypnutí
  gest, nevykreslí controller vůbec a její asset bundly se nepožadují.
- Tabulka přestane být ARIA `grid`: žádné `role="grid"`, `role="row"`, žádný
  putovní `tabindex`.
- Řádky nejsou fokusovatelné, takže klik nikomu nesebere fokus.
- Fill endpoint odmítá. Vypnutý `fillHandle` zavře `fillTableCells` na serveru,
  ne jen úchyt v UI.
- Legenda zkratek zahodí řádky, které už neplatí — s vypnutými rozsahy se
  `Shift`+šipka v nápovědě `?` neobjeví, protože nefunguje.

To poslední je obecné pravidlo: legenda se generuje z toho, co tabulka opravdu
dělá, takže se nemůže rozejít se skutečností.

## Na telefonu jsou z gest tlačítka

Tabulka řízená gesty je desktopová myšlenka. Na telefonu není dvojklik, není
pravý klik a není hover, kterým by se jeden nebo druhý dal objevit — takže
record action, která je na desktopu jen chováním, by na skládané mobilní kartě
byla **nedosažitelná**.

Vykreslí se tam proto jako obyčejné tlačítko, a jen tam:

```php
->recordAction(RecordAction::make(Action::make('open'))->onDoubleClick())
```

| Povrch | Co uživatel dostane |
|--------|---------------------|
| Desktop | Gesto dvojkliku. Žádný sloupec, žádné tlačítko. |
| Mobilní karta | Tlačítko `Open`. |

Fallback si dává pozor, aby nic nezdvojil:

- Akce už přítomné v `->actions()` si drží pořadí a record actions se přidají za ně.
- `recordAction('edit')`, které jen *odkazuje* na akci deklarovanou v
  `->actions()`, ukáže jedno tlačítko — ne totéž dvakrát.
- Akce povýšená do sloupce přes `->alsoInRowActions()` už tlačítkem je, takže se
  nechá být.
- Fallbacková tlačítka se počítají do `->collapseActionsOnMobile()`, takže karta
  tiše nepřeroste práh, který jste nastavili.

Když má karta zůstat čistá, vypněte to:

```php
->recordActionButtonsOnMobile(false)
```

## Jak vybrat

| Tabulka | Doporučení |
|---------|------------|
| Back-office výpis, operátoři na klávesnici | Všechno (výchozí) |
| Veřejný výpis, marketingová stránka | `->gestures(false)` |
| Read-only report, pravý klik se pořád hodí | `TableGestures::none()->contextMenu()` |
| Dlouhý seznam, výběr je důležitý, tažení riskantní | `->gestures(fn ($g) => $g->dragSelect(false))` |
| Vložená do stránky s vlastní obsluhou klávesnice | `->gestures(fn ($g) => $g->keyboard(false))` |

## Viz také

- [Výběr řádků](selection.md) — co dělá které výběrové gesto
- [Record actions](record-actions.md) — navázání akce na gesto řádku
- [Pokročilé](advanced.md) — fill handle
