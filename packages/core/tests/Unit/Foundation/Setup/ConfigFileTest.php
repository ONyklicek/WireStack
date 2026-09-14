<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\ConfigFile;

/*
 * A published config file, as the one line a setup step is allowed to change.
 *
 * `EnvFile` is the first answer and the better one. This is for the switches
 * with no env behind them — `permission.teams` above all, which is what puts
 * the team column into the permission cache key and is read from Spatie's own
 * published file and nowhere else.
 *
 * Every test here is about the refusals, because editing somebody's source is
 * a thing to do rarely and never by guessing.
 */

function configAt(string $contents): ConfigFile
{
    $path = sys_get_temp_dir().'/wire-config-'.getmypid().'-'.uniqid().'.php';
    file_put_contents($path, $contents);

    register_shutdown_function(static fn () => @unlink($path));

    return new ConfigFile($path);
}

function configBody(ConfigFile $file): string
{
    return (string) file_get_contents($file->path());
}

it('rewrites the one line a key is on', function () {
    $file = configAt("<?php\n\nreturn [\n    'teams' => false,\n    'column_names' => [],\n];\n");

    expect($file->exists())->toBeTrue()
        ->and($file->set('teams', 'true'))->toBeTrue()
        ->and(configBody($file))->toContain("    'teams' => true,")
        ->and(configBody($file))->toContain("'column_names' => [],");
});

it('keeps the indentation it found', function () {
    // The file is somebody's source and stays readable as their source.
    $file = configAt("<?php\n\nreturn [\n    'teams' => [\n        'enabled' => false,\n    ],\n];\n");

    expect($file->set('enabled', 'true'))->toBeTrue()
        ->and(configBody($file))->toContain("        'enabled' => true,");
});

it('writes the literal it was handed, quotes and all', function () {
    $file = configAt("<?php\n\nreturn [\n    'relation' => 'teams',\n];\n");

    expect($file->set('relation', "'squads'"))->toBeTrue()
        ->and(configBody($file))->toContain("'relation' => 'squads',");
});

it('refuses a key that is there more than once', function () {
    // Two sections naming the same key, and nothing here can tell which one the
    // caller meant. A regular expression that picked the first would be right
    // about half the time.
    $file = configAt("<?php\n\nreturn [\n    'a' => [\n        'teams' => false,\n    ],\n    'teams' => false,\n];\n");

    expect($file->set('teams', 'true'))->toBeFalse()
        ->and(configBody($file))->not->toContain('true');
});

it('refuses a key it cannot find', function () {
    $file = configAt("<?php\n\nreturn [\n    'teams' => false,\n];\n");

    expect($file->set('unheard_of', 'true'))->toBeFalse();
});

it('refuses a value that opens on its line and closes on another', function () {
    // Replacing the first line of a multi-line value leaves the rest stranded
    // in the file as a syntax error.
    $file = configAt("<?php\n\nreturn [\n    'teams' => [\n        'enabled' => false,\n    ],\n];\n");

    expect($file->set('teams', 'true'))->toBeFalse()
        ->and(configBody($file))->toContain("'enabled' => false,");
});

it('answers nothing at all for a file that was never published', function () {
    $file = new ConfigFile(sys_get_temp_dir().'/wire-config-absent-'.uniqid().'.php');

    expect($file->exists())->toBeFalse()
        ->and($file->set('teams', 'true'))->toBeFalse();
});

it('refuses a file it cannot write', function () {
    $file = configAt("<?php\n\nreturn [\n    'teams' => false,\n];\n");
    chmod($file->path(), 0444);

    expect($file->set('teams', 'true'))->toBeFalse();

    chmod($file->path(), 0644);
})->skipOnWindows();

it('names the file it is about', function () {
    // A step that cannot write says which file to change by hand, so the path
    // has to be askable.
    expect(ConfigFile::forApplication('permission')->path())->toBe(config_path('permission.php'));
});
