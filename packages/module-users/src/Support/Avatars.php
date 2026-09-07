<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Support\StoredFileUrlResolver;
use Throwable;

/**
 * Whether this application's users can have a picture, and where it lives.
 *
 * The same shape as {@see Roles}: the module works without any of it, and where
 * the application's `users` table has somewhere to put a path, the upload
 * appears. `auto` looks, `true` and `false` answer for you.
 *
 * The look is a schema read, not a `class_exists`, because that is what the
 * question actually is — a column, on a table only the application owns. It is
 * asked once per process and remembered: a form is built on every request, and
 * an avatar that cost a `DESCRIBE` each time would be a strange thing to have
 * paid for. A database that cannot be reached answers "no pictures" rather than
 * throwing, so a console command or an install step that runs before the first
 * migration still boots.
 */
final class Avatars
{
    /**
     * Memoised per model-and-column, not per process — see the class docblock.
     *
     * The key matters. A single flag was wrong the moment anything asked this
     * question before the application had finished pointing the module at its
     * own user model: `php artisan about` does, and the answer it cached was
     * "no such class, so no avatars", for the rest of the request.
     *
     * @var array<string, bool>
     */
    private static array $available = [];

    /** Whether the avatar upload should be part of this installation. */
    public static function enabled(): bool
    {
        $setting = config('wire-module-users.avatar.enabled', 'auto');

        if (is_bool($setting)) {
            return $setting;
        }

        return $setting === 'auto' && self::available();
    }

    /** Whether the configured column is really on the users table. */
    public static function available(): bool
    {
        $model = config('wire-module-users.model');
        $column = self::column();
        $key = (is_string($model) ? $model : '').'|'.$column;

        if (array_key_exists($key, self::$available)) {
            return self::$available[$key];
        }

        if (! is_string($model) || ! class_exists($model)) {
            return self::$available[$key] = false;
        }

        try {
            /** @var Model $instance */
            $instance = new $model;

            return self::$available[$key] = Schema::connection($instance->getConnectionName())
                ->hasColumn($instance->getTable(), $column);
        } catch (Throwable) {
            // No connection, no table, no migration yet: all of them mean the
            // same thing here, and none of them is worth a stack trace on a
            // page that was only trying to draw a form.
            return self::$available[$key] = false;
        }
    }

    /** Forget the schema reads — for tests, and for an installer that just migrated. */
    public static function flush(): void
    {
        self::$available = [];
    }

    /** The column the path is stored in. */
    public static function column(): string
    {
        $configured = config('wire-module-users.avatar.column');

        return is_string($configured) && $configured !== '' ? $configured : 'avatar_path';
    }

    /** The disk uploads are moved to. */
    public static function disk(): string
    {
        $configured = config('wire-module-users.avatar.disk');

        return is_string($configured) && $configured !== '' ? $configured : 'public';
    }

    /** The directory within that disk. */
    public static function directory(): string
    {
        $configured = config('wire-module-users.avatar.directory');

        return is_string($configured) && $configured !== '' ? $configured : 'avatars';
    }

    /**
     * The URL for whatever is stored on a record, or null when nothing is.
     *
     * The path-to-URL ladder is not written here: `StoredFileUrlResolver` in
     * `wire-core` already owns it, including the case this column meets most
     * often after the upload one — a value that is *already* a URL, because the
     * application fills the same column from Gravatar or an identity provider.
     * The `ImageColumn` on the users list reaches the same resolver, so the list
     * and the chrome cannot disagree about where a face comes from.
     *
     * A disk that cannot build a URL at all is a misconfiguration this page
     * survives: initials are a worse avatar, not a broken one.
     */
    public static function urlFor(mixed $record): ?string
    {
        if (! $record instanceof Model || ! self::enabled()) {
            return null;
        }

        $stored = $record->getAttribute(self::column());

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return StoredFileUrlResolver::resolve($stored, self::disk());
        } catch (Throwable) {
            return null;
        }
    }
}
