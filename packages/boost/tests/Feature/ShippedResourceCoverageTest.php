<?php

declare(strict_types=1);

use NyonCode\WireBoost\Support\WirePackages;
use Symfony\Component\Finder\Finder;

/**
 * The gate over what wire-boost knows about.
 *
 * Nothing else checks this. A package can ship, document itself and pass every
 * suite while the guidelines and skills still describe the stack as it was two
 * releases ago — that is exactly how `wire-panels` stayed unreported long after
 * it existed. The repository's Stop hook nudges a human; it is local, gitignored
 * and absent in CI, so the ratchet lives here.
 *
 * Both directions matter. A package with nothing written for it is a hole, and a
 * file named after a package that does not exist (`wire-panel.blade.php`, one
 * letter short) is worse: it ships to nobody and the suite stays green.
 */

/**
 * Packages that deliberately have no guideline of their own, and why.
 *
 * @var array<string, string>
 */
const GUIDELINE_EXCEPTIONS = [
    'wire-boost' => 'The agent uses boost; it does not build against it.',
    'wire-module-auth' => 'Covered by the wire-modules guideline.',
    'wire-module-users' => 'Covered by the wire-modules guideline.',
    'wire-module-settings' => 'Covered by the wire-modules guideline.',
    'wire-module-audit' => 'Covered by the wire-modules guideline.',
    'wire-module-notifications' => 'Covered by the wire-modules guideline.',
    'wire-module-media' => 'Covered by the wire-modules guideline.',
];

/**
 * The same, for skills.
 *
 * @var array<string, string>
 */
const SKILL_EXCEPTIONS = GUIDELINE_EXCEPTIONS + [
    'wire-suite' => 'Installing is a command to run, not a workflow to develop against.',
];

/**
 * Shipped resources that are not named after a package and never filtered.
 *
 * @var array<int, string>
 */
const UNNAMED_RESOURCES = [
    'core',
    'wire-v2-upgrade',
];

/**
 * @return array<int, string>
 */
function guidelineNames(): array
{
    $names = [];

    foreach (Finder::create()->files()->in(resourcePath('guidelines'))->name(['*.md', '*.blade.php']) as $file) {
        $names[] = (string) preg_replace('/\.(md|blade\.php)$/', '', $file->getFilename());
    }

    sort($names);

    return $names;
}

/**
 * @return array<int, string>
 */
function skillNames(): array
{
    $names = [];

    foreach (Finder::create()->directories()->in(resourcePath('skills'))->depth(0) as $directory) {
        $names[] = $directory->getFilename();
    }

    sort($names);

    return $names;
}

function resourcePath(string $kind): string
{
    return dirname(__DIR__, 2).'/resources/boost/'.$kind;
}

/**
 * The package a shipped resource speaks for, or null when it speaks for none.
 */
function resourcePackage(string $name): ?string
{
    $name = (string) preg_replace('/-development$/', '', $name);

    return in_array($name, [...WirePackages::all(), WirePackages::MODULES_GROUP], true) ? $name : null;
}

it('ships a guideline for every package that has one to write', function () {
    $covered = array_filter(array_map(resourcePackage(...), guidelineNames()));
    $missing = array_diff(WirePackages::all(), $covered, array_keys(GUIDELINE_EXCEPTIONS));

    expect($missing)->toBe([], 'Add a guideline for '.implode(', ', $missing)
        .', or record it in GUIDELINE_EXCEPTIONS with the reason.');
});

it('ships a skill for every package that has a workflow', function () {
    $covered = array_filter(array_map(resourcePackage(...), skillNames()));
    $missing = array_diff(WirePackages::all(), $covered, array_keys(SKILL_EXCEPTIONS));

    expect($missing)->toBe([], 'Add a skill for '.implode(', ', $missing)
        .', or record it in SKILL_EXCEPTIONS with the reason.');
});

it('covers the modules with the grouped guideline and skill', function () {
    // The six modules are excepted individually because one pair speaks for all
    // of them. If that pair ever went missing the exceptions would hide it.
    expect(guidelineNames())->toContain(WirePackages::MODULES_GROUP)
        ->and(skillNames())->toContain(WirePackages::MODULES_GROUP.'-development');
});

it('names every shipped resource after a real package, or after nothing at all', function () {
    // `wire-panel.blade.php` is one letter short of a package name: it would be
    // filtered out of every application and never noticed.
    foreach ([...guidelineNames(), ...skillNames()] as $name) {
        $recognised = resourcePackage($name) !== null || in_array($name, UNNAMED_RESOURCES, true);

        expect($recognised)->toBeTrue(
            "[{$name}] is named after no known package. Rename it, or add it to UNNAMED_RESOURCES if it is meant to ship everywhere."
        );
    }
});

it('reports the companion packages a module switches surfaces on for', function () {
    // "wire-module-users is installed" does not answer whether the role screens
    // exist — the permission package is what decides that.
    $companions = WirePackages::detect()->companions();

    expect($companions)->toHaveKey('livewire/livewire')
        ->and($companions)->toHaveKey('laravel/fortify')
        ->and($companions)->toHaveKey('nyoncode/laravel-permission-extended');
});
