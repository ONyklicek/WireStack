---
order: 50
---

# Table Exports

Wire Table can export the current table query as CSV, Excel, or PDF. Exports use the current search, filters, sorting, and visible columns.

## Basic Export Buttons

Add buttons or menu items that call `exportTable()` from the Livewire component using `WithTable`.

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

Supported format values:

| Value | File type |
|-------|-----------|
| `csv` | CSV |
| `xlsx` | Excel |
| `pdf` | PDF |

## Configure Export Defaults

Use `ExportAction` in `headerActions()` when you want to define export configuration in the table definition.

```php
use NyonCode\WireTable\Export\ExportAction;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\TableExport;

public function table(Table $table): Table
{
    return $table
        ->model(User::class)
        ->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('email')->label('Email')->searchable(),
            TextColumn::make('role')->label('Role'),
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
}
```

The download still happens through `exportTable('csv')`, `exportTable('xlsx')`, or `exportTable('pdf')`. The first `ExportAction` on the table provides the default export settings.

## Exported Columns

By default, exports include table columns that are visible to the current user. User-hidden columns are skipped.

To export a custom column set:

```php
TableExport::make()
    ->columns([
        TextColumn::make('name')->label('Name'),
        TextColumn::make('email')->label('Email'),
    ]);
```

Column labels are used as headings when headings are enabled.

## Exported Query

`exportTable()` starts from the current filtered and sorted table query, without pagination.

To add export-only constraints:

```php
TableExport::make()
    ->fileName('active-users')
    ->modifyQueryUsing(fn ($query) => $query->where('active', true));
```

To export a completely separate query, use `TableExport` directly:

```php
return TableExport::make()
    ->fileName('inactive-users')
    ->query(User::query()->where('active', false))
    ->columns([
        TextColumn::make('name'),
        TextColumn::make('email'),
    ])
    ->download();
```

## Exported Summaries

Columns with [`query`-scoped summaries](summaries.md) append their totals after
the data rows — the same grand totals the footer shows for the full filtered
set, in every format (CSV, Excel, PDF). Cells render as `Label: value` in the
column they belong to; a column with several summaries produces several rows:

```text
Number,Total
ORD-1,100
ORD-2,250
,"Grand total: 350 Kč"
,"Average: 175 Kč"
```

`page`/`selection`-scoped summaries describe transient UI state and are never
exported. To export bare data without totals:

```php
TableExport::make()
    ->withSummaries(false);
```

Rollup columns (`->sums()`, `->counts()`, …) export their per-row values and
grand totals too. When exporting a **custom query** with rollup columns, the
query must include the matching `withSum`/`withCount` — the same requirement
the table itself has. Sub-row grand totals and
[group subtotals](grouping.md) are footer-only and not included in exports.

## CSV Options

```php
TableExport::make()
    ->fileName('users')
    ->delimiter(';')
    ->enclosure('"')
    ->withHeadings();
```

To remove the heading row:

```php
TableExport::make()
    ->withHeadings(false);
```

## Excel Export

Excel export uses the `xlsx` format.

```blade
<button type="button" wire:click="exportTable('xlsx')">
    Export Excel
</button>
```

Install OpenSpout when your application needs real XLSX files:

```bash
composer require openspout/openspout
```

If OpenSpout is not installed, Wire falls back to CSV output.

## PDF Export

PDF export uses the `pdf` format.

```php
TableExport::make()
    ->fileName('users')
    ->orientation('landscape')
    ->paperSize('A4')
    ->pdfView('exports.users');
```

Install Laravel DomPDF when your application needs PDF files:

```bash
composer require barryvdh/laravel-dompdf
```

If DomPDF is not installed, Wire falls back to CSV output.

## PDF View Data

When using a custom PDF view, design it as a regular Blade export template. The exporter passes `headings`, `rows`, `columns`, and `summaryRows` (pre-formatted total rows, empty when summaries are disabled) to the view.

```blade
{{-- resources/views/exports/users.blade.php --}}
<table>
    @if (! empty($headings))
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
    @endif

    <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $value)
                    <td>{{ $value }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>

    @if (! empty($summaryRows))
        <tfoot>
            @foreach ($summaryRows as $summaryRow)
                <tr>
                    @foreach ($summaryRow as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tfoot>
    @endif
</table>
```

## Related Docs

| Document | What It Covers |
|----------|----------------|
| [Table Overview](overview.md) | Table setup and state |
| [Columns](columns/index.md) | Column labels, visibility, and formatting |
| [Filters](filters/index.md) | Filtered queries used by export |
| [Summaries](summaries.md) | The totals appended to exports |
| [Authorization](../authorization.md) | Restricting export actions by user |
