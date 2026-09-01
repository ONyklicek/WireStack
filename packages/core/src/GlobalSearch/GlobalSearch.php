<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;

/**
 * Searching every registered resource at once.
 *
 * Reads the {@see ResourceRegistry} and nothing else about the application, so
 * the palette gains a resource the moment one is registered and never has a list
 * of its own to fall out of date.
 *
 * ## What it does not do
 *
 * It does not go through `DataSource`/`QueryPlan`, though the V2.5 plan said it
 * would. That path builds its search clauses from a *table component*
 * (`QueryPlanner::plan($component, …)`), and the palette has no component and no
 * table — assembling a fake one per resource per keystroke to reuse the planner
 * would be the second implementation the planner exists to prevent. A resource
 * names the attributes instead, and the query below is the whole of it.
 *
 * ## Scope and authorization
 *
 * Tenancy needs nothing here: V2.4 made it a global Eloquent scope, so a query
 * built from `Model::query()` is already narrowed to the current tenant. What
 * does need doing is the per-record check, because a term can match a record the
 * user may not open — and a palette that lists the title of something forbidden
 * has leaked it, whether or not the click is refused afterwards.
 */
class GlobalSearch
{
    /** Rows taken from each resource before authorization filters them. */
    public const PER_RESOURCE_LIMIT = 5;

    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    /**
     * Every resource's answer to one term, keyed by resource key.
     *
     * A resource with no matches is left out rather than mapped to an empty
     * list, so a caller can render group headings by iterating the result.
     *
     * @return array<string, array<int, GlobalSearchResult>>
     */
    public function search(string $term, int $perResource = self::PER_RESOURCE_LIMIT): array
    {
        $term = trim($term);

        // An empty term matches everything, which in a palette reads as "here is
        // your whole database" the instant the modal opens.
        if ($term === '') {
            return [];
        }

        $results = [];

        foreach ($this->registry->all() as $resource) {
            // The registry only holds DescribesResource, so this is the other
            // half: identity and searchability are separate opt-ins, and a
            // resource that never opted in is skipped rather than asked.
            if (! is_subclass_of($resource, GloballySearchable::class)) {
                continue;
            }

            $found = $this->searchResource($resource, $term, $perResource);

            if ($found !== []) {
                $results[$resource::key()] = $found;
            }
        }

        return $results;
    }

    /**
     * @param  class-string<DescribesResource&GloballySearchable>  $resource
     * @return array<int, GlobalSearchResult>
     */
    protected function searchResource(string $resource, string $term, int $perResource): array
    {
        $model = $resource::modelClass();
        $attributes = $resource::globallySearchableAttributes();

        // A resource with no model has nothing for this query to run against —
        // V2.0 allows one over a non-Eloquent source — and one that declares no
        // attributes has opted in without saying to what.
        if ($model === null || $attributes === [] || ! is_subclass_of($model, Model::class)) {
            return [];
        }

        /** @var class-string<Model> $model */
        /** @var Collection<int, Model> $records */
        $records = $model::query()
            ->where(fn (Builder $query) => $this->matchAny($query, $attributes, $term))
            ->limit($perResource)
            ->get();

        $results = [];

        foreach ($records as $record) {
            if (! $this->canView($record)) {
                continue;
            }

            $results[] = $resource::toGlobalSearchResult($record);
        }

        return $results;
    }

    /**
     * `WHERE (a LIKE %term% OR b LIKE %term%)`, nested so it cannot leak out of
     * whatever the caller has already constrained.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $attributes
     */
    protected function matchAny(Builder $query, array $attributes, string $term): void
    {
        // `%` and `_` are wildcards in LIKE, so without escaping a search for
        // "100%" matches every row starting with "100" and "a_b" matches "axb".
        //
        // The escape character is `!` and it is declared, rather than relying on
        // a backslash: MySQL treats `\` as the default LIKE escape and SQLite
        // does not, so the same query would mean two different things on two
        // supported databases — which is how the test for this failed the first
        // time it ran.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        // Raw, because Laravel's `like` operator has nowhere to put the ESCAPE
        // clause. The column is wrapped by the grammar rather than interpolated:
        // it comes from the resource and not from a user, but a raw fragment
        // that would be an injection if that ever changed is not worth leaving.
        $grammar = $query->getQuery()->getGrammar();

        foreach ($attributes as $attribute) {
            $query->orWhereRaw(
                $grammar->wrap($attribute)." like ? escape '!'",
                ["%{$escaped}%"],
            );
        }
    }

    /**
     * Whether the current user may see this record at all.
     *
     * Falls open when no policy is registered, which is Laravel's own answer for
     * an unguarded model and keeps the palette usable in an app that authorizes
     * nowhere. A resource that must never be listed without a check registers a
     * policy — the same thing it would do for any other read.
     */
    protected function canView(Model $record): bool
    {
        if (Gate::getPolicyFor($record) === null) {
            return true;
        }

        return Gate::allows('view', $record);
    }
}
