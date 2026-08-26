---
order: 100
---

# Návod k upgradu

Jak bezpečně přecházet mezi verzemi Wire a kde hledat breaking changes.

---

## Verzování

Ekosystém Wire se dodává jako čtyři balíčky — `wire-core`, `wire-forms`,
`wire-table`, `wire-sortable` — vydávané společně z jednoho monorepa, takže se
jejich verze pohybují v zámku. Instalujte je a omezujte jako celek.

Wire je aktuálně ve větvi **`0.x`**. Podle běžné konvence před 1.0 mohou minor
vydání obsahovat breaking changes, proto si připněte otestovanou verzi a před
zvýšením si přečtěte changelog:

```jsonc
// composer.json
"require": {
    "nyoncode/wire-core":     "^0.1",
    "nyoncode/wire-forms":    "^0.1",
    "nyoncode/wire-table":    "^0.1",
    "nyoncode/wire-sortable": "^0.1"
}
```

---

## Požadavky

| Závislost | Podporováno |
|------------|-----------|
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 12.61+, 13.12+ |
| Livewire | 4.x |
| Tailwind CSS | 3.x nebo 4.x |
| `nyoncode/laravel-package-toolkit` | ^2.4 |

Před upgradem ověřte, že je vaše aplikace splňuje.

---

## Livewire 4 (2.0)

**Verze 2.0 vyžaduje Livewire 4.** Linie 1.x zůstává na Livewire 3 a dál dostává
opravy; žádné vydání neběží na obou. Nejdřív povyšte Livewire, ověřte, že vaše
vlastní komponenty fungují, teprve pak posuňte Wire.

```bash
composer require livewire/livewire:^4.0
php artisan optimize:clear
composer update "nyoncode/wire-*"
```

Vlastní [upgrade guide Livewiru](https://livewire.laravel.com/docs/upgrading)
pokrývá kód vaší aplikace. Čtyři jeho změny zasahují do toho, co Wire vykresluje
za vás, a jen poslední z nich po vás něco chce.

**`liveOnBlur()` znamená pořád totéž.** Od Livewire 4 říká `wire:model.blur`, kdy
*klient* synchronizuje vlastní stav, ne kdy mluví se serverem — samotné `.blur` se
na server nedostane vůbec. Wire proto emituje `wire:model.live.blur`, takže pole
deklarované přes `->liveOnBlur()` (nebo `->validateOnBlur()`, které to zapíná) se
chová přesně jako dřív. Měnit není co, ledaže jste `wire:model.blur` napsali ručně
v přepsaném view pole — tam doplňte `.live`.

**Vícesouborové uploady se slučují samy.** Livewire 4 si nový upload přislučuje ke
stávajícím položkám vícesouborového pole sám, kdežto 3.x je nahrazoval. Wire tuhle
mezeru dřív zaceloval a už to nedělá. Pokud jste totéž sloučení napsali ve vlastním
`updated()` hooku, odstraňte ho — jinak se stávající položky započtou dvakrát.

**Endpointy Livewiru se přesunuly.** URL jsou nově `/livewire-{hash}/…` místo
`/livewire/…`, kde se hash odvozuje z vašeho `APP_KEY`. Pravidla firewallu, bypass
na CDN a cokoli dalšího, co ten prefix porovnává ručně, je potřeba upravit. Vlastní
asset routes Wiru to nezasahuje — pod tím prefixem nikdy nebyly.

**Alpine dodává Livewire, stále.** Livewire 4 dodává Alpine 3.16. Stejně jako u 3.x
Alpine neinstalujte ani nestartujte samostatně.

---

## Markup řádku a částečné renderování (2.0)

Dvě změny. Jedna je dobrovolná a můžete ji ignorovat, dokud ji nebudete chtít; ta
druhá se stala každé tabulce a stojí za deset minut pozornosti, pokud stylujete,
skriptujete nebo testujete proti markupu tabulky.

### Řádky každé tabulky se skládají jinak

Tělo řádku se dřív rozkládalo v Blade uvnitř řádkové smyčky. Teď se skládá v PHP
z markupu, který Blade zkompiluje jednou pro tabulku (`Support\RowRenderer`, a
`Support\CardRenderer` pro stacked karty). Výsledný markup je tentýž, se dvěma
rozdíly:

- **zmizely per-řádkové morph markery.** Livewire vkládá dvojici
  `<!--[if BLOCK]><![endif]-->` kolem každého `@if` a `@foreach`, který
  zkompiluje, a podmínky řádkové smyčky jich emitovaly 459–999 B na řádek —
  848–1035 B na řádek i s whitespace mezi nimi, a 1 347 B na stacked kartu. Nic
  v DOM na nich nezáviselo kromě samotného morphu Livewire;
- **podmíněné děti řádku teď nesou `wire:key`**, což je to, co je při morphu
  páruje místo těch markerů: `ctx-{key}` na teleportovaném kontextovém menu,
  `sel-{key}` na výběrové buňce, `exp-{key}` na rozbalovači podřádků a
  `act-{key}-{name}` na každém akčním tlačítku vykresleném **se** záznamem.
  Tlačítko bez záznamu — hlavičková akce, hromadná akce, prázdný stav — se
  nezměnilo.

**Co zkontrolovat.** Cokoli, co prochází děti řádku podle pozice nebo počítá
komentářové uzly: CSS `:nth-child()`, které předpokládalo stabilní počet dětí,
řetězec `querySelector`, který markery překračoval, browser test, který na ně
asertoval. Běžné selektory — `[data-row-key]`, `[data-testid]`, `[data-column]`,
`tbody tr` — se nezměnily a zůstávají podporovanou cestou dovnitř.

**Pokud jste publikovali views tabulky**, tohle je ta změna, která umí kousnout
potichu. `tables/index.blade.php` už tělo řádku vůbec neobsahuje: rozdělilo se do
`partials/data-region.blade.php` a řádek s kartou se renderují z PHP.
Publikovaná kopie z 1.x dál funguje — Laravel jí dá přednost — ale drží si starou
cenu a žádné z nového chování, a `rowPartials()` nepřevezme. Publikujte znovu,
nebo lépe, kopii smažte a konfigurujte:

```bash
php artisan vendor:publish --tag=wire-table::views --force
```

### `rowPartials()` — dobrovolné a ve výchozím stavu vypnuté

Zápis může odpovědět oblastmi, kterými pohnul, místo překreslení celé tabulky:

```php
$table->rowPartials()
```

Na stránce s 25 sloupci a 20 řádky stojí uložení buňky 49,3 ms a 556 kB při
běžném renderu a 3,2 ms a 26 kB jako jeden řádek. Pro tabulku, která si o to
neřekne, se nemění nic — nevykreslí se žádná kotva a neutratí se žádný bajt.

**Co za to platíte** je, že překreslený řádek si drží svou pozici: editace, která
by záznam pod aktuálním řazením posunula, ho nechá na místě až do dalšího plného
renderu. Na široké editovatelné mřížce je to ta správná výměna, a proto je to
dobrovolné, ne zapnuté.

Viz [Pokročilé → Řádkové partials](table/advanced.md#radkove-partials), kde je,
čím zápis odpoví u kterého tvaru tabulky, a jak tytéž kotvy slouží `poll()`
a `live()`.

---

## `Widget::lazy()` končí (2.0)

`Widget::lazy()` a `Widget::isLazy()` byly odstraněny. Nikdy nic neodkládaly:
žádná view widgetu ten příznak nečetla — nestálo za ním `wire:init`, žádná
intersect direktiva ani island — takže widget označený jako lazy se vykreslil
celý jako kterýkoli jiný.

```php
StatsOverviewWidget::make()->lazy()   // [tl! --]
StatsOverviewWidget::make()           // [tl! ++]
```

Smazání volání je celá migrace; předtím se nic nevykreslovalo jinak.

**Pokud odklad opravdu chcete**, odložte celou komponentu místo jednoho widgetu —
dashboard je jedna Livewire komponenta a widget je markup uvnitř ní, ne vlastní
komponenta. `<livewire:my-dashboard lazy />` odloží celý grid. Odklad po
jednotlivých widgetech k dispozici není: vyžadoval by island na každý widget
a `@island` uvnitř `@foreach` se nezkompiluje — Blade vytvoří jedno tělo islandu
na jeden výskyt direktivy a to tělo proměnnou cyklu nikdy nedostane.

---

## Views polí: Alpine tělo se přesunulo do bundlu (2.0)

Sedm typů polí mělo celý svůj Alpine controller inlinovaný v markupu jako `x-data`
objekt, takže stránka se šesti date pickery poslala tytéž stovky řádků šestkrát.
Těla jsou teď registrované `Alpine.data()` factory.

**Nemusíte dělat nic, pokud si nepřepisujete některý z těchto views**:
`DateTimePicker`, `TimePicker`, `Select` (searchable combobox), `Tags`, `Rating`,
`RichEditor`, `MarkdownEditor`. Pokud ano, zkopírované `x-data` už neexistuje —
zavolejte factory s konfiguračním objektem:

```blade
{{-- předtím --}}
<div x-data="{ open: false, value: $wire.entangle('data.at'), hasDate: true, /* …300 řádků… */ }">   {{-- [tl! --] --}}

{{-- potom --}}
<div x-data="wireDateTimePicker({                    {{-- [tl! ++:4] --}}
    state: $wire.entangle('data.at'),
    hasDate: true,
    typeable: true,
})">
```

Dvě věci zůstávají v markupu záměrně. **`state`**, protože `$wire.entangle`
a `@entangle` jsou Alpine *magics* a jsou ve scope jen uvnitř `x-data` výrazu —
do bundlu se přesunout nemohou. A jakýkoli **řetězec ze serveru**, který
controller potřebuje, například přeložený titulek pro `prompt()`; ten přichází
jako config.

Třetí pravidlo vás dostane, když portujete vlastní pole: Blade `@if` uvnitř těla
se musí stát runtime větví. Factory se kompiluje jednou a sdílí ji každá instance,
takže *tvar* objektu už nemůže nic měnit — jen jeho chování.

Controllery jsou v `wire-forms-fields.js`, searchable-select combobox
v `wire-core-dropdown.js` (patří core: ten partial includuje sedm povrchů napříč
forms i table). Oba jsou registrátory, takže se načítají s dokumentem, ne na
vyžádání. Každý převedený view navíc includuje
`wire-forms::partials.field-assets`, protože
[`@wireStackScripts`](getting-started.md#javascriptove-assety) je aditivní — aplikace,
která direktivu nikdy nepřidá, musí controller dostat stejně, jinak se `x-data`
vyhodnotí proti prázdnému registru a pole tiše nedělá nic.

---

## Deprecated shimy traitů končí (2.0)

Devět aliasů traitů pod `NyonCode\WireCore\Concerns\` bylo odstraněno. Každý
byl `class_alias()` shim s poznámkou `@deprecated … Will be removed in v2.0`
a tohle je to vydání.

Všechny ukazovaly na trait stejného jména pod `Actions\Concerns\`, takže
migrace je řádek s importem a nic víc:

```php
use NyonCode\WireCore\Concerns\HasIcons;          // [tl! --]
use NyonCode\WireCore\Actions\Concerns\HasIcons;  // [tl! ++]
```

Těch devět jmen: `HasButtonStyles`, `HasColor`, `HasDynamicProperties`,
`HasIcons`, `HasKeyboardShortcut`, `HasLifecycle`, `HasLoadingState`,
`HasModal`, `HasVisibility`.

Samotné traity zůstávají beze změny — stejné metody, stejné chování. Pokud jste
z `WireCore\Concerns\` nikdy neimportovali, není co dělat.

**Jedna výjimka, která stojí za to.** U barev sáhněte po
`Foundation\Concerns\HasColor`: ten je kanonickým vlastníkem a
`Actions\Concerns\HasColor` je sám jen jeho tenký alias.

---

## Minimální verze závislostí (1.17)

**Laravel 10 a 11 končí.** Verze 1.17 přesunula JavaScriptové bundly z package
route do reálných souborů pod `public/vendor` a kód, který je tam zrcadlí, žije
v `nyoncode/laravel-package-toolkit` — vedle deklarace `hasAssets()` a publish
tagu, jehož je čtecí stranou. Toolkit stojí na
`illuminate/support ^12.61.1|^13.12.0` a minimum závislosti je i vaše minimum:
aplikace pod ním balíčky Wire nenainstaluje, ať v jejich vlastním
`composer.json` stojí `^12.0`. Nejdřív povyšte Laravel, pak Wire.

**Constraint toolkitu je `^2.4`.** Přímo si ho nevyžadujete, takže v běžném
případě ho `composer update "nyoncode/wire-*"` posune se vším ostatním a není co
řešit. Viditelný je jen ve dvou situacích:

- váš `composer.json` `nyoncode/laravel-package-toolkit` jmenuje — protože na něm
  stavíte vlastní balíček, nebo ze starého pinu — a drží ho pod 2.4. Composer pak
  hlásí jako neinstalovatelné balíčky Wire, ne toolkit jako starý, takže ten
  constraint rozšiřte na `^2.4` jako první.
- běžíte na Octane. Memo assetů, které je jinak per-request a tady přežívá celý
  worker, se na `RequestTerminated` zahazuje přes `PublishedAssets::flush()`
  z toolkitu, a 2.4 je první vydání, které ho nese. Pod ním worker, který přežije
  deploy, dál emituje `?id=<mtime>` z minulého vydání a `wire:navigate` si nových
  bundlů nikdy nevšimne.

---

## Kroky upgradu

1. **Přečtěte si changelog.** Zkontrolujte `CHANGELOG.md` pro verze, které
   přeskakujete, zejména jakoukoli sekci **Breaking Changes**.

2. **Aktualizujte balíčky.**

   ```bash
   composer update "nyoncode/wire-*"
   ```

3. **Znovu zkontrolujte publikované soubory.** Pokud jste publikovali konfiguraci,
   pohledy nebo překlady, vaše kopie se **neaktualizují** automaticky. Porovnejte
   je s novými verzemi balíčků a zapracujte relevantní změny:

   - `config/wire-*.php`
   - `resources/views/vendor/wire-*/…`
   - `lang/vendor/wire-*/…`

   Čím méně pohledů přepisujete, tím méně je zde ke sladění — viz
   [Vzhled → Přepis pohledů](theming.md#prepis-pohledu).

4. **Vyčistěte cache a přebuildujte assety.**

   ```bash
   php artisan view:clear
   php artisan config:clear
   npm run build
   ```

5. **Spusťte testovací sadu.** [Testovací sada](testing.md) je nejrychlejší
   způsob, jak odchytit breaking change ve vlastních formulářích a tabulkách.

---

## Výběr a klávesová gesta

Z výběru v tabulce se stala plnohodnotná sada gest, ne jen sloupec zaškrtávátek
(viz [Výběr řádků](table/selection.md)). Při upgradu zkontrolujte čtyři věci.

**1. Všechna gesta nad řádkem jsou opt-in — `->gestures()`.** Z výběru se stala
plnohodnotná sada gest: `Shift`/`mod` kliky pro rozsahy, tažení po sloupci se
zaškrtávátky, které nabere celý blok, a z klávesnice šipky, `Space`, `Shift`+šipky
a `mod`+`A`. Nic z toho není zapnuté, dokud si o to tabulka neřekne — každé z nich
totiž mění chování tabulky vůči návštěvníkovi, který ji ovládat nezamýšlel: řádky
jdou do pořadí tabulátoru, označuje se aktivní řádek, tažení začne vybírat
a modifikovaný klik přestane být klikem.

Tabulkám, které to chtějí, přidejte jedno volání:

```php
->gestures()
->selectable()
```

nebo, pokud je celý projekt back office:

```php
// config/wire-table.php
'defaults' => ['gestures' => true],
```

Co změna *neovlivní*: zaškrtávátka, oba ovladače „vybrat vše" i bulk bar fungují
beze změny a tabulka, která si o gesta neřekla, nemontuje delegovaný controller
vůbec. Stejně tak kontextové menu pod pravým tlačítkem a fill handle — o oboje
jste si stejně museli říct sami.
Šest schopností a jak je kombinovat najdete ve [Vrstvě gest](table/gestures.md).

**2. `->onKey()` na navigační klávese nově vyhodí výjimku.** Dřív se tiše
zahodila, takže akce prostě nikdy nevystřelila. Pokud takovou vazbu máte, byla
to už dřív mrtvá větev — přemapujte ji na volnou klávesu:

```text
Enter  Space  ArrowUp  ArrowDown  Home  End  PageUp  PageDown  ContextMenu  F10  ?
```

`Backspace` zůstává k dispozici a nově funguje i jako alias klávesy `Delete`.

**3. Rozsahová gesta už neopouštějí režim „vše odpovídající".** Když je vybráno
„vše, co odpovídá filtru", je uložený seznam seznamem *výjimek* — takže rozsah
přes `Shift`+šipku ho nově **odznačí**, místo aby celý výběr zúžil na jednu
stránku. Pokud výběr čtete přímo, počítejte s tím, že `getSelectedRecordKeys()`
v tomto režimu záměrně vrací `[]`; použijte `selectedRecordsQuery()` nebo
`eachSelectedRecord()`.

**4. Přepublikujte view tabulky, pokud jste ho přepsali.** Gesta potřebují
markup, který zkompilovaný JavaScript hledá, a publikovaná kopie
`resources/views/vendor/wire-table/tables/index.blade.php` ho mít nebude. View
nese kontraktní značku, takže zastaralá kopie spadne hlasitě v konzoli prohlížeče
místo toho, aby tiše vybírala špatné řádky:

```bash
php artisan vendor:publish --tag=wire-table::views --force
```

Své úpravy pak naneste znovu na nový soubor. Pokud jste view přepsali jen kvůli
vzhledu, bývá [Theming](theming.md) menší cesta.

**5. Akce nad záznamem, které byly jen chováním, se na mobilní kartě nově
vykreslí jako tlačítko.** Telefon nemá dvojklik, pravý klik ani hover, kterým by
se jeden nebo druhý dal objevit — akce navázaná jen na gesto tak byla po složení
tabulky nedosažitelná. Nově se na kartě vykreslí jako obyčejné tlačítko, a jen
tam; desktopová tabulka se nemění. Nic se nezdvojí: akce už přítomná
v `->actions()` i akce povýšená přes `->alsoInRowActions()` dá právě jedno
tlačítko a fallbacková tlačítka se počítají do `->collapseActionsOnMobile()`.
Vypnout lze pro konkrétní tabulku:

```php
->recordActionButtonsOnMobile(false)
```

---

<a id="javascript-assets"></a>
## JavaScriptové assety

Alpine controllery Wire si nově deklaruje každý balíček sám a dají se vypsat
z jednoho místa ve vašem layoutu. Při upgradu udělejte dvě věci.

**1. Přidejte `@wireStackScripts` do `<head>` layoutu.**

```blade
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @wireStackScripts {{-- [tl! focus] --}}
</head>
```

Je to aditivní — každý povrch si svůj bundle stále načte sám, takže aplikace bez
direktivy funguje dál. Ale je to právě ono, co opraví komponenty umírající po
návštěvě přes `wire:navigate` (`wireRecordSelection is not defined`, mrtvé
dropdowny, šedý scrim přes tabulku): cesta cachovaného Zpět/Vpřed v Livewire
nečeká na nově injektované `<head>` skripty a imunní je jen bundle, který už
v dokumentu byl. Viz
[Začínáme → JavaScriptové assety](getting-started.md#javascriptove-assety).

Pokud si vaše aplikace tohle dřív obcházela `@include`-ováním partialů balíčků
v layoutu, tyhle includy smažte a použijte direktivu — cesty k partialům jsou
interní a direktiva se s nimi stejně deduplikuje.

**2. `window.Sortable` už se neposkytuje.** SortableJS je zkompilovaný do bundlu
`wire-sortable`, takže `config('wire-sortable.sortablejs_cdn')` je nově ve výchozím
stavu `null` a žádný CDN skript se nenačítá. Řazení to neovlivní — drag controller
používá zabundlovanou kopii a globál nikdy nečte.

Týká se to jen **vašeho vlastního** kódu, pokud na existenci toho globálu spoléhal.
Buď si o skript řekněte zpět:

```php
// config/wire-sortable.php
'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
```

nebo si SortableJS zabundlujte sami:

```js
// resources/js/app.js
import Sortable from 'sortablejs';
window.Sortable = Sortable;
```

Nic dalšího se nemění: konfigurační klíč po nastavení pořád funguje a aplikací,
které ho už nastavené mají, se změna nedotkne.

---

## Hledání breaking changes

`CHANGELOG.md` je zdroj pravdy. Breaking changes jsou vyznačeny pod nadpisem
**Breaking Changes** u každého vydání, často s migrační tabulkou před/po.
Například vydání `0.1.0` přesunulo akce a notifikace z
`NyonCode\WireTable\…` do `NyonCode\WireCore\…`; changelog vypisuje každou
přesunutou třídu, takže můžete `use` příkazy upravit hromadným najít-a-nahradit.

Pokud třída nebo metoda zmíněná v této dokumentaci po upgradu už neexistuje,
byla pravděpodobně přesunuta nebo přejmenována — hledejte původní název
v `CHANGELOG.md`.

---

## Viz také

- [Začínáme](getting-started.md) — požadavky a instalace
- [Konfigurace](configuration.md) — publikovatelná konfigurace
- [Vzhled](theming.md) — udržování přepisů pohledů na minimu
- [Řešení potíží](troubleshooting.md) — problémy, které se objeví po aktualizaci
