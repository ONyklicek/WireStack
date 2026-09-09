---
order: 40
summary: Jak se resource dostane do menu — položka, kterou deklaruje, skupina, ve které sedí, a workspace, který obojí uspořádá.
---

# Navigace

Registr odpovídá na otázku *co existuje*. Menu je otázka jiná — co se zobrazí,
pod jakým nadpisem, v jakém pořadí a kam která položka odkazuje. Na tu odpovídají
tři malé objekty: položka, kterou deklaruje resource, skupina, kterou deklaruje
aplikace, a workspace, který je uspořádá.

## Deklarace položky

Resource, který se má objevit v menu, implementuje `ProvidesNavigation`:

```php
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

public static function navigation(): NavigationItem   // [tl! focus:6]
{
    return NavigationItem::make('Objednávky')
        ->icon('outline:shopping-cart')
        ->group('sales')
        ->sort(10)
        ->badge(fn () => Order::whereNull('shipped_at')->count(), 'danger');
}
```

Statické, jako identita, a ze stejného důvodu: menu se staví ze všech
registrovaných resources naráz a instancovat každý jen kvůli tomu, jak se
jmenuje, by znamenalo složit tabulku a formulář na každou položku. Resource,
který tohle neimplementuje, je pořád registrovaný a routovatelný — jen se
neobjeví, což je přesně to, co chce interní nebo vnořený resource.

`NavigationItem` stojí na kanonických concernech `HasLabel` / `HasIcon` /
`HasVisibility`, ne na vlastních properties, takže mluví stejným slovníkem jako
každá jiná komponenta. Přidává jen to, co potřebuje *menu*: `group()`, `sort()`
a `badge()`. Closure v badge se vyhodnocuje při každém čtení, nikdy se necachuje
— počet neodeslaných objednávek je špatně v okamžiku, kdy se uloží.

Položka, která si sama nepojmenuje label, se jmenuje po svém resource:
`NavigationItem::make()` vedle `->icon()` a `->group()` je běžný tvar a menu
ukáže `pluralLabel()` — „Objednávky". Resource, který chce v menu jiný název než
svůj plurál, ho předá a ten vyhraje.

## Položky pod položkou

Položka může nést vlastní položky, což je druhý nadpis, který menu má — a není to
skupina, protože je zároveň cílem i rodičem:

```php
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

NavigationItem::make('Katalog')
    ->icon('outline:squares-2x2')
    ->children([                                                    // [tl! focus:start]
        NavigationItem::make('Produkty')->url(route('products.index')),
        NavigationItem::make('Kategorie')->url(route('categories.index'))->sort(10),
        NavigationItem::make('Archiv')
            ->url(route('products.archive'))
            ->visible(fn (): bool => auth()->user()?->can('viewArchive') ?? false),
    ]);                                                             // [tl! focus:end]
```

**Jedna úroveň, a to schválně.** Děti dítěte nečte nic, co kreslí menu: sidebar
zanořený třikrát je sidebar, do kterého se nedá trefit myší, a třetí úroveň patří
na stránku, jako záložky nebo sekundární navigace. Hlubší zanoření se neodmítá,
jen se nevykreslí — kdo to udělá, uvidí to okamžitě.

Filtrování a řazení dělá `getChildren()`, ne view: skryté děti vypadnou a zbytek
se vrátí v pořadí `sort()`, takže každý povrch, který kreslí submenu, se shodne na
tom, co v něm je. Je to týž důvod, proč položky filtruje `Workspace` a ne sidebar.
Closure se vyhodnocuje při každém čtení ze stejného důvodu jako badge — děti,
které závisejí na tom, co smí vidět přihlášený uživatel, se nesmějí rozhodnout
jednou, při registraci, a zapamatovat pro všechny.

`hasChildren()` odpoví, jestli se pod položkou vůbec něco vykreslí, a to rozhoduje,
jestli je řádek odkaz, nebo rozbalovátko. Ptejte se jím místo počítání, protože
děti můžou být v tu chvíli ještě closure. Co s tím rozdílem udělá zúžená
[lišta](../admin/sidebar.md#sbalene-menu) — tooltip pro list, popover pro rodiče —
je věc shellu, ne téhle vrstvy.

## Skupiny

`group()` bere **klíč**, ne nadpis. Skupinu nevlastní žádný resource — sdílí ji
jich několik — takže co nadpis říká, jakou nese ikonu, kde sedí mezi ostatními
skupinami a jestli je vůbec vidět, patří `NavigationGroup`, deklarované tam, kde
si aplikace tuhle svou část skládá:

```php
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;

public function boot(): void
{
    $this->app->make(NavigationGroups::class)->registerMany([   // [tl! focus:start]
        NavigationGroup::make('sales')
            ->label(__('nav.sales'))
            ->icon('outline:banknotes')
            ->sort(10),
        NavigationGroup::make('admin')
            ->sort(90)
            ->collapsed()
            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
    ]);                                                          // [tl! focus:end]
}
```

**Skupina, kterou nikdo nedeklaruje, funguje dál.** `Workspace` si z klíče udělá
implicitní, takže `->group('sales')` žádnou registraci nepotřebuje a nadpis
spadne na `Str::headline()` klíče. Registrace říká pět věcí, které holý klíč
neumí — nadpis oddělený od klíče, ikonu, pořadí mezi ostatními skupinami, jednu
podmínku viditelnosti pro všechno uvnitř a jestli se skupina skládá. Typovaný
seznam je [API `NavigationGroup`](#navigationgroup-api) níž.

Nadpis a klíč jsou oddělené schválně. `->group(__('nav.billing'))` udělal
z překladu klíč pole, takže totéž menu bylo v každém jazyce klíčované jinak; klíč
je teď slug a text vlastní `HasLabel`. `hiddenLabel()` skupinu zachová, ale
nadpis nevykreslí — to chce menu, které odděluje linkou místo slovem.

Skládání přichází z `CanBeCollapsed` — `->collapsible()` dá nadpisu rozbalovátko,
`->collapsed()` ho nechá začít zavřený — týž concern, který používá `Section`
a `Repeater`, takže ta dvojice znamená napříč frameworkem jednu věc, a ne tři.
Složení skrývá, neodebírá: složená skupina pořád ukazuje své položky v zúžené
[liště](../admin/sidebar.md#sbalene-menu), kde se nadpis, který by ji rozbalil,
nekreslí vůbec.

**Provider není jediné místo.** `NavigationGroups` je singleton v kontejneru,
takže cokoli, co drží kontejner, do něj smí zaregistrovat dřív, než se menu
postaví — `boot()` provideru je jen ta obvyklá chvíle. [Modul](modules.md) se ho
nedotkne vůbec: svou skupinu vrátí z `Module::navigation()` a `wire-core` ji
zaregistruje při bootu modulů, takže modul veze svůj nadpis, ikonu i pořadí vedle
resourců, které seskupuje, místo aby je nechal na tom, kdo ho nainstaloval.
Moduly se bootují v pořadí registrace, takže modul uvedený za tím, na kterém
závisí, vidí jeho skupiny už deklarované.

Registrace téhož klíče podruhé přepíše, což je způsob, jak aplikace upraví
skupinu dodanou balíčkem, aniž by ten balíček editovala. `NavigationGroups` je
singleton v kontejneru a jinak obyčejný registr — [jeho
API](#navigationgroups-api) má pět metod.

## Workspace

`Workspace` výsledek uspořádá:

```php
use NyonCode\WireCore\Core\Resources\Workspace;

$nav = app(Workspace::class)->navigation();
// ['sales' => NavigationGroup, '' => NavigationGroup]   bez skupiny je klíč ''
```

Skupiny se vrací v pořadí `sort()` a shodné drží pořadí, v jakém se registrovala
jejich první položka; uvnitř skupiny platí totéž pro položky. Skryté položky
vypadnou a skrytá skupina si své položky vezme s sebou.

Položky zůstávají klíčované **registrovaným klíčem**, přes seskupení i přes
řazení, a každá nese URL stránky svého klíče. Nic ji nedeklaruje: *registr* URL
pořád nedrží — ten, který by ji držel, by byl panel — ale menu se zeptá, kam je
klíč routovaný, a odpověď doplní; `null` pro resource, který nedeklaruje stránky,
i pro aplikaci, která neroutuje nic.

Položka může svůj cíl pojmenovat sama přes `->url('https://status.example.com')`
a to, co pojmenuje, vždycky vyhraje — externí odkaz nebo aplikace, jejíž shell má
vlastní URL schéma.

`Workspace::items()` odpovídá na tutéž otázku bez nadpisů: každá viditelná
položka, plochý seznam v pořadí `sort()`, klíčovaný registrovaným klíčem — to, co
ukáže menu, které skupiny nekreslí. Položky ze skryté skupiny v něm taky nejsou.

Obojí bere [zónu](routing.md#zony), a `linkedOnly: true`, když má menu obsahovat jen to, na
co ta zóna dosáhne:

```php
app(Workspace::class)->navigation();                                    // všechny položky, bez zóny
app(Workspace::class)->navigation(zone: 'business');                    // odkazující do business
app(Workspace::class)->navigation(zone: 'business', linkedOnly: true);  // [tl! focus]
```

`Workspace` neví, co je resource, a to je záměr. Jeho položky přicházejí
z `Catalog`u, který čte libovolný počet zdrojů `RegistrySource` — jedním je
`ResourceRegistry`, druhým `Widgets\DashboardRegistry` — takže menu míchá
resources, dashboardy a cokoli, co aplikace zaregistruje později, aniž by se
o nich `Workspace` dozvěděl. Router i paleta globálního hledání čtou tentýž
katalog, takže jedna registrace obslouží všechny tři. Dva zdroje hlásící se k jednomu klíči jsou odmítnuty, ne smířeny:
jedna položka by jinak zabrala místo druhé a menu, kterému tiše zmizel řádek, se
pozná až v den, kdy ten řádek byl potřeba.

Fallback labelu výše je resourcový, protože `pluralLabel()` je slovo resource.
Cokoli jiného v menu si položku pojmenuje samo.

Stejně jako registr nevlastní `Workspace` routing ani layout — menu vykresluje
aplikace. Ptá se, kam je klíč routovaný; nerozhoduje o tom.

## Vykreslení menu

```blade
@foreach($nav as $group)
    @if($group->hasVisibleLabel())
        <p>{!! icon($group->getIcon()) !!} {{ $group->getLabel() }}</p>
    @endif

    @foreach($group->getItems() as $key => $item)
        {{-- Registrovaná položka bez vlastní stránky do menu pořád patří;
             jen to není odkaz. --}}
        <a @if($item->getUrl()) href="{{ $item->getUrl() }}" wire:navigate @endif>   {{-- [tl! focus] --}}
            {!! icon($item->getIcon()) !!}
            {{ $item->getLabel() }}
            <x-wire::badge :color="$item->getBadgeColor() ?? 'gray'">{{ $item->getBadge() }}</x-wire::badge>
        </a>

        @if($item->hasChildren())
            <ul>
                @foreach($item->getChildren() as $child)
                    <li><a href="{{ $child->getUrl() }}" wire:navigate>{{ $child->getLabel() }}</a></li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endforeach
```

`wire-admin` tohle menu dodává napsané — viz [sidebar](../admin/sidebar.md), který
kreslí tytéž tři objekty, aktivní položku pozná z právě vykreslované routy a žádný
vlastní stav nedrží. To výše je pro aplikaci, která si kreslí vlastní rám.

## Změna menu, které jste neregistrovali

Instalace [modulu](modules.md) dá jeho položky do menu a aplikace je upraví přes
[hook `navigation.building`](../core/plugins/hooks.md), místo aby ten modul
neinstalovala:

```php
$manager->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
    unset($payload->items['media']);                                     // [tl! focus]
    $payload->items['docs'] = NavigationItem::make('Docs')->url('/docs')->sort(90); // [tl! focus]

    return $payload;
}, for: 'admin');
```

Běží nad **plochým klíčovaným seznamem**, před seskupením a seřazením, takže krmí
`navigation()` i `items()` — hook jen v jednom z nich by nechal sidebar a command
paletu neshodnout se na tom, co v menu je. Klíče zachovejte: právě z nich dělá
konzument odkaz. Řadit tady je zbytečná práce, menu stejně seřadí `sort()` položky.

`for:` pojmenuje **zónu** — jedinou identitu, kterou menu má: nepatří k žádné
komponentě a neukazuje jednu registrovanou třídu. Menu stavěné bez zóny žádné
zúžení nenese, takže zúžený callback vynechá.

## NavigationItem API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `NavigationItem::make(string\|Closure\|null $label = null)` | `self` | Nová položka. Bez labelu ji pojmenuje `pluralLabel()` resource |
| `label(string\|Closure\|null $label)` | `self` | Vlastní text položky, který ten fallback přebije |
| `hiddenLabel(bool $condition = true)` | `self` | Položku zachová, text nevykreslí — řádek jen s ikonou |
| `icon(string\|Icon\|Closure\|null $icon, string\|IconPosition\|null $position = null)` | `self` | Ikona vedle labelu |
| `group(string\|Closure\|null $group)` | `self` | **Klíč** skupiny, pod kterou položka patří; `null` je nejvyšší úroveň |
| `sort(int $sort)` | `self` | Pořadí uvnitř skupiny; shody drží pořadí prvního výskytu |
| `badge(mixed $badge, string\|Closure\|null $color = null)` | `self` | Počet nebo krátký text vedle labelu, volitelně s barvou |
| `url(string\|Closure\|null $url)` | `self` | Explicitní cíl, který vždycky vyhraje nad routovaným |
| `children(array\|Closure $children)` | `self` | Položky pod touhle — jedna úroveň, filtrované a seřazené při čtení |
| `visible(bool\|Closure $condition = true)` / `hidden(bool\|Closure $condition = true)` | `self` | Jestli je položka v menu vůbec |
| `getLabel(): ?string` | `string\|null` | Vyhodnocený text, nebo `null`, když ji nic nepojmenovalo |
| `hasVisibleLabel(): bool` / `isLabelHidden(): bool` | `bool` | Jestli text vykreslit |
| `getIcon(): ?string` | `string\|null` | Vyhodnocené jméno ikony |
| `getGroup(): ?string` | `string\|null` | Vyhodnocený klíč skupiny |
| `getSort(): int` | `int` | Váha řazení |
| `getBadge(): ?string` | `string\|null` | Badge, vyhodnocený při **každém** čtení, nikdy necachovaný |
| `getBadgeColor(): ?string` | `string\|null` | Jeho barva, nebo `null` pro výchozí konzumenta |
| `getUrl(): ?string` | `string\|null` | Explicitní URL, jinak routované, jinak `null` |
| `getChildren(): array` | `array<int, NavigationItem>` | Viditelné děti v pořadí `sort()` |
| `hasChildren(): bool` | `bool` | Jestli je řádek rozbalovátko místo obyčejného odkazu |
| `isVisible(): bool` / `isHidden(): bool` | `bool` | Vyhodnocená viditelnost |

## NavigationGroup API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `NavigationGroup::make(string $key)` | `self` | Klíč, na který položky míří přes `group()` |
| `label(string\|Closure\|null $label)` | `self` | Nadpis. Oddělený od klíče schválně: přeložený nadpis se nesmí stát klíčem pole |
| `hiddenLabel(bool $condition = true)` | `self` | Položky seskupí, nadpis nevykreslí |
| `icon(string\|Icon\|Closure\|null $icon, string\|IconPosition\|null $position = null)` | `self` | Ikona vedle nadpisu |
| `sort(int $sort)` | `self` | Pořadí mezi skupinami; shody drží pořadí prvního výskytu |
| `visible(bool\|Closure $condition = true)` / `hidden(bool\|Closure $condition = true)` | `self` | Zobrazí nebo skryje **celou** skupinu — jedna podmínka místo téže podmínky na každém resource v ní |
| `collapsible(bool\|Closure $condition = true)` | `self` | Nadpis dostane rozbalovátko |
| `collapsed(bool\|Closure $condition = true)` | `self` | Začne složená. Složení skrývá; z zúžené lišty položky nikdy neodebere |
| `withItems(array $items): self` | `self` | **Kopie** nesoucí položky, které menu ukáže pod skupinou — volá ji `Workspace`; deklarovaná skupina je singleton, takže naplnit ji na místě by znamenalo, že druhé volání `navigation()` odpoví jinak než první |
| `getKey(): string` | `string` | Slug, který je zároveň `getName()` |
| `getLabel(): ?string` | `string\|null` | Nadpis, s fallbackem na `Str::headline()` klíče |
| `hasVisibleLabel(): bool` | `bool` | Jestli nadpis vykreslit |
| `getItems(): array` | `array<string, NavigationItem>` | Položky pod ní, klíčované registrovaným klíčem |
| `hasItems(): bool` | `bool` | Jestli by skupina vůbec něco vykreslila |
| `isCollapsible(): bool` / `isCollapsed(): bool` | `bool` | Vyhodnocený stav složení |

## NavigationGroups API

Singleton v kontejneru, ve kterém deklarované skupiny žijí. `Workspace` z něj
čte; zapisuje do něj aplikace i balíček.

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `register(NavigationGroup $group): void` | `void` | Deklaruje jednu skupinu; týž klíč podruhé přepíše, což je způsob, jak aplikace upraví skupinu dodanou balíčkem |
| `registerMany(iterable $groups): void` | `void` | Totéž pro víc skupin naráz |
| `find(string $key): ?NavigationGroup` | `NavigationGroup\|null` | Deklarovaná skupina s tímhle klíčem, nebo `null`, když ji jmenují jen položky |
| `has(string $key): bool` | `bool` | Jestli byl klíč deklarovaný |
| `all(): array` | `array<string, NavigationGroup>` | Každá deklarovaná skupina, klíčovaná klíčem, v pořadí registrace |

## Workspace API

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `navigation(?string $zone = null, bool $linkedOnly = false)` | `array<string, NavigationGroup>` | Menu: skupiny v pořadí, každá se svými položkami |
| `items(?string $zone = null, bool $linkedOnly = false)` | `array<string, NavigationItem>` | Totéž menu ploše, bez nadpisů |
| `registered()` | `array<string, class-string>` | Každá třída za menu, ať má položku nebo ne |

## Catalog API

Všechno, co aplikace zaregistrovala, ať je to cokoli — jeden seznam, ze kterého
čte menu, router i vyhledávací paleta.

| Metoda | Vrací | Účel |
| --- | --- | --- |
| `all(): array` | `array<string, class-string>` | Každá registrovaná třída, klíčovaná, v pořadí registrace; dva zdroje hlásící se k jednomu klíči odmítne |
| `implementing(string $capability): array` | `array<string, class-string>` | Jen ty, které implementují daný kontrakt — `ProvidesNavigation`, `ProvidesPages`, `GloballySearchable` |
| `find(string $key): ?string` | `class-string\|null` | Třída s tímto klíčem |
| `has(string $key): bool` | `bool` | Jestli je klíč registrovaný |

Registr se stane jedním z jeho zdrojů tím, že implementuje `RegistrySource`
(`registeredClasses(): array`) — tak se dashboard registr dostane ke všem třem
povrchům, aniž by ho kterýkoli z nich importoval. Cokoli, co smí adresovat router,
implementuje navíc `HasRegistryKey` (`static key(): string`) — `ProvidesPages` ho
rozšiřuje, protože stránce, kterou nelze adresovat, nejde dát URL.

## Související

- [Resources](resources.md) — vlastník, kterého položka jmenuje
- [Routování](routing.md) — odkud se bere URL položky a co na tom mění zóna
- [Moduly](modules.md) — položky celé oblasti deklarované v jednom manifestu
- [Sidebar](../admin/sidebar.md) — menu, které z tohohle všeho kreslí `wire-admin`
- [Globální vyhledávání](../core/global-search.md) — palette čtoucí stejný katalog
- [Widgety](../core/widgets/index.md) — dashboardy, druhý druh věcí, které menu drží
