<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Forms\Runtime\RelationshipSaveHandler;

beforeEach(function () {
    Schema::dropIfExists('rsh_children');
    Schema::dropIfExists('rsh_parents');

    Schema::create('rsh_parents', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('rsh_children', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('rsh_parent_id');
        $table->string('label');
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('rsh_children');
    Schema::dropIfExists('rsh_parents');
});

// ─── Tests ────────────────────────────────────────────────────────────

test('saves new children via repeater relationship', function () {
    $parent = createRshParent();
    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    $handler->save($parent, [$repeater], [
        'children' => [
            ['label' => 'Child A', 'sort_order' => 1],
            ['label' => 'Child B', 'sort_order' => 2],
        ],
    ]);

    $children = $parent->children()->get();
    expect($children)->toHaveCount(2)
        ->and($children[0]->label)->toBe('Child A')
        ->and($children[1]->label)->toBe('Child B');
});

test('updates existing children', function () {
    $parent = createRshParent();
    $child = $parent->children()->create(['label' => 'Old', 'sort_order' => 0]);

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    $handler->save($parent, [$repeater], [
        'children' => [
            ['id' => $child->id, 'label' => 'Updated', 'sort_order' => 5],
        ],
    ]);

    $child->refresh();
    expect($child->label)->toBe('Updated')
        ->and($child->sort_order)->toBe(5);
});

test('deletes removed children', function () {
    $parent = createRshParent();
    $child1 = $parent->children()->create(['label' => 'Keep', 'sort_order' => 0]);
    $child2 = $parent->children()->create(['label' => 'Remove', 'sort_order' => 1]);

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    $handler->save($parent, [$repeater], [
        'children' => [
            ['id' => $child1->id, 'label' => 'Keep', 'sort_order' => 0],
        ],
    ]);

    expect($parent->children()->count())->toBe(1);
});

test('handles mixed create update and delete', function () {
    $parent = createRshParent();
    $existing = $parent->children()->create(['label' => 'Existing', 'sort_order' => 0]);
    $parent->children()->create(['label' => 'ToDelete', 'sort_order' => 1]);

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    $handler->save($parent, [$repeater], [
        'children' => [
            ['id' => $existing->id, 'label' => 'Updated', 'sort_order' => 0],
            ['label' => 'New Child', 'sort_order' => 2],
        ],
    ]);

    $children = $parent->children()->orderBy('sort_order')->get();

    expect($children)->toHaveCount(2)
        ->and($children[0]->label)->toBe('Updated')
        ->and($children[1]->label)->toBe('New Child');
});

test('applies mutate callback before save', function () {
    $parent = createRshParent();

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')
        ->relationship('children')
        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => array_merge($data, ['sort_order' => 99]));

    $handler->save($parent, [$repeater], [
        'children' => [
            ['label' => 'Mutated'],
        ],
    ]);

    expect($parent->children()->first()->sort_order)->toBe(99);
});

test('skips repeater without relationship', function () {
    $parent = createRshParent();

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('items'); // no ->relationship()

    $handler->save($parent, [$repeater], [
        'items' => [['label' => 'foo']],
    ]);

    expect($parent->children()->count())->toBe(0);
});

test('skips non-existent relationship method', function () {
    $parent = createRshParent();

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('nonexistent')->relationship('nonexistent');

    $handler->save($parent, [$repeater], [
        'nonexistent' => [['label' => 'foo']],
    ]);

    expect(true)->toBeTrue();
});

test('handles empty items array by deleting all children', function () {
    $parent = createRshParent();
    $parent->children()->create(['label' => 'Will be deleted', 'sort_order' => 0]);

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    $handler->save($parent, [$repeater], [
        'children' => [],
    ]);

    expect($parent->children()->count())->toBe(0);
});

test('handles missing data key gracefully', function () {
    $parent = createRshParent();
    $parent->children()->create(['label' => 'Should remain', 'sort_order' => 0]);

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    $handler->save($parent, [$repeater], []);

    expect($parent->children()->count())->toBe(0);
});

test('delete fires model deleting and deleted events', function () {
    $parent = createRshParent();
    $child1 = $parent->children()->create(['label' => 'Keep', 'sort_order' => 0]);
    $parent->children()->create(['label' => 'Delete', 'sort_order' => 1]);

    $deletedIds = [];
    RshChildModel::deleting(function (RshChildModel $model) use (&$deletedIds) {
        $deletedIds[] = $model->getKey();
    });

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    $handler->save($parent, [$repeater], [
        'children' => [
            ['id' => $child1->id, 'label' => 'Keep', 'sort_order' => 0],
        ],
    ]);

    expect($deletedIds)->toHaveCount(1);
});

test('soft-deleted children are soft-deleted not hard-deleted', function () {
    Schema::dropIfExists('rsh_soft_children');
    Schema::create('rsh_soft_children', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('rsh_soft_parent_id');
        $table->string('label');
        $table->integer('sort_order')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });

    $parent = RshSoftParentModel::create(['name' => 'Soft Parent']);
    $toRemove = $parent->softChildren()->create(['label' => 'Remove', 'sort_order' => 0]);

    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('softChildren')->relationship('softChildren');

    $handler->save($parent, [$repeater], ['softChildren' => []]);

    // Record still exists in DB (soft-deleted, not hard-deleted)
    expect(RshSoftChildModel::withTrashed()->find($toRemove->id))->not->toBeNull()
        ->and(RshSoftChildModel::withTrashed()->find($toRemove->id)->trashed())->toBeTrue();

    Schema::dropIfExists('rsh_soft_children');
});

test('does not mass-assign a client-supplied primary key on create (regression)', function () {
    $parent = createRshParent();
    $handler = new RelationshipSaveHandler;
    $repeater = Repeater::make('children')->relationship('children');

    // A client-supplied id that does NOT match any existing child must not be
    // written as the new row's primary key; the row is created with a fresh id.
    $handler->save($parent, [$repeater], [
        'children' => [
            ['id' => 999, 'label' => 'Forged', 'sort_order' => 1],
        ],
    ]);

    $child = $parent->children()->first();
    expect($child)->not->toBeNull()
        ->and($child->id)->not->toBe(999)
        ->and($child->label)->toBe('Forged');
});

// ─── Helpers ──────────────────────────────────────────────────────────

function createRshParent(): Model
{
    return RshParentModel::create(['name' => 'Parent 1']);
}

test('syncs a belongsToMany repeater: applies the mutator and skips malformed rows', function () {
    Schema::create('rsh_tags', function (Blueprint $t) {
        $t->id();
        $t->string('label');
    });
    Schema::create('rsh_parent_tag', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('parent_id');
        $t->unsignedBigInteger('tag_id');
        $t->string('note')->nullable();
    });

    $tag = RshTag::create(['label' => 'x']);
    $parent = RshBtmParent::create(['name' => 'P']);

    // A spy mutator records every row it is handed. The order of the guards is
    // the contract: a non-array row is skipped BEFORE the mutator (so the spy
    // never sees it), while a row missing the related key is mutated first and
    // skipped AFTER.
    $seen = [];
    $repeater = Repeater::make('tags')
        ->relationship('tags')
        ->mutateRelationshipDataBeforeSaveUsing(function (array $d) use (&$seen) {
            $seen[] = $d;

            return array_merge($d, ['note' => 'mutated']);
        });

    (new RelationshipSaveHandler)->save($parent, [$repeater], [
        'tags' => [
            ['id' => $tag->id],           // valid: mutated, then synced
            'not-an-array',               // skipped before the mutator
            ['note' => 'no related key'], // mutated, then skipped (no related id)
        ],
    ]);

    // The mutator saw the two array rows, never the string, proving the
    // non-array guard runs first.
    expect($seen)->toHaveCount(2)
        ->and($seen[0])->toBe(['id' => $tag->id]);

    // Only the row that kept a related id was written to the pivot, carrying the
    // mutator's note.
    $pivot = DB::table('rsh_parent_tag')->get();
    expect($pivot)->toHaveCount(1)
        ->and((int) $pivot->first()->tag_id)->toBe($tag->id)
        ->and($pivot->first()->note)->toBe('mutated');

    Schema::dropIfExists('rsh_parent_tag');
    Schema::dropIfExists('rsh_tags');
});

class RshParentModel extends Model
{
    protected $table = 'rsh_parents';

    protected $guarded = [];

    public function children(): HasMany
    {
        return $this->hasMany(RshChildModel::class, 'rsh_parent_id');
    }
}

class RshChildModel extends Model
{
    protected $table = 'rsh_children';

    protected $guarded = [];
}

class RshTag extends Model
{
    protected $table = 'rsh_tags';

    protected $guarded = [];

    public $timestamps = false;
}

class RshBtmParent extends Model
{
    protected $table = 'rsh_parents';

    protected $guarded = [];

    /** @return BelongsToMany<RshTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RshTag::class, 'rsh_parent_tag', 'parent_id', 'tag_id')->withPivot('note');
    }
}

class RshSoftChildModel extends Model
{
    use SoftDeletes;

    protected $table = 'rsh_soft_children';

    protected $guarded = [];
}

class RshSoftParentModel extends Model
{
    protected $table = 'rsh_parents';

    protected $guarded = [];

    public function softChildren(): HasMany
    {
        return $this->hasMany(RshSoftChildModel::class, 'rsh_soft_parent_id');
    }
}
