<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Concerns;

use NyonCode\WireModuleUsers\Support\Avatars;

/**
 * The one line an application's `User` writes to be drawn with a picture.
 *
 *     use NyonCode\WireCore\Foundation\Contracts\HasAvatar;
 *     use NyonCode\WireModuleUsers\Concerns\InteractsWithAvatar;
 *
 *     class User extends Authenticatable implements HasAvatar
 *     {
 *         use InteractsWithAvatar;
 *     }
 *
 * The contract lives in `wire-core` and the reading of it here, which is the
 * split that lets the shell draw an avatar without knowing this package exists:
 * the chrome asks the interface, and an application with its own answer —
 * Gravatar, an identity provider, a second table — implements it instead of
 * taking this trait.
 *
 * No business logic of its own: where the column is, which disk it is on and
 * what a stored value means are {@see Avatars}'s, and this is the delegation.
 */
trait InteractsWithAvatar
{
    public function getAvatarUrl(): ?string
    {
        return Avatars::urlFor($this);
    }
}
