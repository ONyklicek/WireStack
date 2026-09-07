<?php

declare(strict_types=1);

use NyonCode\WireModuleAuth\Exceptions\AuthFrameException;
use NyonCode\WireModuleAuth\Install\LayoutScaffold;

/*
 * The one file `composer require` cannot write.
 *
 * The installer's own test drives it through the command; this covers the two
 * answers the command never sees — a base path it can write into from nothing,
 * and one it cannot.
 */

it('creates the directories it needs on the way', function () {
    // A fresh application has no `resources/views/components/layouts` at all,
    // which is the ordinary case rather than an edge one.
    $base = sys_get_temp_dir().'/wire-auth-scaffold-'.bin2hex(random_bytes(4));

    try {
        expect((new LayoutScaffold($base))->write())->toBeTrue()
            ->and($base.'/'.LayoutScaffold::PATH)->toBeFile()
            // Written a second time, it changes nothing: an application that ran
            // the installer, edited the file and ran it again keeps its edits.
            ->and((new LayoutScaffold($base))->write())->toBeFalse();
    } finally {
        @unlink($base.'/'.LayoutScaffold::PATH);

        foreach (['/resources/views/components/layouts', '/resources/views/components', '/resources/views', '/resources'] as $dir) {
            @rmdir($base.$dir);
        }

        @rmdir($base);
    }
});

it('says which file to copy when it cannot write at all', function () {
    // A file where the directory has to be: `mkdir` fails, and so does the
    // `is_dir` recheck that covers a parallel run having made it. The message
    // names the stub, because copying it by hand is the whole remedy.
    $base = sys_get_temp_dir().'/wire-auth-scaffold-'.bin2hex(random_bytes(4));

    @mkdir($base.'/resources/views/components', 0755, true);
    file_put_contents($base.'/resources/views/components/layouts', 'not a directory');

    try {
        expect(fn () => (new LayoutScaffold($base))->write())
            ->toThrow(AuthFrameException::class, 'auth-layout.blade.stub');
    } finally {
        @unlink($base.'/resources/views/components/layouts');

        foreach (['/resources/views/components', '/resources/views', '/resources'] as $dir) {
            @rmdir($base.$dir);
        }

        @rmdir($base);
    }
});
