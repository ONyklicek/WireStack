---
order: 95
summary: Jedna příkazová paleta nad všemi registrovanými resources — přihlaste resource, připojte komponentu, a jeden výraz dosáhne na objednávky, zákazníky i faktury v jednom seznamu.
---

# Globální hledání

Jedno vyhledávací pole nad vším, co má aplikace registrované. Uživatel otevře
paletu, píše a dostane záznamy z každého [resource](resources.md), který se
přihlásil — seskupené po resourcech, s limitem na resource a profiltrované tím,
co ten uživatel smí vidět.

Paleta získá resource ve chvíli, kdy je zaregistrovaný. Nedrží si vlastní seznam,
takže není co zapomenout aktualizovat.

## Jak to funguje

Každý stisk klávesy je Livewire round trip, debouncovaný na 250 ms. Co běží a
v jakém pořadí:

1. `GlobalSearchPalette` — komponenta, kterou připojíte — drží hledaný výraz,
   příznak otevření a klávesový kurzor, a žádný dotaz. Ptá se `GlobalSearch`.
2. `GlobalSearch` čte [`ResourceRegistry`](resources.md) a přeskočí každý
   resource, který neimplementuje `GloballySearchable`. Identita a prohledávatelnost
   jsou oddělené přihlášky: resource, který prohledávatelný být nemá — audit log,
   spojovací tabulka, která dostala resource kvůli routování — to řekne tím, že
   kontrakt neimplementuje, ne tím, že by vracel prázdné pole z metody, kterou
   mít musel.
3. Pro každý zbývající resource pustí **jeden** dotaz:
   `WHERE (a LIKE %term% OR b LIKE %term%) LIMIT 5`, nad atributy, které resource
   deklaroval.
4. Každý nalezený záznam projde autorizační kontrolou a to, co přežije, dostane
   zpátky resource, který z toho udělá `GlobalSearchResult`.
5. Resource, který nic nenašel, ve výsledku úplně chybí, místo aby byl namapovaný
   na prázdný seznam — takže vykreslení hlaviček skupin je pouhá iterace.

**Stojí to** jeden dotaz na přihlášený resource na stisk klávesy. To je důvod jak
pro limit na resource, tak pro pravidlo, že atributy jsou obyčejné sloupce — join
na resource na stisk klávesy je způsob, jak paleta přestane být rychlým prvním
odhadem.

**Výchozí stav a hraniční případy:**

- **Prázdný výraz nevrací nic.** Matchovat všechno by znamenalo „tady je celá tvá
  databáze" ve chvíli, kdy se modál otevře.
- `%` a `_` jsou v `LIKE` divoké karty, takže se výraz escapuje, a escape znak je
  deklarovaný explicitně (`ESCAPE '!'`). MySQL bere `\` jako výchozí escape a
  SQLite ne, takže dotaz spoléhající na tenhle default by na dvou podporovaných
  databázích znamenal dvě různé věci.
- Resource **bez modelu** (nad non-Eloquent `DataSource`) nebo s **prázdným
  seznamem atributů** nepřispěje ničím, místo aby chyboval.
- Skupiny mají v hlavičce `pluralLabel()` resource, ne jeho klíč — klíč je to, co
  resource routuje a konfiguruje, ne to, co čte uživatel.

## Přihlášení resource

Implementujte `GloballySearchable` — dvě statické metody, statické ze stejného
důvodu jako identita a navigace: paleta se ptá každého registrovaného resource,
co umí prohledat, dřív než se cokoli instancuje.

```php
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;

final class OrderResource implements DescribesResource, GloballySearchable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Order::class;
    }

    public static function globallySearchableAttributes(): array   // [tl! focus:start]
    {
        // Sloupce na vlastním modelu tohohle resource. Ne status: paleta, která
        // na „paid" odpoví každou zaplacenou objednávkou, je report, ne skok.
        return ['number', 'customer'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult(
            resourceKey: self::key(),
            recordKey: $record->getKey(),
            title: $record->number,
            subtitle: $record->customer.' · '.$record->status,
            icon: 'outline:document-text',
        );
    }                                                              // [tl! focus:end]
}
```

`GlobalSearchResult` je záměrně plochý a už vyřešený — paleta vykresluje spoustu
řádků najednou a nesmí kvůli žádnému z nich volat zpátky do resource:

| Argument | Typ | Co to je |
| --- | --- | --- |
| `resourceKey` | `string` | Který resource ho vyrobil; paleta podle toho seskupuje |
| `recordKey` | `int\|string` | Klíč záznamu, pro `wire:key` a pro kliknutí |
| `title` | `string` | Řádek, který uživatel čte první |
| `subtitle` | `?string` | Kontext pod ním — stav, e-mail, datum |
| `url` | `?string` | Kam výběr vede; odvozené, když se vynechá, `null`, když záznam nic neroutuje |
| `icon` | `?string` | Jméno ikony, resolvované jako každá jiná ikona ve frameworku |

**Všimni si, co příklad nepředává.** Řádek už obě půlky své URL nese — klíč
resource a klíč záznamu — takže ji framework sestaví:
`urlFor($resourceKey, 'view', ['record' => $recordKey])`. Napsat sem cestu
znamená kopírovat to, co router
už ví, a je to ta kopie, která zastará: než se URL začala odvozovat, měl tenhle
repozitář ve vlastním workbenchi dvě natvrdo psané cesty a obě byly špatně —
jedna mířila do shellu, druhá na stránku bez záznamu, a nic neselhalo.

`url:` předávej výslovně jen tehdy, když řádek vede někam, kam konvence nedosáhne
— do externího systému, na report, na stránku mimo vlastní resource. Explicitní
URL vždycky vyhraje.

Řádek s `null` url se pořád vykreslí a pořád se přes něj dá projít šipkami; Enter
na něm neudělá nic, místo aby navigoval někam vymyšleným. To je odpověď pro
resource, který nedeklaruje stránky.

## Připojení palety

Komponentu připojte jednou, v layoutu:

```blade
@livewire('wire-global-search')
```

**Otevření je věc aplikace.** Komponenta poslouchá událost `open-global-search` a
sama žádnou klávesu nezabírá, protože framework, který by si na každé stránce
nárokoval ⌘K, by bral kombinaci, kterou aplikace už může používat. Tohle píše
aplikace:

```blade
<div
    x-data
    @keydown.window.cmd.k.prevent="$dispatch('open-global-search')"
    @keydown.window.ctrl.k.prevent="$dispatch('open-global-search')"
>
    <button type="button" x-on:click="$dispatch('open-global-search')">
        Hledat… <kbd>⌘K</kbd>
    </button>
</div>

@livewire('wire-global-search')
```

Uvnitř palety jsou klávesy navázané už teď: **↑/↓** chodí po řádcích, **Enter**
otevře aktivní, **Escape** zavírá. Kurzor je plochý index přes všechny skupiny,
ne dvojice (skupina, řádek), protože přesně tak se šipkami pohybuje — Dolů na
posledním řádku jedné skupiny dojde na první řádek další. Změna výrazu vrátí
kurzor nahoru, jinak by Enter otevřel něco z výsledků, na které se uživatel už
nedíval.

Dialog je teleportovaný do `<body>`, jako každý modál ve frameworku, takže ho
nikdy neořízne polohovaný předek.

## Autorizace a tenancy

Paleta, která vypíše název zakázaného záznamu, ho **už prozradila**, ať se
kliknutí potom odmítne nebo ne. Takže:

- **Tenancy tady nepotřebuje nic.** Je to globální Eloquent scope, takže dotaz
  postavený z `Model::query()` je už zúžený na aktuálního tenanta.
- **Po záznamech** se hledání ptá policy modelu na `view`. Když model **nemá
  registrovanou žádnou policy**, kontrola propadne otevřená — je to vlastní
  odpověď Laravelu na nehlídaný model a je to to, co drží paletu použitelnou
  v aplikaci, která neautorizuje nikde. Resource, který se nikdy nesmí vypsat bez
  kontroly, si zaregistruje policy, což je totéž, co by udělal pro jakékoli jiné
  čtení.

Limit se aplikuje **dřív** než autorizační filtr, takže uživatel bez přístupu ke
všem pěti nejlepším shodám jednoho resource uvidí, že ten resource z výsledků
vypadl, místo aby viděl díru.

## Když obyčejné sloupce nestačí

`globallySearchableAttributes()` zůstává odpovědí pro obyčejné sloupce. Aplikace,
jejíž hledání potřebuje join, fulltextový index nebo externí vyhledávací službu,
nahradí *hledání*, ne kontrakt: `GlobalSearch::searchResource()` a `matchAny()`
jsou `protected` přesně kvůli tomu a paleta si své hledání resolvuje z kontejneru.

```php
use NyonCode\WireCore\GlobalSearch\GlobalSearch;

class ScoutGlobalSearch extends GlobalSearch
{
    protected function searchResource(string $resource, string $term, int $perResource): array   // [tl! focus]
    {
        // …zeptat se vyhledávací služby, hity namapovat přes $resource::toGlobalSearchResult()
    }
}

// V service provideru:
$this->app->bind(GlobalSearch::class, ScoutGlobalSearch::class);   // [tl! focus]
```

## Hledání bez palety

`GlobalSearch` je obyčejná služba. Vlastní povrch — stránka s výsledky, mobilní
obrazovka, API endpoint — se jí může zeptat přímo a odpověď si vykreslit sám:

```php
use NyonCode\WireCore\GlobalSearch\GlobalSearch;

$groups = app(GlobalSearch::class)->search('INV-100');
// ['orders' => [GlobalSearchResult, …], 'customers' => [GlobalSearchResult, …]]

$more = app(GlobalSearch::class)->search('INV-100', perResource: 20);
```

## API

```php
GlobalSearch::search(string $term, int $perResource = 5): array   // [klíč => GlobalSearchResult[]]
GlobalSearch::PER_RESOURCE_LIMIT                                  // 5

GlobalSearchResult::withUrl(?string $url): GlobalSearchResult     // tentýž řádek, namířený
```

`search()` čte [`Catalog`](resources.md#catalog-api), takže paleta získá resource
ve chvíli, kdy je zaregistrovaný, a nikdy si nedrží vlastní seznam. Registrovaná
věc, která se přihlásí k hledání a nemá model — dashboard — se přeskočí, místo aby
se jí ptalo.

Kontrakt, který resource implementuje:

```php
static globallySearchableAttributes(): array          // jména obyčejných sloupců na modelu
static toGlobalSearchResult(object $record): GlobalSearchResult
```

Komponenta palety, pro vlastní trigger nebo pro test:

```php
$palette->open(): void                 // navázané i na událost `open-global-search`
$palette->close(): void                // vyčistí výraz, takže se příště otevře prázdná
$palette->moveDown(): void
$palette->moveUp(): void
$palette->select(): mixed              // naviguje na aktivní řádek, nebo null
$palette->selectedUrl(): ?string       // kam aktivní řádek vede
$palette->flatResults(): array         // všechny řádky, v pořadí vykreslení
$palette->groupLabels(): array         // [klíč resource => popisek v množném čísle]
```

## Související

- [Resources](resources.md) — registr, který paleta čte, a kontrakt identity, který implementuje každý resource
- [Autorizace](../authorization.md) — policies, tenancy a co scopované není
- [Modály](modals.md) — vzor teleportu do body, který dialog následuje
