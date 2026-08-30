<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Contracts;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Notifications\Drivers\DatabaseDriver;

/**
 * Who a persisted notification belongs to.
 *
 * Its own contract because "the current user" is the application's answer, not
 * the framework's: a job running on a queue has no authenticated user, a
 * multi-tenant app may address a team rather than a person, and a console
 * command has neither. The default asks the auth guard and answers null when
 * there is nobody — at which point {@see DatabaseDriver}
 * writes nothing rather than inventing a recipient.
 */
interface ResolvesNotifiable
{
    public function resolve(): ?Model;
}
