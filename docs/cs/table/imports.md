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

## Související dokumentace

- [Exporty](exports.md)
- [Akce](actions.md)
