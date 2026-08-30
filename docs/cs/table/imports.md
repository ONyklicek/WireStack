---
order: 52
---

# Importy tabulky

Wire Table umí importovat řádky z nahraného CSV souboru do modelu tabulky — zrcadlo [Exportů](exports.md). Sloupce mapují hlavičky souboru na atributy modelu, každá buňka se přetypuje a zvaliduje per sloupec a selhání validace po řádcích se sbírají místo přerušení běhu.

## Deklarace importéru

Připojte `ImportAction` s konfigurací `TableImport` do hlavičkových akcí tabulky:

```php
use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\ImportColumn;
use NyonCode\WireTable\Import\TableImport;

public function table(Table $table): Table
{
    return $table
        ->model(Contact::class)
        ->columns([...])
        ->headerActions([
            ImportAction::makeImport() // [tl! focus:start]
                ->importConfig(
                    TableImport::make()
                        ->model(Contact::class)
                        ->columns([
                            ImportColumn::make('name')
                                ->requiredMapping()
                                ->rules(['required']),
                            ImportColumn::make('email')
                                ->rules(['nullable', 'email'])
                                ->guess(['e-mail', 'mail']),
                            ImportColumn::make('age')
                                ->castStateUsing(fn ($value) => (int) $value),
                        ])
                ), // [tl! focus:end]
        ]);
}
```

## Spuštění importu

Hostitelský seam je `WithTable::importTable(string $filePath): ImportResult`. Aplikace k němu zapojí UI nahrání souboru — typicky Livewire upload, jehož reálná cesta dočasného souboru se předá dovnitř:

```php
use Livewire\WithFileUploads;

class Contacts extends Component
{
    use WithTable;
    use WithFileUploads;

    public $importFile = null;

    public function runImport(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:csv,txt']);

        $result = $this->importTable($this->importFile->getRealPath());

        // např. notifikace: "{$result->getImported()} imported, {$result->getFailedCount()} skipped"
    }
}
```

`importTable()` vyresolvuje konfiguraci `ImportAction` z hlavičkových akcí tabulky (zrcadlí `exportTable()`) a invaliduje cachované záznamy, takže další render ukáže nové řádky.

## Mapování hlaviček

Sloupec se spáruje s hlavičkou souboru podle svého **labelu**, **názvu atributu** nebo libovolného aliasu z `guess()` — case-insensitive a otrimované. Mapování se vyresolvuje jednou z řádku hlaviček.

- `requiredMapping()` označí hlavičku, kterou soubor **musí** obsahovat; chybějící vyhodí `RuntimeException` před zpracováním jakéhokoli řádku.
- Nenamapované volitelné sloupce se pro každý řádek jednoduše přeskočí.

## Validace po řádcích

`rules()` validují každou namapovanou buňku. Selhávající řádek se přeskočí a zaznamená — běh pokračuje:

```php
$result = $this->importTable($path);

$result->getImported();     // perzistované řádky
$result->getFailedCount();  // řádky přeskočené validací
$result->hasFailures();
$result->getFailures();     // [['row' => 3, 'errors' => ['The Email field must be…']], …]
```

## Aktualizovat nebo vytvořit

Párovat existující záznamy místo vždy vytvářet:

```php
TableImport::make()
    ->model(Contact::class)
    ->columns([...])
    ->updateExisting(['email'])   // updateOrCreate klíčované emailem
```

Každý atribut `updateExisting()` musí být krmen namapovaným sloupcem souboru — nenamapovaný párovací atribut nechá celý běh selhat předem (jinak by prázdná množina párovacích klíčů tiše přepsala nesouvisející záznamy).

## Vlastní perzistence

Převezměte perzistenci úplně pomocí `createUsing()` (`model()` není potřeba):

```php
TableImport::make()
    ->columns([...])
    ->createUsing(function (array $data) {
        Contact::firstOrNew(['email' => $data['email']])->fill($data)->save();
    })
```

## Volby CSV

```php
TableImport::make()
    ->delimiter(';')
    ->enclosure('"')
```

Importér zvládá UTF-8 BOM, prázdné řádky a řádky s méně/více buňkami, než má hlavička (chybějící buňky se stanou prázdnými řetězci, přebytečné se zahodí). CSV je jediný podporovaný formát (jako importér ve Filamentu).

## Metody

| Metoda | Na | Popis |
|--------|----|-------------|
| `model(string)` | `TableImport` | Cílový Eloquent model |
| `columns(array)` | `TableImport` | Seznam `ImportColumn` |
| `delimiter(string)` / `enclosure(string)` | `TableImport` | Volby parsování CSV |
| `updateExisting(array)` | `TableImport` | Párovací atributy `updateOrCreate` |
| `createUsing(Closure)` | `TableImport` | Vlastní handler perzistence po řádcích |
| `label(string\|Closure)` | `ImportColumn` | Popisek hlavičky (výchozí je headline z názvu) |
| `requiredMapping()` | `ImportColumn` | Soubor musí obsahovat tento sloupec |
| `rules(array)` | `ImportColumn` | Validační pravidla per buňka |
| `castStateUsing(Closure)` | `ImportColumn` | Transformovat surovou hodnotu buňky |
| `guess(array)` | `ImportColumn` | Alternativní názvy hlaviček |
| `importTable(string)` | hostitel | Spustí import hned, z reálné cesty |
| `queueTableImport(string, ?string)` | hostitel | Spustí ho na workeru, z cesty na disku |

## Import na frontě

Import byl už předtím cesta dovnitř, výsledek ven — přesun na frontu proto v jeho
pipeline nemění nic. Job přidává ty tři věci, které si od requestu vypůjčit
nemůže: soubor, který ho přežije, výsledek, který má kam jít, a selhání, které je
vidět.

```php
public function importInBackground(): void
{
    $path = $this->file->store('imports', 's3'); // [tl! focus]

    $this->queueTableImport($path, 's3'); // [tl! focus]
}
```

**Nahraný soubor nejdřív ulož a předej to, co uložení vrátí.**
`queueTableImport()` bere **cestu na disku**, ne reálnou cestu dočasného uploadu:
worker může být klidně jiný stroj a dočasný soubor Livewire tam v tu chvíli
nebude.

Výsledek přijde jako notifikace — počet naimportovaných a odmítnutých řádků —
protože import na frontě nemá návratovou hodnotu. Běh, který nějaké řádky
odmítl, hlásí **varování**, ne úspěch: import, který potichu zahodil řádek, je
přesně ten druh úspěchu, o kterém je lepší vědět.

**Chybějící soubor job položí.** Čtečka CSV bere nečitelnou cestu jako „žádné
řádky", což je správně, když se uživatel dívá, a lež, když běží fronta:
„naimportováno 0 řádků, 0 chyb" se nedá odlišit od prázdného souboru. Worker,
který upload nenajde, vyhodí `ImportException` a zkusí to znovu.

## Související dokumentace

- [Exporty](exports.md)
- [Akce](actions.md)
