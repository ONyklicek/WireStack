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

Je to proto jeden vypínač a tabulka začíná na té tiché straně:

```php
->gestures()
```

Tohle je ta desktopová tabulka. Bez toho dostanete obyčejnou webovou — tu, kterou
většina stránek chce.

## Co tabulka dostane, když si neřekne

**Klávesová navigace a označování tažením jsou vypnuté, dokud si o ně neřeknete.**
Jsou to ty dvě, které mění chování tabulky vůči návštěvníkovi, který ji ovládat
nezamýšlel: klávesová navigace dá řádky do pořadí tabulátoru, označí aktivní
řádek a začne odpovídat na šipky a `mod`+klávesu, a tažení promění stisk
v checkboxovém sloupci na blokový výběr — gesto, které lidé najdou omylem dřív
než schválně.

Zbytek stejně potřebuje vlastní pozvánku, takže je povolený od začátku: rozsah
potřebuje `->selectable()`, kontextové menu potřebuje navázané akce, fill handle
potřebuje `->fillHandle()` a nápověda `?` potřebuje klávesovou vrstvu, kterou
tenhle default nechává vypnutou.

```php
// Obyčejný výpis. Checkboxy fungují, Shift+klik pořád dělá rozsah,
// nic neodpovídá na šipku, nic se neoznačuje tažením.
Table::make()->selectable()

// Ta samá tabulka jako aplikace.
Table::make()->gestures()->selectable()
```

Když chcete jít opačným směrem — žádné kontextové menu, žádné rozsahy, žádný fill
handle, vůbec nic — řekněte si o to:

```php
->gestures(false)
```

## Co se počítá jako gesto

Šest schopností, každá zvlášť přepínatelná. „Výchozí" je to, co dostane tabulka,
která `gestures()` nikdy nezavolá:

| Schopnost | Výchozí | Co pokrývá |
|-----------|---------|------------|
| `keyboard` | **vyp** | Navigaci v mřížce: putovní `tabindex`, šipky, `Home`/`End`, `PageUp`/`PageDown`, `Enter` / `Shift`+`Enter` pro primární a sekundární record action, `Space` pro přepnutí výběru a každou vlastní `keyboardShortcut()` / `onKey()` proti aktivnímu řádku. Zároveň je to to, co z tabulky dělá ARIA `grid`. |
| `rangeSelection` | zap | `Shift`+klik, `mod`+klik a `mod`+`Shift`+klik na řádek, plus `Shift`+šipka, `Shift`+`Home` a `Shift`+`End` z klávesnice. |
| `dragSelect` | **vyp** | Označování tažením: stisknout v checkboxovém sloupci a táhnout přes blok řádků. |
| `contextMenu` | zap | Kontextové menu řádku pod pravým tlačítkem — jak `rowContextMenu()`, tak libovolnou `onContextMenu()` record action. |
| `shortcutHelp` | zap¹ | Nápovědu zkratek pod `?`. |
| `fillHandle` | zap² | Fill handle nad editovatelnými buňkami ve stylu Excelu. |

`mod` je `Ctrl` na Windows a `⌘` na macOS.

¹ Povolená, ale čte klávesovou vrstvu — s výchozím nastavením se tedy neotevře.
² Povolený, ale tabulka si o něj pořád musí říct přes `->fillHandle()`.

## Kombinování

Předejte closure. Dostane gesta téhle tabulky a nastaví je na místě — návratová
hodnota se ignoruje, takže funguje jak fluent řetězec, tak víceřádkové tělo.

```php
->gestures(fn (TableGestures $g) => $g
    ->keyboard()          // šipky, Enter, zkratky …
    ->dragSelect(false))  // … ale pořád žádné označování tažením
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

`TableGestures::defaults()`, `TableGestures::all()` a `TableGestures::none()` jsou
tři výchozí body: dodávaný default, všechno, nic.

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
„zapnuto" tu musí znamenat dvě různé věci:

| Hodnota | Význam |
|---------|--------|
| `false` (výchozí) | Vypnuto |
| `null` | Rozhoduje tabulka — zapnuto pro tabulku s record actions nebo pro selectable. Tohle nastaví `gestures()` |
| `true` | Zapnout natvrdo, i pro tabulku, která nemá ani jedno |

`gestures()` nechává klávesnici na `null` místo aby ji zapínalo natvrdo: tabulka
bez record actions a bez výběru nemá pro šipky co dělat a putovní tabindex nad
netečnými řádky je horší než žádný:

```php
Table::make()->gestures()                                          // není grid
Table::make()->gestures()->selectable()                            // je grid
Table::make()->gestures(fn (TableGestures $g) => $g->keyboard(true))   // grid tak jako tak
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
    'gestures' => true,
],
```

`null` (nebo chybějící klíč) ponechá dodávaný default popsaný výše, `true` povolí
všechno všem tabulkám — back office si vrstvu zapne jednou tady místo u každé
tabulky — `false` nepovolí nic a mapa kombinuje:

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
| Veřejný výpis, marketingová stránka | Nic dělat nemusíte — to je výchozí stav |
| Back-office výpis, operátoři na klávesnici | `->gestures()`, nebo `'gestures' => true` v configu |
| Veřejná stránka, kde nesmí ani pravý klik | `->gestures(false)` |
| Read-only report, pravý klik se pořád hodí | `TableGestures::none()->contextMenu()` |
| Dlouhý seznam, klávesnice se hodí, tažení je riskantní | `->gestures(fn ($g) => $g->keyboard())` |
| Vložená do stránky s vlastní obsluhou klávesnice | `->gestures(fn ($g) => $g->dragSelect())` |

## Viz také

- [Výběr řádků](selection.md) — co dělá které výběrové gesto
- [Record actions](record-actions.md) — navázání akce na gesto řádku
- [Pokročilé](advanced.md) — fill handle
