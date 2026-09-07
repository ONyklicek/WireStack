<?php

declare(strict_types=1);

namespace Workbench\App\Settings;

use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\ProvidesSettingsDefaults;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

/**
 * A second group, so the preview shows the part of the screen that only exists
 * when there is more than one: the switcher, and a group reached by its own URL.
 *
 * One group is not a choice, and the screen does not draw it as one.
 */
final class MailSettings implements DescribesSettingsGroup, ProvidesSettingsDefaults, SettingsGroup
{
    public static function group(): string
    {
        return 'mail';
    }

    public static function label(): string
    {
        return 'Mail';
    }

    public static function schema(): array
    {
        return [
            TextInput::make('from_address')->label('From address')->email(),
            TextInput::make('from_name')->label('From name'),
            Select::make('transport')
                ->label('Transport')
                ->options(['smtp' => 'SMTP', 'ses' => 'Amazon SES', 'log' => 'Log']),
        ];
    }

    public static function icon(): ?string
    {
        return 'outline:envelope';
    }

    public static function description(): ?string
    {
        return 'Where transactional mail is sent from.';
    }

    public static function sort(): int
    {
        return 20;
    }

    public static function defaults(): array
    {
        return [
            'from_address' => 'noreply@example.com',
            'from_name' => 'Nyon',
            'transport' => 'smtp',
        ];
    }
}
