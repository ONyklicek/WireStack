<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use NyonCode\WireModuleUsers\Concerns\InteractsWithAvatar;

/**
 * A record that can show a picture of itself — a user, in practice.
 *
 * In core, and as an interface, for one reason: the shell draws the signed-in
 * user in its top bar and must not know where an avatar comes from. An
 * application storing a path in a column, one resolving Gravatar, one asking an
 * identity provider and one that has no pictures at all are four answers to the
 * same question, and the chrome asks it the same way in every case:
 *
 *     $user instanceof HasAvatar ? $user->getAvatarUrl() : null
 *
 * `wire-module-users` ships an implementation
 * ({@see InteractsWithAvatar}) over a
 * configured column, and an application that installs no module can implement
 * this on its own `User` and be drawn just the same.
 *
 * **Null is a real answer**, not a failure: a user who has uploaded nothing gets
 * their initials, which is what every surface falls back to.
 */
interface HasAvatar
{
    /** An absolute URL to this record's picture, or null when it has none. */
    public function getAvatarUrl(): ?string;
}
