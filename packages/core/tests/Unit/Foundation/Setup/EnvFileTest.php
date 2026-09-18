<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\EnvFile;

/*
 * The application's `.env`, as something a setup step can write one line into.
 *
 * Every switch these steps flip is `env(...)` in a published config file, so
 * there are two places to write and only one of them is safe: a config is PHP,
 * and editing it means a parser or a regular expression over somebody's source.
 */

function envAt(string $contents): EnvFile
{
    $path = sys_get_temp_dir().'/wire-env-'.getmypid().'-'.uniqid().'.env';
    file_put_contents($path, $contents);

    register_shutdown_function(static fn () => @unlink($path));

    return new EnvFile($path);
}

it('reads a key it was given', function () {
    $env = envAt("APP_NAME=Laravel\nWIRE_AUDIT_ENABLED=false\n");

    expect($env->exists())->toBeTrue()
        ->and($env->get('WIRE_AUDIT_ENABLED'))->toBe('false')
        ->and($env->get('APP_NAME'))->toBe('Laravel')
        ->and($env->get('NOT_THERE'))->toBeNull();
});

it('reads a quoted value without its quotes', function () {
    expect(envAt("A=\"one, two\"\nB='three'\n")->get('A'))->toBe('one, two')
        ->and(envAt("A=\"one, two\"\nB='three'\n")->get('B'))->toBe('three');
});

it('answers nothing at all for a file that is not there', function () {
    // An application running on real environment variables with no `.env` is a
    // deployment, not a broken install — the steps read this and leave it be.
    $env = new EnvFile(sys_get_temp_dir().'/wire-env-absent-'.uniqid().'.env');

    expect($env->exists())->toBeFalse()
        ->and($env->get('ANYTHING'))->toBeNull()
        ->and($env->set('ANYTHING', 'x'))->toBeFalse();
});

it('replaces the line a key is on, rather than adding a second', function () {
    // A `.env` with the same key twice is read as whichever came last, which is
    // a bug nobody looks for.
    $env = envAt("APP_NAME=Laravel\nWIRE_AUDIT_ENABLED=false\nAPP_ENV=local\n");

    expect($env->set('WIRE_AUDIT_ENABLED', 'true'))->toBeTrue()
        ->and($env->get('WIRE_AUDIT_ENABLED'))->toBe('true');

    $contents = (string) file_get_contents((new ReflectionProperty($env, 'path'))->getValue($env));

    expect(substr_count($contents, 'WIRE_AUDIT_ENABLED='))->toBe(1)
        ->and($contents)->toContain('APP_NAME=Laravel')
        ->and($contents)->toContain('APP_ENV=local');
});

it('adds a key that was not there, at the end', function () {
    $env = envAt('APP_NAME=Laravel');

    expect($env->set('WIRE_AUDIT_ENABLED', 'true'))->toBeTrue()
        ->and($env->get('APP_NAME'))->toBe('Laravel')
        ->and($env->get('WIRE_AUDIT_ENABLED'))->toBe('true');
});

it('quotes a value the parser would otherwise cut short', function () {
    // A bare value ends at the first space, comma or `#`, so a driver list
    // written as `session, database` would silently become `session,`.
    $env = envAt("APP_NAME=Laravel\n");

    $env->set('WITH_COMMA', 'session,database');
    $env->set('WITH_SPACE', 'two words');
    $env->set('PLAIN', 'redis');

    $contents = (string) file_get_contents((new ReflectionProperty($env, 'path'))->getValue($env));

    expect($contents)->toContain('WITH_COMMA="session,database"')
        ->and($contents)->toContain('WITH_SPACE="two words"')
        ->and($contents)->toContain('PLAIN=redis')
        ->and($env->get('WITH_COMMA'))->toBe('session,database');
});

it('is bound to the application\'s own .env', function () {
    expect((new ReflectionProperty(EnvFile::forApplication(), 'path'))->getValue(EnvFile::forApplication()))
        ->toBe(base_path('.env'));
});

it('refuses to write a file it cannot write', function () {
    $path = sys_get_temp_dir().'/wire-env-ro-'.uniqid().'.env';
    file_put_contents($path, "A=1\n");
    chmod($path, 0444);

    try {
        expect((new EnvFile($path))->set('A', '2'))->toBeFalse();
    } finally {
        chmod($path, 0644);
        @unlink($path);
    }
});
