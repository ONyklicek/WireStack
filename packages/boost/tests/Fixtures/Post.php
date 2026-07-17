<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Exercises every way a model can legitimately answer to a name: a real column,
 * a cast, an appended accessor, a classic accessor and a relation.
 */
class Post extends Model
{
    protected $table = 'boost_posts';

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<int, string> */
    protected $appends = ['excerpt'];

    /** @var array<string, string> */
    protected $casts = ['published' => 'boolean'];

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    /**
     * Modern accessor style, appended to the model's array form.
     */
    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => 'excerpt');
    }

    /**
     * Modern accessor style, not appended — resolvable purely by being an
     * Attribute-returning method, and protected as Laravel documents it.
     */
    protected function summary(): Attribute
    {
        return Attribute::get(fn (): string => 'summary');
    }

    /**
     * Classic accessor style.
     */
    public function getHeadlineAttribute(): string
    {
        return 'headline';
    }

    /**
     * A public method that is neither a relation nor an accessor.
     */
    public function publish(): void {}

    /**
     * An untyped relation, the way older code writes it — only calling it can
     * settle whether it is one.
     */
    public function tags()
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    /**
     * Untyped and explosive: calling it is the only way to find out, and it is
     * not a relation.
     */
    public function boom()
    {
        throw new RuntimeException('nope');
    }

    /**
     * A method that needs arguments cannot be a relation.
     */
    public function scopeOfType(Builder $query, string $type): void {}

    /**
     * A non-public method cannot be a relation.
     */
    protected function secret(): void {}
}
