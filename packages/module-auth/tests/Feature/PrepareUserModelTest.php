<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleAuth\Install\PrepareUserModel;

/*
 * The user model, made ready for the sign-in features that were switched on.
 *
 * Found on a clean install: Fortify's config had two-factor and passkeys on,
 * the migrations for both had run, and the user model had neither trait — so
 * the sign-in screen offered "Sign in with a passkey" to accounts that could
 * never have one, and the profile drew no two-factor or passkey card at all.
 */

function pumConsole(array &$said): SetupConsole
{
    return new class($said) implements SetupConsole
    {
        public function __construct(private array &$said) {}

        public function ask(string $question, ?string $default = null): string
        {
            return (string) $default;
        }

        public function secret(string $question): string
        {
            return '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return (string) $default;
        }

        public function select(string $question, array $options, array $default = []): array
        {
            return $default;
        }

        public function note(string $message): void
        {
            $this->said[] = $message;
        }

        public function warn(string $message): void
        {
            $this->said[] = $message;
        }

        public function isInteractive(): bool
        {
            return false;
        }
    };
}

/** The features block the way `fortify:install` publishes it, with some switched off. */
function pumFortify(bool $twoFactor = true, bool $passkeys = true): void
{
    $two = $twoFactor ? '' : '// ';
    $pass = $passkeys ? '' : '// ';

    file_put_contents(config_path('fortify.php'), <<<PHP
        <?php

        use Laravel\\Fortify\\Features;

        return [
            'home' => '/home',

            'features' => [
                Features::registration(),
                Features::resetPasswords(),
                {$two}Features::twoFactorAuthentication([
                {$two}    'confirm' => true,
                {$two}]),
                {$pass}Features::passkeys([
                {$pass}    'confirmPassword' => true,
                {$pass}]),
            ],
        ];
        PHP);
}

/** The user model of a fresh Laravel application. */
function pumModel(): void
{
    @mkdir(app_path('Models'), 0755, true);

    file_put_contents(app_path('Models/User.php'), <<<'PHP'
        <?php

        namespace App\Models;

        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Foundation\Auth\User as Authenticatable;
        use Illuminate\Notifications\Notifiable;

        class User extends Authenticatable
        {
            use HasFactory, Notifiable;
        }
        PHP);
}

beforeEach(function () {
    $this->kept = [];

    foreach ([config_path('fortify.php'), app_path('Models/User.php')] as $path) {
        $this->kept[$path] = is_file($path) ? file_get_contents($path) : null;
    }

    $this->step = new PrepareUserModel;
});

afterEach(function () {
    foreach ($this->kept as $path => $contents) {
        $contents === null ? @unlink($path) : file_put_contents($path, $contents);
    }
});

it('is registered, after Fortify and before the migrations', function () {
    expect(SetupRegistry::instance()->all())->toContain(PrepareUserModel::class)
        ->and($this->step->sort())->toBeGreaterThan(40)->toBeLessThan(100)
        ->and($this->step->package())->toBe('nyoncode/wire-module-auth')
        ->and($this->step->label())->toBe('User model');
});

it('gives the model the two-factor trait and the passkey trait and contract', function () {
    pumFortify();
    pumModel();

    expect($this->step->state())->toBe(SetupState::Pending)
        ->and($this->step->summary())->toContain('two-factor')->toContain('passkeys');

    $said = [];
    expect($this->step->apply(pumConsole($said)))->toBe(SetupOutcome::Applied);

    $model = (string) file_get_contents(app_path('Models/User.php'));

    expect($model)->toContain('use Laravel\Fortify\TwoFactorAuthenticatable;')
        ->toContain('use Laravel\Passkeys\PasskeyAuthenticatable;')
        ->toContain('use Laravel\Passkeys\Contracts\PasskeyUser;')
        ->toContain('implements PasskeyUser')
        ->toContain('    use TwoFactorAuthenticatable;')
        ->toContain('    use PasskeyAuthenticatable;')
        ->and(implode("\n", $said))->toContain('TwoFactorAuthenticatable, PasskeyAuthenticatable, PasskeyUser');

    exec('php -l '.escapeshellarg(app_path('Models/User.php')).' 2>&1', $output, $code);
    expect($code)->toBe(0);

    // Done now, and a second run writes nothing.
    expect($this->step->state())->toBe(SetupState::Done)
        ->and($this->step->summary())->toContain('can hold');

    $said = [];
    $this->step->apply(pumConsole($said));

    expect((string) file_get_contents(app_path('Models/User.php')))->toBe($model)
        ->and($said)->toBe([]);
});

it('follows the published config: a feature switched off gets nothing', function () {
    pumFortify(twoFactor: true, passkeys: false);
    pumModel();

    $said = [];
    $this->step->apply(pumConsole($said));

    expect((string) file_get_contents(app_path('Models/User.php')))
        ->toContain('TwoFactorAuthenticatable')
        ->not->toContain('Passkey');
});

it('has nothing to do when both are off', function () {
    pumFortify(twoFactor: false, passkeys: false);
    pumModel();

    expect($this->step->state())->toBe(SetupState::Done);
});

it('waits for Fortify, and for a model to prepare', function () {
    @unlink(config_path('fortify.php'));
    pumModel();

    expect($this->step->state())->toBe(SetupState::Blocked)
        ->and($this->step->summary())->toContain('Fortify');

    pumFortify();
    @unlink(app_path('Models/User.php'));

    expect($this->step->state())->toBe(SetupState::Blocked)
        ->and($this->step->summary())->toContain('app/Models/User.php');

    $said = [];
    expect($this->step->apply(pumConsole($said)))->toBe(SetupOutcome::Skipped);
});

it('reports a model it cannot write and says what to add by hand', function () {
    pumFortify();
    pumModel();
    chmod(app_path('Models/User.php'), 0444);

    try {
        $said = [];
        expect($this->step->apply(pumConsole($said)))->toBe(SetupOutcome::Failed)
            ->and(implode("\n", $said))->toContain('PasskeyAuthenticatable');
    } finally {
        chmod(app_path('Models/User.php'), 0644);
    }
});
