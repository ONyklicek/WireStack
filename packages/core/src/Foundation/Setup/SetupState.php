<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;

/**
 * Where a {@see SetupStep} stands before anybody is asked anything.
 *
 * Three answers rather than two, and the third is the one that matters. An
 * installer that knows only "done" and "not done" has to treat "no database
 * connection" as something to offer — and then the question is answered yes and
 * the run dies inside a task spinner with a PDO exception. {@see Blocked} is
 * how a step says *not yet, and here is why*, and it is reported rather than
 * offered.
 */
enum SetupState: string
{
    /**
     * Already the case. Named in the summary and left alone.
     */
    case Done = 'done';

    /**
     * Can be done now, and is worth offering.
     */
    case Pending = 'pending';

    /**
     * Cannot be done yet, and the step says why.
     *
     * Not a failure: an application with no database has a blocked database
     * step, and that is the correct state of a perfectly healthy install that
     * has not been configured yet.
     */
    case Blocked = 'blocked';
}
