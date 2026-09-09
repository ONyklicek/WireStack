---
order: 10
summary: Vlastnická vrstva — jedna entita deklarovaná jednou, stránky, které ji vykreslí, menu, ve kterém se objeví, a routy, které na ni vedou.
---

# Panely

`wire-panels` je vrstva nad komponentami. Tabulka, formulář a infolist jsou
nezávislé primitivy, které o sobě navzájem nevědí; tenhle balíček přidává třídu,
která říká, že **je to všechno jedna entita**, a stránky, menu a routy, které z
toho jednoho prohlášení plynou.

```bash
composer require nyoncode/wire-panels
```

Nic tenhle balíček nevyžaduje. Aplikace, která své tabulky a formuláře vykresluje
z vlastních Livewire komponent, ho nikdy nenainstaluje a všechno pod ním funguje
dál — což je zkouška toho, jestli je vlastnická vrstva opravdu volitelná.

## Jak to funguje

Čtyři věci stojí na sobě a každá z nich je použitelná i bez té nad sebou:

| Vrstva | Na co odpovídá | Stránka |
| --- | --- | --- |
| **Resource** | „tohle je entita Order, takhle se vypisuje, edituje a zobrazuje“ | [Resources](resources.md) |
| **Stránka** | „tahle Livewire komponenta vykreslí ten povrch pro jeden záznam nebo pro všechny“ | [Stránky](pages.md) |
| **Menu** | „které z nich se kde objeví, pod jakým nadpisem a v jakém pořadí“ | [Navigace](navigation.md) |
| **Router** | „která URL vede na kterou stránku a v jaké zóně“ | [Routování](routing.md) |

Resource bez stránek je pořád zaregistrovaný a pořád introspektovatelný. Stránky
fungují i bez resourcu — napiš na stránku `table()` a je to obyčejná komponenta s
`WithTable`. Položka menu, jejíž klíč nikam neroutuje, se vykreslí bez odkazu
místo toho, aby spadla. Každá vrstva degraduje na tu pod sebou, místo aby ji
vyžadovala.

Pohromadě je drží **registr názvů tříd**, ne objekt panelu. Nic tady nestaví
tabulku, aby odpovědělo na otázku „co je v menu“: identita je statická, povrchy
jsou instanční metody a menu o čtyřiceti resourcech nesestaví ani jeden z nich.

## Co veze který balíček

Resource je deklarovaný napříč balíčky, které vlastní typy, jež jmenuje — takže
aplikace instaluje jen to, co její resource opravdu používají:

| Co potřebuješ | Kde to žije | Protože to jmenuje |
| --- | --- | --- |
| `DescribesResource`, `DescribesRecords`, `ResourceRegistry` | `wire-core` | nic než skaláry |
| `ProvidesResourceForm` | `wire-forms` | `Form` |
| `ProvidesResourceInfolist` | `wire-core` (vedle infolistů) | `Infolist` |
| `ProvidesResourceTable`, `ProvidesRelationManagers` | `wire-panels` | `Table`, `RelationManager` |
| `ListPage` a ostatní stránky | `wire-panels` | `Table`, `Form`, hostitelské traity |

Praktický důsledek: **resource s formulářem a bez výpisu potřebuje `wire-forms` a
nic víc.** Identita přichází z `wire-core`, který si `wire-forms` už tak jako tak
vyžaduje, takže deklarace resourcu nikdy nepřitáhne tabulkový balíček — včetně
jeho assetů, migrací, configu a Livewire synthesizeru.

`wire-panels` sedí nad každým komponentovým balíčkem a nic na něm nezávisí. Ten
směr je smyslem věci: resource skládá primitivy, takže balíček, který vlastní
resource, je ten, který smí jmenovat všechny ostatní — a žádný z nich nesmí
jmenovat jeho.

## Rychlý start

Jedna entita, deklarovaná jednou, vykreslená stránkou o čtyřech řádcích:

```php
use App\Models\Order;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

final class OrderResource implements DescribesResource, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string   // [tl! focus:start]
    {
        return Order::class;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->searchable(),
            TextColumn::make('customer.name')->label('Zákazník'),
        ]);
    }                                              // [tl! focus:end]
}

final class ListOrders extends ListPage
{
    protected static ?string $resource = OrderResource::class;   // [tl! focus]
}
```

```php
// config/wire-core.php
'resources' => [
    App\Resources\OrderResource::class,
],
```

To je funkční výpis. Doplnění formuláře rozjede založení a editaci, doplnění
`navigation()` ho dá do menu, deklarace `pages()` mu dá URL — každé zvlášť a v
tomhle pořadí.

## Co tenhle balíček nedělá

Neveze žádný layout, žádné markup sidebaru a žádný chrome dashboardu. Celostránková
Livewire komponenta layout potřebuje a tenhle balíček ho nedodává — buď nastav
`livewire.component_layout` na vlastní, nebo nainstaluj
[admin shell](../admin/overview.md), což je hotová odpověď a přesně proto
samostatný balíček.

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Resources](resources.md) | Identita, kontrakty povrchů, pojmenování, registrace, čtení registru |
| [Stránky](pages.md) | `ListPage`, `CreatePage`, `EditPage`, `ViewPage`, dohledání záznamu, vnořené relation managery |
| [Navigace](navigation.md) | `NavigationItem`, `NavigationGroup`, `Workspace`, katalog, který čtou všechny tři povrchy |
| [Routování](routing.md) | `pages()`, `Route::wireResources()`, tvar URL, zóny, routy z configu |
| [Moduly](modules.md) | Manifest jedné byznysové oblasti — její resource, dashboardy a nadpis v menu v jedné třídě |

## Jak dosáhnout na stránku, kterou posílá modul

Každá resource stránka implementuje `IdentifiesHookTarget`, takže plugin hook jde
zúžit na resource, který ukazuje — `for: 'invoices'` dosáhne na list, formulář
i detail toho modulu a na nic jiného. Stránka sama má taky svůj hook:
[`page.mounting`](../core/plugins/hooks.md) se spustí, jakmile se namountuje.

Běží **poslední**, a to je měření, ne preference: Livewire volá vlastní `mount()`
komponenty dřív než `mount{Trait}` hooky, takže edit stránka už má vyřešený záznam
a naplněný formulář — a proto callback může přidat klíč do state bagu, místo aby ho
naplnění přepsalo.

Stránku měňte přes její **veřejnou** plochu. Namountuje se jednou a každý další
update odpovídá ze snapshotu, který nese jen veřejné properties — stav zapsaný
jinam je správný na prvním vykreslení a na druhém je pryč.

```php
$manager->hook(Hook::PageMounting, function (PageMountingPayload $payload) {
    $payload->page->data['team_id'] = auth()->user()->team_id;   // [tl! focus]

    return $payload;
}, for: 'invoices');
```

`$payload->title` je ze stejného důvodu jen ke čtení: `$title` stránky je
protected, takže hook, který by ho nastavil, by nabízel přesně tuhle past.

## Související

- [Admin shell](../admin/overview.md) — volitelný layout, uvnitř kterého se tyhle stránky vykreslují
- [Hotové moduly](../modules/index.md) — celé oblasti dodané jako balíčky, postavené na téhle vrstvě
- [Globální vyhledávání](../core/global-search.md) — command palette nad stejným katalogem
- [Konfigurace](../start/configuration.md) — kde se deklaruje `resources` a `wire-panels.routes`
