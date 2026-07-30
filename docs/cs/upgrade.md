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
| Laravel | 10, 11, 12 |
| Livewire | 3.x |
| Tailwind CSS | 3.x nebo 4.x |

Před upgradem ověřte, že je vaše aplikace splňuje.

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
