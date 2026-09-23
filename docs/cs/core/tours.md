---
order: 82
summary: Průvodci, kteří se spustí, když člověk obrazovku vidí poprvé, a znovu, když ji aktualizace změní. Omezení na zónu, resource, stránku a oprávnění.
---

# Tour

Průvodce, který krok za krokem ukazuje na skutečné prvky obrazovky: panel vedle
prvku, jedna nebo dvě věty o něm a tlačítka Další, Zpět a Přeskočit. Spustí se
sám, když člověk poprvé uvidí obrazovku, kterou si průvodce nárokuje. Po
aktualizaci ho můžete spustit znovu pro každého, kdo prošel předchozí verzi.
Sáhněte po něm, když je na obrazovce tolik věcí, že nový uživatel neví, kde
začít. Na jeden ovládací prvek stačí tooltip; průvodce je na to, jak spolu
souvisí víc prvků.

```php
use NyonCode\WireCore\Tours\Tour;
```

## Jak to funguje

Průvodce je definice, kterou jednou zaregistrujete v service provideru. Framework
pak při každém vykreslení celé stránky rozhodne, jestli ho má tento člověk
vidět, a samotný průvodce běží v prohlížeči.

**Který průvodce se spustí.** Zatímco layout vykresluje stránku, framework si z
aktuální routy přečte tři věci: zónu, registrovaný resource a druh stránky
(`index`, `create`, `view`, `edit` nebo vlastní stránka resource). Pak projde
registrované průvodce **od nejnižšího `sort()`** a vezme prvního, který splní
všechno z tohoto:

1. Tento člověk **tuto verzi** zatím nedokončil ani nepřeskočil a ani si ji
   [neodložil](tour-welcome.md) v session, ve které je.
2. Sedí jeho omezení umístění. Každé z `zones()`, `resource()` a `page()`, které
   nastavíte, musí sedět, a to, které nenastavíte, sedí na cokoli.
3. Pustí ho jeho viditelnost. To je `visible()` / `hidden()` a sdílená
   autorizace: `permission()`, `authorize()`, `authorizeUsing()`.

Když sedí víc průvodců, spustí se jen první. Ostatní počkají na další návštěvu
a spustí se, až bude první dokončen nebo přeskočen. Dva průvodci hned po sobě
jsou horší než jeden.

**Výchozí stav.** Průvodce bez omezení běží pro každého na každé obrazovce, ve
verzi `'1'` a se `sort` `0`. Ve výchozím stavu není zaregistrovaný žádný.
Framework nemá vlastního průvodce, takže aplikace, která žádného nezaregistruje,
nedostane vůbec nic.

**Co znamená „viděl".** Dokončení i přeskočení se zaznamenají stejně. Obojí
uloží aktuální hodnotu `since()` průvodce k jeho id, pro daného člověka. Průvodce
se spustí znovu jen tehdy, když se uložená hodnota od `since()` liší. Jedno
porovnání tak pokrývá oba případy:

- první návštěvu, kdy zatím nic uložené není, a
- upraveného průvodce, kdy je uložená hodnota stará.

Nic neporovnává čísla verzí jako čísla a nic nečte verzi balíčku. Chcete-li
průvodce ukázat znovu všem, změňte `since()`.

**Kde běží.** Rozhodování a zaznamenání probíhá na serveru. Přechody mezi kroky
běží v prohlížeči, v Alpine. Panel umisťuje pomocník nad Floating UI, který
`@wireStackScripts` už dává na každou stránku, takže tato funkce nepřidává žádný
vlastní JavaScriptový bundle. Livewire request dělají dvě věci: každý nově
zobrazený krok, aby se zaznamenalo, kam až člověk došel, a dokončení nebo
přeskočení, aby se zaznamenalo, že skončil.

**Opuštěný v půlce.** Kdo průvodce opustí kliknutím jinam, aniž by ho dokončil
nebo přeskočil, potká ho při příští návštěvě stránky, na které průvodce začíná,
u kroku, u kterého skončil. Pokud je ten krok na jiné stránce, průvodce se
otevře u posledního kroku před ním na této stránce, takže „Další" vede zpět k
němu. Krok se ukládá k hodnotě `since()` průvodce, takže po nové verzi začne
průvodce od začátku. Dokončení, přeskočení i opětovné spuštění ho smažou.

**Kde se ukládá.** Pod klíčem `tours` v uživatelském úložišti preferencí, s
vlastním nastavením driveru `wire-core.tours.preferences.default`. **To má
výchozí hodnotu `session`**, ne driver `null`, který je výchozí pro zbytek
úložiště preferencí. S úložištěm, které si nic nepamatuje, by průvodce stejného
člověka přerušoval při každém načtení stránky. Se `session` je nejhorší případ
jednou za session. Pro „jednou provždy" ho přepněte na `database` a spusťte
migraci (viz [Zapamatování](#zapamatovani)). Host používá driver pro hosty,
který je také `session`.

**Pasti.**

- **Routa se čte jednou, při vykreslení stránky.** Během Livewire requestu je
  aktuální routou vlastní endpoint Livewire, ne vaše stránka, takže zóna,
  resource i stránka jsou tam neznámé. Framework je proto přečte jednou, když
  layout vykresluje stránku, a dál je jen předává.
- **Id je identita.** Potvrzení se ukládají k němu. Přejmenovat id průvodce je
  totéž jako zaregistrovat nového: všichni ho uvidí znovu.
- **Krok, jehož prvek chybí nebo je skrytý, se přeskočí a nezapočítá.**
  Prohlížeč při startu projde všechny kroky a nechá si jen ty, jejichž prvek je
  vidět, takže počítadlo vždy ukazuje, kolik kroků člověk opravdu uvidí. Prvek,
  který je vykreslený, ale skrytý, třeba neotevřený dropdown, se počítá jako
  chybějící. Průvodce, který takhle přijde o všechny kroky, se nespustí.
- **Na telefonu se panel přichytí dolů.** Pod 640 px přestane plavat vedle
  prvku a sedí přes spodní okraj obrazovky, zatímco zvýrazňující rámeček dál
  označuje prvek. Každý krok odscrolluje svůj prvek do místa mezi horní lištou a
  panelem. Co telefon skrývá — sidebar je tam vysouvací panel — se přeskočí jako
  každý skrytý prvek, takže telefon může napočítat méně kroků než desktop. Když
  okno překročí 640 px jedním či druhým směrem, panel se umístí znovu.
- **Krok na jiné stránce tam přejde.** Krok vytvořený s
  [`on()`](tour-step.md#na-jine-strance) patří na jinou stránku téže zóny a
  přeskočí se pro toho, koho routa té stránky nepustí.
  „Další" tam přejde a průvodce pokračuje; „Zpět" z něj vrátí. Stránka a krok
  cestují v query stringu (`wire-tour`, `wire-tour-step`), který prohlížeč
  odstraní, jakmile si je průvodce převezme. Server ho přijme jen pro průvodce,
  kterého tento člověk nedokončil, v zóně, pro kterou byl deklarován, a u kroku,
  který na té stránce opravdu je.
- **Potřebuje layout shellu.** Průvodce vykresluje layout `wire-admin`. Pokud
  vykreslujete vlastní layout, přečtěte si [Vlastní layout](#vlastni-layout).

## Základní použití

Zaregistrujte ho v metodě `boot()` service provideru:

```php
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

$this->app->make(Tours::class)->register(
    Tour::make('getting-started')->steps([
        TourStep::make('admin-sidebar')->text('Všechny části aplikace najdete tady.'),
        TourStep::make('global-search-trigger')->text('Nebo skočte rovnou na libovolný záznam.'),
    ]),
);
```

Každý krok jmenuje **element hook**, tedy jméno `data-wire`, které framework
píše do svého markupu. Najdete ho stejně jako cokoli jiného na stránce —
otevřete devtools a přečtěte atribut přímo z prvku, nebo si je vypište hromadně
jednořádkovým příkazem z [Vzhled → Jak jméno najít](../start/theming.md#jak-jmeno-najit).
Kroky podrobně popisuje [TourStep](tour-step.md).

## Zeptat se nejdřív

Průvodce, který se spustí sám, je vyrušení, se kterým nikdo nesouhlasil. Dejte
mu [welcome blok](tour-welcome.md) a z první doby se stane otázka — karta
uprostřed obrazovky s tlačítkem spustit teď a tlačítkem nechat se zeptat znovu
později:

```php
use NyonCode\WireCore\Tours\TourWelcome;

Tour::make('getting-started')
    ->welcome(
        TourWelcome::make()
            ->heading('Dvě minuty a budete se tu vyznat')
            ->text('Ukážeme na čtyři věci a pak vás necháme být.'),
    )
    ->steps([...]);
```

**Odložit není Přeskočit.** Přeskočit je konečné — ukládá se přesně jako
dokončení. Odložit průvodce položí na dobu session a při příští návštěvě se
zeptá znovu, a počítá se: to, které dosáhne limitu `postpone()` (výchozí `3`),
průvodce zaznamená jako viděný, takže se pozdrav nemůže vracet navždy.
Blok, jeho popisky i vlastní markup popisuje [TourWelcome](tour-welcome.md).

## Registrace

`Tours` je singleton. Každé volání `register()` přidává do stejného registru,
takže průvodce může registrovat balíček i aplikace. Registrace je
**idempotentní podle id**: když se id zaregistruje znovu, druhá registrace se
ignoruje a zůstane první. Provider, který se spustí dvakrát, tak nemůže vytvořit
dvě kopie průvodce. Znamená to také, že dvě definice se stejným id se nikdy
nesloučí.

Průvodce se kontroluje při registraci. **Průvodce bez kroků vyhodí**
`TourDefinitionException`. Bez této kontroly by se zaznamenal jako viděný hned
při startu, za něco, co člověk nikdy neviděl.

```php
$this->app->make(Tours::class)->register($first, $second, $third);
```

## Kdo průvodce uvidí

Viditelnost průvodce funguje jako u každé jiné komponenty. Používá stejnou
[sdílenou autorizaci](../start/authorization.md#sdilena-pravidla-komponent)
jako sloupce, akce a widgety, ověřovanou přes `Gate` z Laravelu. Wildcard
oprávnění a super-admin gate z `laravel-permission-extended` platí stejně jako
všude jinde:

```php
Tour::make('sales-orders')->permission('sales.*');

Tour::make('month-end')->authorize('closeTheBooks');

Tour::make('team-leads')->authorizeUsing(fn (User $user) => $user->leads_a_team);
```

Průvodce s oprávněním se **hostovi nikdy neukáže**. Průvodce po obrazovce, kterou
člověk nemůže otevřít, tu obrazovku prozrazuje, takže autorizace tu jako všude
jinde při pochybnosti odmítá.

Různí lidé obvykle potřebují různé průvodce: administrátor průvodce po
nastavení, člověk z obchodu průvodce po objednávkách. Zaregistrujte jednoho
průvodce pro každé publikum. Každý má vlastní id, takže se každý potvrzuje
zvlášť. Komu se změní role, ten jednoho viděl a druhého ne, a druhého dostane.

## Kde běží

Tři omezení, všechna volitelná, kombinovaná přes AND:

```php
Tour::make('orders-list')
    ->zones('sales')          // jen v zóně sales
    ->resource('orders')      // jen na stránkách resource orders
    ->page('index');          // jen na jeho seznamu
```

Každé přijímá víc hodnot a sedí na kteroukoli z nich:

```php
->resource('orders', 'invoices')
->page('index', 'view')
```

**Zóny** jsou prefixy jmen rout ze [směrovacích zón](../panels/routing.md).
`zones('sales')` a `zones('sales.')` znamenají totéž. Aplikace bez zón má jednu
nepojmenovanou zónu a **`zones(null)`** ji pojmenuje. Potřebujete to, když chcete
říct „jen mimo všechny zóny"; vynechat `zones()` znamená „v jakékoli zóně, i v
žádné".

```php
->zones(null)             // jen aplikace bez zóny
->zones('sales', null)    // zóna sales, nebo žádná zóna
```

Pro podmínku, kterou tato tři omezení nevyjádří, použijte `visible()` s
closurou. Spouští se, zatímco layout vykresluje stránku, jen u průvodce, jehož
zóna, resource a stránka už sedí, a nikdy během Livewire requestu. Za jedno
vykreslení se může spustit víckrát, protože stejnou otázku klade i položka pro
opětovné spuštění, takže ať je levná.

Tato omezení říkají, kde průvodce **začíná**. Krok může i tak vést jinam v téže
zóně, pomocí [`on()`](tour-step.md#na-jine-strance). Stránka, na kterou dovede,
omezením průvodce odpovídat nemusí, protože průvodce je tam přenesen, ne vybrán.

## Co je nového po aktualizaci

Průvodce po novince je průvodce jako každý jiný. Dejte mu vydání, ve kterém ho
posíláte:

```php
Tour::make('saved-views')
    ->since('2.2')
    ->resource('orders')
    ->steps([
        TourStep::make('table-view-save')->text('Uložte si filtry, které používáte každý den.'),
    ]);
```

Když změníte, co průvodce ukazuje, změňte i jeho `since()`. Každý, kdo prošel
starou verzi, uvidí novou jednou, a nikdo jiný ji neuvidí dvakrát. Hodnota se
musí jen lišit: může to být číslo vydání, datum nebo slovo.

**Samotná úprava kroků nestačí.** Obsah průvodce se nijak nehashuje, takže
průvodce, kterému se `since()` nepohnulo, zůstává potvrzený a nové znění se
dostane jen k lidem, kteří ho nikdy nedokončili. Nasazení nového vydání to
neudělá také — průvodce není nový proto, že vyšla patch verze. Změna `since()`
je jediná věc, která říká „ukaž to znovu“, a je to celá cesta po aktualizaci:
není tu druhé úložiště, které by se mazalo, ani příkaz, který by se pouštěl.

Průvodce po novince má často jen jeden krok. Používá stejná omezení, takže
funkci, kterou může používat jen jedna role, ukáže průvodce jen té roli.

## Pořadí

Když si stejnou obrazovku pro stejného člověka nárokuje víc průvodců, **spustí
se ten s nejnižším `sort()`**. Další se spustí při některé z příštích návštěv,
až bude první dokončen nebo přeskočen.

```php
Tour::make('admin-settings')->permission('admin.*')->sort(-10);
Tour::make('everyone')->sort(0);
```

Je to explicitní číslo, protože nejkonkrétnější shodu nejde spolehlivě vybrat.
Omezení na zónu a omezení na oprávnění se nedají porovnat. Průvodci se stejným
`sort` si zachovají pořadí, ve kterém byli zaregistrováni.

## Zapamatování

Vlastní nastavení úložiště průvodců, oddělené od zbytku úložiště preferencí:

```php
// config/wire-core.php
'tours' => [
    'preferences' => [
        'default' => env('WIRE_TOURS_DRIVER', 'session'),
        'guest' => env('WIRE_TOURS_GUEST_DRIVER', 'session'),
    ],
],
```

| Driver | Pamatuje si | Potřebuje |
| --- | --- | --- |
| `session` (výchozí) | Po dobu session | Nic |
| `database` | Navždy | Migraci `wire_preferences` |
| `null` | Nic — průvodce běží při každém načtení stránky | Nic; jen pro testování |

```bash
php artisan vendor:publish --tag="wire-core::migrations"
php artisan migrate
```

## Opětovné spuštění

Když si aktuální obrazovku nárokuje nějaký průvodce, objeví se v uživatelském
menu přihlášeného člověka položka **Spustit průvodce znovu**. Smaže jeho záznam
o tomto jednom průvodci, a jen o něm, a znovu načte stránku, takže se průvodce
spustí znovu. Na obrazovkách, které si žádný průvodce nenárokuje, se položka
nevykreslí vůbec.

Opětovné načtení je záměr. Panel je součástí layoutu stránky, který Livewire
request znovu nevykresluje. Server si ani neukládá adresu, kam se vrátit:
prohlížeč znovu načte stránku, na které už je.

### Vlastní spouštěč

Položku v uživatelském menu kreslí framework a vykresluje se jen tam, kde si
obrazovku už nějaký průvodce nárokuje — takže tam stačí průvodce zapomenout a
prohlížeč znovu načte stránku, na které je. Spouštěč **někde jinde** — tlačítko
nápovědy v hlavičce stránky, řádek v nastavení — je pro průvodce, který běží
tam, kde dotyčný není, takže samotné zapomenutí by vypadalo, že nedělá nic.
`replayNow()` ho zapomene *a* vrátí cestu na obrazovku, kde běží:

```php
use Livewire\Component;
use NyonCode\WireCore\Tours\TourState;

class HelpButton extends Component
{
    public function replayOnboarding(TourState $tours)
    {
        return $tours->replayNow('getting-started', auth()->user());   // [tl! focus]
    }

    public function render()
    {
        return view('tours.replay-button');
    }
}
```

Adresa se staví z vlastních `zones()`, `resource()` a `page()` průvodce přes
téhož vlastníka, ze kterého pochází každý odkaz v menu, takže se hýbe spolu s
routami. Nese průvodce v query stejně, jako po stránkách cestuje krok, takže
průvodce už při otevření stránky běží.

Tři věci, které je dobré vědět, než si takový spouštěč zapojíte:

- **Může odpovědět ničím, a průvodce je stejně zapomenutý.** Průvodce omezený
  ničím užším než zónou nemá jedinou obrazovku, kam poslat, a ten, jehož stránku
  dotyčný nesmí otevřít, nemá žádnou, kam ho poslat smí. Vrácení null z Livewire
  akce neudělá nic, takže když není kam jít, načtěte stránku znovu:

  ```php
  if (($redirect = $tours->replayNow('getting-started', auth()->user())) === null) {
      $this->js('window.location.reload()');
  }

  return $redirect;
  ```

- **Adresa se nikdy nebere z requestu.** Počítá se z průvodce a z rout. Zřejmá
  implementace drží adresu stránky ve veřejné Livewire vlastnosti — a každá
  veřejná vlastnost je zapisovatelná z prohlížeče, takže „uživatel může
  přesměrovat jen sám sebe“ přestane platit ve chvíli, kdy někdo dostane odkaz,
  který ji nastaví.
- **Neznámé id se ignoruje**, není to chyba — průvodce mohl být odstraněn mezi
  vykreslením stránky a kliknutím.

`replay()` je totéž bez přesměrování, pro spouštěč na obrazovce, kterou si
průvodce už nárokuje. Zavolejte jedno z nich jednou pro každé id průvodce, které
chcete nabídnout.

#### Jak ho dostat na obrazovku

Komponenta výše je to, co dělá request. Všechno níž je způsob, jak ji někomu
dostat před oči, a všechny mountují tutéž komponentu.

**Vlastní view.** Tlačítko je obyčejné, takže bere propsy sdílené komponenty:

```blade
{{-- resources/views/tours/replay-button.blade.php --}}
<x-wire::button wire:click="replayOnboarding" color="gray" size="sm" icon="academic-cap">
    {{ __('Spustit průvodce znovu') }}
</x-wire::button>
```

**Vedle vlastních tlačítek tabulky**, jako header akce. Tahle cesta `replayNow()`
použít nemůže: `successRedirect()` bere URL, ne redirect, který vrací Livewire
akce — průvodce se proto zapomene v callbacku a adresa se spočítá vedle:

```php
use NyonCode\WireCore\Tours\TourDestination;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourState;

HeaderAction::make('replay-tour')
    ->label('Spustit průvodce znovu')
    ->icon('academic-cap')
    ->action(fn () => app(TourState::class)->replay('getting-started', auth()->user()))
    ->successRedirect(function (): ?string {                                    // [tl! focus:start]
        $tour = app(Tours::class)->get('getting-started');

        return $tour === null
            ? null
            : app(TourDestination::class)->start($tour, auth()->user());
    });                                                                         // [tl! focus:end]
```

V tom bloku jsou dva ostré rohy a ani jeden není kosmetický:

- **Callback akce se plní podle *jména* parametru, ne z kontejneru.**
  `fn (TourState $tours) => …` tady není dependency injection — invoker předá
  pojmenované argumenty z payloadu (`record`, `records`, `data`) a nic jiného,
  takže parametr, který naplnit neumí, nepředá vůbec a closure spadne na počtu
  argumentů. Uvnitř closure sáhněte po `app()`, jako výše.
- **`Tours::get()` odpoví null na id, které nezná, a `TourDestination::start()`
  bere `Tour`.** Tohle je jediné místo v celé funkci, kde je překlep v id
  `TypeError`, a ne tiché nic — takže se null ošetří, místo aby se předpokládalo,
  že nenastane.

**Na každé stránce, bez editace stránky.** [Render hook](../start/theming.md#render-hooky)
vloží komponentu do pozice, kterou layout už nabízí — `panels.page.header.end`,
`admin.topbar.end`, `admin.sidebar.end` nebo `table.toolbar.end`:

```php
use NyonCode\WireCore\Core\Plugin\RenderHook;

RenderHook::add('panels.page.header.end', fn () => view('tours.replay-hook'));
```

```blade
{{-- resources/views/tours/replay-hook.blade.php --}}
@livewire('help-button')
```

**Do uživatelského menu, vedle položky frameworku.** `PageChrome` je registr, do
kterého je přispěná i samotná položka pro opětovné spuštění, a aplikace do něj
může přidávat z `boot()` service provideru:

```php
use NyonCode\WireCore\Foundation\View\PageChrome;

app(PageChrome::class)->add('tours.replay-hook', PageChrome::USER_MENU, sort: 30);
```

Je idempotentní podle jména view, takže provider, který nabootuje dvakrát, přidá
jednu položku. Na rozdíl od položky frameworku se ta vaše kreslí na každé
obrazovce — nikdo se jí neptá, jestli si tuhle nějaký průvodce nárokuje.

**Ne odkaz.** Adresa, kterou `replayNow()` vrací, je skutečná URL, ale poslat
někomu holý odkaz nespustí nic: server přijme průvodce v query jen pro člověka,
který ho **nedokončil**. Zapomenutí je ta půlka, kterou odkaz udělat neumí, a
proto všechny způsoby výše vedou přes request.

## Vlastní layout

Průvodce i položka pro opětovné spuštění se vykreslují přes `PageChrome`,
registr, přes který balíčky přidávají views do layoutu, aniž by ho znaly.
Layout `wire-admin` tento registr vykresluje. Pokud vykreslujete vlastní layout,
přidejte do něj stejné dvě smyčky, jaké používá shell. Je to stejný krok, jaký
potřebuje jakékoli jiné [page chrome](../modules/media.md#odkud-se-bere-modal):

```blade
{{-- na konec <body> --}}
@foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views() as $view)
    @include($view)
@endforeach

{{-- do uživatelského menu --}}
@foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views(\NyonCode\WireCore\Foundation\View\PageChrome::USER_MENU) as $view)
    @include($view)
@endforeach
```

V `<head>` musí být také `@wireStackScripts`, protože panel umisťují kontrolery,
které přináší.

## Stylování

Každá část průvodce nese element hook, takže ji můžete přestylovat ve vlastním
stylesheetu, bez publikování view:

| Hook | Prvek |
| --- | --- |
| `tour-backdrop` | Ztmavená vrstva přes stránku |
| `tour-highlight` | Prstenec kolem aktuálního prvku |
| `tour-panel` | Panel vedle něj |
| `tour-heading`, `tour-text` | Titulek a text kroku |
| `tour-progress` | „Krok 2 z 4" |
| `tour-next`, `tour-back`, `tour-skip` | Tři tlačítka |
| `tour-welcome`, `tour-welcome-*` | [Welcome blok](tour-welcome.md#stylovani), když ho průvodce má |

```css
[data-wire="tour-panel"] { @apply rounded-2xl shadow-2xl; }
[data-wire="tour-highlight"] { @apply ring-amber-400; }
```

Jména se řídí slibem [stylovacích hooků](../start/theming.md#stylovaci-hooky):
mohou přibývat, ale v minor vydání se nepřejmenovávají ani neodstraňují.

## Rozšířený příklad

Tři průvodci v jedné aplikaci: průvodce při prvním spuštění pro všechny, jeden
pro obchodní tým v jeho vlastní zóně a průvodce po novince vydané ve 2.2.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Tours::class)->register(
            Tour::make('getting-started')                    // [tl! focus:start]
                ->zones(null)
                ->steps([
                    TourStep::make('admin-sidebar')
                        ->heading('Všechno je tady')
                        ->text('Každá část aplikace má v tomto menu svou položku.')
                        ->placement('right-start'),
                    TourStep::make('global-search-trigger')
                        ->text('Odsud prohledáte všechny záznamy v aplikaci.'),
                    TourStep::make('admin-user')
                        ->text('Tady je váš profil a možnost spustit tohoto průvodce znovu.')
                        ->placement('bottom-end'),
                ]),                                          // [tl! focus:end]

            Tour::make('sales-orders')                       // [tl! focus:start]
                ->permission('sales.*')
                ->zones('sales')
                ->resource('orders')
                ->page('index')
                ->sort(-10)                                  // obchodníkům přednost před getting-started
                ->steps([
                    TourStep::make('admin-nav-item')
                        ->where('resource', 'customers')     // jedna položka z mnoha
                        ->text('Zákazníci a jejich otevřené objednávky jsou na jedno kliknutí.')
                        ->placement('right'),
                    TourStep::make('table-filters-trigger')
                        ->text('Vyfiltrujte si podle stavu, co ještě čeká na odeslání.'),
                ]),                                          // [tl! focus:end]

            Tour::make('saved-views')                        // [tl! focus:start]
                ->since('2.2')                               // změňte, aby se ukázal znovu
                ->resource('orders')
                ->steps([
                    TourStep::make('table-view-save')
                        ->heading('Novinka ve 2.2')
                        ->text('Uložte si filtry a sloupce, které používáte každý den, a vraťte se k nim jedním kliknutím.'),
                ]),                                          // [tl! focus:end]
        );
    }
}
```

Obchodník, který otevře seznam objednávek v zóně sales, dostane nejdřív
`sales-orders`, protože má nižší sort, a `saved-views` při některé z dalších
návštěv. `getting-started` je omezený na část aplikace bez zóny, takže v zóně
sales se nespustí nikdy. Všichni ostatní uvidí `getting-started` všude, kde
není zóna, a `saved-views` na kterékoli stránce objednávek.

## API Tour

Omezení a obsah. Viditelnost a autorizace — `->visible()`, `->hidden()`,
`->permission()`, `->authorize()`, `->authorizeUsing()` — jsou
[sdílená pravidla komponent](../start/authorization.md#sdilena-pravidla-komponent)
a kroky popisuje [TourStep](tour-step.md).

```php
Tour::make(string $id)                // stabilní id — ukládají se k němu potvrzení; prázdné vyhodí výjimku
->steps(array $steps)                 // array<TourStep>, v pořadí zobrazení; průvodce bez kroků vyhodí při register()
->welcome(TourWelcome $welcome)       // začít blokem, který se zeptá dřív, než ukáže — výchozí: žádný, průvodce prostě začne   // [tl! focus:start]
->postpone(int $times)                // kolik „Odložit“, než se zaznamená jako viděný; 0 tlačítko odstraní — výchozí: wire-core.tours.postpone (3) // [tl! focus:end]
->since(string $version)              // verze obsahu, porovnává se na nerovnost — výchozí '1'
->sort(int $sort)                     // nejnižší se spustí první, když si obrazovku nárokuje víc průvodců — výchozí 0
->zones(?string ...$zones)            // jména zón; null je aplikace bez zóny — výchozí: jakákoli zóna
->resource(string ...$resources)      // klíče registrovaných resources — výchozí: jakýkoli i žádný
->page(string ...$pages)              // 'index'|'create'|'view'|'edit'|vlastní stránka resource — výchozí: jakákoli
->getId(): string
->getVersion(): string
->getSort(): int
->getSteps(): array                   // array<int, TourStep>
->getPostponeLimit(): ?int            // autorovo číslo, nebo null pro konfigurované
->getWelcome(): ?TourWelcome
```

Registrace, na singletonu `Tours`:

```php
app(Tours::class)->register(Tour ...$tours): void   // idempotentní podle id — pozdější registrace id se ignoruje
app(Tours::class)->all(): array                     // array<int, Tour>, od nejnižšího sort
app(Tours::class)->get(string $id): ?Tour
app(Tours::class)->has(string $id): bool
```

Co jeden člověk viděl, na singletonu `TourState`. Tohle volá položka pro
opětovné spuštění, [vlastní spouštěč](#vlastni-spoustec) i test:

```php
app(TourState::class)->replay(string $tourId, ?Authenticatable $user): void                 // zapomenout, aby se spustil znovu; neznámé id se ignoruje
app(TourState::class)->replayNow(string $tourId, ?Authenticatable $user): ?RedirectResponse // a k tomu cesta na obrazovku, kde běží, nebo null
app(TourState::class)->acknowledge(string $tourId, ?Authenticatable $user): void            // zaznamenat jako dokončený, stejně jako to dělá Dokončit a Přeskočit
app(TourState::class)->postpone(string $tourId, ?Authenticatable $user): void               // „Odložit“ — na tuhle session, počítá se; to poslední potvrdí
```

## Související

- [TourStep](tour-step.md) — na co krok ukazuje a jak se zúží
- [TourWelcome](tour-welcome.md) — zeptat se před spuštěním a co stojí „Odložit“
- [Testování → Testování průvodce](../start/testing.md#testovani-pruvodce) — jak ověřit, že je registrovaný a že se spustí
- [Řešení potíží → Průvodce se nikdy neobjeví](../start/troubleshooting.md#pruvodce-se-nikdy-neobjevi) — co projít, když se nic neděje
- [Autorizace](../start/authorization.md) — sdílená pravidla, která používá viditelnost průvodce
- [Směrovací zóny](../panels/routing.md) — odkud se berou jména zón
- [Vzhled → Jak jméno najít](../start/theming.md#jak-jmeno-najit) — jak přečíst jméno hooku pro krok přímo ze stránky
