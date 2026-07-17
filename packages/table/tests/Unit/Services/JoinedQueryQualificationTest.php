<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

/**
 * A relation-scoped table over a belongs-to-many joins the pivot table; when
 * the pivot carries a column colliding with the related table (here `name`),
 * every search/sort/filter clause must stay qualified or the SQL is ambiguous.
 */
class JqUser extends Model
{
    protected $table = 'jq_users';

    protected $guarded = [];

    public $timestamps = false;

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(JqGroup::class, 'jq_group_user', 'user_id', 'group_id');
    }
}

class JqGroup extends Model
{
    protected $table = 'jq_groups';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('jq_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('jq_groups', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('jq_group_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('group_id');
        $table->string('name')->nullable();
    });

    $user = JqUser::create(['name' => 'U']);
    $alpha = JqGroup::create(['name' => 'Alpha']);
    $beta = JqGroup::create(['name' => 'Beta']);
    $user->groups()->attach($alpha->id, ['name' => 'PIVOT 1']);
    $user->groups()->attach($beta->id, ['name' => 'PIVOT 2']);
    $this->user = $user;
});

afterEach(function () {
    Schema::dropIfExists('jq_group_user');
    Schema::dropIfExists('jq_groups');
    Schema::dropIfExists('jq_users');
});

function jqBuildQuery(array $args = [])
{
    $base = test()->user->groups()->getQuery()->select('jq_groups.*');

    $table = Table::make()
        ->query($base)
        ->columns([
            TextColumn::make('name')->searchable()->sortable()->filterable(),
        ]);

    return (new TableQueryService)->buildQuery(
        baseQuery: $table->getQuery(),
        table: $table,
        search: $args['search'] ?? '',
        filterValues: [],
        sortColumn: $args['sort'] ?? null,
        sortDirection: 'asc',
        columnFilterValues: $args['columnFilters'] ?? [],
    );
}

it('sorts by a colliding column over the joined query', function () {
    expect(jqBuildQuery(['sort' => 'name'])->pluck('jq_groups.name')->all())
        ->toBe(['Alpha', 'Beta']);
});

it('searches a colliding column over the joined query', function () {
    expect(jqBuildQuery(['search' => 'Alp'])->pluck('jq_groups.name')->all())
        ->toBe(['Alpha']);
});

it('column-filters a colliding column over the joined query (regression: applyFilter emitted an unqualified column)', function () {
    expect(jqBuildQuery(['columnFilters' => ['name' => 'Beta']])->pluck('jq_groups.name')->all())
        ->toBe(['Beta']);
});
