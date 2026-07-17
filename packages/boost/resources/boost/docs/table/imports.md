---
order: 52
---

# Table Imports

Wire Table can import rows from an uploaded CSV file into the table's model — the mirror of [Exports](exports.md). Columns map file headers to model attributes, each cell is cast and validated per column, and per-row validation failures are collected instead of aborting the run.

## Declare the Importer

Attach an `ImportAction` with a `TableImport` config to the table's header actions:

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

## Run the Import

The host seam is `WithTable::importTable(string $filePath): ImportResult`. The application wires the file-upload UI to it — typically a Livewire upload whose temp file's real path is passed in:

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

        // e.g. notify: "{$result->getImported()} imported, {$result->getFailedCount()} skipped"
    }
}
```

`importTable()` resolves the `ImportAction` config from the table's header actions (mirroring `exportTable()`) and invalidates cached records so the next render shows the new rows.

## Header Mapping

A column matches a file header by its **label**, its **attribute name**, or any `guess()` alias — case-insensitive and trimmed. Mapping is resolved once from the header row.

- `requiredMapping()` marks a header the file **must** contain; a missing one throws a `RuntimeException` before any row is processed.
- Unmapped optional columns are simply skipped for every row.

## Per-Row Validation

`rules()` validate each mapped cell. A failing row is skipped and recorded — the run continues:

```php
$result = $this->importTable($path);

$result->getImported();     // rows persisted
$result->getFailedCount();  // rows skipped by validation
$result->hasFailures();
$result->getFailures();     // [['row' => 3, 'errors' => ['The Email field must be…']], …]
```

## Update or Create

Match existing records instead of always creating:

```php
TableImport::make()
    ->model(Contact::class)
    ->columns([...])
    ->updateExisting(['email'])   // updateOrCreate keyed by email
```

Every `updateExisting()` attribute must be fed by a mapped file column — an unmapped match attribute fails the whole run up front (otherwise an empty match-key set would silently overwrite unrelated records).

## Custom Persistence

Take over persistence entirely with `createUsing()` (no `model()` needed):

```php
TableImport::make()
    ->columns([...])
    ->createUsing(function (array $data) {
        Contact::firstOrNew(['email' => $data['email']])->fill($data)->save();
    })
```

## CSV Options

```php
TableImport::make()
    ->delimiter(';')
    ->enclosure('"')
```

The importer handles a UTF-8 BOM, blank lines, and rows with fewer/more cells than the header (missing cells become empty strings, extras are dropped). CSV is the only supported format (like Filament's importer).

## Methods

| Method | On | Description |
|--------|----|-------------|
| `model(string)` | `TableImport` | Target Eloquent model |
| `columns(array)` | `TableImport` | `ImportColumn` list |
| `delimiter(string)` / `enclosure(string)` | `TableImport` | CSV parsing options |
| `updateExisting(array)` | `TableImport` | `updateOrCreate` match attributes |
| `createUsing(Closure)` | `TableImport` | Custom per-row persistence handler |
| `label(string\|Closure)` | `ImportColumn` | Header label (defaults to a headline of the name) |
| `requiredMapping()` | `ImportColumn` | The file must contain this column |
| `rules(array)` | `ImportColumn` | Per-cell validation rules |
| `castStateUsing(Closure)` | `ImportColumn` | Transform the raw cell value |
| `guess(array)` | `ImportColumn` | Alternative header names |

## Related Docs

- [Exports](exports.md)
- [Actions](actions.md)
