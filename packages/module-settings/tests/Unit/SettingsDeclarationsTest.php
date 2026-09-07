<?php

declare(strict_types=1);

use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Resources\SettingsResource;
use NyonCode\WireModuleSettings\SettingsModule;

it('is a resource with no model at all', function () {
    // The contract has always allowed it: what DescribesResource carries is
    // identity — a key, a label, a place in a menu — and settings have all three
    // while having no table of records.
    expect(SettingsResource::modelClass())->toBeNull()
        ->and(SettingsResource::key())->toBe('settings')
        ->and(SettingsResource::label())->not->toBe('')
        ->and(SettingsResource::pluralLabel())->not->toBe('')
        ->and(SettingsResource::navigation()->getGroup())->toBe('system');
});

it('routes one page under two kinds, so a group can be a URL', function () {
    expect(SettingsResource::pages())->toBe([
        'index' => SettingsPage::class,
        'view' => SettingsPage::class,
    ]);
});

it('has no URL for a group while nothing routes it', function () {
    expect(SettingsResource::urlForGroup('branding'))->toBeNull();
});

it('lets an application rename the menu group it ships', function () {
    config()->set('wire-module-settings.navigation.label', 'Configuration');

    expect((new SettingsModule)->navigation()?->getLabel())->toBe('Configuration');
});

it('falls back to its own heading when the application renames nothing', function () {
    expect((new SettingsModule)->navigation()?->getLabel())->not->toBe('');
});
