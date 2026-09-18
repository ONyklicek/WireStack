<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;

/**
 * What came of running a {@see SetupStep}.
 *
 * The step returns this rather than a bool, because "it did not happen" splits
 * into two things an installer has to report differently: a step that declined
 * — nothing to ask with, the person said no at a question inside it — and one
 * that tried and could not. The first is a line in the summary; the second
 * decides the exit code.
 */
enum SetupOutcome: string
{
    /**
     * It did what it said it would.
     */
    case Applied = 'applied';

    /**
     * It declined, and said why.
     *
     * Reached when a step needs an answer it cannot have — running unattended
     * with no default worth guessing — or when a question inside it was
     * answered no. Not an error: the installer moves on.
     */
    case Skipped = 'skipped';

    /**
     * It tried and could not finish.
     *
     * The only outcome that fails the command, for the same reason a failed
     * publish does: a setup that cannot say it failed is one a deploy script
     * cannot check.
     */
    case Failed = 'failed';
}
