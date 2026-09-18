<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Install;

use NyonCode\WireCore\Foundation\Setup\ClassSource;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * Give the user model what the Fortify features switched on need.
 *
 * `fortify:install` publishes a config with two-factor and passkeys on, and the
 * migrations for both — and leaves the model alone. So an application set up by
 * `wire:install` offered "Sign in with a passkey" on its sign-in screen and had
 * no way to register one: the profile's passkey card and two-factor card only
 * appear for a model that can hold them, and Fortify's own passkey routes
 * resolve a `PasskeyUser`. Found on a clean install, where neither worked.
 *
 * What is added follows the published config, not the defaults: a feature the
 * application switched off (the question {@see ConfigureFortify} asks) gets
 * nothing. And only where the package behind the feature is installed —
 * `laravel/passkeys` arrives with Fortify's passkeys, but a trait that does not
 * exist would take the whole application down on its next request.
 *
 * Runs after {@see ConfigureFortify} (40), whose answers it reads, and before the
 * migrations (100).
 */
final readonly class PrepareUserModel implements SetupStep
{
    /**
     * Feature => what the user model needs for it: traits, then interfaces.
     *
     * @var array<string, array{0: list<class-string>, 1: list<class-string>}>
     */
    public const NEEDS = [
        'twoFactorAuthentication' => [['Laravel\\Fortify\\TwoFactorAuthenticatable'], []],
        'passkeys' => [['Laravel\\Passkeys\\PasskeyAuthenticatable'], ['Laravel\\Passkeys\\Contracts\\PasskeyUser']],
    ];

    /** How each feature is named to a person reading the installer. */
    private const LABELS = [
        'twoFactorAuthentication' => 'two-factor',
        'passkeys' => 'passkeys',
    ];

    public function label(): string
    {
        return 'User model';
    }

    public function state(): SetupState
    {
        $model = ClassSource::userModel();

        if ($model === null || ! is_file(config_path('fortify.php'))) {
            return SetupState::Blocked;
        }

        return $this->missing($model) === [] ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        $model = ClassSource::userModel();

        if ($model === null) {
            return 'no app/Models/User.php to prepare';
        }

        if (! is_file(config_path('fortify.php'))) {
            return 'needs Fortify set up first — there is no config/fortify.php';
        }

        $missing = $this->missing($model);

        return $missing === []
            ? 'the user model can hold what the sign-in features store'
            : 'let the user model hold '.implode(' and ', array_map(static fn (string $feature): string => self::LABELS[$feature], array_keys($missing))).', or those features are switched on and do nothing';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        $model = ClassSource::userModel();

        if ($model === null) {
            return SetupOutcome::Skipped;
        }

        $missing = $this->missing($model);

        foreach ($missing as [$traits, $interfaces]) {
            foreach ($traits as $trait) {
                $model->addTrait($trait);
            }

            foreach ($interfaces as $interface) {
                $model->addInterface($interface);
            }
        }

        if (! $model->save()) {
            $console->warn('Could not write '.$model->path().' — add these to the class yourself: '.$this->names($missing).'.');

            return SetupOutcome::Failed;
        }

        if ($missing !== []) {
            $console->note('Added to the user model: '.$this->names($missing).'.');
        }

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-auth';
    }

    public function sort(): int
    {
        return 45;
    }

    /**
     * The switched-on features whose needs the model does not meet yet.
     *
     * @return array<string, array{0: list<class-string>, 1: list<class-string>}>
     */
    private function missing(ClassSource $model): array
    {
        $states = (new FortifyFeatures((string) file_get_contents(config_path('fortify.php'))))
            ->states(array_keys(self::NEEDS));

        $missing = [];

        foreach (self::NEEDS as $feature => [$traits, $interfaces]) {
            // Switched on, installed, and not there yet — a trait whose package
            // is missing would take the application down on its next request.
            if (($states[$feature] ?? false) === true && self::installed([...$traits, ...$interfaces]) && $model->needs($traits, $interfaces)) {
                $missing[$feature] = [$traits, $interfaces];
            }
        }

        return $missing;
    }

    /** @param  list<class-string>  $classes */
    private static function installed(array $classes): bool
    {
        return array_filter($classes, static fn (string $class): bool => ! trait_exists($class) && ! interface_exists($class)) === [];
    }

    /** @param  array<string, array{0: list<class-string>, 1: list<class-string>}>  $missing */
    private function names(array $missing): string
    {
        $classes = [];

        foreach ($missing as [$traits, $interfaces]) {
            foreach ([...$traits, ...$interfaces] as $class) {
                $parts = explode('\\', $class);
                $classes[] = (string) end($parts);
            }
        }

        return implode(', ', $classes);
    }
}
