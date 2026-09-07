<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Support;

use Illuminate\Support\Facades\Gate;
use NyonCode\WireModuleMedia\Models\Media;

/**
 * Whether the current user may do that to a file.
 *
 * One question asked in one place, because the library is reached from three
 * directions — the screen, the picker, and the route that streams a private
 * file — and three copies of an access rule is two copies too many.
 *
 * **A library with no policy behaves exactly as it did before this existed.**
 * Laravel's `Gate::allows()` denies an ability nobody defined, so asking it
 * unconditionally would have locked every existing installation out of its own
 * files on upgrade. So the policy is asked for first: absent, everything is
 * allowed; registered, it is obeyed everywhere at once.
 *
 *   Gate::policy(Media::class, MediaPolicy::class);
 *
 * The abilities are Laravel's own vocabulary — `viewAny`, `view`, `create`,
 * `update`, `delete` — so a policy written for this reads like every other
 * policy in the application. One is this module's own: **`replace`**, asked
 * before the editor writes new bytes under an existing row, and falling back to
 * `update` on a policy that does not define it (ADR 0035).
 */
final class MediaAccess
{
    public static function allows(string $ability, Media|string $target = Media::class): bool
    {
        $policy = Gate::getPolicyFor(Media::class);

        // No policy is a decision an application made by not making one, and the
        // honest reading of it is "this is not access-controlled" rather than
        // "nobody may do anything".
        if ($policy === null) {
            return true;
        }

        // Overwriting the bytes behind a published URL is materially larger than
        // renaming a row, so it is worth being able to allow one and refuse the
        // other. But a policy written before the editor existed has no `replace`
        // method, and Laravel's gate denies an ability nobody defined — which
        // would take a feature away from every library that upgraded. So an
        // undefined `replace` is asked as `update`, which is the answer that
        // policy was written to give.
        if ($ability === 'replace' && ! method_exists($policy, 'replace')) {
            $ability = 'update';
        }

        return Gate::allows($ability, $target);
    }

    public static function denies(string $ability, Media|string $target = Media::class): bool
    {
        return ! self::allows($ability, $target);
    }
}
