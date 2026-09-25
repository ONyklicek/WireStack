---
order: 30
summary: Livewire komponenty, které vykreslí resource — seznam, založení, editaci, detail a dashboard — a k tomu vlastní stránka; co která skládá, akce vedle jejich nadpisu a jak se k nim dostane jeden záznam.
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
| `ManagePage` | `WithTable` | `ProvidesResourceTable`, `ProvidesResourceForm` | seznam se založením a editací v modálech |
| `CreatePage` | `WithForms`, `WithActions` | `ProvidesResourceForm` | prázdný formulář |
| `EditPage` | `WithForms`, `WithActions` | `ProvidesResourceForm` | ten samý formulář navázaný na záznam |
| `ViewPage` | `WithActions` | `ProvidesResourceInfolist` | jeden záznam, read-only |
| `DashboardPage` | `WithWidgets` | `Dashboard` | mřížku widgetů |

`ViewPage` neskládá žádnou formulářovou ani tabulkovou traitu: read-only znamená
žádný stav k navázání a nic k odeslání, takže `Infolist` je celý povrch.

**Akce umí spustit každá stránka**, a každá je spouští jedním enginem. Seznam má
ten, který přináší `WithTable`; ostatní tři skládají
[`WithActions`](../core/actions/standalone.md), který spouští jejich
[akce v hlavičce](#akce-v-hlavicce), [halt](../core/actions/lifecycle.md#halt-vykonavani)
vyvolaný uvnitř jedné z nich a na detailu i vlastní akce infolistu s callbackem —
tlačítko u entry, akci v hlavičce sekce — které dispatchují na hostitelův
`callInfolistAction()` a dřív tam nikoho nenašly.

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

## Neuložené změny

Stránky založení a editace se zeptají, než jejich vstup zůstane ležet — reload,
zavřený panel, odkaz s `wire:navigate` v menu. Porovnává se stav formuláře proti
naposledy uloženému, takže hodnota napsaná a vrácená zpět se na nic neptá a
stisk *Uložit* varování, které tím přestává být potřeba, nikdy nezastaví.
Mechanismus je `wireUnsavedChanges` z bundlu formulářů
([Formuláře](../forms/overview.md#varovani-pred-ztratou-neulozeneho-vstupu)); stránka
jen říká, že ho chce, na své cestě `data` a metodě `save()`.

Stránka, která se ptát nemá, ho vypne:

```php
protected function warnsAboutUnsavedChanges(): bool
{
    return false;
}
```

## Záložky seznamu

Seznam může být několika seznamy týchž záznamů — všechny faktury, otevřené,
po splatnosti. Záložka je jméno a to, jak zúží dotaz:

```php
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WirePanels\Resources\ListTab;

final class ListInvoices extends ListPage
{
    protected static ?string $resource = InvoiceResource::class;

    protected function tabs(): array                  // [tl! focus:start]
    {
        return [
            ListTab::make('all')->showCount(),
            ListTab::make('open')->query(fn (Builder $q) => $q->whereNull('paid_at'))->showCount(),
            ListTab::make('overdue')
                ->icon('outline:exclamation-triangle')
                ->badgeColor('danger')
                ->query(fn (Builder $q) => $q->where('due_at', '<', now()))
                ->showCount(),
        ];
    }                                                  // [tl! focus:end]
}
```

**Záložka zužuje základní dotaz**, před vyhledáváním, filtry a řazením, takže
všechno, co tabulka umí, v ní dál funguje. Obaluje `modifyQueryUsing()`, který
tabulka už měla, místo aby ho nahradila: resource, který archivované faktury
nikdy nevypisuje, je nevypisuje v žádné záložce. Aplikuje se na tabulku, kterou
`WithTable` právě složila, takže platí i pro stránku, která si `table()` píše sama.

**Aktivní záložka je `$activeTab`, nesená v URL jako `?tab=`.** Odkaz, reload
i tlačítko zpět skončí na téže záložce. Prázdné nebo neznámé jméno je první
záložka a přepnutí začne seznam znovu od první stránky.

**Počet se ptá téhož základního rozsahu** — rozsahu resource, ne vyhledávání ani
filtrů — jedním `count()` za každou záložku, která ho ukazuje, při každém
vykreslení. `badge()` místo toho nastaví vlastní číslo a na nic se neptá. Počet
je šedý, pokud `badgeColor()` neřekne jinak.

```php
ListTab::make(string $name)                     // jméno, které nese URL
->label(string|Closure|null $label)             // výchozí: jméno, polidštěné
->icon(string|Icon|Closure|null $icon)
->query(?Closure $callback)                     // fn (Builder $query) => $query->…
->showCount(bool $condition = true)             // spočítat záznamy záložky
->badge(int|Closure|null $count)                // vlastní číslo
->badgeColor(string|Color|null $color)          // výchozí 'gray'
```

## Widgety na stránce

Kterákoli stránka — seznam, záznam, vlastní stránka — může dát widgety nad
a pod svůj obsah:

```php
protected function headerWidgets(): array
{
    return [StatsOverviewWidget::make()->stats([Stat::make('Otevřené', (string) Invoice::open()->count())])];
}
```

`footerWidgets()` je totéž pod obsahem, `pageWidgetColumns()` říká, jak široký je
řádek (ve výchozím stavu 3), a položka `null` se přeskočí. Jsou **vykreslené, ne
hostované**: stránka je vykreslí mřížkou, kterou používá dashboard, a hostitelem
widgetů se nestane. Widget, který polluje, načítá se líně nebo má vlastní akce,
potřebuje za sebou `WithWidgets`, a na to je [stránka s dashboardem](#stranky-s-dashboardem).

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

## Akce v hlavičce

Stránka dá akce vedle svého nadpisu tím, že je deklaruje:

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

final class ViewOrder extends ViewPage
{
    protected static ?string $resource = OrderResource::class;

    protected function headerActions(): array     // [tl! focus:start]
    {
        return [
            Action::make('ship')
                ->label('Označit jako odeslané')
                ->requiresConfirmation()
                ->visible(fn (Order $record): bool => $record->shipped_at === null)
                ->action(fn (Order $record) => $record->update(['shipped_at' => now()])),
            $this->deleteHeaderAction(),
        ];
    }                                              // [tl! focus:end]
}
```

Jsou to obyčejné [akce](../core/actions/index.md) — modal, formulář, wizard,
potvrzení i halt fungují — vykreslené stejným pohledem tlačítka jako každá jiná
akce, od `sm` vedle nadpisu a na telefonu pod ním.

**Akce stránky záznamu se týkají jejího záznamu.** Editace a detail mountují každou
akci proti záznamu, který ukazují, takže `visible(fn ($record))` i `authorizeUsing()`
pracující se záznamem se ptají na tentýž záznam při vykreslení tlačítka i při
kliknutí. Bez toho by se tlačítko ptalo na objednávku a kliknutí na nic.

**Kam kliknutí dopadne, závisí na stránce, a nic jiného ne.** Akce stránky se
seznamem běží enginem její tabulky — druhý engine by byl druhý zásobník modalů na
jedné komponentě — takže kliknutí volá `openHeaderActionModal()` nebo
`executeHeaderAction()` a tabulka najde akci stránky dřív než svou vlastní.
Ostatní stránky volají `mountAction()`. `headerActionClick()` je místo, kde to
každá stránka říká; tlačítko je v obou případech `Action::render()`.

Akcí v hlavičce může být i `ActionGroup` a položka, která je `null`, se přeskočí,
takže podmíněná akce se píše rovnou do pole:

```php
protected function headerActions(): array
{
    return [
        ...parent::headerActions(),
        $this->headerActionRecord()?->locked_at !== null ? null : Action::make('lock')->action(...),
    ];
}
```

### Ty, které přicházejí s ní

**Stránka se seznamem nabízí *Nový*** — odkaz na stránku založení, vykreslený, když
resource nějakou routuje a tento uživatel ji smí otevřít. Ptá se přesně na to, na
co se ptá `can:` middleware routy pro založení, takže nikdy nenabídne nic, co by
router odmítl — a proto je zapnutý ve výchozím stavu. Ponechte ho přes
`...parent::headerActions()`, nebo si ho postavte sami přes `createHeaderAction()`.

**Editace a detail nabízejí *Smazat* a vykreslí ho, jen když se o něj řekne** —
`$this->deleteHeaderAction()`, jako výše. Kdo smí smazat co, je pravidlo aplikace:
modul uživatelů nesmaže posledního super-admina a výchozí tlačítko by kolem toho
prošlo bez povšimnutí. Když se o něj řekne, rozhoduje takhle:

```text
model má policy            →  rozhodne delete() té policy
žádnou nemá                →  smazat smí ten, kdo smí otevřít editaci záznamu
editace neexistuje         →  nikdo — read-only resource tlačítko Smazat nedostane
```

Potvrdí, smaže přes model — takže model se soft deletes maže měkce — pošle
notifikaci o úspěchu a vrátí se na seznam přes `wire:navigate`. Kde žádný seznam
routovaný není, zůstane.

## Resource na jedné stránce

Entita, jejíž formulář má pole nebo dvě — štítky, jednotky, platební podmínky —
nepotřebuje stránku na založení a další na editaci. `ManagePage` je seznam
s oběma jako modály nad ním a *Smazat* na řádku:

```php
use NyonCode\WirePanels\Resources\Pages\ManagePage;

final class ManageTags extends ManagePage
{
    protected static ?string $resource = TagResource::class;   // [tl! focus]
}

public static function pages(): array
{
    return ['index' => ManageTags::class];
}
```

Jediný `form()` resource se vykreslí v obou modálech. **Co ukládají, je věc
modelu**: modal drží stav v rámci akce, ne v `$data` stránky, takže založení je
`Model::create($data)` a editace `$record->update($data)` s validovanými daty.
Formulář, který potřebuje životní cyklus stránky — repeater nad relací,
optimistický zámek, `Form::using()` — chce stránku založení a stránku editace.

**Kdo smí co, rozhoduje policy modelu** — `create`, `update`, `delete` — když ji
model má. Bez ní tu žádná kontrola není a strážcem je routa stránky: kdo smí
stránku otevřít, smí spravovat její záznamy. Záměrně to není kontrola, která
odpoví ano, protože každý autorizační callback odmítne request bez přihlášeného
uživatele a nechráněná stránka by vypsala záznamy bez možnosti je změnit.

## Záznamy v koši

Resource nad soft-deletujícím modelem může svůj koš v panelu spravovat místo
toho, aby ho skrýval — stačí to říct:

```php
use NyonCode\WirePanels\Resources\Contracts\ManagesTrashedRecords;

final class InvoiceResource implements DescribesResource, ManagesTrashedRecords, ProvidesResourceTable
{
    // …model používá SoftDeletes
}
```

Ta jedna deklarace dosáhne na každou stránku, takže seznam a stránka záznamu se
nemohou rozejít v tom, jestli záznam v koši existuje:

- **Seznam** dostane filtr `trashed`, *Obnovit* a *Trvale smazat* na řádku
  v koši a obojí i jako hromadné akce — ty působí jen na záznamy výběru, které
  jsou v koši, protože trvalé smazání přes živé řádky je ztráta dat, ne zrcadlo
  obnovení.
- **Stránka záznamu** otevře záznam v koši místo 404, skryje na něm
  `deleteHeaderAction()` a stránce, která o ně požádá, nabídne
  `restoreHeaderAction()` a `forceDeleteHeaderAction()`:

```php
protected function headerActions(): array
{
    return [$this->deleteHeaderAction(), $this->restoreHeaderAction(), $this->forceDeleteHeaderAction()];
}
```

Každá z nich se rozhoduje jako *Smazat*: `restore()` nebo `forceDelete()` policy
modelu, když policy existuje, jinak stránka editace záznamu, pro každý záznam
zvlášť. Kontrakt nad modelem bez `SoftDeletes` odmítne se zprávou, která jmenuje
oba, místo aby selhal uvnitř dotazu.

## Vlastní stránka

Tabule, kalendář, report: stránka, která není žádným z povrchů resource, chce
pořád stejný nadpis, drobečkovou navigaci a akce v hlavičce jako stránky kolem ní.
`Page` je přesně tohle, kolem pohledu, který napíšete vy:

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WirePanels\Pages\Page;

final class TaskBoard extends Page
{
    protected static string $view = 'livewire.task-board';   // [tl! focus]

    protected ?string $title = 'Tabule';

    protected function headerActions(): array      // [tl! focus:start]
    {
        return [
            Action::make('newTask')
                ->form([TextInput::make('title')->required()])
                ->action(fn (array $data) => Task::create($data)),
        ];
    }                                               // [tl! focus:end]

    /** Vlastní tlačítko karty — tentýž engine, deklarovaný vedle hlavičky. */
    protected function actions(): array
    {
        return [
            Action::make('moveTask')->action(fn (array $arguments) => $this->move($arguments)),
        ];
    }

    protected function getViewData(): array
    {
        return ['lanes' => Task::query()->get()->groupBy('status')];
    }
}
```

Pohled je jen obsah — stránka nad ním vykreslí nadpis a pod ním hostitele modalů,
a veřejné vlastnosti komponenty, `$this` i cokoli vrátí `getViewData()` do něj
doputují:

```blade
<div class="grid grid-cols-3 gap-4">
    @foreach($lanes as $status => $tasks)
        <section>…</section>
    @endforeach
</div>
```

Routuje se jako každá stránka — `RoutePage::make(TaskBoard::class)` v `pages()`
některého vlastníka, což jí dá URL, `can:` stráž a položku v menu — nebo se
namountuje ručně. Drobečková navigace je opt-in: implementujte
`ProvidesBreadcrumbs` a nějakou vraťte. Stránka, která nejmenuje žádný pohled, se
odmítne vykreslit, místo aby nakreslila prázdný rámeček.

`Page` je pohodlí, ne podmínka. Skládá `HostsPageActions` a vlastní komponenta,
která skládá tutéž traitu, dostane tytéž akce v hlavičce bez dědění od čehokoli.

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
**Nejvýš dva drobky** u běžného resource, protože tohle je celá hloubka, kterou
jeho stránky mají — seznam není uvnitř ničeho a stopa o jednom drobku se
nevykreslí vůbec, takže stránka se seznamem za tohle neplatí nic.
[Vnořený resource](resources.md#vnorene-resource) začíná stopu u seznamu
a záznamu svého rodiče.

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

## Ostatní stránky záznamu

Editace a detail vedle ní jsou dvě stránky téhož záznamu — a dokud to stránka
neřekne, vede mezi nimi jediná cesta: zpátky přes seznam. Menu nepomůže, zná
resources, ne záznamy, a drobečky vedou *nahoru*, ne do strany. Stránky jednoho
záznamu se proto vykreslí jako řada záložek nad vlastním obsahem stránky.

**Nic se pro to nedeklaruje.** `pages()` už ten seznam je a router už ví, které
z nich berou záznam — je to tatáž otázka, kterou odpovídá, když staví `{record}`
do URL. Stránka je záložka, když má záznam ve své URI:

```php
class OrderResource implements DescribesResource, ProvidesPages
{
    public static function pages(): array
    {
        return [
            'index'   => ListOrders::class,          // bez záznamu → není záložka
            'create'  => CreateOrder::class,         // bez záznamu → není záložka
            'view'    => ViewOrder::class,           // [tl! focus:start]
            'edit'    => EditOrder::class,
            'history' => RoutePage::make(OrderHistory::class)
                ->uri('{record}/history')            // tohle z ní dělá záložku
                ->icon('outline:clock')
                ->permission('orders.audit')
                ->sort(30),                          // [tl! focus:end]
        ];
    }
}
```

Stránku, která se nijak nepojmenovala, pojmenuje framework: `view` a `edit` mají
překlad v každém dodávaném jazyce, cokoli dalšího je vlastní klíč, čitelně
přepsaný — `history` se zobrazí jako *History*. `RoutePage::label()` obojí přebíjí.

```php
$page->subNavigation();
// ['view' => NavigationItem('Detail'), 'edit' => NavigationItem('Upravit'), 'history' => NavigationItem('History')]
```

Záložky jsou [`NavigationItem`y](navigation.md#navigationitem-api), stejně jako
drobečky nad nimi — „popisek, ikona a URL" tu má jednoho vlastníka.

**Co se vykreslí, rozhodují tři pravidla**, a všechna tři jsou o tom nevykreslit
něco zavádějícího:

- Stránka, kterou tenhle člověk nesmí otevřít, se **vynechá** — tatáž
  pravomoc, kterou je hlídaná routa, ptaná dřív, než se odkaz nakreslí. Záložka,
  která spadne na 403, je horší než žádná.
- Stránka, jejíž URL nejde postavit, se **vynechá**. Na rozdíl od řádku v menu,
  který poctivě říká „registrováno, tady nerouteno", je záložka, co nikam nevede,
  prostě rozbitá. Totéž vyprázdní pruh pro záznam, který není Eloquent: není co
  dát do URL místo klíče.
- **Míň než dvě záložky je žádná.** Jedna záložka je nadpis stránky napsaný
  podruhé — pravidlo, které drobečky pro stopu délky jedna už dodržují.

Aktuální záložka se pozná podle **druhu stránky** — `Zone::currentPage()`, čtený
ze jména routy — ne porovnáním URL záložky s aktuální, kde o rozsvícení rozhoduje
lomítko na konci nebo query string. Stejně jako zóna vedle něj se čte jednou při
mountu a drží se ve veřejném `$currentPage`, protože během Livewire updatu je
jméno routy `livewire.update` ([ADR 0027](routing.md#zony)) a editační stránka se
překresluje při každém stisku klávesy.

Stránky detailu a editace to skládají samy. Vlastní stránka se do řady přidá tím,
že složí tentýž trait:

```php
class OrderHistory extends Component
{
    use BelongsToResource;
    use LinksToRecordPages;
    use ResolvesOneRecord;

    protected static ?string $resource = OrderResource::class;
}
```

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
| `breadcrumbs(): array` | `array<int, NavigationItem>` | Kde stránka sedí — nejvýš dva drobky, u vnořeného resource čtyři |
| `public ?string $currentPage` | `string\|null` | Druh téhle stránky — `view`, `edit`, nebo ten, který resource pojmenoval — čtený při mountu a nesený dál |
| `subNavigation(mixed $record = null): array` | `array<string, NavigationItem>` | Ostatní stránky záznamu, klíčované druhem stránky. Pod dvěma prázdné |
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
| `ListPage` | `table(Table $table): Table`, `tabs(): array`, `public string $activeTab`, `getListTabs(): array`, `getActiveListTab(): ?ListTab` |
| `CreatePage` | `public ?array $data`, `form(Form $form): Form`, `save(): mixed`, `getRedirectUrl(mixed $record): ?string` |
| `EditPage` | totéž, plus `recordData(): array` a `mountedRecord()`, který naplní formulář |
| `CreatePage`, `EditPage` | `warnsAboutUnsavedChanges(): bool` — ve výchozím stavu `true` |
| každá stránka kromě dashboardu | `headerWidgets(): array`, `footerWidgets(): array`, `pageWidgetColumns(): int` |
| `ViewPage` | `infolist(): Infolist` |
| `ManagePage` | vše, co má `ListPage`, navíc `createHeaderAction()` jako modal, *Upravit* a *Smazat* na řádku, `guardedByPolicy(Action, string, bool): Action`, `manageForm(): Form` |
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

Každá stránka kromě dashboardu skládá `InteractsWithHeaderActions` a každá kromě
seznamu ji skládá skrze `HostsPageActions`, která přidává `WithActions`:

| Člen | Typ | Účel |
| --- | --- | --- |
| `headerActions(): array` | `array<int, Action\|ActionGroup\|null>` | *(protected)* Deklarace akcí v hlavičce stránky; položky `null` se přeskočí |
| `getHeaderActions(): array` | `array<int, Action\|ActionGroup>` | Deklarované akce, vyřešené jednou za request |
| `headerActionRecord(): ?Model` | `Model\|null` | *(protected)* Záznam, kterého se akce týkají — editace a detail odpoví svým |
| `headerActionClick(): ResolvesActionClick` | `ResolvesActionClick` | *(protected)* Do které Livewire metody kliknutí na této stránce dopadne |
| `createHeaderAction(): ?Action` | `Action\|null` | *(protected, seznam)* Hotové *Nový*, nebo `null`, kde není žádná stránka založení k otevření |
| `deleteHeaderAction(): DeleteAction` | `DeleteAction` | *(protected, editace a detail)* Hotové *Smazat* — potvrdit, smazat, zpět na seznam |
| `mayDeleteRecord(): bool` | `bool` | *(protected, editace a detail)* Nejdřív policy, pak oprávnění editace, jinak ne |
| `restoreHeaderAction(): RestoreAction` | `RestoreAction` | *(protected, editace a detail)* *Obnovit*, jen na záznamu v koši |
| `forceDeleteHeaderAction(): ForceDeleteAction` | `ForceDeleteAction` | *(protected, editace a detail)* *Trvale smazat*, jen na záznamu v koši, zpět na seznam |

`Page` přidává jen to, co potřebuje vlastní stránka:

| Člen | Typ | Účel |
| --- | --- | --- |
| `protected static string $view` | `string` | Pohled vykreslený pod nadpisem. Povinný |
| `protected ?string $title` | `string\|null` | Nadpis, nebo žádný |
| `getViewData(): array` | `array<string, mixed>` | *(protected)* Cokoli, co pohled potřebuje vedle veřejných vlastností |

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
