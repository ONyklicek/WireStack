<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\Search\SearchClauseCompiler;
use NyonCode\WireCore\Core\Query\Search\SearchTerm;
use NyonCode\WireCore\Core\Query\Search\SearchToken;

/**
 * Applies a parsed search term from the QueryPlan.
 *
 * Each token gets its own grouped WHERE and the groups AND together, while the
 * columns inside one group OR: `Ada Lovelace` therefore means "some column has
 * Ada *and* some column has Lovelace", which is what lets a first name in one
 * column and a surname in another match together. An unsplit term is a single
 * token, so the classic single-group behaviour is the same code path.
 */
final class ApplySearch implements QueryPipe
{
    private readonly SearchTerm $term;

    private readonly SearchClauseCompiler $compiler;

    /**
     * @param  SearchTerm|string|null  $term  A parsed term, or a raw string to match literally
     * @param  array<int, callable(Builder<Model>, string): mixed>  $extraCallbacks
     *                                                                               Host-supplied per-column search callbacks, OR-combined into the same group
     *                                                                               as the plan clauses so a custom-search column never suppresses the default
     *                                                                               ones.
     */
    public function __construct(
        private readonly SearchStrategy $strategy,
        SearchTerm|string|null $term = null,
        private readonly array $extraCallbacks = [],
        ?SearchClauseCompiler $compiler = null,
    ) {
        $this->term = $term instanceof SearchTerm
            ? $term
            : SearchTerm::literal($term ?? '');

        $this->compiler = $compiler ?? new SearchClauseCompiler;
    }

    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        $hasClauses = $plan->hasSearch() || $this->extraCallbacks !== [];

        if (! $hasClauses || $this->term->isEmpty()) {
            return $next($builder, $plan);
        }

        foreach ($this->term->tokens as $token) {
            $builder->where(function (Builder $query) use ($plan, $token): void {
                $this->applyToken($query, $plan, $token);
            });
        }

        return $next($builder, $plan);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyToken(Builder $query, QueryPlan $plan, SearchToken $token): void
    {
        $applied = false;

        foreach ($plan->searchClauses as $clause) {
            $applied = $this->compiler->apply($query, $clause, $token, $this->strategy) || $applied;
        }

        // A comparison no column could answer — `>100` where nothing numeric is
        // searchable — would otherwise contribute an empty group and quietly
        // match every row. Searching it as the literal text the user typed keeps
        // the result explainable.
        if (! $applied && $token->isComparison()) {
            $text = $token->asText();

            foreach ($plan->searchClauses as $clause) {
                $this->compiler->apply($query, $clause, $text, $this->strategy);
            }
        }

        // Custom column callbacks share this OR group, so default-column
        // matches and custom matches combine rather than one dropping the other.
        foreach ($this->extraCallbacks as $callback) {
            $query->orWhere(function (Builder $sub) use ($callback, $token): void {
                $callback($sub, $token->searchText());
            });
        }
    }
}
