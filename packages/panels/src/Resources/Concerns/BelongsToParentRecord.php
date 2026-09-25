<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WirePanels\Exceptions\ResourcePageException;
use NyonCode\WirePanels\Resources\Contracts\NestedResource;
use NyonCode\WireTable\Table;

/**
 * The half of a page that is about the record its resource belongs to.
 *
 * Inert for every resource that is not a {@see NestedResource}: `$parent` stays
 * null, no parameter is added to a URL, and no query is touched. For one that
 * is, the parent's key arrives from the route's `{parent}` (or a mount argument)
 * and everything the page reaches for goes through the parent's relationship —
 * which is what makes a line of another order a 404 rather than a page.
 */
trait BelongsToParentRecord
{
    /** The parent record's key. Public because Livewire carries it across requests. */
    public mixed $parent = null;

    /** Per request, never in the snapshot. */
    private ?Model $resolvedParentRecord = null;

    /** Livewire calls this for the trait, on mount. */
    public function mountBelongsToParentRecord(): void
    {
        $nested = $this->nestedResourceClass();

        if ($nested === null) {
            return;
        }

        $this->parent ??= request()->route('parent');

        if ($this->parent === null) {
            throw ResourcePageException::missingParent(static::class, $nested::parentResource());
        }

        abort_if($this->parentRecord() === null, 404);
    }

    /** Whether the page's resource belongs to a record of another. */
    protected function isNested(): bool
    {
        return $this->nestedResourceClass() !== null;
    }

    /**
     * The page's resource, when it is nested.
     *
     * @return class-string<NestedResource>|null
     */
    protected function nestedResourceClass(): ?string
    {
        $resource = static::$resource;

        return $resource !== null && is_subclass_of($resource, NestedResource::class) ? $resource : null;
    }

    /** The parent record, or null when the page is not nested or the key reaches nothing. */
    protected function parentRecord(): ?Model
    {
        $nested = $this->nestedResourceClass();

        if ($nested === null) {
            return null;
        }

        // Read here as well as in the mount hook, because a record page's own
        // mount() resolves its record — through this — before any trait's
        // mount hook has run.
        $this->parent ??= request()->route('parent');

        if ($this->parent === null) {
            return null;
        }

        if ($this->resolvedParentRecord === null) {
            $model = $nested::parentResource()::modelClass();
            $this->resolvedParentRecord = $model === null ? null : $model::query()->find($this->parent);
        }

        return $this->resolvedParentRecord;
    }

    /**
     * The parent's relationship that holds this page's records.
     *
     * @return Relation<Model, Model, mixed>|null
     */
    protected function parentRelation(): ?Relation
    {
        $nested = $this->nestedResourceClass();
        $parent = $this->parentRecord();

        if ($nested === null || $parent === null) {
            return null;
        }

        $relation = $parent->{$nested::parentRelationship()}();

        return $relation instanceof Relation ? $relation : null;
    }

    /**
     * The table, narrowed to the parent's records.
     *
     * By key, through the relationship's own query — `whereIn(key, …)` — so any
     * relationship the parent declares scopes the list correctly, whatever its
     * keys look like, and the table's own modification stays in force.
     */
    protected function applyParentScope(Table $table): Table
    {
        $relation = $this->parentRelation();

        if ($relation === null) {
            return $table;
        }

        $base = $table->getModifyQueryCallback();
        $related = $relation->getRelated();
        $keys = $relation->getQuery()->toBase()->select($related->getQualifiedKeyName());

        return $table->modifyQueryUsing(function (Builder $query) use ($base, $keys): Builder {
            $query = $base === null ? $query : ($base($query) ?? $query);

            return $query->whereIn($query->getModel()->getQualifiedKeyName(), $keys);
        });
    }

    /**
     * The route parameters this page's URLs need beside the record's.
     *
     * @return array<string, mixed>
     */
    protected function parentRouteParameters(): array
    {
        return $this->isNested() && $this->parent !== null ? ['parent' => $this->parent] : [];
    }

    /**
     * The crumbs that lead to the parent record: its list, then the record.
     *
     * @return array<int, NavigationItem>
     */
    protected function parentBreadcrumbs(): array
    {
        $nested = $this->nestedResourceClass();
        $record = $this->parentRecord();

        if ($nested === null || $record === null) {
            return [];
        }

        $parent = $nested::parentResource();
        $urls = app(ResolvesPageUrls::class);
        $key = $parent::key();

        return [
            NavigationItem::make($parent::pluralLabel())->url($urls->urlFor($key, 'index', [], $this->breadcrumbZone)),
            NavigationItem::make($this->parentRecordTitle($record) ?? $parent::label().' '.$record->getKey())->url(
                $urls->urlFor($key, 'view', ['record' => $record->getKey()], $this->breadcrumbZone)
                    ?? $urls->urlFor($key, 'edit', ['record' => $record->getKey()], $this->breadcrumbZone),
            ),
        ];
    }

    /**
     * What the parent record is called in the trail: the first of the
     * attributes a record is usually named by, or null — and the trail falls
     * back to the parent's label and key, *Order 17*. A page with a better
     * answer says so.
     */
    protected function parentRecordTitle(Model $record): ?string
    {
        foreach (['name', 'title', 'number', 'label', 'subject'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
