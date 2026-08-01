<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TrashedFilter;
use Workbench\App\Models\Task;

/** A soft-deleting stand-in: no workbench model uses the trait. */
function softDeletingQuery(): Builder
{
    $model = new class extends Model
    {
        use SoftDeletes;

        protected $table = 'tasks';
    };

    return $model->newQuery();
}

it('can be created', function () {
    expect(TrashedFilter::make('trashed'))->toBeInstanceOf(TrashedFilter::class);
});

// ─── State normalisation ────────────────────────────────────────────────────

it('recognises only the two explicit scopes', function () {
    $filter = TrashedFilter::make('trashed');

    expect($filter->normalizeValue('with'))->toBe('with')
        ->and($filter->normalizeValue('only'))->toBe('only')
        // Anything else means "live records only" — the filter is inactive.
        ->and($filter->normalizeValue(null))->toBeNull()
        ->and($filter->normalizeValue(''))->toBeNull()
        ->and($filter->normalizeValue('nonsense'))->toBeNull()
        ->and($filter->normalizeValue(true))->toBeNull();
});

// ─── Query application ──────────────────────────────────────────────────────

it('leaves the default scope alone when inactive', function () {
    $query = softDeletingQuery();

    expect(TrashedFilter::make('trashed')->apply($query, null))->toBe($query)
        ->and(TrashedFilter::make('trashed')->apply($query, ''))->toBe($query);

    // Untouched means the soft-delete scope still constrains the query.
    expect($query->toSql())->toContain('deleted_at');
});

it('drops the soft-delete constraint for "with"', function () {
    $sql = TrashedFilter::make('trashed')->apply(softDeletingQuery(), 'with')->toSql();

    expect($sql)->not->toContain('deleted_at');
});

it('keeps only trashed records for "only"', function () {
    $sql = TrashedFilter::make('trashed')->apply(softDeletingQuery(), 'only')->toSql();

    expect($sql)->toContain('deleted_at')->toContain('is not null');
});

it('never touches the query planner', function () {
    expect(TrashedFilter::make('trashed')->bypassesPlanner())->toBeTrue();
});

it('routes through a query callback when one is set', function () {
    $seen = null;

    $filter = TrashedFilter::make('trashed')
        ->query(function ($query, $value) use (&$seen) {
            $seen = $value;

            return $query->whereNotNull('deleted_at');
        });

    $sql = $filter->apply(softDeletingQuery(), 'only')->toSql();

    expect($seen)->toBe('only')->and($sql)->toContain('deleted_at');
});

// ─── Misconfiguration ───────────────────────────────────────────────────────

// Without this the failure surfaces as "Call to undefined method ::onlyTrashed()"
// from deep inside the query builder, naming neither the filter nor the model.
it('says so when the model has no soft deletes', function () {
    TrashedFilter::make('trashed')->apply(Task::query(), 'only');
})->throws(TableConfigurationException::class, 'does not use the SoftDeletes trait');

it('does not check the model while inactive', function () {
    $query = Task::query();

    expect(TrashedFilter::make('trashed')->apply($query, null))->toBe($query);
});

// ─── Surface ────────────────────────────────────────────────────────────────

it('offers the two scopes as options, with "without" as the placeholder', function () {
    $filter = TrashedFilter::make('trashed');

    expect($filter->getOptions())->toBe([
        'with' => 'With deleted',
        'only' => 'Only deleted',
    ])->and($filter->isSelectLike())->toBeTrue()
        ->and($filter->getWithoutTrashedLabel())->toBe('Without deleted');
});

it('takes custom option labels', function () {
    $filter = TrashedFilter::make('trashed')
        ->withTrashedLabel('Vše včetně archivu')
        ->onlyTrashedLabel('Jen archiv');

    expect($filter->getOptions())->toBe([
        'with' => 'Vše včetně archivu',
        'only' => 'Jen archiv',
    ]);
});

it('names the active scope in its indicator chip', function () {
    $filter = TrashedFilter::make('trashed')->label('Records');

    expect($filter->getIndicator('only'))->toBe('Records: Only deleted')
        ->and($filter->getIndicator('with'))->toBe('Records: With deleted')
        // Inactive (and unrecognised) states raise no chip at all.
        ->and($filter->getIndicator(null))->toBeNull()
        ->and($filter->getIndicator('nonsense'))->toBeNull();
});

it('renders through the shared select surfaces', function () {
    $filter = TrashedFilter::make('trashed');

    expect($filter->inlineView())->toBe('tables.columns.partials.filter-select')
        ->and($filter->getFormFields()[0]->getOptions())->toBe($filter->getOptions());
});

// The panel view calls isSearchable() and getPlaceholder() on whatever filter it
// is handed. Extending Filter directly rendered a 500 that no unit test saw,
// because the unit tests only asked for the view *name*.
it('renders its own panel view without fataling', function () {
    $html = TrashedFilter::make('trashed')->label('Records')->render();

    expect($html)->toContain('With deleted')
        ->toContain('Only deleted')
        ->toContain('Without deleted');
});

it('is a select filter, so it inherits the whole select surface', function () {
    $filter = TrashedFilter::make('trashed');

    expect($filter)->toBeInstanceOf(SelectFilter::class)
        ->and($filter->isSearchable())->toBeFalse()
        ->and($filter->getPlaceholder())->toBe('Without deleted')
        // An explicit placeholder still wins over the default.
        ->and(TrashedFilter::make('t')->placeholder('Scope')->getPlaceholder())->toBe('Scope');
});

// Inherited from SelectFilter, options() would have accepted a value the filter
// can never apply — it switches a scope rather than matching values.
it('refuses options it could never apply', function () {
    TrashedFilter::make('trashed')->options(['a' => 'A']);
})->throws(TableConfigurationException::class, 'cannot be set');
