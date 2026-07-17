<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Metadata\AccessorMetadata;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Services\TableQueryService;

// ─── Test Models (no schema needed — we never hit the DB here) ───────────────

class BpcUser extends Model
{
    protected $table = 'bpc_users';

    protected $guarded = [];
}

class BpcCompany extends Model
{
    protected $table = 'bpc_companies';

    protected $guarded = [];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Invoke buildPlannerColumns() against a pre-seeded registry.
 *
 * @param  array<int, Column>  $columns
 * @return array<int, Column>
 */
function invokeBuildPlannerColumns(MetadataRegistry $registry, string $modelClass, array $columns): array
{
    $service = new TableQueryService;

    $reflection = new ReflectionClass($service);

    $reflection->getProperty('registry')->setValue($service, $registry);
    $reflection->getProperty('currentModelClass')->setValue($service, $modelClass);

    $method = $reflection->getMethod('buildPlannerColumns');

    return $method->invoke($service, $columns);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('resolves capabilities through a relation chain', function () {
    $registry = new MetadataRegistry;
    $registry->registerModel(BpcUser::class);
    $registry->registerModel(BpcCompany::class);
    $registry->registerRelation(BpcUser::class, new RelationMetadata(
        name: 'company',
        type: 'BelongsTo',
        parentModel: BpcUser::class,
        relatedModel: BpcCompany::class,
        foreignKey: 'company_id',
        localKey: 'id',
        morphType: null,
        pivotTable: null,
        isMorph: false,
        isToMany: false,
    ));
    $registry->registerColumn(BpcCompany::class, ColumnMetadata::forDatabaseColumn('name'));

    $columns = invokeBuildPlannerColumns($registry, BpcUser::class, [
        Column::make('company.name'),
    ]);

    $capabilities = $columns[0]->getCapabilities();

    expect($capabilities->isSearchable())->toBeTrue()
        ->and($capabilities->isSortable())->toBeTrue()
        ->and($capabilities->isFilterable())->toBeTrue();
});

it('leaves capabilities untouched when a relation is unknown', function () {
    $registry = new MetadataRegistry;
    $registry->registerModel(BpcUser::class);
    // Note: no relation registered for "company"

    $column = Column::make('company.name');
    $before = $column->getCapabilities()->all();

    $columns = invokeBuildPlannerColumns($registry, BpcUser::class, [$column]);

    expect($columns[0]->getCapabilities()->all())->toBe($before);
});

it('marks runtime-only accessors with the RuntimeOnly capability', function () {
    $registry = new MetadataRegistry;
    $registry->registerModel(BpcUser::class);
    $registry->registerAccessor(BpcUser::class, AccessorMetadata::runtimeOnly('full_name'));

    $columns = invokeBuildPlannerColumns($registry, BpcUser::class, [
        Column::make('full_name'),
    ]);

    expect($columns[0]->getCapabilities()->has(Capability::RuntimeOnly))->toBeTrue()
        ->and($columns[0]->getCapabilities()->isSearchable())->toBeFalse()
        ->and($columns[0]->getCapabilities()->isSortable())->toBeFalse();
});

it('grants SQL capabilities to accessors backed by an expression', function () {
    $registry = new MetadataRegistry;
    $registry->registerModel(BpcUser::class);
    $registry->registerAccessor(
        BpcUser::class,
        AccessorMetadata::withSqlExpression('full_name', "first_name || ' ' || last_name"),
    );

    $columns = invokeBuildPlannerColumns($registry, BpcUser::class, [
        Column::make('full_name'),
    ]);

    $capabilities = $columns[0]->getCapabilities();

    expect($capabilities->hasSqlExpression())->toBeTrue()
        ->and($capabilities->isSearchable())->toBeTrue()
        ->and($capabilities->isSortable())->toBeTrue();
});

it('skips capability resolution for aggregate columns', function () {
    $registry = new MetadataRegistry;
    $registry->registerModel(BpcUser::class);

    $column = Column::make('orders->count()');
    $before = $column->getCapabilities()->all();

    $columns = invokeBuildPlannerColumns($registry, BpcUser::class, [$column]);

    expect($columns[0]->getCapabilities()->all())->toBe($before);
});
