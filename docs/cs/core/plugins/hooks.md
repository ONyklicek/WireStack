---
order: 30
summary: "Hook systém: kde se framework ptá, jestli k tomu chce někdo něco říct, jak hook zúžit na jednu komponentu a typované hooky, které z toho dělají kontrakt."
---

# Hooky

Hook je pojmenované místo, kde se framework zastaví a zeptá, jestli k tomu chce
někdo něco říct. Tahle stránka je seznam těch míst, co která dostane, co smí
vrátit — a jak hook zúžit tak, aby se spustil pro jednu komponentu, ne pro každou
tabulku v aplikaci.

## Hook systém

Hooky nechají pluginy a aplikační kód komunikovat přes pojmenované callbacky.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('orders.exporting', function (array $payload): array {
        $payload['query']->where('tenant_id', auth()->user()->tenant_id);

        return $payload;
    });
}
```

Spusťte hook z vlastní služby nebo komponenty:

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

$payload = app(PluginManager::class)->runHook('orders.exporting', [
    'query' => Order::query(),
]);

$query = $payload['query'];
```

Hook ovlivní runtime chování jen když nějaký kód zavolá `runHook()` nebo `runTypedHook()` pro ten název hooku. Registrace hooku uloží callback; automaticky nepatchuje chování tabulky, formuláře ani akce.

### Dispatch vlastního typovaného hooku

Nabídnout hook znamená tři věci předtím, než ho nabídnete: ověřit, že je vůbec navázaný `PluginManager`, ověřit, že někdo poslouchá, a **teprve pak** zaplatit za payload. Tohle vlastní `HookDispatch` a přes něj dispatchuje každý hook v tomhle frameworku:

```php
use NyonCode\WireCore\Core\Plugin\HookDispatch;

$payload = HookDispatch::typed('orders.exporting', fn () => new ExportingOrders( // [tl! focus]
    query: $this->query(),                                                       // [tl! focus]
    format: $format,                                                             // [tl! focus]
));                                                                              // [tl! focus]

$query = $payload !== null ? $payload->query : $this->query();                   // [tl! focus]
```

Payload přichází jako **closure**, a to je ten smysl: postavit ho může znamenat přečíst sloupce tabulky nebo widgety dashboardu a aplikace, která žádný plugin neinstaluje, za to nemá platit nic. Closure se spustí, až když se najde callback, který ho přijme.

**`null` znamená „nikdo neposlouchal“, ne „nic se nezměnilo“.** Když se to slije dohromady přes `?? $original`, obnoví se vaše vlastní hodnota pokaždé, když callback pole vyprázdní — a vyprázdnit ho je legitimní odpověď, takže filtr, který odstranil všechny sloupce, by tiše vypadal jako no-op. Porovnávejte proti `null` výslovně.

### Dodávané hooky

`Hook` je kanonický zápis každého jména níže a prostý řetězec je vždy přijat místo něj — `Hook::TableComposing` a `'table.composing'` je totéž jméno.

| Hook | Kdy běží | Co mění |
|---|---|---|
| `Hook::TableComposing` | jednou, když hostitel složí svou tabulku | samotnou tabulku — sloupce a filtry tak, jak se vykreslují, hledá a řadí |
| `Hook::TableConfiguring` | uvnitř query service, při každém dotazu | to, co se chystá přečíst planner |
| `Hook::TableQuerying` | po sestavení plánu, před jeho během | dotaz a vynucené řazení |
| `Hook::TableQueried` | po aplikaci všech pipes | nic — pozorování |
| `Hook::FormConfiguring` | jednou, když se ze schématu stává config | schéma |
| `Hook::FormSaving` | před uložením zvalidovaných dat | data |
| `Hook::FormSaved` | až záznam existuje | nic — pozorování |
| `Hook::ActionExecuting` | před pipeline akce | kontext |
| `Hook::ActionExecuted` | po jejím dokončení | nic — pozorování |
| `Hook::InfolistConfiguring` | jednou, když se čte schéma infolistu | schéma |
| `Hook::WidgetConfiguring` | jednou, než hostitel profiltruje widgety viditelností | widgety, ještě než dostanou klíče |
| `Hook::ExportConfiguring` | jednou na export, ať se doručí jakkoli | dotaz a sloupce, které se dostanou do souboru |
| `Hook::NavigationBuilding` | pokaždé, když se staví menu | položky, klíčované tím, co je zaregistrovalo |
| `Hook::PageMounting` | jednou, když se resource stránka namountuje | veřejný stav té stránky |
| `Hook::SearchQuerying` | jednou za resource, při každém globálním hledání | dotaz toho resource |
| `Hook::ImportConfiguring` | jednou na import, ať se doručí jakkoli | mapování a konfiguraci importu |
| `Hook::CellUpdating` | než se zapíše inline editace buňky | hodnotu, nebo zápis odmítne |
| `Hook::FormFilling` | když se formulář plní ze záznamu | to, s čím pole přijdou |

**`table.composing` a `table.configuring` nejsou dvě jména pro jeden okamžik.** Configuring běží uvnitř `TableQueryService` nad poli, která se chystá spotřebovat planner, takže sloupec přidaný tam se hledá a řadí, ale **nikdy se nevykreslí**. Composing běží nad instancí tabulky, kterou hostitel složil, takže sloupec přidaný tam je sloupec, který uživatel vidí. Chcete přidat sloupec — `TableComposing`; chcete ovlivnit dotaz — `TableConfiguring` nebo `TableQuerying`.

Všechno kromě sedmi hooků z prvního bloku je **typed-only**: bere objekt payloadu přes `runTypedHook()` a nemá pole jako protějšek. Těch sedm se kvůli zpětné kompatibilitě dispatchuje oběma způsoby a každý callback patří právě jednomu dispatcheru — viz [Který dispatcher dostane váš callback](#ktery-dispatcher-dostane-vas-callback). Nové hooky pole nedostanou: dvojí dispatch je dluh zpětné kompatibility 2.x, ne vzor, který by se měl rozšiřovat.

### Zúžení hooku na jednu komponentu

Callback bez zúžení běží pro každou tabulku, formulář i akci v aplikaci. `for:` ho zúží na jednu — podle registrovaného klíče resource, který stránka ukazuje, podle třídy hostitelské komponenty, nebo podle modelu:

```php
$manager->hook(Hook::TableComposing, $addColumn, for: 'invoices');            // jeden resource
$manager->hook(Hook::FormConfiguring, $addField, for: Invoice::class);        // jeden model
$manager->hook(Hook::TableComposing, $addColumn, for: ListInvoices::class);   // jedna stránka
```

Právě tohle dělá z nainstalovaného [doménového modulu](../../panels/modules.md) něco upravitelného: jeho list se staví uvnitř kódu, který nevlastníte, takže klíč, pod kterým se zaregistroval, je jediné držadlo, které máte. Stránka svůj klíč zná, protože implementuje `IdentifiesHookTarget` — každá resource stránka ho implementuje a dashboard stránka odpoví klíčem dashboardu, který ukazuje; samostatná komponenta žádný nemá a zúží se podle třídy nebo modelu.

Dva hooky pojmenovávají něco jiného než komponentu, protože k žádné nepatří:

| Hook | Co pojmenuje `for:` |
|---|---|
| `Hook::NavigationBuilding` | [zónu](../../panels/navigation.md), pro kterou se menu staví |
| `Hook::SearchQuerying` | katalogový klíč hledaného resource, nebo jeho model |

Zúžený callback se přeskočí tam, kde dispatch žádný cíl nenese — včetně hooků, které dispatchuje váš vlastní kód bez něj. Pustit callback napsaný pro jeden modul na komponentu, kterou nikdy neviděl, je ta horší ze dvou chyb. Takže menu stavěné bez zóny i infolist nad prostým polem místo modelu zúžený callback vynechají.

### Návratové hodnoty hooku

Array hooky dostanou aktuální payload pole.

| Návrat callbacku | Výsledek |
|-----------------|--------|
| `array` | Nahradí payload pro další callback |
| `null` nebo jiná ne-array hodnota | Ponechá aktuální payload beze změny |
| výjimka | Probublá k volajícímu |

### Priorita hooku

Callbacky běží ve vzestupné prioritě. Nižší čísla běží dřív.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: -100);
    $manager->hook('table.querying', fn (array $payload) => $payload);
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: 100);
}
```

Doporučené rozsahy:

| Priorita | Použití pro |
|----------|---------|
| `-100` | Bezpečnost, tenancy, scoping |
| `0` | Normální chování feature |
| `100` | Audit, logování, telemetrie |

Callbacky se stejnou prioritou si zachovají pořadí registrace.

### Runtime hooky

Tyto hooky emitují aktuální balíčky:

| Hook | Balíček | Kdy | Payload | Konzumuje vrácený payload |
|------|---------|------|---------|---------------------------|
| `table.composing` | Table | Nad instancí tabulky, kterou postavil hostitel — sloupec přidaný tady uživatel uvidí | `table`, `columns`, `filters` | Ano, čte upravená pole |
| `table.configuring` | Table | Uvnitř `TableQueryService`, nad poli, která si plánovač chystá vzít — hledá a řadí se podle nich, ale nevykreslí se | `table`, `columns`, `filters` | Ano, čte upravená pole |
| `table.querying` | Table | Před naplánováním dotazu tabulky | `table`, `columns`, `filters`, `sort_column`, `sort_direction`, `search` | Ano, čte `force_sort_column` a `force_sort_direction` |
| `table.queried` | Table | Po sestavení dotazu, i s plánem za ním | `table`, `query`, `plan` | Ne |
| `form.configuring` | Forms | Jednou, když se schéma formuláře stane jeho configem — protějšek `table.configuring` a způsob, jak plugin přidá pole do cizího formuláře | `form`, `schema` | Ano, čte upravené schéma |
| `form.saving` | Forms | Po mutaci a před perzistencí | `config`, `data` | Ano, čte upravená `data` |
| `form.saved` | Forms | Po perzistenci a uložení relací | `config`, `record` | Ne |
| `action.executing` | Table | Před během pipeline akce | `action`, `actionName`, `actionType`, `recordIds`, `data`, `component` | Ne |
| `action.executed` | Table | Po běhu pipeline akce | `action`, `actionName`, `actionType`, `recordIds`, `result`, `component` | Ne |
| `infolist.configuring` | Core | Jednou, když se čte schéma infolistu pro vykreslení — read-only polovina `form.configuring` | `infolist`, `schema` | Ano, čte upravené schéma |
| `widget.configuring` | Core | Než hostitel profiltruje widgety podle viditelnosti | `host`, `widgets` | Ano, čte upravený seznam |
| `navigation.building` | Core | Když `Workspace` skládá menu, ještě před seskupením | `items`, `zone` | Ano, čte upravené položky |
| `search.querying` | Core | Per resource v palette, **před** `get()` a před `canView()` — callback dotaz zúží a nemůže se rozšířit za kontrolu policy | `query`, `term`, `resource` | Ano, čte upravený dotaz |
| `export.configuring` | Table | Po filtru viditelnosti, takže callback vidí, co by bylo v souboru, ne všechno, co tabulka deklaruje | `export`, `query`, `columns` | Ano, čte upravený dotaz i sloupce |
| `page.mounting` | Panels | Když se mountuje stránka resourcu, ještě před vykreslením | `page`, `title`, `zone` | Ano, čte upravený titulek |
| `infolist.configuring` | Core | Jednou, když se čte schéma infolistu — read-only protějšek `form.configuring` | `infolist`, `schema` | Ano, čte upravené schéma |
| `widget.configuring` | Core | Nad deklarovanými widgety, než dostanou klíče a než je profiltruje viditelnost | `host`, `widgets` | Ano, čte upravený seznam |
| `export.configuring` | Table | V `buildTableExport()`, takže streamované stažení a zařazený soubor jsou jeden export | `export`, `query`, `columns` | Ano, čte obojí |
| `navigation.building` | Core | Nad plochým klíčovaným seznamem položek, před seskupením a seřazením | `items`, `zone` | Ano, čte upravené položky |
| `page.mounting` | Panels | Jakmile se resource stránka namountuje — po jejím vlastním `mount()`, takže záznam je vyřešený | `page`, `title`, `zone` | Ne — mění se sama stránka |
| `search.querying` | Core | Za každý resource, nad dotazem, který se paleta chystá spustit | `query`, `term`, `resource` | Ano, čte dotaz |
| `import.configuring` | Table | V `importTable()`, kam se zařazený import vrací — druhá půlka `export.configuring` | `import`, `columns`, `path` | Ano, čte sloupce |
| `cell.updating` | Table | V `CellEditPipeline::commit()`, po vlastních kontrolách sloupce a před zápisem | `column`, `columnName`, `record`, `value`, `oldValue`, `refusal` | Ano, čte obojí |
| `form.filling` | Forms | V `Form::fill()` — cesta *dovnitř*, kde `form.saving` je cesta ven | `form`, `data` | Ano, čte data |

U několika z nich stojí za to znát pořadí, protože právě ono z nich dělá užitečné hooky, ne jen brzké:

- **`widget.configuring` běží dřív, než se orazítkují klíče.** Klíč widgetu se odvozuje z jeho indexu v neprofiltrovaném seznamu, takže widget přidaný později by žádný neměl — a poll tick by ho nedosáhl — nebo by si vzal ten, na který už jiný widget slyší. Běží taky před filtrem viditelnosti, takže `visible()` přidaného widgetu platí.
- **`export.configuring` běží v `buildTableExport()`**, které volá `exportTable()` i `queueTableExport()`. Hook jen nad stažením by zařazenou kopii nechal nepokrytou a nikdo by si toho nevšiml, dokud by ty dva soubory neporovnal. Běží *po* viditelnosti sloupců, takže dostanete to, co by soubor obsahoval.
- **`cell.updating` běží uvnitř commitu jako poslední.** Kontrola oprávnění sloupce, kontrola optimistického zámku i jeho validace už proběhly, takže callback zužuje, co se zapíše, a nemůže obejít stráž, kterou sloupec deklaroval. Sedí v pipeline, ne ve svých dvou volajících, protože inline editor i fill handle procházejí právě jí — hook jen na jednom z nich by bylo pravidlo, kterému tažení přes sloupec tiše uteče. Nastavení `$payload->refusal` zápis zastaví a do prohlížeče dorazí jako vlastní chybová hláška buňky.
- **`import.configuring` žádné `buildTableImport()` nepotřeboval.** Na rozdíl od exportního protějšku se zařazený import vrací zpátky do `importTable()` — `RunImportJob` namountuje hostitele a zavolá ho — takže jeden dispatch pokryje obě doručení. Běží *až po* autorizační kontrole `ImportAction`, takže cesta, kterou callback vidí, je ta, kterou akce už otevřít povolila, a je jen ke čtení.
- **`form.filling` se nedispatchuje z `getInitialState()`.** To odpovídá na jinou otázku — co potřebuje ovládací prvek, než se cokoli naváže — a edit stránka volá obojí; hook na obou by se spustil dvakrát na stránku, což je způsob, jak callback, který přidává, přidá dvakrát.
- **`page.mounting` běží poslední.** Livewire volá vlastní `mount()` komponenty dřív než `mount{Trait}` hooky, takže edit stránka už má vyřešený záznam a naplněný formulář — a proto callback může přidat klíč do state bagu, místo aby ho naplnění přepsalo. Stránku měňte přes její **veřejnou** plochu: stránka se namountuje jednou a každý další update odpovídá ze snapshotu, který nese jen veřejné properties — stav zapsaný jinam je správný na prvním vykreslení a na druhém je pryč. Proto je `$title` v payloadu jen ke čtení: `$title` stránky je protected, takže hook, který by ho nastavil, by nabízel přesně tohle.

Plugin manager nevynucuje názvy hooků. Pro aplikační hooky používejte názvy popisující vaši hranici, jako `orders.exporting`, `orders.exported`, `billing.invoice.saving` nebo `crm.customer.synced`.

### Příklad: přidání řádku do detailu modulu

Tvar, který potřebuje každý modulový balíček: resource posílá list, formulář a detail a aplikace do jednoho z nich přidá, aniž by tu třídu vlastnila.

```php
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Infolists\Components\TextEntry;

public function register(PluginManager $manager): void
{
    $manager->hook(
        Hook::InfolistConfiguring,                                                   // [tl! focus]
        function (InfolistConfiguringPayload $payload): InfolistConfiguringPayload { // [tl! focus]
            $payload->schema = [...$payload->schema, TextEntry::make('crm_id')];     // [tl! focus]
                                                                                     // [tl! focus]
            return $payload;                                                         // [tl! focus]
        },                                                                           // [tl! focus]
        for: 'users',                                                                // [tl! focus]
    );
}
```

Ty samé tři řádky s `Hook::FormConfiguring` a polem ho přidají do formuláře vedle. Ta symetrie je smysl celé té dvojice.


### Příklad: Vynutit řazení tabulky v hooku

Balíček sortable používá `table.querying` k vynucení řazení, když je tabulka v režimu přeřazování. Stejný vzor funguje pro aplikačně specifická query pravidla.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('table.querying', function (array $payload): array {
        $table = $payload['table'] ?? null;

        if (! $table instanceof OrdersTable) {
            return $payload;
        }

        $payload['force_sort_column'] = 'position';
        $payload['force_sort_direction'] = 'asc';

        return $payload;
    }, priority: -100);
}
```

Použijte `modifyQueryUsing()`, když potřebujete změnit jen jednu tabulku. Použijte `table.querying`, když pravidlo patří ke znovupoužitelné integraci.

## Makra: ta druhá půlka

Hook je pro komponentu, kterou nikdy neuvidíte. **Makro** je pro tu, kterou v ruce držíte, když chcete jen novou slovní zásobu pro třídu, kterou jste nenapsali:

```php
Column::macro('money', fn (): Column => $this->alignment('right')->formatStateUsing(fn ($v) => number_format($v, 2)));

TextColumn::make('total')->money();
```

Makrovatelné jsou `Table`, `Form`, `Column`, `Field`, `Filter` a `BaseAction`. Makra deklarujte v `boot()` pluginu, nikdy v `register()` — viz [Pluginy](index.md#zivotni-cyklus).

Po makru sáhněte, když komponentu stavíte a chcete to jen říct kratšími slovy; po hooku, když se komponenta staví uvnitř modulu, který jste nainstalovali.

## Typované hooky

`runTypedHook()` je dostupný pro rozšiřovací body, které preferují object payloady místo polí.

```php
final class ExportingOrders
{
    public function __construct(
        public Builder $query,
        public string $format,
    ) {}
}

$payload = app(PluginManager::class)->runTypedHook(
    'orders.exporting',
    new ExportingOrders(Order::query(), 'csv')
);
```

Callbacky dostanou payload objekt. Vrácení objektu nahradí payload pro další callback; vrácení `null` nebo jiného ne-objektu ponechá aktuální payload.

```php
$manager->hook('orders.exporting', function (ExportingOrders $payload): ExportingOrders {
    $payload->query->where('tenant_id', auth()->user()->tenant_id);

    return $payload;
});
```

**Jedna asymetrie, kterou je dobré znát, než si vyberete variantu:** typovaný payload `table.querying` se dispatchuje *až* po sestavení plánu, takže slouží ke čtení hotového plánu a jeho výsledek se nečte zpět. Přebití řazení patří na polní hook, který běží před plánovačem.

Core také dodává typované payload DTO pod `NyonCode\WireCore\Core\Plugin\Hooks` pro běžné tvary table, form a action hooků — a runtime je **už dispatchuje**. Každý vestavěný bod životního cyklu spouští oba dispatchery za sebou: `table.configuring`, `table.querying` a `table.queried` z `TableQueryService`, `form.saving` a `form.saved` ze save handleru, `action.executing` a `action.executed` z action runtime. Callback na kterémkoli z těch hooků si tedy může vzít přímo `TableQueryingPayload`, `FormSavingPayload`, `ActionExecutingPayload` a další.

### Který dispatcher dostane váš callback

Protože v každém bodě životního cyklu běží oba dispatchery, musí každý callback patřit právě jednomu z nich — a rozhoduje o tom **typový hint prvního parametru**:

| První parametr | Dispatcher | Payload |
| --- | --- | --- |
| `array $payload` | `runHook()` | pole |
| DTO nebo jakýkoli jiný typový hint | `runTypedHook()` | objekt |
| **bez typového hintu nebo bez parametru** | `runHook()` | pole |

Otypujte ho. Callback bez hintu se kvůli zpětné kompatibilitě považuje za array variantu, což znamená, že typovaný payload tiše nikdy neuvidí:

```php
// Běží jen na array dispatchi — $payload je pole.
$manager->hook('form.saving', function ($payload) { /* … */ });   // [tl! --]
// Řekne si, který payload chce, a dostane ho.
$manager->hook('form.saving', function (FormSavingPayload $payload): FormSavingPayload { // [tl! ++]
    $payload->data['audited_at'] = now();                                                // [tl! ++]
                                                                                          // [tl! ++]
    return $payload;                                                                      // [tl! ++]
});                                                                                       // [tl! ++]
```

## Související

- [Pluginy](index.md) — odkud se hooky registrují
- [Rozšiřování povrchů](extending.md) — registry, do kterých hook často zapisuje
- [Příklady a testování](examples.md) — hooky ve dvou hotových pluginech
- [Životní cyklus ukládání](../../forms/save-lifecycle.md) — vlastní hookovací body formuláře
