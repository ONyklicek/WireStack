---
order: 30
summary: Pět Livewire komponent, které vykreslí resource — seznam, založení, editaci, detail a dashboard — co která skládá a jak se k nim dostane jeden záznam.
---

# Stránky

Stránka je obyčejná Livewire komponenta, která vykreslí jeden z povrchů resourcu.
Skládá tu samou hostitelskou traitu, kterou by aplikace složila ručně, takže se na
tabulkách, formulářích ani infolistech nic nemění — co stránka odstraňuje, je
zapojování, ne primitivum.

```php
use NyonCode\WirePanels\Resources\Pages\ListPage;
```

## Jak to funguje

Pět stránek a každá z nich je hostitelská komponenta plus ukazatel na vlastníka:

| Stránka | Skládá | Čte | Vykreslí |
| --- | --- | --- | --- |
| `ListPage` | `WithTable` | `ProvidesResourceTable` | seznam |
| `CreatePage` | `WithForms` | `ProvidesResourceForm` | prázdný formulář |
| `EditPage` | `WithForms` | `ProvidesResourceForm` | ten samý formulář navázaný na záznam |
| `ViewPage` | nic | `ProvidesResourceInfolist` | jeden záznam, read-only |
| `DashboardPage` | `WithWidgets` | `Dashboard` | mřížku widgetů |

`ViewPage` záměrně neskládá žádnou hostitelskou traitu: read-only znamená žádný
stav k navázání a nic k odeslání, takže `Infolist` je celý povrch.

**Co to stojí, je akce s callbackem.** Akce infolistu — tlačítko u entry, akce
v hlavičce sekce — dispatchuje na hostitelův `callInfolistAction()`, který patří
action runtimu, jejž tahle stránka neskládá; a
[halt](../core/actions/lifecycle.md#halt-vykonavani), který vzniká uvnitř téhož
pipeline, se také nemá kde objevit. Z pěti stránek hostí akce jedině `ListPage`,
a to skrze `WithTable`. Na detailu z toho vedou dvě cesty: dejte akci `url()`, což
ji vykreslí jako odkaz a po hostiteli nechce nic — vlastní `MediaResource`
frameworku otevírá a stahuje přesně takhle — nebo dejte povrch do vlastní Livewire
komponenty skládající [`WithActions`](../core/actions/standalone.md) a tu na
stránku namountuj.

Každá stránka funguje i **bez vlastníka** — napište si na ni `table()`, `form()`
nebo `widgets()` a je to obyčejná hostitelská komponenta. Co ale stránka *napůl*
deklarovaná udělá, je výjimka: stránka bez resource i bez `table()`, nebo mířící
na resource, který seznam nedeklaruje, se ozve nahlas místo aby vykreslila
prázdnou tabulku — ta by se totiž četla jako „žádné záznamy“, ne jako chyba.

Stránky nevlastní routing. Namountuj si je, kam aplikace chce, jako každou
Livewire komponentu; [Routování](routing.md) je opt-in, který jim dá URL.

## Stránka se seznamem

`ListPage` vykreslí seznam jednoho resource. Skládá `WithTable`, takže je to
obyčejný table host — polling, řádkové partialy, gesta, exporty i všechno ostatní
přichází beze změny, protože žádná z těch věcí o resource neví.

```php
use NyonCode\WirePanels\Resources\Pages\ListPage;

final class ListOrders extends ListPage
{
    protected static ?string $resource = OrderResource::class;
}
```

To je celá stránka. Nadpis se bere z množného označení resource — proto je ten
popisek na *statickém* kontraktu: stránka ho ukáže, aniž by cokoli skládala.
Přebijte ho nastavením `$title`.

Resource není povinný. Stránka si může tabulku napsat sama a žádný resource
nepoužít, přesně jako každá `WithTable` komponenta:

```php
final class ListOrders extends ListPage
{
    public function table(Table $table): Table
    {
        return $table->model(Order::class)->columns([
            TextColumn::make('number'),
        ]);
    }
}
```

Obě cesty jsou plnohodnotné.

## Založení, editace a detail

Zbylé tři stránky navazují na seznam. Založení a editace sdílejí jeden formulář —
resource ho deklaruje jednou:

```php
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

final class CreateOrder extends CreatePage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}

final class EditOrder extends EditPage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}

final class ViewOrder extends ViewPage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}
```

Jeden `form()` slouží záměrně pro založení i editaci — formulář na založení a
formulář na editaci, které se rozejdou, jsou přesně ta chyba, které tenhle tvar
předchází. Kde se opravdu musí lišit, předá stránka formulář, který resource
teprve tvaruje, místo aby resource deklaroval dva.

## Jak se k stránce dostane záznam

Editace a detail ukazují jeden záznam a ten přichází jako **klíč**:

```blade
@livewire(EditOrder::class, ['record' => $order->getKey()])
```

Ne model, a je to záměr. Mount argumenty Livewire komponenty končí v jejím
snapshotu, takže hydratovaný model je tam jednak větší než klíč, jednak zastaralý
v okamžiku, kdy dorazí další request. Cestuje klíč; záznam se resolvuje per
request. Přebijte `resolveRecord()`, když ho chcete hledat jinak — soft-delete
scope, tenant guard, non-Eloquent zdroj. Vrací `Model|RecordContract|null`, takže
override opravdu může vrátit něco, co není model:

```php
protected function resolveRecord(): RecordContract
{
    return new ArrayRecord($this->reports->find($this->record), 'id');
}
```

**View stránka** to vykreslí tak, jak to je: infolist se každou hodnotu zeptá
kontraktu, místo aby do záznamu sahal. **Edit stránka** to odmítne, a to zprávou,
která pojmenuje cestu ven — životní cyklus ukládání formuláře je Eloquent (relační
repeatery, optimistický zámek, `$model->save()`), takže záznam, který se
nerozbalí na model, na něj navázat nejde. Přebijte na stránce `form()`, nechte model
nenavázaný a dejte formuláři vlastní příkaz.

**Perzistence zůstává formuláři.** `Form` už vlastní validate → mutate → hooky →
persist → notify; stránka jen naváže model a zavolá `save()`. Resource nad
non-Eloquent zdrojem si deklaruje `Form::using()` ve vlastním `form()` a tyhle
stránky se nemění.

Stránky navazují formulář na stavovou cestu `data` a deklarují odpovídající
veřejnou property, protože navázání formuláře na hostitele je práce stránky —
stejné dělení, díky kterému `table()` resource nic neví o komponentě, která ho
vykresluje. Resource, který potřebuje jinou cestu, si ji nastaví ve svém
`form()`, který běží potom, a tedy vyhraje.

## Kam uložení dopadne

Úspěšné uložení jsou dvě rozhodnutí a stránky se shodnou jen na prvním.
**Založení přesměrovává, editace zůstává.** Záznam, který stránka se založením
právě podala, existuje, formulář, který ho podal, je pořád plný, a další
stisknutí téhož tlačítka podá druhý — takže stránka, na které vznikl, je jediné
místo, kde uživatel nesmí zůstat stát. Editace je už na vlastní stránce svého
záznamu a ta po uložení drží přesně to, co se zapsalo.

Založení dopadne na vlastní stránku záznamu, seznam je záložní varianta:

```text
view  →  edit  →  index  →  zůstat
```

Každý krok se přeskočí, když resource takovou stránku nedeklaruje, když ji
aplikace neroutuje, když záznam nejde dát do URL — resource nad non-Eloquent
zdrojem nemá klíč, ze kterého by ji postavil — nebo když stránka deklaruje
oprávnění, které uživatel nemá. To poslední je podstatné, protože `ResourceRoutes`
z téže deklarace udělá middleware `can:`: přesměrovat do 403 je striktně horší než
stránka, na kterou se člověk zrovna díval. Když neroutuje nic, dojde se na
poslední „zůstat" — což dostane stránka namountovaná ručně nebo vykreslená uvnitř
něčeho jiného.

Obě stránky si svůj cíl pojmenují přepsáním jediné metody:

```php
final class CreateOrder extends CreatePage
{
    protected static ?string $resource = OrderResource::class;

    protected function getRedirectUrl(mixed $record): ?string   // [tl! focus:3]
    {
        return $this->pageUrl('index');
    }
}
```

Dostane, co vrátilo uložení formuláře — model v obyčejném Eloquent případě a
cokoliv, co odpovědělo `Form::using()`, jinak — a `null` znamená zůstat.
`pageUrl()` je pomocník na URL sourozenců popsaný níž;
[`reachablePageUrl()`](#cesta-na-jeji-dalsi-stranky) je totéž minus stránky, které
tenhle uživatel otevřít nesmí.

Přesměrování jde přes `wire:navigate`, jako každý jiný odkaz v panelu.

**Toast o úspěchu jde s ním.** Notifikace je browser event a navigate vymění
dokument, který by ji ukázal — takže ji driver zároveň flashne a toast container
ji po příchodu vykreslí. Nemusí se nic zapínat: je to to, co
[session driver](../core/notifications/index.md#drivery) dělal odjakživa, jen to
teď někdo čte.

## Stránky s dashboardem

Dashboard se deklaruje stejně jako resource a `DashboardPage` je jeho seznamová
stránka: Livewire komponenta skládající `WithWidgets`, namířená na vlastníka.

```php
use NyonCode\WirePanels\Resources\Pages\DashboardPage;

final class SalesDashboardPage extends DashboardPage
{
    protected static ?string $dashboard = SalesDashboard::class;   // [tl! focus]
}
```

Všechno, co `WithWidgets` už umí — razítkování klíčů widgetů, filtrování podle
viditelnosti, mřížka i odpověď na tik pollingu jedním widgetem místo celé stránky
— přichází beze změny. Stejně jako u resource stránek je i tady plnohodnotné
napsat widgety přímo na stránku a žádný dashboard nedeklarovat. Co dashboard
deklaruje a co je widget, popisují [Widgety](../core/widgets/index.md).

## Vnořené relation managery

Resource může pojmenovat relací omezené tabulky, které patří vedle jeho záznamu:

```php
use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;

public function relationManagers(): array   // [tl! focus:3]
{
    return [OrderItemsRelationManager::class];
}
```

`EditPage` a `ViewPage` je pak vloží pod formulář nebo infolist, namountované na
záznam. Na [`RelationManager`](../table/relation-managers.md) se nic nemění —
namountovat si ho přímo funguje přesně jako dřív; tohle jen odstraňuje nutnost
opakovat to zapojení na každé stránce. Resource, který žádný nedeklaruje,
nevykreslí žádný, což je běžný stav, ne chyba.

## Kde stránka sedí

Menu ví, která z jeho položek je aktivní. Že je editační stránka *uvnitř*
seznamu, ze kterého přišla, neví nic, dokud to stránka neřekne — a to je ta jediná
část navigace, kterou menu dodat nemůže. Všechny čtyři resourcové stránky
implementují [`ProvidesBreadcrumbs`](resources.md#api-povrchovych-kontraktu) a odpovídají
už teď:

```php
$page->breadcrumbs();
// [ NavigationItem('Objednávky')->url('/admin/orders'), NavigationItem('Objednávka #17') ]
```

Drobky jsou `NavigationItem`y, ne vlastní tvar — drobek je popisek a obvykle URL,
což je přesně to, co [ta třída](navigation.md#navigationitem-api) veze od chvíle,
kdy ji potřebovalo menu. Poslední drobek URL nenese: to je stránka, na které jste.
**Nejvýš dva drobky**, protože tohle je celá hloubka, kterou tyhle stránky mají —
seznam není uvnitř ničeho a stopa o jednom drobku se nevykreslí vůbec, takže
stránka se seznamem za tohle neplatí nic.

Zóna, do které stopa odkazuje, se čte **jednou, při mountu**, a drží se ve veřejné
`$breadcrumbZone`. Veřejná být musí, aby přežila round trip, a číst se musí při
mountu, protože jindy to nejde: během Livewire updatu je
`Route::currentRouteName()` rovno `livewire.update`, takže drobek, který by si
zónu odvodil znovu, by odkazoval správně při prvním vykreslení a mimo zónu při
každém dalším ([ADR 0027](routing.md#zony)).

Stránka, která není resourcová — obrazovka nastavení, vlastní seznam modulu —
implementuje kontrakt sama a vrátí, jakou stopu má. Stránka, která není uvnitř
ničeho, to řekne tím, že ho neimplementuje, ne tím, že vrátí prázdné pole z
metody, kterou mít musela.

## Cesta na její další stránky

Na sourozence stránka odkazuje přes `pageUrl()` a na to, co vyžadují, se ptá přes
`pagePermission()`:

```php
$this->pageUrl('edit', $order);   // /admin/orders/17/edit, nebo null
$this->pagePermission('edit');    // oprávnění, kterým je hlídaná i routa, nebo null
```

Obojí sedí na **stránce**, ne na resource, a důvodem je zóna: týž resource
namountovaný ve dvou zónách má dvě různé editační URL a resource nemá jak vědět,
ve které je zrovna kreslený. Stránka to ví — zónu si přečetla při mountu a drží ji.

`pagePermission()` se z deklarace `pages()` na resource odvozuje, ne opisuje, a
právě proto existuje: `ResourceRoutes` z téže deklarace udělá middleware `can:`,
takže tlačítko skryté jedním a routa hlídaná druhým se nemůžou rozejít. Stránka
deklarovaná jako holý class string nevyžaduje nic — a tlačítko taky ne.

`null` je v obou případech skutečná odpověď — resource, který takovou stránku
nedeklaruje, nebo aplikace, která ji neroutuje, dostane tlačítko bez odkazu místo
odkazu rozbitého.

`reachablePageUrl()` je obojí zeptané naráz: URL, nebo `null` tam, kde by ji
tenhle uživatel stejně neotevřel.

```php
$this->reachablePageUrl('view', $order);   // URL, nebo null — neroutované, nebo nepovolené
```

Právě na tom [přesměrovává stránka se založením](#kam-ulozeni-dopadne) — a existuje
místo toho, aby si dvojici skládal každý volající sám, z jednoho důvodu:
deklarované oprávnění je zároveň middleware `can:` na té routě, takže odkaz, který
by ho ignoroval, by byl odkazem do 403.

`hookKey()` je třetí z nich. Odpoví registrovaným klíčem toho, co stránka ukazuje,
a to je právě to, co dělá z [`for: 'invoices'`](../core/plugins/hooks.md) něco, co
jde v aplikaci napsat: tabulka i formulář na téhle stránce se staví uvnitř kódu,
který aplikace nemusí vlastnit — modul dodaný jako balíček — takže ten klíč je
jediné držadlo, které na ně má. Stránka, která nedeklaruje nic, odpoví `null` a
zúží se třídou, protože vyhodit tady výjimku by z *chybějícího* zúžení hooku
udělalo pád při renderu.

## Page API

Každá resourcová stránka skládá `BelongsToResource` — tu polovinu, která je o tom,
*který* resource stránka ukazuje:

| Člen | Typ | Účel |
| --- | --- | --- |
| `protected static ?string $resource` | `class-string\|null` | Vlastník, nebo žádný — stránka, která si píše vlastní povrch, je stejně plnohodnotná |
| `protected ?string $title` | `string\|null` | Přebití nadpisu. Fallback si každá stránka určuje sama, protože seznam chce plurál a formulář singulár |
| `public ?string $breadcrumbZone` | `string\|null` | Zóna přečtená při mountu a nesená přes round trip |
| `getTitle(): ?string` | `string\|null` | Nadpis; poslední drobek stopy je on |
| `breadcrumbs(): array` | `array<int, NavigationItem>` | Kde stránka sedí — nejvýš dva drobky |
| `static resourceClass(): ?string` | `class-string\|null` | Deklarovaný resource, pro cokoli, co se ptá zvenčí |
| `hookKey(): ?string` | `string\|null` | Registrovaný klíč, kterým hook pluginu adresuje povrchy téhle stránky |
| `pageUrl(string $page, mixed $record = null): ?string` | `string\|null` | *(protected)* Kde je jedna ze stránek tohohle resource, v zóně téhle stránky |
| `pagePermission(string $page): ?string` | `string\|null` | *(protected)* Oprávnění, které ta stránka vyžaduje, tak jak ho deklaroval resource |
| `reachablePageUrl(string $page, mixed $record = null): ?string` | `string\|null` | *(protected)* Táž URL, minus stránky, které tenhle uživatel otevřít nesmí |
| `requireResource(string $surface): object` | `object` | *(protected)* Deklarovaný resource, zkontrolovaný; vyhodí výjimku místo vykreslení prázdné stránky |
| `resourceLabel(): ?string` | `string\|null` | *(protected)* Singulární popisek resource |

Co každá stránka přidává, je jen její vlastní povrch:

| Stránka | Přidává |
| --- | --- |
| `ListPage` | `table(Table $table): Table` |
| `CreatePage` | `public ?array $data`, `form(Form $form): Form`, `save(): mixed`, `getRedirectUrl(mixed $record): ?string` |
| `EditPage` | totéž, plus `recordData(): array` a `mountedRecord()`, který naplní formulář |
| `ViewPage` | `infolist(): Infolist` |
| `DashboardPage` | `protected static ?string $dashboard`, `protected static ?string $layoutKey`, `static dashboardClass(): ?string`, `getWidgets(): array`, `getWidgetColumns(): int`, `widgetLayoutKey(): ?string` |

`DashboardPage` neskládá žádný resource — má vlastní `$title`, `getTitle()`
i `hookKey()`, přičemž poslední odpoví klíčem **dashboardu**. `$layoutKey` je
`null` schválně: stránka, která si widgety deklaruje inline, žádný registrovaný
klíč nemá a odvodit ho z názvu třídy by uživatelův uložený layout přivázalo ke
třídě, kterou nevlastní — přejmenování by osiřelo každý layout a nic by to
neřeklo. Takže se pojmenuje tam, nebo stránka není přizpůsobitelná.

Editace a detail řeší jeden záznam přes `ResolvesOneRecord`:

| Člen | Typ | Účel |
| --- | --- | --- |
| `public mixed $record` | `mixed` | **Klíč** záznamu. Veřejný, protože ho Livewire veze mezi requesty |
| `mount(mixed $record = null): void` | `void` | Vezme klíč a zavolá `mountedRecord()` |
| `mountedRecord(): void` | `void` | *(protected)* Hook pro to, co stránka udělá, jakmile zná svůj záznam — editační tu naplní formulář, detail nepotřebuje nic |
| `resolveRecord(): Model\|RecordContract\|null` | `Model\|RecordContract\|null` | *(protected)* Samotný záznam. Přebijte ho kvůli soft-delete scope, tenant guardu nebo neeloquentnímu zdroji |
| `nativeRecord(): mixed` | `mixed` | *(protected)* Nativní objekt za ním — to, čím se mountuje relation manager, protože relace se dotazují na modelu |
| `requireEloquentRecord(): ?Model` | `Model\|null` | *(protected)* Totéž jako model, nebo odmítnutí, které pojmenuje cestu ven |
| `recordAttributes(): array` | `array<string, mixed>` | *(protected)* Jeho atributy, ať je záznam jakéhokoli druhu |

Editace a detail skládají navíc `EmbedsRelationManagers`, jehož jediná metoda je
`relationManagers(): array`.

## Layout je váš

Celostránková Livewire komponenta potřebuje layout a tenhle balíček ho nedodává —
nastavte `livewire.component_layout` na vlastní, nebo nainstalujte
[admin shell](../admin/overview.md), který jeden veze.

## Související

- [Resources](resources.md) — vlastník, kterého tyhle stránky čtou
- [Navigace](navigation.md) — `NavigationItem`, tvar, ze kterého je drobek udělaný
- [Routování](routing.md) — jak stránkám dát URL, v jedné zóně nebo ve víc
- [Tabulky](../table/overview.md) a [Formuláře](../forms/overview.md) — povrchy, které stránky hostí
- [Infolisty](../core/infolists/index.md) — co vykreslí stránka detailu
- [Widgety](../core/widgets/index.md) — co vykreslí stránka s dashboardem
