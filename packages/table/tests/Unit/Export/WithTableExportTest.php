<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Export\ExportAction;
use NyonCode\WireTable\Export\TableExport;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WithTableExportUser extends Model
{
    protected $table = 'with_table_export_users';

    protected $guarded = [];
}

class WithTableExportComponent extends Component
{
    use WithTable;

    public ?string $capturedExportSql = null;

    public array $capturedExportBindings = [];

    public function table(Table $table): Table
    {
        return $table
            ->model(WithTableExportUser::class)
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('secret')->label('Secret'),
            ])
            ->filters([
                Filter::make('active'),
            ])
            ->headerActions([
                ExportAction::makeExport()
                    ->exportConfig(
                        TableExport::make()
                            ->fileName('users')
                            ->modifyQueryUsing(function ($query) {
                                $this->capturedExportSql = $query->toSql();
                                $this->capturedExportBindings = $query->getBindings();

                                return $query;
                            })
                    ),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('with_table_export_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('secret');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    WithTableExportUser::create(['id' => 1, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'secret' => 'hidden-c', 'active' => true]);
    WithTableExportUser::create(['id' => 2, 'name' => 'Alice', 'email' => 'alice@example.com', 'secret' => 'hidden-a', 'active' => true]);
    WithTableExportUser::create(['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com', 'secret' => 'hidden-b', 'active' => false]);
});

afterEach(function () {
    Schema::dropIfExists('with_table_export_users');
});

it('exports the current filtered sorted table query using visible columns', function () {
    $component = new WithTableExportComponent;
    $component->tableSearch = 'example.com';
    $component->tableFilters = ['active' => true];
    $component->tableSortColumn = 'name';
    $component->tableSortDirection = 'desc';
    $component->hiddenColumns = ['secret'];

    $response = $component->exportTable();
    $output = captureWithTableExportResponse($response);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Disposition'))->toContain('users.csv')
        ->and($component->capturedExportSql)->toContain('order by "with_table_export_users"."name" desc')
        ->and(array_map(fn ($binding) => (int) $binding, $component->capturedExportBindings))->toContain(1)
        ->and($output)->toContain('Name')
        ->and($output)->toContain('Email')
        ->and($output)->toContain('Alice')
        ->and($output)->toContain('Charlie')
        ->and($output)->not->toContain('Bob')
        ->and($output)->not->toContain('Secret')
        ->and($output)->not->toContain('hidden-a');
});

function captureWithTableExportResponse(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}
