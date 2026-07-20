<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\ImportColumn;
use NyonCode\WireTable\Import\TableImport;
use NyonCode\WireTable\Table;

class WithTableImportUser extends Model
{
    protected $table = 'with_table_import_users';

    protected $guarded = [];

    public $timestamps = false;
}

class WithTableImportComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WithTableImportUser::class)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('email'),
            ])
            ->headerActions([
                ImportAction::makeImport()->importConfig(
                    TableImport::make()
                        ->model(WithTableImportUser::class)
                        ->columns([
                            ImportColumn::make('name')->requiredMapping()->rules(['required']),
                            ImportColumn::make('email')->rules(['required', 'email']),
                        ])
                ),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class WithTableImportNoActionComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table->model(WithTableImportUser::class)->columns([TextColumn::make('name')]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class WithTableImportDeniedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WithTableImportUser::class)
            ->columns([TextColumn::make('name'), TextColumn::make('email')])
            ->headerActions([
                ImportAction::makeImport()
                    ->authorizeUsing(fn () => false)
                    ->importConfig(
                        TableImport::make()
                            ->model(WithTableImportUser::class)
                            ->columns([
                                ImportColumn::make('name')->requiredMapping()->rules(['required']),
                                ImportColumn::make('email')->rules(['required', 'email']),
                            ])
                    ),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

function withTableImportTempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'wire-hostimport-');
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    Schema::create('with_table_import_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('with_table_import_users');

    foreach (glob(sys_get_temp_dir().'/wire-hostimport-*') ?: [] as $path) {
        @unlink($path);
    }
});

it('imports a file using the ImportAction config declared on the table', function () {
    $path = withTableImportTempCsv("name,email\nJohn,john@example.com\nBad,not-an-email\n");

    $result = (new WithTableImportComponent)->importTable($path);

    expect($result->getImported())->toBe(1)
        ->and($result->getFailedCount())->toBe(1)
        ->and(WithTableImportUser::where('email', 'john@example.com')->exists())->toBeTrue();
});

it('refuses to import when the ImportAction denies authorization', function () {
    // Regression: importTable() is a public Livewire endpoint and ran the import
    // without checking the ImportAction's authorization, so a client could bypass
    // an ->authorize() guard and feed an arbitrary path to the importer.
    $path = withTableImportTempCsv("name,email\nJohn,john@example.com\n");

    $result = (new WithTableImportDeniedComponent)->importTable($path);

    expect($result->getImported())->toBe(0)
        ->and($result->getTotal())->toBe(0)
        ->and(WithTableImportUser::count())->toBe(0);
});

it('imports nothing when no ImportAction is configured and the file has no rows', function () {
    // No ImportAction → falls back to a default (empty) TableImport. With a
    // header-only file there are no rows to persist, so the run is a clean no-op.
    $path = withTableImportTempCsv("name\n");

    $result = (new WithTableImportNoActionComponent)->importTable($path);

    expect($result->getImported())->toBe(0)
        ->and(WithTableImportUser::count())->toBe(0);
});
