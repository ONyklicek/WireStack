# Exports

Wire Table exports the current table query as CSV, Excel, or PDF. The export uses the active search, filters, sorting, and visible columns.

## Buttons

Call `exportTable()` from a Livewire component using `WithTable`.

```blade
<button type="button" wire:click="exportTable('csv')">
    Export CSV
</button>

<button type="button" wire:click="exportTable('xlsx')">
    Export Excel
</button>

<button type="button" wire:click="exportTable('pdf')">
    Export PDF
</button>
```

Supported values are `csv`, `xlsx`, and `pdf`.

## Configuration

Use `ExportAction` to define export settings on the table.

```php
use NyonCode\WireTable\Export\ExportAction;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\TableExport;

return $table
    ->model(User::class)
    ->columns([
        TextColumn::make('name')->label('Name')->searchable()->sortable(),
        TextColumn::make('email')->label('Email')->searchable(),
    ])
    ->headerActions([
        ExportAction::makeExport()
            ->formats([ExportFormat::Csv, ExportFormat::Excel])
            ->exportConfig(
                TableExport::make()
                    ->fileName('users')
                    ->delimiter(';')
                    ->withHeadings()
            ),
    ]);
```

The first `ExportAction` on the table provides the default settings. The actual download still happens through `exportTable('csv')`, `exportTable('xlsx')`, or `exportTable('pdf')`.

## Query And Columns

`exportTable()` starts from the filtered and sorted table query, without pagination.

```php
TableExport::make()
    ->fileName('active-users')
    ->modifyQueryUsing(fn ($query) => $query->where('active', true));
```

By default, exports include only columns visible to the current user. To use a custom set:

```php
TableExport::make()
    ->columns([
        TextColumn::make('name')->label('Name'),
        TextColumn::make('email')->label('Email'),
    ]);
```

## Optional Dependencies

Install OpenSpout for real XLSX output:

```bash
composer require openspout/openspout
```

Install Laravel DomPDF for real PDF output:

```bash
composer require barryvdh/laravel-dompdf
```

Without these optional packages, Excel and PDF exports fall back to CSV output.

For the full guide, see [Table Exports](../../../docs/table/exports.md).
