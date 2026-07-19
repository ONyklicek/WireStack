<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Column::loadRelations() — the audit's 6a fix (render-optimization-audit-2026-07-17.md).
 *
 * A relation dereferenced ONLY inside a closure (displayUsing/url/color) has no column
 * path, so the query planner cannot discover it and it lazy-loads once per row — the
 * single largest real-world N+1. `->loadRelations('company')` adds it to the eager set,
 * flattening the query count regardless of row count.
 */
class LrCompany extends Model
{
    protected $table = 'lr_companies';

    protected $guarded = [];
}

class LrUser extends Model
{
    protected $table = 'lr_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(LrCompany::class, 'company_id');
    }
}

class LrHintedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table->model(LrUser::class)->paginated(false)->columns([
            TextColumn::make('name'),
            TextColumn::make('company_label')
                ->displayUsing(fn ($state, $record) => $record->company?->name)
                ->loadRelations('company'),
        ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class LrUnhintedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table->model(LrUser::class)->paginated(false)->columns([
            TextColumn::make('name'),
            TextColumn::make('company_label')
                ->displayUsing(fn ($state, $record) => $record->company?->name),
        ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function lrSeed(int $rows): void
{
    if (LrCompany::count() === 0) {
        foreach (range(1, 5) as $c) {
            LrCompany::create(['name' => "Company $c"]);
        }
    }
    $now = now();
    LrUser::insert(array_map(fn ($i) => [
        'name' => "User $i", 'company_id' => ($i % 5) + 1,
        'created_at' => $now, 'updated_at' => $now,
    ], range(1, $rows)));
}

function lrQueryCount(string $component): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test($component)->html();
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
}

beforeEach(function () {
    Schema::create('lr_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('lr_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->foreignId('company_id');
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('lr_users');
    Schema::dropIfExists('lr_companies');
});

it('loadRelations() keeps the query count flat as rows grow (no N+1)', function () {
    lrSeed(30);

    // Eager-loaded: the relation is fetched in one extra query regardless of rows.
    $q = lrQueryCount(LrHintedComponent::class);

    expect($q)->toBeLessThanOrEqual(3); // records + companies (+ maybe a count)
});

it('without the hint the closure relation lazy-loads once per row (the N+1 it fixes)', function () {
    lrSeed(30);

    $hinted = lrQueryCount(LrHintedComponent::class);
    $unhinted = lrQueryCount(LrUnhintedComponent::class);

    // The un-hinted table pays ~30 extra lazy company loads; the hint removes them.
    expect($unhinted)->toBeGreaterThan($hinted + 20);
});
