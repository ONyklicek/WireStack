---
order: 50
---

# Exporty tabulky

Wire Table umí exportovat aktuální dotaz tabulky jako CSV, Excel nebo PDF. Exporty používají aktuální hledání, filtry, řazení a viditelné sloupce.

## Základní tlačítka exportu

Přidejte tlačítka nebo položky menu, které volají `exportTable()` z Livewire komponenty používající `WithTable`.

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

Podporované hodnoty formátu:

| Hodnota | Typ souboru |
|-------|-----------|
| `csv` | CSV |
| `xlsx` | Excel |
| `pdf` | PDF |

## Konfigurace výchozích hodnot exportu

Použijte `ExportAction` v `headerActions()`, když chcete definovat konfiguraci exportu v definici tabulky.

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

Stahování stále probíhá přes `exportTable('csv')`, `exportTable('xlsx')` nebo `exportTable('pdf')`. První `ExportAction` na tabulce poskytuje výchozí nastavení exportu.

## Exportované sloupce

Ve výchozím stavu exporty zahrnují sloupce tabulky viditelné aktuálnímu uživateli. Sloupce skryté uživatelem se přeskočí.

Pro export vlastní sady sloupců:

```php
TableExport::make()
    ->columns([
        TextColumn::make('name')->label('Name'),
        TextColumn::make('email')->label('Email'),
    ]);
```

Popisky sloupců se použijí jako hlavičky, když jsou hlavičky zapnuté.

## Exportovaný dotaz

`exportTable()` vychází z aktuálního filtrovaného a seřazeného dotazu tabulky, bez stránkování.

Pro přidání omezení jen pro export:

```php
TableExport::make()
    ->fileName('active-users')
    ->modifyQueryUsing(fn ($query) => $query->where('active', true));
```

Pro export úplně samostatného dotazu použijte `TableExport` přímo:

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

## Exportované souhrny

Sloupce se [souhrny v rozsahu `query`](summaries.md) připojí své součty za datové
řádky — stejné celkové součty, jaké patička zobrazuje pro celou filtrovanou
sadu, v každém formátu (CSV, Excel, PDF). Buňky se vykreslí jako `Label: hodnota`
ve sloupci, kam patří; sloupec s několika souhrny vyprodukuje několik řádků:

```text
Number,Total
ORD-1,100
ORD-2,250
,"Grand total: 350 Kč"
,"Average: 175 Kč"
```

Souhrny v rozsahu `page`/`selection` popisují přechodný stav UI a nikdy se
neexportují. Pro export holých dat bez součtů:

```php
TableExport::make()
    ->withSummaries(false);
```

Rollup sloupce (`->sums()`, `->counts()`, …) exportují své hodnoty per řádek
i celkové součty. Při exportu **vlastního dotazu** s rollup sloupci musí dotaz
obsahovat odpovídající `withSum`/`withCount` — stejný požadavek jako u samotné
tabulky. Celkové součty podřádků a [mezisoučty skupin](grouping.md) jsou jen
v patičce a do exportů se nezahrnují.

## Volby CSV

```php
TableExport::make()
    ->fileName('users')
    ->delimiter(';')
    ->enclosure('"')
    ->withHeadings();
```

Pro odstranění řádku hlaviček:

```php
TableExport::make()
    ->withHeadings(false);
```

## Excel export

Excel export používá formát `xlsx`.

```blade
<button type="button" wire:click="exportTable('xlsx')">
    Export Excel
</button>
```

Nainstalujte OpenSpout, když vaše aplikace potřebuje skutečné XLSX soubory:

```bash
composer require openspout/openspout
```

Pokud OpenSpout není nainstalován, Wire spadne zpět na CSV výstup.

## PDF export

PDF export používá formát `pdf`.

```php
TableExport::make()
    ->fileName('users')
    ->orientation('landscape')
    ->paperSize('A4')
    ->pdfView('exports.users');
```

Nainstalujte Laravel DomPDF, když vaše aplikace potřebuje PDF soubory:

```bash
composer require barryvdh/laravel-dompdf
```

Pokud DomPDF není nainstalován, Wire spadne zpět na CSV výstup.

## Data PDF pohledu

Při použití vlastního PDF pohledu ho navrhněte jako běžnou Blade export šablonu. Exportér předá pohledu `headings`, `rows`, `columns` a `summaryRows` (předformátované řádky součtů, prázdné když jsou souhrny vypnuté).

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

## Export na frontě

Download je response a job žádnou nevrací. Export na frontě je proto jiné
**doručení**, ne totéž přesunuté jinam: zapíše soubor na disk a uživateli řekne,
kde je.

```php
public function exportInBackground(): void
{
    $this->queueTableExport('csv', 's3', 'exports/orders'); // [tl! focus]
}
```

Uživatel dostane „export se připravuje" hned a druhou notifikaci se jménem
souboru, až worker doběhne — přesně proto existuje
[databázový driver notifikací](../core/notifications.md): než velký export
skončí, není už kam blikat, žádný request nezbyl.

**Stav cestuje s jobem.** Bez toho by worker namountoval čerstvou komponentu a
vyexportoval celou tabulku, takže kdo si ji vyfiltroval na dvacet řádků, dostane
všech deset tisíc — v souboru natolik věrohodném, že to nikdo nezkontroluje.

```php
$this->queueTableExport(
    format: 'xlsx',
    disk: 's3',              // null použije filesystems.default
    directory: 'exports',    // [tl! focus]
);
```

Job veze **třídu komponenty a formát**, nikdy dotaz: dotaz jsou closury a
builder a ani jedno serializaci nepřežije. Hostitel se postaví znovu a je
požádán o svůj filtrovaný dotaz, takže soubor odpovídá datům v okamžiku běhu
jobu, ne v okamžiku kliknutí.

Každý exportér zapisuje jednou metodou, `writeTo(string $path, ...)`, kde
`php://output` je cesta jako každá jiná — download a soubor na disku jsou proto
tytéž řádky, tytéž sloupce a tytéž souhrny. Vlastní exportér ji musí
implementovat:

```php
class JsonExporter implements Exporter
{
    public function writeTo(string $path, Builder $query, array $columns, array $summaryRows = []): void // [tl! focus]
    {
        // ...
    }

    public function extension(): string // [tl! focus]
    {
        return 'json';
    }

    public function export(Builder $query, array $columns, string $fileName, array $summaryRows = []): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $this->writeTo('php://output', $query, $columns, $summaryRows), // [tl! focus]
            $fileName,
        );
    }
}
```

Exportér si příponu pojmenuje sám, protože formát není vždycky to, co se
opravdu zapíše: `ExcelExporter` bez PhpSpreadsheet degraduje na CSV a uložený
soubor jménem `.xlsx` s CSV uvnitř je lež, která vyplave až mnohem později, když
ho někdo konečně otevře. Download se přejmenovává ze stejného důvodu.

## Související dokumentace

| Dokument | Co pokrývá |
|----------|----------------|
| [Přehled tabulek](overview.md) | Nastavení a stav tabulky |
| [Sloupce](columns/index.md) | Popisky sloupců, viditelnost a formátování |
| [Filtry](filters/index.md) | Filtrované dotazy použité exportem |
| [Souhrny](summaries.md) | Součty připojené k exportům |
| [Autorizace](../authorization.md) | Omezení akcí exportu podle uživatele |
