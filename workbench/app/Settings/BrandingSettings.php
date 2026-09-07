<?php

declare(strict_types=1);

namespace Workbench\App\Settings;

use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

/**
 * What the settings module has to be given before it can show anything.
 *
 * The module owns storage and the screen; a group like this is the application's
 * half — and the workbench is the application here, which is why this is the
 * only place in the repository that declares one.
 *
 * It implements two of the three optional contracts as well, because a preview
 * of the screen that used none of them would show the plainest version of it:
 * the icon and description are what the switcher and the heading are drawn with,
 * and the defaults are why the fields have values in them on a database where
 * nothing has been saved yet.
 */
final class BrandingSettings implements DescribesSettingsGroup, ProvidesSettingsDefaults, SettingsGroup
{
    public static function group(): string
    {
        return 'branding';
    }

    public static function label(): string
    {
        return 'Branding';
    }

    public static function schema(): array
    {
        return [
            TextInput::make('company_name')->label('Company name'),
            TextInput::make('support_email')->label('Support e-mail')->email(),
            Toggle::make('dark_by_default')->label('Dark theme by default'),
        ];
    }

    public static function icon(): ?string
    {
        return 'outline:swatch';
    }

    public static function description(): ?string
    {
        return 'How the panel introduces itself: the name on it and who to write to.';
    }

    public static function sort(): int
    {
        return 10;
    }

    public static function defaults(): array
    {
        return [
            'company_name' => 'Nyon',
            'support_email' => 'support@example.com',
            'dark_by_default' => false,
        ];
    }
}
