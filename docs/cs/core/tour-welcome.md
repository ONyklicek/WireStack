---
order: 84
summary: Úvodní karta představí obsah průvodce a nabídne jeho spuštění nebo odložení na později.
---

# TourWelcome

Úvodní karta [průvodce](tours.md) stručně představí jeho obsah a nabídne dvě
možnosti: spustit jej hned, nebo se k němu vrátit později. Hodí se zejména pro
delší průvodce, jejichž automatické spuštění by mohlo uživatele vyrušit. Bez
úvodní karty průvodce rovnou ztlumí stránku a zvýrazní první prvek.

```php
use NyonCode\WireCore\Tours\TourWelcome;
```

## Jak to funguje

**Kdy se zobrazí.** Prohlížeč nejprve ověří, které kroky odkazují na prvek na
aktuální stránce. Teprve potom může zobrazit úvodní kartu. Pokud žádný krok
neodkazuje na prvek na této stránce, průvodce se nespustí a karta se nezobrazí.
Karta se zobrazí **pouze při spuštění od začátku**. Pokud uživatel přejde
na stránku pomocí [`on()`](tour-step.md#na-jine-strance) nebo se vrátí
k nedokončenému průvodci, pokračuje rovnou tam, kde skončil.

**Dvě odpovědi.**

- **Spustit** zavře kartu a zahájí průvodce prvním krokem. Zatím se nic
  neukládá; dokončení nebo přeskočení se zaznamená později.
- **Odložit** zavře kartu a uloží *odložení*. Průvodce se znovu nabídne až při
  další návštěvě, po skončení aktuální relace.

**Odložit neznamená Přeskočit.** Přeskočení je konečné a uloží se stejně jako
dokončení. Odložení pouze znamená „teď ne, zeptej se znovu později“.

**Počet odložení je omezený.** Průvodce se nebude nabízet stále dokola. Každé
odložení zvýší počítadlo. Po dosažení limitu nastaveného pomocí
[`postpone()`](tours.md#api-tour) se uloží stejný stav jako při přeskočení a
průvodce se přestane nabízet. Počet odložení se počítá napříč relacemi.
Výchozí limit je `3`; nastavuje se v `wire-core.tours.postpone`.
Volání `->postpone(0)` tlačítko **Odložit** skryje a ponechá pouze možnost
průvodce spustit.

**Proč se odložení váže na relaci, ne na čas.** Ukládá se identifikátor relace,
ve které k odložení došlo, a odložení platí jen po dobu jejího trvání. Díky tomu
má „později“ stejný význam při použití obou ovladačů: při `session` záznam po
skončení relace zanikne, zatímco `database` jej uchová. Identifikátor relace
umožňuje odlišit odložení od přeskočení. Není třeba nastavovat časový limit ani
řešit časová pásma.

**Kde běží.** Karta je součástí HTML stránky; Alpine ji pouze zobrazí nebo skryje.
Požadavek na server odešle jen tlačítko **Odložit**, aby se odložení zaznamenalo.
Klávesa Esc kartu zavře a chová se stejně jako **Odložit**. Pokud průvodce
odložení nepovoluje, karta se zavře bez uložení a při další návštěvě se zobrazí
znovu.

**Na co dát pozor.**

- **Počet odložení se zachová při změně verze, ale ne po dokončení.**
  Dokončení, přeskočení i opětovné spuštění průvodce smaže počet odložení.
  Změnou [`since()`](tours.md#co-je-noveho-po-aktualizaci) se počet také
  vynuluje. Odložení z předchozí verze se tak nezapočítají do nového průvodce.
- **Popisky se překládají při vykreslení stránky, nikoli při registraci.**
  Průvodce se registruje v metodě `boot()` poskytovatele služeb. Pokud by se
  překlad vyhodnotil už při registraci, použil by jazyk aktivní v konzoli.
  Ponechte `start()` a `later()` bez vlastních popisků a použije se překlad
  frameworku.
- **Karta potřebuje rozvržení stránky, které ji vykreslí**, stejně jako zbytek
  průvodce. Viz
  [Tour → Vlastní layout](tours.md#vlastni-layout).

## Základní použití

```php
Tour::make('getting-started')
    ->welcome(
        TourWelcome::make()
            ->heading('Vítejte v aplikaci')
            ->text('Za minutu se zorientujete v jejích hlavních částech.'),
    )
    ->steps([
        TourStep::make('admin-sidebar')->text('Hlavní části aplikace najdete v této nabídce.'),
    ]);
```

## Obsah

`heading()` nastavuje nadpis karty a `text()` krátký popis pod ním. Obojí je
prostý text a kteroukoli hodnotu můžete vynechat. Překládejte je stejně jako
ostatní texty v aplikaci:

```php
TourWelcome::make()
    ->heading(__('tours.welcome.heading'))
    ->text(__('tours.welcome.text'));
```

## Tlačítka

Oba popisky jsou nepovinné. Pokud je nenastavíte, použije se přeložené znění
frameworku. Nemusíte tak opakovat stejný text v každém průvodci a riskovat, že
některý zůstane v nesprávném jazyce:

```php
TourWelcome::make()
    ->start('Provést aplikací')    // výchozí: „Spustit průvodce“
    ->later('Teď ne');             // výchozí: „Třeba později“
```

Tlačítko **Odložit** se zobrazí pouze tehdy, když průvodce odložení povoluje.
Následující příklad nastavuje stejný výsledek jako `->postpone(0)` — kartu, ze
které lze průvodce pouze spustit:

```php
Tour::make('one-step')->postpone(0)->welcome(TourWelcome::make()->heading('Novinka ve 2.2'));
```

## Jak často se zeptá znovu

`postpone()` nastavujete na [Tour](tours.md#api-tour), nikoli na kartě
`TourWelcome`. Počet povolených odložení je vlastností průvodce, který kartu
zobrazuje.

```php
Tour::make('getting-started')->postpone(5);   // pět „později“, pak se ptát přestane
```

Když se nenastaví, platí konfigurovaná výchozí hodnota:

```php
// config/wire-core.php
'tours' => [
    'postpone' => env('WIRE_TOURS_POSTPONE', 3),
],
```

## Vlastní markup

Pokud chcete změnit strukturu karty, nejen její vzhled — například přidat
ilustraci, logo nebo krátké video — nahraďte výchozí kartu pomocí `view()`:

```php
TourWelcome::make()
    ->heading('Vítejte na palubě')
    ->view('tours.welcome');
```

Vlastní view se vloží **do Alpine komponenty průvodce**. V tomto kontextu jsou
dostupné následující vlastnosti a metody:

| Jméno | Co to je |
| --- | --- |
| `greeting` | Určuje, zda se karta zobrazí. Vlastní view musí její viditelnost řídit samo, například pomocí `x-show="greeting"` |
| `begin()` | Spustí průvodce |
| `later()` | Zavře kartu a uloží odložení, pokud ho průvodce povoluje |
| `welcome` | Hodnoty `heading`, `text`, `start` a `later`; při zakázaném odložení je `later` rovno `null` |

View obdrží také `$welcome` (objekt s daty karty) a `$tour`. Můžete je použít
pro obsah, který chcete vykreslit na serveru:

```blade
{{-- resources/views/tours/welcome.blade.php --}}
<div x-show="greeting" x-cloak class="fixed inset-0 z-[63] grid place-items-center p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-2xl dark:bg-gray-800">
        <img src="/img/welcome.svg" alt="" class="mx-auto mb-4 h-24">

        <h2 class="text-lg font-semibold">{{ $welcome->getHeading() }}</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $welcome->getText() }}</p>

        <div class="mt-6 flex justify-center gap-2">
            <button type="button" x-show="welcome.later" x-text="welcome.later" x-on:click="later()"></button> {{-- [tl! focus:start] --}}
            <button type="button" x-text="welcome.start" x-on:click="begin()"></button> {{-- [tl! focus:end] --}}
        </div>
    </div>
</div>
```

Vlastní view mění strukturu karty, nikoli její chování. O tom, kdy se karta
zobrazí a co se stane po odložení, stále rozhoduje průvodce.

## Stylování

Výchozí karta obsahuje element hooky, takže většinu úprav vzhledu zvládnete bez
vlastního view:

| Hook | Prvek |
| --- | --- |
| `tour-welcome` | Vrstva přes celou obrazovku s kartou uprostřed |
| `tour-welcome-heading`, `tour-welcome-text` | Nadpis a popis karty |
| `tour-welcome-start`, `tour-welcome-later` | Tlačítka pro spuštění a odložení |

```css
[data-wire="tour-welcome"] > div { @apply max-w-lg rounded-3xl; }
[data-wire="tour-welcome-start"] { @apply bg-emerald-600 hover:bg-emerald-700; }
```

## Rozšířený příklad

Ukázka průvodce pro první použití aplikace: před spuštěním představí jeho obsah
a dovolí jej dvakrát odložit.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;
use NyonCode\WireCore\Tours\TourWelcome;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Tours::class)->register(
            Tour::make('getting-started')
                ->postpone(2)                                    // [tl! focus:start]
                ->welcome(
                    TourWelcome::make()
                        ->heading('Za dvě minuty se v aplikaci zorientujete')
                        ->text('Představíme vám čtyři důležité části aplikace.')
                        ->start('Spustit průvodce')
                        ->later('Teď ne'),
                )                                                // [tl! focus:end]
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('Hlavní části aplikace')
                        ->text('Jednotlivé části najdete v této nabídce.')
                        ->placement('right-start'),

                    TourStep::make('global-search-trigger')
                        ->text('Nebo skočte rovnou na libovolný záznam.'),
                ]),
        );
    }
}
```

Pokud uživatel zvolí „Teď ne“, průvodce se nabídne při příští návštěvě a ještě
jednou potom. Další odložení by znamenalo čtvrté zobrazení, proto se průvodce
místo toho označí jako vyřízený a přestane se nabízet. Uživatel jej může znovu
spustit přes
[Spustit průvodce znovu](tours.md#opetovne-spusteni) v uživatelském menu.

## API TourWelcome

```php
TourWelcome::make()                   // bez argumentů — všechny volby jsou nepovinné
->heading(?string $heading)           // nadpis karty — bez výchozí hodnoty
->text(?string $text)                 // popis karty, prostý text — bez výchozí hodnoty
->start(?string $start)               // popisek tlačítka pro spuštění — výchozí __('wire-core::messages.tour_start')
->later(?string $later)               // popisek tlačítka pro odložení — výchozí __('wire-core::messages.tour_later')
->view(?string $view)                 // použít toto view místo výchozí karty — bez výchozí hodnoty
->getHeading(): ?string
->getText(): ?string
->getStart(): ?string                 // vlastní popisek, nebo null při použití výchozího překladu
->getLater(): ?string
->getView(): ?string
```

## Související

- [Tour](tours.md) — kdo průvodce uvidí, kde běží a jak se počítá `postpone()`
- [TourStep](tour-step.md) — na co jednotlivé zastávky ukazují
- [Konfigurace](../start/configuration.md#pruvodci) — výchozí povolený počet odkladů
- [Vzhled → Stylovací hooky](../start/theming.md#stylovaci-hooky) — co element hook slibuje
