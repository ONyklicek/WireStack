<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tests\Fixtures\Dashboards;

/**
 * A dashboard whose class name is exactly `Dashboard`.
 *
 * In a namespace of its own because that is the only way to have one: the name
 * collides with the base class everywhere else. It exists for the fallback in
 * `Dashboard::baseName()` — stripping the suffix from "Dashboard" leaves nothing
 * to call the thing, and every other fixture in the suite has a prefix, so the
 * fallback was the one line of that class no test reached.
 */
class Dashboard extends \NyonCode\WireCore\Widgets\Dashboard
{
    public function widgets(): array
    {
        return [];
    }
}
