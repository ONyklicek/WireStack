<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Tests\Unit\Filters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

class FoContact extends Model
{
    protected $table = 'fo_contacts';

    protected $guarded = [];

    public function notes(): HasMany
    {
        return $this->hasMany(FoNote::class, 'contact_id');
    }
}

class FoNote extends Model
{
    protected $table = 'fo_notes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('fo_contacts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('role')->nullable();
        $table->integer('score')->nullable();
        $table->timestamps();
    });
    Schema::create('fo_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('contact_id');
        $table->string('body');
        $table->timestamps();
    });

    FoContact::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@gmail.com', 'role' => 'admin', 'score' => 10]);
    FoContact::create(['id' => 2, 'name' => 'Bo', 'email' => 'bo@example.com', 'role' => 'editor', 'score' => 5]);
    FoContact::create(['id' => 3, 'name' => 'Cy', 'email' => null, 'role' => null, 'score' => null]);
    FoContact::create(['id' => 4, 'name' => 'Di', 'email' => '', 'role' => 'viewer', 'score' => 7]);

    FoNote::create(['contact_id' => 1, 'body' => 'urgent call']);
    FoNote::create(['contact_id' => 1, 'body' => 'follow up']);
    FoNote::create(['contact_id' => 2, 'body' => 'follow up']);
});

afterEach(function () {
    Schema::dropIfExists('fo_notes');
    Schema::dropIfExists('fo_contacts');
});

/** Names left by the panel filter at the given state. */
function foPanel(Filter $filter, mixed $state): array
{
    $table = Table::make()->model(FoContact::class)->filters([$filter]);

    // Nested the way the live binding writes a dotted (relation) name.
    $values = [];
    Arr::set($values, $filter->getName(), $state);

    return (new TableQueryService)->buildQuery(
        baseQuery: FoContact::query(),
        table: $table,
        filterValues: $values,
    )->orderBy('id')->pluck('name')->all();
}

/** Names left by a header filter on `$column` at the given state. */
function foColumn(TextColumn $column, mixed $state): array
{
    $table = Table::make()->model(FoContact::class)->columns([$column]);

    return (new TableQueryService)->buildQuery(
        baseQuery: FoContact::query(),
        table: $table,
        columnFilterValues: [$column->getName() => $state],
    )->orderBy('id')->pluck('name')->all();
}

// ─── The enum ────────────────────────────────────────────────────────────

it('resolves the operator spellings the text filter always accepted', function () {
    expect(FilterOperator::resolve('like'))->toBe(FilterOperator::Contains)
        ->and(FilterOperator::resolve('='))->toBe(FilterOperator::Equals)
        ->and(FilterOperator::resolve('<>'))->toBe(FilterOperator::NotEquals)
        ->and(FilterOperator::resolve('>='))->toBe(FilterOperator::GreaterThanOrEqual)
        ->and(FilterOperator::resolve('<'))->toBe(FilterOperator::LessThan)
        ->and(FilterOperator::resolve('<='))->toBe(FilterOperator::LessThanOrEqual)
        ->and(FilterOperator::resolve('>'))->toBe(FilterOperator::GreaterThan)
        ->and(FilterOperator::resolve('not_in'))->toBe(FilterOperator::NotIn)
        ->and(FilterOperator::resolve(FilterOperator::In))->toBe(FilterOperator::In)
        ->and(FilterOperator::resolve(null))->toBeNull()
        ->and(FilterOperator::resolve('nonsense'))->toBeNull();
});

it('labels every operator in both languages', function () {
    foreach (FilterOperator::cases() as $operator) {
        expect($operator->label())->not->toStartWith('wire-table::');
    }

    app()->setLocale('cs');
    expect(FilterOperator::NotContains->label())->toBe('neobsahuje');
    app()->setLocale('en');
});

it('knows which operators need a value and which exclude', function () {
    expect(FilterOperator::IsEmpty->requiresValue())->toBeFalse()
        ->and(FilterOperator::Contains->requiresValue())->toBeTrue()
        ->and(FilterOperator::NotIn->isNegative())->toBeTrue()
        ->and(FilterOperator::NotIn->positive())->toBe(FilterOperator::In)
        ->and(FilterOperator::NotEquals->positive())->toBe(FilterOperator::Equals)
        ->and(FilterOperator::NotContains->positive())->toBe(FilterOperator::Contains)
        ->and(FilterOperator::Equals->positive())->toBe(FilterOperator::Equals)
        ->and(FilterOperator::forNumber())->toContain(FilterOperator::GreaterThanOrEqual);
});

it('maps to a single planner clause only where one clause says it', function () {
    expect(FilterOperator::Contains->toPlannerClause('x'))->toBe(['LIKE', '%x%'])
        ->and(FilterOperator::StartsWith->toPlannerClause('x'))->toBe(['LIKE', 'x%'])
        ->and(FilterOperator::EndsWith->toPlannerClause('x'))->toBe(['LIKE', '%x'])
        ->and(FilterOperator::Contains->toPlannerClause('50%_!'))->toBe(['LIKE', '%50!%!_!!%'])
        ->and(FilterOperator::Equals->toPlannerClause('x'))->toBe(['=', 'x'])
        ->and(FilterOperator::GreaterThan->toPlannerClause(3))->toBe(['>', 3])
        ->and(FilterOperator::GreaterThanOrEqual->toPlannerClause(3))->toBe(['>=', 3])
        ->and(FilterOperator::LessThan->toPlannerClause(3))->toBe(['<', 3])
        ->and(FilterOperator::LessThanOrEqual->toPlannerClause(3))->toBe(['<=', 3])
        ->and(FilterOperator::In->toPlannerClause(['a']))->toBe(['IN', ['a']])
        ->and(FilterOperator::IsNotEmpty->toPlannerClause(null))->toBe(['IS NOT NULL', null])
        ->and(FilterOperator::NotContains->toPlannerClause('x'))->toBeNull()
        ->and(FilterOperator::IsEmpty->toPlannerClause(null))->toBeNull()
        ->and(FilterOperator::Contains->toPlannerClause(['x']))->toBeNull();
});

it('compares numbers both ways through constrain()', function () {
    $names = fn (FilterOperator $op, mixed $value) => tap(FoContact::query(), fn ($q) => $op->constrain($q, 'score', $value))->orderBy('id')->pluck('name')->all();

    expect($names(FilterOperator::GreaterThan, 5))->toBe(['Ada', 'Di'])
        ->and($names(FilterOperator::GreaterThanOrEqual, 7))->toBe(['Ada', 'Di'])
        ->and($names(FilterOperator::LessThan, 7))->toBe(['Bo'])
        ->and($names(FilterOperator::LessThanOrEqual, 7))->toBe(['Bo', 'Di'])
        ->and($names(FilterOperator::StartsWith, 1))->toBe(['Ada'])
        ->and($names(FilterOperator::EndsWith, 5))->toBe(['Bo'])
        ->and($names(FilterOperator::IsEmpty, null))->toBe(['Cy'])
        ->and($names(FilterOperator::IsNotEmpty, null))->toBe(['Ada', 'Bo', 'Di']);
});

// ─── TextFilter with a picker ────────────────────────────────────────────

it('offers only what the filter type supports', function () {
    $filter = TextFilter::make('email')->operators(['contains', 'in', FilterOperator::IsEmpty]);

    expect($filter->getOperators())->toBe([FilterOperator::Contains, FilterOperator::IsEmpty])
        ->and(TextFilter::make('email')->operators()->getOperators())->toBe(FilterOperator::forText())
        ->and(TextFilter::make('email')->operators()->operators(false)->hasOperatorPicker())->toBeFalse()
        ->and(Filter::make('x')->operators()->hasOperatorPicker())->toBeFalse();
});

it('applies every text operator, keeping NULLs for the negatives and matching blank as empty', function (string $operator, ?string $value, array $expected) {
    $filter = TextFilter::make('email')->operators();

    expect(foPanel($filter, ['value' => $value, 'operator' => $operator]))->toBe($expected);
})->with([
    'contains' => ['contains', 'gmail', ['Ada']],
    'does not contain' => ['not_contains', 'gmail', ['Bo', 'Cy', 'Di']],
    'equals' => ['equals', 'bo@example.com', ['Bo']],
    'does not equal' => ['not_equals', 'bo@example.com', ['Ada', 'Cy', 'Di']],
    'starts with' => ['starts_with', 'ada', ['Ada']],
    'ends with' => ['ends_with', '.com', ['Ada', 'Bo']],
    'is empty (no value needed)' => ['is_empty', null, ['Cy', 'Di']],
    'is not empty' => ['is_not_empty', null, ['Ada', 'Bo']],
]);

it('matches % and _ literally, in the planner and in constrain() alike', function (string $operator, string $value, array $expected) {
    FoContact::create(['id' => 5, 'name' => 'Pct', 'email' => '50%off@shop.cz', 'role' => 'a_b', 'score' => 1]);
    FoContact::create(['id' => 6, 'name' => 'Und', 'email' => '50xoff@shop.cz', 'role' => 'axb', 'score' => 1]);

    // email goes through the planner clause; role (with ->query() absent but a
    // negative operator) through constrain().
    expect(foPanel(TextFilter::make('email')->operators(), ['value' => $value, 'operator' => $operator]))->toBe($expected);
})->with([
    'contains a per cent' => ['contains', '50%', ['Pct']],
    'starts with a per cent' => ['starts_with', '50%', ['Pct']],
    'ends with an underscore-free tail' => ['ends_with', '%off@shop.cz', ['Pct']],
    'does not contain a per cent' => ['not_contains', '50%', ['Ada', 'Bo', 'Cy', 'Di', 'Und']],
]);

it('matches an underscore literally through constrain()', function () {
    FoContact::create(['id' => 5, 'name' => 'Pct', 'email' => null, 'role' => 'a_b', 'score' => 1]);
    FoContact::create(['id' => 6, 'name' => 'Und', 'email' => null, 'role' => 'axb', 'score' => 1]);

    $query = FoContact::query();
    FilterOperator::Contains->constrain($query, 'role', 'a_b');

    expect($query->pluck('name')->all())->toBe(['Pct'])
        ->and(foPanel(TextFilter::make('role')->operators(), ['value' => 'A_B', 'operator' => 'contains']))->toBe(['Pct']);
});

it('falls back to the default for an operator the filter does not offer', function () {
    $filter = TextFilter::make('email')->operators(['contains', 'not_contains']);

    expect(foPanel($filter, ['value' => 'gmail', 'operator' => 'is_empty']))->toBe(['Ada'])
        ->and($filter->operatorFromState('nope'))->toBe(FilterOperator::Contains);
});

it('starts on the developer-chosen operator, or the first offered when that one is not', function () {
    expect(TextFilter::make('e')->operator('starts_with')->operators()->getDefaultOperator())->toBe(FilterOperator::StartsWith)
        ->and(TextFilter::make('e')->operators(['ends_with', 'equals'])->getDefaultOperator())->toBe(FilterOperator::EndsWith)
        ->and(TextFilter::make('e')->operators()->defaultOperator('not_contains')->getDefaultOperator())->toBe(FilterOperator::NotContains)
        ->and(Filter::make('x')->getDefaultOperator())->toBe(FilterOperator::Equals)
        ->and(Filter::make('x')->multiple()->getDefaultOperator())->toBe(FilterOperator::In);
});

it('says how it compares on the indicator chip', function () {
    $filter = TextFilter::make('email')->operators();

    expect($filter->getIndicator(['value' => 'gmail', 'operator' => 'not_contains']))->toBe('Email: does not contain gmail')
        ->and($filter->getIndicator(['value' => null, 'operator' => 'is_empty']))->toBe('Email: is empty')
        ->and($filter->getIndicator(['value' => '', 'operator' => 'contains']))->toBeNull()
        ->and(TextFilter::make('email')->getIndicator(['value' => 'gmail']))->toBe('Email: gmail');
});

it('seeds the operator beside the value and persists it in the URL', function () {
    $filter = TextFilter::make('email')->operators();

    expect($filter->wrapValue(null))->toBe(['value' => null, 'operator' => 'contains'])
        ->and($filter->wrapValue(['value' => 'x', 'operator' => 'equals']))->toBe(['value' => 'x', 'operator' => 'equals'])
        ->and($filter->getQueryStringFields())->toBe(['value' => '', 'operator' => '_op'])
        ->and(TextFilter::make('email')->getQueryStringFields())->toBe(['value' => '']);
});

it('leaves the stored filter alone: forState() hands back a copy', function () {
    $filter = TextFilter::make('email')->operators();
    $copy = $filter->forState(['operator' => 'not_contains']);

    expect($copy)->not->toBe($filter)
        ->and($copy->getActiveOperator())->toBe(FilterOperator::NotContains)
        ->and($filter->getActiveOperator())->toBe(FilterOperator::Contains)
        ->and(TextFilter::make('email')->forState(['operator' => 'x']))->toBeInstanceOf(TextFilter::class);
});

// ─── SelectFilter with a picker ──────────────────────────────────────────

it('applies is any of / is none of / emptiness on a select', function (string $operator, mixed $value, array $expected) {
    $filter = SelectFilter::make('role')->multiple()->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])->operators();

    expect(foPanel($filter, ['value' => $value, 'operator' => $operator]))->toBe($expected);
})->with([
    'is any of' => ['in', ['admin', 'viewer'], ['Ada', 'Di']],
    'is none of keeps NULL' => ['not_in', ['admin'], ['Bo', 'Cy', 'Di']],
    'is empty' => ['is_empty', [], ['Cy']],
    'is not empty' => ['is_not_empty', [], ['Ada', 'Bo', 'Di']],
]);

it('plans "is any of" as one IN clause and leaves the rest to apply()', function () {
    $filter = SelectFilter::make('role')->multiple()->operators();

    expect($filter->forState(['operator' => 'in'])->toPlannerDefinitions(['a'])[0]->operator)->toBe('in')
        ->and($filter->forState(['operator' => 'not_in'])->toPlannerDefinitions(['a']))->toBe([])
        ->and($filter->forState(['operator' => 'in'])->toPlannerDefinitions([]))->toBe([])
        ->and(SelectFilter::make('role')->toPlannerDefinitions('a')[0]->operator)->toBe('=');
});

// ─── Relations: a negative means "no related record matches" ─────────────

it('excludes across a relation with whereDoesntHave, not whereHas(NOT …)', function () {
    $filter = TextFilter::make('notes.body')->operators();

    expect(foPanel($filter, ['value' => 'urgent', 'operator' => 'not_contains']))->toBe(['Bo', 'Cy', 'Di'])
        ->and(foPanel($filter, ['value' => 'follow', 'operator' => 'contains']))->toBe(['Ada', 'Bo'])
        ->and(foPanel($filter, ['value' => null, 'operator' => 'is_empty']))->toBe(['Cy', 'Di']);
});

// ─── Header (column) filters ─────────────────────────────────────────────

it('takes {value, operator} as the header state once the column offers operators', function () {
    $column = TextColumn::make('email')->filterable()->filterOperators();

    expect($column->initialFilterState())->toBe(['value' => null, 'operator' => 'contains'])
        ->and(foColumn($column, ['value' => 'gmail', 'operator' => 'not_contains']))->toBe(['Bo', 'Cy', 'Di'])
        ->and(foColumn($column, ['value' => 'gmail', 'operator' => 'contains']))->toBe(['Ada'])
        ->and(foColumn($column, ['value' => null, 'operator' => 'is_not_empty']))->toBe(['Ada', 'Bo'])
        ->and(foColumn($column, ['value' => '', 'operator' => 'contains']))->toBe(['Ada', 'Bo', 'Cy', 'Di']);
});

it('keeps the plain scalar header state for a column without operators', function () {
    $column = TextColumn::make('email')->filterable();

    expect($column->initialFilterState())->toBeNull()
        ->and(foColumn($column, 'gmail'))->toBe(['Ada']);
});
