---
order: 60
summary: Historie za zvonkem notifikací — stejné řádky s tabulkou nad nimi, ve výchozím stavu omezené na toho, kdo se dívá.
---

# Modul notifikací

Panel zvonku ukazuje posledních pár. Tohle jsou stejné řádky s tabulkou nad
nimi: k hledání, s filtrem podle přečtení, a čitelné i potom, co z panelu
vyjely.

```bash
composer require nyoncode/wire-module-notifications
php artisan wire-module-notifications:install
```

## Jak to funguje

**Omezené na toho, kdo se dívá.** Notifikace je někomu adresovaná, takže výchozí
stav ukazuje vlastní notifikace přihlášeného. Odhlášenému to znamená **žádné** —
lákavá alternativa, tedy žádný filtr, je únik dat. `scope => 'all'` z toho udělá
administrativní pohled, což je jiná obrazovka a chce na stránce policy.

**Otevření notifikaci označí za přečtenou**, v record hooku stránky, protože to
dělá každá schránka odjakživa a počet nepřečtených je pak správně, aniž by někdo
na něco klikal.

**Není to v postranním menu, a to je záměr.** Notifikace nejsou entita, kterou
spravujete z menu — jsou to schránka, a cesta dovnitř je zvonek: nese počet
nepřečtených a jeho panel odkazuje rovnou sem. Trvalá položka pro obrazovku, na
kterou se chodí přes odznak, je ta položka, kterou nikdo nečte. Stránka zůstává
registrovaná i nasměrovaná, takže její URL dál funguje; nastavte
`navigation.visible` (nebo `WIRE_NOTIFICATIONS_IN_NAVIGATION=true`), pokud chcete
i tu položku.

**Ukládá jen driver `database`.** Pod výchozím `session` není co vypisovat
a instalátor to řekne.

## Konfigurace

```php
// config/wire-module-notifications.php
'model' => NyonCode\WireCore\Notifications\DatabaseNotification::class,

'scope' => 'own',   // 'own' vlastní řádky přihlášeného; 'all' řádky všech [tl! focus]

'navigation' => [
    'visible' => false,   // do schránky se chodí přes zvonek, ne přes řádek v menu
    'group' => 'system',
    'label' => null,      // null použije vlastní nadpis skupiny modulu
    'icon' => 'outline:bell',
    'sort' => 96,
],
```

`model` je třída, kterou obrazovka vypisuje, a patří `wire-core`: tenhle modul
vlastní obrazovku, ne tabulku. Vlastní model z ní musí dědit — řádky zapisuje
driver notifikací, který o tomhle modulu neví.

`scope => 'all'` udělá ze schránky administrativní pohled na poštu všech, což
chce oprávnění na stránce, ne jen klíč v konfiguraci. Odhlášenému `own`
neodpovídá nic, což je ta bezpečná půlka volby.

Texty jsou publikovatelný překladový soubor a markup publikovatelný pohled —
`wire-module-notifications::translations` a `…::views`; co to stojí, říká
[Vzhled → Lokalizace](../start/theming.md#lokalizace) a
[Přepis pohledů](../start/theming.md#prepis-pohledu).

## Co dostanete

| Obrazovka | Poznámky |
| --- | --- |
| Notifikace | Notifikace i s větou, kvůli které vznikla, tónovaná podle typu, nejnovější první; relativní časy; hledání v payloadu; filtr přečtené/nepřečtené |
| Jedna notifikace | Typ, časy a obsah jako klíč/hodnota |

Čte se to jako schránka, ne jako tabulka sloupců, a ta rozhodnutí stojí za to
vypsat, protože se dají omylem vrátit:

- **Nepřečtené je váha, ne buňka.** Řádek je tónovaný a středně tučný, stejně jako
  to říká panel zvonečku — obě plochy se pak čtou jako jeden produkt a sloupec,
  který zabíral chip `STAV`, teď používá zpráva.
- **Hledání čte payload.** Viditelný text žije v JSON sloupci `data`, takže je
  hledání deklarované nad `data->title` a `data->message`. Vyhledávací pole,
  které nic nenajde, je horší než žádné.
- **Není nakreslená jako tabulka.** `layout('list')` a s ním tři ovládání, která
  dávají smysl jen nad mřížkou sloupců: žádný checkbox na řádku, žádný panel
  sloupců, žádné `Show [10] records`. Označení všeho přečteným je hlavičková
  akce nad celou filtrovanou množinou — silnější než výběr a tišší než on.
- **Slovesa řádku sedí za jedním tichým spouštěčem.** Označit přečtené, označit
  nepřečtené, smazat. Stránku, jejímž jediným úkolem je být čtená, nemá
  ovládat plné modré a plné červené tlačítko na každém řádku.
- **Řádek otevře vlastní stránku notifikace**, což ji označí za přečtenou — záměrně
  vlastní stránku, ne to, kam míří `->url()`: seznam, jehož řádky vedou jednou na
  fakturu a jindy na notifikaci, je seznam, na který nejde klikat s jistotou.
  Odkaz z payloadu je na stránce, kterou otevře.

Výběr řádků nabídne tři slovesa schránky — **označit přečtené**, **označit
nepřečtené** a **smazat**. Nic z toho se znovu neomezuje: výběr vychází ze
stejného dotazu, který tabulka vypisuje, a ten už jsou vlastní řádky diváka —
hromadná akce se tedy nedostane dál než obrazovka, ze které se spustila.

## Související

- [Notifikace](../core/notifications/index.md) — drivery, zvonek, živá půlka a toasty
- [Moduly](../panels/modules.md) — jak balíček dodává takovou oblast

