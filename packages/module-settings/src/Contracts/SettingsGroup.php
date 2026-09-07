<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

/**
 * One tab of the settings page, declared by the application.
 *
 * The module owns storage and a screen; **what** is configurable is the
 * application's, because no package can know. A group names itself, gives a form
 * schema, and that is the whole contract:
 *
 *   final class BrandingSettings implements SettingsGroup
 *   {
 *       public static function group(): string { return 'branding'; }
 *
 *       public static function label(): string { return __('Branding'); }
 *
 *       public static function schema(): array
 *       {
 *           return [TextInput::make('company_name'), Toggle::make('dark_default')];
 *       }
 *   }
 *
 * Registered in `wire-module-settings.groups`, which is the same shape every
 * other list in this framework uses.
 */
interface SettingsGroup
{
    /** The storage group these values live in. */
    public static function group(): string;

    /** The tab's heading. */
    public static function label(): string;

    /**
     * The form components this group is edited with.
     *
     * @return array<int, mixed>
     */
    public static function schema(): array;
}
