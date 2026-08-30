<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use Throwable;

/**
 * The default recipient: whoever is logged in.
 *
 * Answers null rather than throwing when there is nobody — a queue worker, a
 * console command, a request before authentication. The driver treats null as
 * "nothing to write", so a notification raised outside a session is dropped
 * instead of being stored against a recipient nobody can read it as.
 */
final class AuthenticatedNotifiable implements ResolvesNotifiable
{
    public function resolve(): ?Model
    {
        try {
            $user = auth()->guard()->user();
        } catch (Throwable) {
            // No auth context at all (console, queue, a misconfigured guard).
            // Same fail-quiet choice HasAuthorization makes for the same reason.
            return null;
        }

        return $user instanceof Model ? $user : null;
    }
}
