<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Services\SubRowQuery;
use NyonCode\WireTable\Support\SubRowRelation;
use NyonCode\WireTable\Table;

/*
 * Which relations a table can draw children from, and what it gets back.
 *
 * The rule is a refusal as much as a resolution: only a direct parent→child
 * relation has a foreign key on the child to restrict by, so anything else
 * yields null and the caller drops the grand totals. Driven through WithTable
 * that refusal is invisible — the footer simply has no total, which is also
 * what a table with no summaries looks like.
 */

class SrqParent extends Model
{
    protected $table = 'srq_parents';

    protected $guarded = [];

    public $timestamps = false;

    public function children(): HasMany
    {
        return $this->hasMany(SrqChild::class, 'parent_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(SrqNote::class, 'noteable');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SrqTag::class, 'srq_parent_tag', 'parent_id', 'tag_id');
    }
}

class SrqChild extends Model
{
    protected $table = 'srq_children';

    protected $guarded = [];

    public $timestamps = false;
}

class SrqNote extends Model
{
    protected $table = 'srq_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class SrqTag extends Model
{
    protected $table = 'srq_tags';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    foreach (['srq_parents', 'srq_children', 'srq_notes', 'srq_tags', 'srq_parent_tag'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('srq_parents', fn (Blueprint $t) => $t->id());
    Schema::create('srq_children', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('parent_id');
    });
    Schema::create('srq_notes', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('noteable_id');
        $t->string('noteable_type');
    });
    Schema::create('srq_tags', fn (Blueprint $t) => $t->id());
    Schema::create('srq_parent_tag', function (Blueprint $t) {
        $t->unsignedBigInteger('parent_id');
        $t->unsignedBigInteger('tag_id');
    });
});

function srqOpen(string $relation): ?SubRowRelation
{
    return app(SubRowQuery::class)->open(
        Table::make()->model(SrqParent::class)->subRows($relation),
    );
}

it('opens a hasMany, and hands back the keys with the query', function () {
    $relation = srqOpen('children');

    expect($relation)->toBeInstanceOf(SubRowRelation::class)
        // Qualified, because a child query commonly joins and both tables have an id.
        ->and($relation->foreignKey)->toBe('srq_children.parent_id')
        // Unqualified: it names a column on the parent.
        ->and($relation->localKey)->toBe('id')
        ->and($relation->children->getModel())->toBeInstanceOf(SrqChild::class);
});

it('constrains a morph relation to its own parent type', function () {
    // Without this the totals sweep in every other type sharing the notes table.
    $relation = srqOpen('notes');

    expect($relation)->not->toBeNull()
        ->and($relation->children->toSql())->toContain('noteable_type');
});

it('refuses a relation with no foreign key on the child', function () {
    // belongsToMany keeps the link in a pivot, so there is nothing on the child
    // to restrict by — the caller drops the grand totals rather than computing
    // a wrong one.
    expect(srqOpen('tags'))->toBeNull();
});

it('refuses a table with no sub-rows at all', function () {
    expect(app(SubRowQuery::class)->open(Table::make()->model(SrqParent::class)))->toBeNull();
});

it('hands back an unrestricted query, leaving the parent set to the caller', function () {
    // The split this exists for: which children the relation *can* produce is a
    // fact about the model, while which parents are in scope — this page, the
    // selection, the whole filtered set — only the host knows.
    expect(srqOpen('children')->children->toSql())->not->toContain('parent_id in');
});
