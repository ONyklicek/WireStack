<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

use NyonCode\WireModuleSettings\Support\Settings;

/**
 * A settings group that says what its values are before anybody sets them.
 *
 * The alternative is the default living at every call site — `Settings::get(
 * 'from_address', 'noreply@example.com', 'mail')` written in six places, five of
 * them agreeing. Declared once here, {@see Settings::get()} answers it without
 * being told, and so does the form the group is edited with: an application
 * installs the module and the screen already has the right values in it rather
 * than a page of empty inputs.
 *
 *   public static function defaults(): array
 *   {
 *       return ['from_address' => 'noreply@example.com', 'queue' => true];
 *   }
 *
 * A stored value always wins over a default; a `$default` passed to `get()` is
 * the last fallback, for a key the group never declared at all.
 */
interface ProvidesSettingsDefaults
{
    /**
     * The group's values as they stand before anything is stored.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array;
}
