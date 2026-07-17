<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;

/*
 * RelationMetadata::fromRelation() reflects a live Eloquent relation into the
 * immutable metadata the query planner reasons about — the morph/to-many flags,
 * the keys, the pivot table. It reflects the relation object only, no database,
 * which is the point of a value object: everything it needs it reads off the
 * relation, so every relation type can be checked here without a schema.
 */

class RmdUser extends Model
{
    protected $guarded = [];

    public function profile(): HasOne
    {
        return $this->hasOne(RmdProfile::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(RmdPost::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RmdRole::class, 'rmd_role_user', 'user_id', 'role_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(RmdComment::class, 'commentable');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(RmdImage::class, 'imageable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(RmdTag::class, 'taggable');
    }

    public function comboComments(): HasManyThrough
    {
        return $this->hasManyThrough(RmdComment::class, RmdPost::class, 'user_id', 'post_id');
    }

    public function soleComment(): HasOneThrough
    {
        return $this->hasOneThrough(RmdComment::class, RmdPost::class, 'user_id', 'post_id');
    }

    public function softProfile(): HasOne
    {
        return $this->hasOne(RmdSoftProfile::class, 'user_id');
    }

    public function publishedProfile(): HasOne
    {
        // Method constraint on a model with no global scopes.
        return $this->hasOne(RmdProfile::class, 'user_id')->where('published', true);
    }
}

class RmdSoftProfile extends Model
{
    use SoftDeletes;

    protected $guarded = [];
}

class RmdProfile extends Model
{
    protected $guarded = [];
}

class RmdPost extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RmdUser::class, 'user_id');
    }
}

class RmdRole extends Model
{
    protected $guarded = [];
}

class RmdComment extends Model
{
    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

class RmdImage extends Model
{
    protected $guarded = [];
}

class RmdTag extends Model
{
    protected $guarded = [];
}

/** Build metadata for a named relation declared on a model instance. */
function rmdMeta(Model $model, string $relation): RelationMetadata
{
    return RelationMetadata::fromRelation($relation, $model::class, $model->{$relation}());
}

// ─── Relation-type classification ─────────────────────────────

it('classifies a belongs-to as a joinable single relation', function () {
    $meta = rmdMeta(new RmdPost, 'user');

    expect($meta->type)->toBe('BelongsTo')
        ->and($meta->isMorph)->toBeFalse()
        ->and($meta->isToMany)->toBeFalse()
        ->and($meta->isJoinable())->toBeTrue()
        ->and($meta->requiresEagerLoad())->toBeFalse()
        ->and($meta->relatedModel)->toBe(RmdUser::class)
        ->and($meta->parentModel)->toBe(RmdPost::class)
        ->and($meta->name)->toBe('user')
        ->and($meta->foreignKey)->toBe('user_id');
});

it('classifies a has-one as a joinable single relation with a local key', function () {
    $meta = rmdMeta(new RmdUser, 'profile');

    expect($meta->type)->toBe('HasOne')
        ->and($meta->isToMany)->toBeFalse()
        ->and($meta->isJoinable())->toBeTrue()
        ->and($meta->foreignKey)->toBe('user_id')
        ->and($meta->localKey)->toBe('id');
});

it('classifies a has-many as a to-many relation that must be eager-loaded', function () {
    $meta = rmdMeta(new RmdUser, 'posts');

    expect($meta->type)->toBe('HasMany')
        ->and($meta->isToMany)->toBeTrue()
        ->and($meta->isMorph)->toBeFalse()
        ->and($meta->isJoinable())->toBeFalse()
        ->and($meta->requiresEagerLoad())->toBeTrue();
});

it('classifies a has-many-through as a to-many relation', function () {
    $meta = rmdMeta(new RmdUser, 'comboComments');

    expect($meta->type)->toBe('HasManyThrough')
        ->and($meta->isToMany)->toBeTrue()
        ->and($meta->requiresEagerLoad())->toBeTrue();
});

it('classifies a has-one-through as a joinable single relation carrying its intermediate table', function () {
    // Singular through: joinable, but needs the intermediate table + the two
    // bridging keys so the planner can emit base -> through -> far.
    $meta = rmdMeta(new RmdUser, 'soleComment');

    expect($meta->type)->toBe('HasOneThrough')
        ->and($meta->isToMany)->toBeFalse()
        ->and($meta->isJoinable())->toBeTrue()
        ->and($meta->isThrough())->toBeTrue()
        ->and($meta->throughTable)->toBe((new RmdPost)->getTable())
        ->and($meta->firstKey)->toBe('user_id')          // through.first → base
        ->and($meta->secondLocalKey)->toBe('id')         // through local key
        ->and($meta->foreignKey)->toBe('post_id');       // far → through
});

it('does not mark a plain single relation as through', function () {
    expect(rmdMeta(new RmdPost, 'user')->isThrough())->toBeFalse()
        ->and(rmdMeta(new RmdUser, 'profile')->isThrough())->toBeFalse();
});

it('scopes a belongsTo/hasOne from its own relation query when the model has global scopes', function () {
    // RmdSoftProfile uses SoftDeletes (a global scope), so the join is a scoped
    // subquery rebuilt from the relation itself (parent + method).
    $scope = rmdMeta(new RmdUser, 'softProfile')->scope;

    expect($scope)->not->toBeNull()
        ->and($scope->relationParent)->toBe(RmdUser::class)
        ->and($scope->relationMethod)->toBe('softProfile')
        // The target class is kept as the fallback source.
        ->and($scope->model)->toBe(RmdSoftProfile::class);
});

it('scopes a relation that declares its own constraints even without global scopes', function () {
    // publishedProfile() adds ->where('published', true); RmdProfile has no scopes.
    $scope = rmdMeta(new RmdUser, 'publishedProfile')->scope;

    expect($scope)->not->toBeNull()
        ->and($scope->relationParent)->toBe(RmdUser::class)
        ->and($scope->relationMethod)->toBe('publishedProfile');
});

it('leaves a plain relation with no scopes or constraints as a direct join', function () {
    expect(rmdMeta(new RmdUser, 'profile')->scope)->toBeNull();
});

it('records the pivot table of a belongs-to-many', function () {
    $meta = rmdMeta(new RmdUser, 'roles');

    expect($meta->type)->toBe('BelongsToMany')
        ->and($meta->isToMany)->toBeTrue()
        ->and($meta->isMorph)->toBeFalse()
        ->and($meta->pivotTable)->toBe('rmd_role_user');
});

// ─── Morph relations ──────────────────────────────────────────

it('flags a morph-one and records its morph type', function () {
    $meta = rmdMeta(new RmdUser, 'avatar');

    expect($meta->type)->toBe('MorphOne')
        ->and($meta->isMorph)->toBeTrue()
        ->and($meta->isToMany)->toBeFalse()
        ->and($meta->morphType)->toBe('imageable_type');
});

it('flags a morph-many as both morph and to-many', function () {
    $meta = rmdMeta(new RmdUser, 'comments');

    expect($meta->type)->toBe('MorphMany')
        ->and($meta->isMorph)->toBeTrue()
        ->and($meta->isToMany)->toBeTrue()
        ->and($meta->morphType)->toBe('commentable_type')
        // A morph is never joinable — its target is not known statically.
        ->and($meta->isJoinable())->toBeFalse()
        ->and($meta->requiresEagerLoad())->toBeTrue();
});

it('leaves a morph-to without a related model, resolved only at runtime', function () {
    $meta = rmdMeta(new RmdComment, 'commentable');

    expect($meta->type)->toBe('MorphTo')
        ->and($meta->isMorph)->toBeTrue()
        // The whole reason relatedModel is nullable: a morphTo points at
        // different models per row, so there is nothing to name here.
        ->and($meta->relatedModel)->toBeNull()
        ->and($meta->morphType)->toBe('commentable_type')
        ->and($meta->isJoinable())->toBeFalse();
});

it('flags a morph-to-many as morph, to-many, and pivoted', function () {
    $meta = rmdMeta(new RmdUser, 'tags');

    expect($meta->type)->toBe('MorphToMany')
        ->and($meta->isMorph)->toBeTrue()
        ->and($meta->isToMany)->toBeTrue()
        ->and($meta->pivotTable)->toBe('taggables');
});

// ─── The value object itself ──────────────────────────────────

it('is an immutable carrier of exactly what it was given', function () {
    $meta = new RelationMetadata(
        name: 'posts',
        type: 'HasMany',
        parentModel: RmdUser::class,
        relatedModel: RmdPost::class,
        foreignKey: 'user_id',
        localKey: 'id',
        morphType: null,
        pivotTable: null,
        isMorph: false,
        isToMany: true,
    );

    expect($meta->requiresEagerLoad())->toBeTrue()
        ->and($meta->isJoinable())->toBeFalse();
});
