---
order: 84
summary: Blok, kterým průvodce začíná — čeho se prohlídka týká a volba mezi „spustit teď“ a „zeptej se znovu později“.
---

# TourWelcome

Blok, kterým [průvodce](tours.md) začíná: karta uprostřed obrazovky s tím, co
prohlídka pokrývá, a dvě tlačítka — spustit teď, nebo se nechat zeptat znovu
později. Sáhněte po něm, když je průvodce dost dlouhý na to, aby jeho nevyžádané
spuštění bylo vyrušení. Bez něj průvodce začne tím, že ztlumí stránku a na něco
ukáže, což je to, co dělal vždycky.

```php
use NyonCode\WireCore\Tours\TourWelcome;
```

## Jak to funguje

**Kdy se zobrazí.** Prohlížeč si nejdřív naplánuje průvodce — které kroky mají
na této stránce prvek — a teprve pak pozdraví. Průvodce, kterému chybí prvky
všech kroků, se nespustí, takže ani nepozdraví. Karta se zobrazí **jen když
průvodce začíná od začátku**: kdo se sem dostal krokem s
[`on()`](tour-step.md#na-jine-strance) nebo se vrací k prohlídce, kterou nechal
rozdělanou, je vrácen tam, kde skončil, aniž by se ho někdo ptal, jestli spustit
něco, co už běží.

**Dvě odpovědi.**

- **Spustit** zavře kartu a začne prohlídku prvním krokem. Nic se nezaznamenává
  — dokončení nebo přeskočení se zaznamená později, jako vždy.
- **Odložit** zavře průvodce a zaznamená *odklad*: průvodce se do konce této
  session znovu nenabídne a při příští návštěvě se zeptá znovu.

**Odložit není Přeskočit.** Přeskočit je konečné — ukládá se přesně jako
dokončení, protože kdo přeskočil, rozhodl se. Odložit je odpověď, která se dřív
nedala dát: teď ne, zeptej se znovu.

**Odklady se počítají a dojdou.** Pozdrav, který se vrací navždy, by byl horší
než ten, který se nezeptal nikdy. Každé Odložit zvýší počet a to, které dosáhne
limitu z [`postpone()`](tours.md#api-tour), místo toho průvodce potvrdí — tentýž
záznam, jaký píše Přeskočit, takže nic dalšího nemusí řešit čtvrtý stav. Výchozí
limit je `3` a konfiguruje se jako `wire-core.tours.postpone`. `->postpone(0)`
tlačítko Odložit odstraní úplně a zbyde karta, kterou lze jen spustit.

**Proč session a ne hodiny.** Odklad je orazítkovaný id session, ve které
vznikl, a platí, jen dokud trvá ta session. To je to, co dává „později“ na každém
driveru stejný význam: na `session` by záznam stejně vypršel, zatímco na
`database` sezení přežije — a bez toho porovnání by se odklad nedal odlišit od
přeskočení. Žádná doba ke konfiguraci a žádné hodiny, které by šly splést mezi
časovými pásmy.

**Kde běží.** Karta je markup, který stránka už nesla, jen ho Alpine zobrazí a
skryje. Request dělá jedině Odložit, aby odklad zaznamenal. Escape kartu zavře a
znamená totéž co Odložit — a u průvodce, který odklad nepovoluje, ji zavře bez
zaznamenání čehokoli, takže průvodce při příští návštěvě pozdraví znovu.

**Pasti.**

- **Počet přežije změnu verze, ale ne dokončení.** Dokončení, přeskočení i
  opětovné spuštění odklad i s jeho počtem smažou. Změna
  [`since()`](tours.md#co-je-noveho-po-aktualizaci) počet také začne od nuly,
  protože počet držený proti starší verzi by znamenal, že autorův třetí pozdrav
  je pro někoho prvním.
- **Popisky se překládají při vykreslení stránky, ne při registraci.** Průvodce
  se registruje v `boot()` service provideru a překlad vyhodnocený tam je v tom
  jazyce, který měla konzole. Nechte `start()` a `later()` nenastavené a
  dostanete přeloženou formulaci frameworku.
- **Karta potřebuje layout, který ji kreslí**, stejně jako zbytek průvodce. Viz
  [Tour → Vlastní layout](tours.md#vlastni-layout).

## Základní použití

```php
Tour::make('getting-started')
    ->welcome(
        TourWelcome::make()
            ->heading('Vítejte na palubě')
            ->text('Minuta, a budete vědět, kde co je.'),
    )
    ->steps([
        TourStep::make('admin-sidebar')->text('Všechny části aplikace jsou tady.'),
    ]);
```

## Obsah

`heading()` je tučný řádek, `text()` věta nebo dvě pod ním. Obojí je prostý text
a kterékoli z nich se dá vynechat. Překládejte je jako každý jiný řetězec:

```php
TourWelcome::make()
    ->heading(__('tours.welcome.heading'))
    ->text(__('tours.welcome.text'));
```

## Tlačítka

Oba popisky jsou volitelné a oba se vrací k přeložené formulaci frameworku — což
obvykle chcete, protože opakovat ji v každém průvodci je způsob, jak jeden z nich
skončí ve špatném jazyce:

```php
TourWelcome::make()
    ->start('Provést aplikací')    // výchozí: „Spustit průvodce“
    ->later('Teď ne');             // výchozí: „Třeba později“
```

Tlačítko Odložit se vykreslí, jen když průvodce odklad povoluje. Tyhle dvě věci
říkají totéž — karta, kterou lze jen spustit:

```php
Tour::make('one-step')->postpone(0)->welcome(TourWelcome::make()->heading('Novinka ve 2.2'));
```

## Jak často se zeptá znovu

`postpone()` patří na [Tour](tours.md#api-tour), ne sem: kolikrát se smí
prohlídka odložit, je vlastnost prohlídky, ne karty, která ji nabízí.

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

Když chcete jiný markup, a ne jiné stylování — ilustraci, logo, krátké video —
`view()` kartu frameworku nahradí úplně:

```php
TourWelcome::make()
    ->heading('Vítejte na palubě')
    ->view('tours.welcome');
```

View se vloží **dovnitř vlastního Alpine scope průvodce** a ten scope je celý
kontrakt:

| Jméno | Co to je |
| --- | --- |
| `greeting` | Jestli má být karta vidět. View si svou viditelnost řídí samo — nikdo ji za vás nezobrazí |
| `begin()` | Spustit prohlídku |
| `later()` | Zavřít ji a zaznamenat odklad, pokud ho průvodce povoluje |
| `welcome` | Vyhodnocená data: `heading`, `text`, `start`, `later` (null, když je odklad vypnutý) |

Dostane také `$welcome` (tento objekt) a `$tour`, pro cokoli, co raději vykreslíte
na serveru:

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

View je únikový poklop pro markup, ne pro chování: kdy se karta objeví a co stojí
Odložit, zůstává u průvodce.

## Stylování

Karta frameworku nese element hooky, takže většina změn žádné view nepotřebuje:

| Hook | Prvek |
| --- | --- |
| `tour-welcome` | Vycentrovaná vrstva |
| `tour-welcome-heading`, `tour-welcome-text` | Dva řádky |
| `tour-welcome-start`, `tour-welcome-later` | Dvě tlačítka |

```css
[data-wire="tour-welcome"] > div { @apply max-w-lg rounded-3xl; }
[data-wire="tour-welcome-start"] { @apply bg-emerald-600 hover:bg-emerald-700; }
```

## Rozšířený příklad

Průvodce při prvním spuštění, který se před startem zeptá, povolí dva odklady a
vlastními slovy aplikace řekne, o co jde.

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
                        ->heading('Dvě minuty a budete se tu vyznat')
                        ->text('Ukážeme na čtyři věci a pak vás necháme být.')
                        ->start('Ukázat')
                        ->later('Teď ne'),
                )                                                // [tl! focus:end]
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('Všechno je tady')
                        ->text('Každá část aplikace má v tomhle menu svou položku.')
                        ->placement('right-start'),

                    TourStep::make('global-search-trigger')
                        ->text('Nebo skočte rovnou na libovolný záznam.'),
                ]),
        );
    }
}
```

Kdo zvolí „Teď ne“, bude se dotázán při příští návštěvě a ještě jednou potom.
Potřetí by to byl čtvrtý pozdrav, takže se průvodce místo toho zaznamená jako
viděný a přestane. Pořád si ho může vrátit přes
[Spustit průvodce znovu](tours.md#opetovne-spusteni) v uživatelském menu.

## API TourWelcome

```php
TourWelcome::make()                   // bez argumentů — všechno na něm je volitelné
->heading(?string $heading)           // tučný řádek — výchozí žádný
->text(?string $text)                 // tělo, prostý text — výchozí žádné
->start(?string $start)               // popisek tlačítka pro spuštění — výchozí __('wire-core::messages.tour_start')
->later(?string $later)               // popisek tlačítka pro odložení — výchozí __('wire-core::messages.tour_later')
->view(?string $view)                 // vykreslit tohle view místo karty frameworku — výchozí žádné
->getHeading(): ?string
->getText(): ?string
->getStart(): ?string                 // autorův popisek, nebo null pro ten frameworkový
->getLater(): ?string
->getView(): ?string
```

## Související

- [Tour](tours.md) — kdo průvodce uvidí, kde běží a jak se počítá `postpone()`
- [TourStep](tour-step.md) — na co jednotlivé zastávky ukazují
- [Konfigurace](../start/configuration.md#pruvodci) — výchozí povolený počet odkladů
- [Vzhled → Stylovací hooky](../start/theming.md#stylovaci-hooky) — co element hook slibuje
