<?php

declare(strict_types=1);

namespace NyonCode\WireTable\RelationManagers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use RuntimeException;

/**
 * Base for a relation-scoped table — the standalone counterpart to Filament's
 * relation managers.
 *
 * A subclass names an owner relationship and defines the table (columns, filters,
 * actions) exactly like any {@see WithTable} component. This class forces the
 * table's query to the owner record's relationship, so the list only shows the
 * related rows, and exposes relationship-aware persistence helpers
 * ({@see createRelatedRecord()}, {@see attachRelated()}, {@see detachRelated()})
 * for the create/attach/detach actions to call.
 *
 * Usage:
 *   class PostsRelationManager extends RelationManager
 *   {
 *       protected string $relationship = 'posts';
 *
 *       public function table(Table $table): Table
 *       {
 *           return $table->columns([TextColumn::make('title')]);
 *       }
 *   }
 *
 *   // rendered from the parent view:
 *
 *   @livewire(PostsRelationManager::class, ['ownerRecord' => $user])
 */
abstract class RelationManager extends Component
{
    use WithTable {
        WithTable::getTable as protected resolveBaseTable;
    }

    public ?Model $ownerRecord = null;

    /** Name of the owner relationship this manager scopes to (e.g. 'posts'). */
    protected string $relationship = '';

    /** Optional heading rendered above the table. */
    protected ?string $title = null;

    protected bool $relationshipQueryApplied = false;

    public function mount(?Model $ownerRecord = null): void
    {
        $this->ownerRecord = $ownerRecord;
    }

    public function getOwnerRecord(): Model
    {
        if (! $this->ownerRecord instanceof Model) {
            throw new RuntimeException(static::class.' requires an ownerRecord.');
        }

        return $this->ownerRecord;
    }

    public function getRelationshipName(): string
    {
        if ($this->relationship === '') {
            throw new RuntimeException(static::class.' must define a $relationship name.');
        }

        return $this->relationship;
    }

    /**
     * A fresh relationship instance on the owner record.
     */
    public function getRelationship(): Relation
    {
        $name = $this->getRelationshipName();
        $relation = $this->getOwnerRecord()->{$name}();

        if (! $relation instanceof Relation) {
            throw new RuntimeException(
                static::class.": [$name] is not an Eloquent relationship on ".$this->getOwnerRecord()::class.'.'
            );
        }

        return $relation;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Build the table then pin its query to the owner relationship, so the list
     * is always scoped to the related rows regardless of what the subclass set.
     */
    public function getTable(): Table
    {
        $table = $this->resolveBaseTable();

        if (! $this->relationshipQueryApplied) {
            $table->query($this->getRelationship()->getQuery());
            $this->relationshipQueryApplied = true;
        }

        return $table;
    }

    /**
     * Create a related record through the relationship (sets the foreign key for
     * has-one/has-many, creates and attaches for belongs-to-many).
     *
     * @param  array<string, mixed>  $data
     */
    public function createRelatedRecord(array $data): Model
    {
        $relation = $this->getRelationship();

        if ($relation instanceof HasOneOrMany || $relation instanceof BelongsToMany) {
            return $relation->create($data);
        }

        throw new RuntimeException(
            "The [{$this->getRelationshipName()}] relationship does not support creating related records."
        );
    }

    /**
     * Attach existing record(s) to a belongs-to-many relationship.
     *
     * @param  mixed  $id  A key, model, or array/collection of them.
     * @param  array<string, mixed>  $attributes  Pivot attributes.
     */
    public function attachRelated(mixed $id, array $attributes = []): void
    {
        $this->belongsToManyRelation(__FUNCTION__)->attach($id, $attributes);
    }

    /**
     * Detach record(s) from a belongs-to-many relationship (all when $id null).
     *
     * @param  mixed  $id  A key, model, array/collection of them, or null for all.
     */
    public function detachRelated(mixed $id = null): void
    {
        $this->belongsToManyRelation(__FUNCTION__)->detach($id);
    }

    protected function belongsToManyRelation(string $operation): BelongsToMany
    {
        $relation = $this->getRelationship();

        if (! $relation instanceof BelongsToMany) {
            throw new RuntimeException(
                static::class."::$operation() requires a belongs-to-many relationship, [{$this->getRelationshipName()}] given."
            );
        }

        return $relation;
    }

    public function render(): View
    {
        return view('wire-table::relation-manager', [
            'title' => $this->getTitle(),
        ]);
    }
}
