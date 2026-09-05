<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireTable\Services\TableQueryService;
use RuntimeException;

/**
 * A debugging aid that cannot answer the question it was asked.
 *
 * `RuntimeException` because it is about the state of the container rather than
 * about a bad argument: every parameter of `debugQueryPlan()` is a simulated
 * request and any of them is legitimate.
 *
 * Thrown rather than returned as `['error' => …]`, which is what this replaces.
 * That shape was a lie by omission on the one surface where being lied to costs
 * the most: the caller is looking at a plan to find out why a query does what it
 * does, and an array whose `query_plan` key is simply absent reads as "this
 * table plans nothing" — the exact wrong conclusion. It also could not be
 * handled, since nothing else this method returns has an `error` key to check
 * for. AI_CODING_STANDARD.md bans the shape from a domain or support class for
 * this reason.
 */
final class TableIntrospectionException extends RuntimeException implements WireException
{
    /**
     * The query service built a query but is holding no plan.
     *
     * Unreachable through the real {@see TableQueryService}: `buildQuery()` has
     * no return path that skips the planner, so a call that comes back has set
     * `lastPlan`. It is reachable — and covered — by binding something else to
     * the contract, which is the case worth naming: a test double, or an
     * application that swapped the service for its own. Left as a null check
     * returning "no plan", that substitution would present as a table whose
     * planner had decided to do nothing.
     */
    public static function noQueryPlan(): self
    {
        return new self(
            'The table query service built a query but produced no QueryPlan, so there is '
            .'nothing to report. The bundled '.TableQueryService::class.' always plans before '
            .'it executes, so this means something else is bound to that class — a test double, '
            .'or an application-level replacement that does not keep the plan.'
        );
    }
}
