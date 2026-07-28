---
order: 47
---

# Výběr řádků

Z výběru může být sada gest, ne jen sloupec zaškrtávátek. Tabulka se
`->selectable()` — nebo taková, která má jen `->bulkActions()`, což výběr
implikuje — dává zaškrtávátka, ovladače „vybrat vše" a bulk bar:

```php
->selectable()
->bulkActions([DeleteBulkAction::make()])
```

Přidejte `->gestures()` a chová se navíc jako seznam v desktopovém správci
souborů: `Shift`+klik vezme rozsah, `mod`+klik přidá jeden řádek, tažení po
sloupci se zaškrtávátky nabere celý blok a z klávesnice šipky procházejí řádky,
`Space` přepíná, `Shift`+šipky roztahují a `mod`+`A` vezme stránku.

```php
->gestures()
->selectable()
```

To rozdělení je záměrné: klávesová navigace, rozsahový výběr i označování tažením
mění chování tabulky vůči někomu, kdo ji ovládat nezamýšlel, takže čekají, až si
o ně řeknete (viz [Vrstva gest](gestures.md)). U všeho níže je uvedeno, co
potřebuje.

## Co umí myš

| Gesto | Výsledek | Potřebuje |
|-------|----------|-----------|
| Klik do buňky výběru | Přepne řádek a nastaví kotvu rozsahu | — |
| `Shift` + klik | Vybere rozsah mezi kotvou a tímto řádkem | `gestures()` |
| `mod` + klik | Přepne tento jeden řádek (kdekoli na něm) a zakotví zde | `gestures()` |
| `mod` + `Shift` + klik | Přidá celý blok k tomu, co už je vybrané | `gestures()` |
| Tažení po sloupci se zaškrtávátky | Přejede a vybere souvislý úsek řádků | `gestures()` |
| Klik na samotný řádek | Označí řádek (viz níže) — zaškrtávátko nezaškrtne | — |

Cílem je **celá buňka výběru**, ne jen šestnáctipixelové políčko uvnitř: samotné
políčko je pod každým doporučením pro velikost dotykového cíle a zbytek buňky
zůstává mrtvý. Klik v buňce se nikdy nedostane k akci navázané na řádek.

Prostý klik na *tělo* řádku řádek označí — stane se aktivním řádkem pro
klávesnici a kotvou pro další rozsah — ale nevybere ho. Výběr zůstává záměrný.
Výjimkou je `mod`+klik, a přesně od toho ten modifikátor je.

## Co umí klávesnice

Všechno v téhle sekci potřebuje `->gestures()` — viz
[Vrstva gest](gestures.md).

| Klávesa | Výsledek |
|---------|----------|
| `↑` / `↓` | Posun aktivního řádku |
| `Home` / `End` | Skok na první / poslední řádek stránky |
| `PageUp` / `PageDown` | Posun o jednu obrazovku |
| `Space` | Přepne aktivní řádek a zakotví zde |
| `Shift` + `↑` / `↓` | Zvětší nebo zmenší rozsah od kotvy |
| `Shift` + `Home` / `End` | Rozšíří rozsah k prvnímu / poslednímu řádku |
| `mod` + `Shift` + `↑` / `↓` | Totéž co `Shift`+`Home` / `End` |
| `mod` + `A` | Vybere všechny řádky na stránce |
| `?` | Zobrazí zkratky, na které tabulka reaguje |

Klávesnicový výběr řídí **stejný** stav jako zaškrtávátka a lišta hromadných
akcí: šipkou na řádek, `Space` pro výběr, `Shift`+šipka pro rozšíření, pak
hromadná akce.

Klávesy se k tabulce dostanou jen tehdy, když má fokus **samotný řádek**. Stisk
uvnitř řádku — tlačítko akce, editovatelná buňka, dropdown — patří tomu prvku,
takže `Space` napsaný do buňky zůstane mezerou a `?` napsaný do vyhledávání
nápovědu neotevře.

## Jak se chovají rozsahy

Každý rozsah roste od **kotvy**: řádku, který jste naposledy vybrali klávesou
`Space`, zaškrtávátkem nebo `mod`+klikem. Kotva je neviditelná a jednorázová —
prostý posun šipkou ji zruší.

Rozsah zapisuje **to, co už bylo vybrané, plus rozsah** — ne jen rozsah samotný.
Vyberte řádky 2–6, zakotvěte na řádku 8, `Shift`+šipkou dolů na 12 a máte
vybráno 2–6 a 8–12. Zmenšení rozsahu vrátí jen ty řádky, které rozsah sám
přidal.

Když výběr žádnou vlastní kotvu nemá — vznikl přes `mod`+`A` nebo přes pruh
„vybrat vše" — první `Shift`+šipka roste od vzdálenější hrany souvislého bloku,
ve kterém stojíte. Díky tomu *zmenší nebo zvětší blok, který vidíte*, místo aby
zahodila zbytek výběru.

Jednotlivé řádky z výběru vyřadíte tak, že na ně přejdete šipkami a stisknete
`Space`, nebo je odkliknete `mod`+klikem.

## Výběr přes hranici stránky

Jakmile je vybraná celá stránka, nabídne lišta **Vybrat všech N** a výběr přejde
z výčtu klíčů na „vše, co odpovídá aktuálnímu filtru" (co ten tvar znamená a proč
existuje, popisují [Hromadné akce](actions.md#hromadne-akce)).

Gesta fungují i v tomto režimu a čtou se tak, jak se od „všechno kromě…" čeká:

- `Shift`+šipka přes rozsah ho **odznačí**, protože uložený seznam je seznam
  výjimek.
- `mod`+`A` stojí stranou. Vybráno je už všechno, není co přidat.
- Zaškrtávátko v hlavičce edituje výjimky a nikdy vás tiše nevrátí zpět k
  výčtovému výběru.

## Tažení po sloupci

Stiskněte tlačítko ve sloupci se zaškrtávátky a táhněte: každý řádek, přes který
projedete, se vybere, a tabulka u okraje sama odroluje. Gesto je záměrně úzce
vymezené.

- **Jen přidává.** Couvnutí zpět nic neodznačí. Přejezd může vždy jen přidávat.
- **Jen myš.** Prst tažený po sloupci roluje stránku, jak má.
- **Jen ve sloupci se zaškrtávátky.** Tažení jinde označuje text jako vždy.
- Startuje až prvním pohybem, který změní řádek, takže prostý klik zůstane
  prostým klikem.

## Nápověda ke zkratkám

Stiskněte `?` s fokusem na řádku a tabulka ukáže přesně to, na co reaguje —
včetně vašich vlastních vazeb přes `->onKey()` a jejich popisků. Seznam vzniká z
konfigurace té konkrétní tabulky, takže tabulka bez akcí nad záznamem si žádné
nevymyslí.

Tentýž seznam je k dispozici jako data, pokud si ho chcete vykreslit sami:

```php
$sections = $table->shortcutLegend()->sections();
```

Každá sekce má přeložený `heading` a seznam hodnotových objektů `ShortcutHint`
(`->keys`, `->description`, `->labels(mac: true)`).

## Přístupnost

Výběr není funkce jen pro myš a tabulka to dává najevo:

- Tabulka je ARIA **grid**: `aria-rowcount`, `aria-multiselectable` a
  `aria-rowindex` na každém řádku, počítaný přes celou sadu výsledků — takže
  první řádek druhé stránky se ohlásí jako řádek 12, ne znovu jako řádek 1.
- Každý řádek hlásí `aria-selected`, drženo v souladu s živým výběrem, ne s
  poslední odpovědí serveru.
- Změny výběru se ohlašují v „polite" live regionu: *„3 z 40 vybráno"*,
  *„Vybráno vše (40)"*, *„Výběr zrušen"*.
- Aktivní řádek je označen podbarvením **a** pruhem u náběžné hrany. Samotná
  barva by selhala u každého, kdo ty dva odstíny nerozliší, a samotné podbarvení
  má kontrast asi 1,1:1 — pod hranicí 3:1. Pruh ji splňuje ve světlém i tmavém
  režimu.

Pokud se marker bije s vaším designem, přepište ho — přepis nahrazuje obě
poloviny, takže si ručí za vlastní kontrast:

```php
->activeRowClass('bg-amber-100 [&>td:first-of-type]:before:bg-amber-600')
```

## Klávesy, které si tabulka vyhrazuje

Grid vlastní klávesy, kterými naviguje, takže navázat na ně akci nad záznamem by
znamenalo mrtvý kód. `->onKey()` proto odmítne už při konfiguraci, místo aby
vazbu tiše zahodil:

```text
Enter  Space  ArrowUp  ArrowDown  Home  End  PageUp  PageDown  ContextMenu  F10  ?
```

`Backspace` **vyhrazený není**: funguje jako platformní alias klávesy `Delete`,
takže vazba `->onKey('Delete')` reaguje na obojí a explicitní
`->onKey('Backspace')` zůstává platný.

## Jak to zase vypnout

Tabulka, která si o vrstvu gest řekla, může vracet schopnosti po jedné — nebo
všechny najednou:

```php
->gestures(fn (TableGestures $g) => $g->dragSelect(false))  // klávesnici nech, tažení pryč
->gestures(fn (TableGestures $g) => $g->keyboard(false))    // a naopak
->gestures(false)                                           // všechna gesta včetně rozsahů
```

Zaškrtávátka fungují v každé z těch variant dál — viz
[Vrstva gest](gestures.md).

## Související dokumentace

- [Akce nad záznamem](record-actions.md) — vazby na klik, dvojklik a kontextové
  menu celého řádku
- [Hromadné akce](actions.md#hromadne-akce) — práce s výběrem
- [Vrstva gest](gestures.md) — jak tahle gesta vypnout, celá nebo po částech
