<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\WirePackages;

/**
 * @param  array<int, string>  $names
 */
function packagesWith(array $names): WirePackages
{
    $versions = [];

    foreach ($names as $name) {
        $versions[WirePackages::composerName($name)] = '2.0.0';
    }

    return new WirePackages($versions);
}

it('names the whole stack, the optional layers and every module', function () {
    expect(WirePackages::all())
        ->toContain('wire-core', 'wire-forms', 'wire-table', 'wire-sortable')
        ->toContain('wire-panels', 'wire-admin', 'wire-suite', 'wire-boost')
        ->toContain('wire-module-auth', 'wire-module-users', 'wire-module-settings')
        ->toContain('wire-module-audit', 'wire-module-notifications', 'wire-module-media')
        ->and(WirePackages::modules())->toHaveCount(6);
});

it('prefixes a short name with the vendor', function () {
    expect(WirePackages::composerName('wire-table'))->toBe('nyoncode/wire-table');
});

it('reads the installed versions from composer', function () {
    // The monorepo has every package on disk, so detect() is the one place the
    // real Composer metadata is exercised.
    $detected = WirePackages::detect();

    expect($detected->isInstalled('wire-core'))->toBeTrue()
        ->and($detected->isInstalled('wire-panels'))->toBeTrue()
        ->and($detected->versions())->toHaveKey('nyoncode/wire-boost');
});

it('reports a package as installed only when it has a version', function () {
    $packages = packagesWith(['wire-core', 'wire-table']);

    expect($packages->isInstalled('wire-core'))->toBeTrue()
        ->and($packages->isInstalled('wire-panels'))->toBeFalse()
        ->and($packages->versions())->toBe([
            'nyoncode/wire-core' => '2.0.0',
            'nyoncode/wire-table' => '2.0.0',
        ]);
});

it('treats the modules group as installed as soon as one module is', function () {
    // Requiring all six would hide the guideline from every application that
    // installed exactly the area it needed.
    expect(packagesWith(['wire-module-media'])->isInstalled(WirePackages::MODULES_GROUP))->toBeTrue()
        ->and(packagesWith(['wire-core'])->isInstalled(WirePackages::MODULES_GROUP))->toBeFalse();
});

it('ships a resource named after a package only where that package is', function () {
    $packages = packagesWith(['wire-core', 'wire-table', 'wire-module-users']);

    expect($packages->shipsResource('wire-table'))->toBeTrue()
        ->and($packages->shipsResource('wire-panels'))->toBeFalse()
        ->and($packages->shipsResource('wire-modules'))->toBeTrue();
});

it('reads the package off a skill directory name', function () {
    $packages = packagesWith(['wire-table']);

    expect($packages->shipsResource('wire-table-development'))->toBeTrue()
        ->and($packages->shipsResource('wire-sortable-development'))->toBeFalse();
});

it('ships anything it is not the judge of', function () {
    // `core`, a one-off like the upgrade skill, and a project's own file under
    // .ai/guidelines are not named after a package and always ship.
    $packages = packagesWith([]);

    expect($packages->shipsResource('core'))->toBeTrue()
        ->and($packages->shipsResource('wire-v2-upgrade'))->toBeTrue()
        ->and($packages->shipsResource('our-house-style'))->toBeTrue();
});
