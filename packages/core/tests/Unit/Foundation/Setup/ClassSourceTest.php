<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\ClassSource;

/*
 * An application class's source, edited the way a person would edit it.
 *
 * What a setup step writes into the application's user model has to read as if
 * somebody typed it, has to leave the file valid PHP, and has to be a no-op the
 * second time — installers run twice.
 */

/** The user model a fresh Laravel 13 application ships. */
const CS_FRESH_USER = <<<'PHP'
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $hidden = ['password'];
}
PHP;

function csFile(string $contents = CS_FRESH_USER): string
{
    $path = sys_get_temp_dir().'/wire-class-source-'.bin2hex(random_bytes(4)).'.php';
    file_put_contents($path, $contents);

    return $path;
}

function csValid(string $path): bool
{
    exec('php -l '.escapeshellarg($path).' 2>&1', $output, $code);

    return $code === 0;
}

it('adds a trait the way a person would: an import, and a use in the class body', function () {
    $path = csFile();

    $source = new ClassSource($path);

    expect($source->needs(['Laravel\Fortify\TwoFactorAuthenticatable']))->toBeTrue();

    $source->addTrait('Laravel\Fortify\TwoFactorAuthenticatable');

    expect($source->save())->toBeTrue()
        ->and(csValid($path))->toBeTrue();

    $contents = (string) file_get_contents($path);

    expect($contents)->toContain("use Laravel\\Fortify\\TwoFactorAuthenticatable;\n")
        ->toContain('    use TwoFactorAuthenticatable;')
        ->and((new ClassSource($path))->usesTrait('Laravel\Fortify\TwoFactorAuthenticatable'))->toBeTrue();

    unlink($path);
});

it('adds an interface to a class that implements nothing, and to one that already implements something', function () {
    $path = csFile();

    (new ClassSource($path))->addInterface('Laravel\Passkeys\Contracts\PasskeyUser')->save();

    expect((string) file_get_contents($path))->toContain('class User extends Authenticatable implements PasskeyUser')
        ->and(csValid($path))->toBeTrue();

    (new ClassSource($path))->addInterface('Illuminate\Contracts\Auth\MustVerifyEmail')->save();

    expect((string) file_get_contents($path))->toContain('implements PasskeyUser, MustVerifyEmail')
        ->and(csValid($path))->toBeTrue();

    unlink($path);
});

it('changes nothing the second time', function () {
    $path = csFile();

    (new ClassSource($path))
        ->addTrait('Laravel\Passkeys\PasskeyAuthenticatable')
        ->addInterface('Laravel\Passkeys\Contracts\PasskeyUser')
        ->save();

    $once = (string) file_get_contents($path);

    $again = (new ClassSource($path))
        ->addTrait('Laravel\Passkeys\PasskeyAuthenticatable')
        ->addInterface('Laravel\Passkeys\Contracts\PasskeyUser');

    expect($again->needs(['Laravel\Passkeys\PasskeyAuthenticatable'], ['Laravel\Passkeys\Contracts\PasskeyUser']))->toBeFalse()
        ->and($again->save())->toBeTrue()
        ->and((string) file_get_contents($path))->toBe($once);

    unlink($path);
});

it('finds a trait that is already there among others on one line', function () {
    // The way an application writes it by hand: `use HasFactory, Notifiable, TwoFactorAuthenticatable;`.
    $path = csFile(str_replace(
        ["use Illuminate\\Notifications\\Notifiable;\n", 'use HasFactory, Notifiable;'],
        ["use Illuminate\\Notifications\\Notifiable;\nuse Laravel\\Fortify\\TwoFactorAuthenticatable;\n", 'use HasFactory, Notifiable, TwoFactorAuthenticatable;'],
        CS_FRESH_USER,
    ));

    expect((new ClassSource($path))->usesTrait('Laravel\Fortify\TwoFactorAuthenticatable'))->toBeTrue()
        // Used but never imported is not the same class, and is reported as missing.
        ->and((new ClassSource($path))->usesTrait('Other\Package\Notifiable'))->toBeFalse();

    unlink($path);
});

it('imports after the namespace in a file that has no imports yet', function () {
    $path = csFile("<?php\n\nnamespace App\\Models;\n\nclass User\n{\n}\n");

    (new ClassSource($path))->addTrait('Laravel\Fortify\TwoFactorAuthenticatable')->save();

    expect((string) file_get_contents($path))->toContain("namespace App\\Models;\n\nuse Laravel\\Fortify\\TwoFactorAuthenticatable;")
        ->and(csValid($path))->toBeTrue();

    unlink($path);
});

it('reports a file it cannot write rather than throwing', function () {
    $path = csFile();
    chmod($path, 0444);

    $source = (new ClassSource($path))->addTrait('Laravel\Fortify\TwoFactorAuthenticatable');

    try {
        expect($source->save())->toBeFalse();
    } finally {
        chmod($path, 0644);
        unlink($path);
    }
});

it('finds the application s user model where Laravel puts it, or says there is none', function () {
    $models = app_path('Models');
    $existed = is_file($models.'/User.php');

    if (! $existed) {
        @mkdir($models, 0755, true);
        file_put_contents($models.'/User.php', CS_FRESH_USER);
    }

    try {
        expect(ClassSource::userModel()?->path())->toBe($models.'/User.php')
            ->and(ClassSource::userModel()?->contents())->toContain('class User');
    } finally {
        if (! $existed) {
            unlink($models.'/User.php');
        }
    }

    if (! is_file($models.'/User.php') && ! is_file(app_path('User.php'))) {
        expect(ClassSource::userModel())->toBeNull();
    }
});

it('leaves a file with no class declaration alone', function () {
    $path = csFile("<?php\n\nreturn [];\n");

    $source = (new ClassSource($path))->addInterface('Laravel\Passkeys\Contracts\PasskeyUser');

    expect($source->contents())->not->toContain('implements');

    unlink($path);
});
