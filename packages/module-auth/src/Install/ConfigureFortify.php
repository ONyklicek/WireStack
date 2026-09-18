<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Install;

use Illuminate\Contracts\Console\Kernel;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\RedundantMigrations;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * Fortify, installed and turned down to what this application actually offers.
 *
 * This package answers Fortify's seven view callbacks and owns none of the
 * security behind them, which leaves an installation with a gap nothing
 * reports: the screens are registered, the routes are not, and the sign-in page
 * is a 404 until somebody runs `fortify:install` by hand. That command
 * publishes `config/fortify.php` and an `App\Providers\FortifyServiceProvider`,
 * and registers the provider in `bootstrap/providers.php` — three files, none of
 * which a package may write on its own behalf.
 *
 * ## Blocked, not Pending, when Fortify is not installed
 *
 * `laravel/fortify` is this package's own composer dependency, so the normal
 * case is that it is there. It can still be missing — a `--no-dev` install of a
 * fork, a replaced package — and the answer then is a `composer require` line,
 * never an installer that shells out to composer inside the application it is
 * about to change.
 *
 * ## The features are a second question
 *
 * `fortify.features` is the list of things Fortify routes: registration,
 * password reset, e-mail verification, two-factor, passkeys. The config
 * `fortify:install` publishes has some of them on and e-mail verification
 * commented out, and no application wants exactly that by accident — a panel
 * nobody signs up for should not have a public `/register`. So once the config
 * is published this offers the list, ticked as the file has it, and writes the
 * answer by commenting or uncommenting each feature's lines.
 *
 * It edits only the shapes Fortify publishes, which {@see FortifyFeatures}
 * reads — one line, or an options array over several. An application that has
 * already rewritten the array keeps its rewrite and is told so, because a
 * regular expression that keeps looking through somebody's edited config
 * eventually matches the wrong thing.
 */
final readonly class ConfigureFortify implements SetupStep
{
    /**
     * The features worth asking about, in the order Fortify lists them.
     *
     * Not every feature Fortify has: `updateProfileInformation` and
     * `updatePasswords` are what the users module's own profile cards are built
     * on, so turning them off breaks a screen this stack ships. They stay on and
     * stay out of the question.
     *
     * @var array<string, string>
     */
    private const FEATURES = [
        'registration' => 'Registration — a public sign-up page',
        'resetPasswords' => 'Password reset — the "forgot password" flow',
        'emailVerification' => 'E-mail verification — a signed link before the panel opens',
        'twoFactorAuthentication' => 'Two-factor — an authenticator app, with recovery codes',
        'passkeys' => 'Passkeys — signing in with a device rather than a password',
    ];

    public function __construct(private Kernel $artisan, private RedundantMigrations $migrations) {}

    public function label(): string
    {
        return 'Fortify';
    }

    public function state(): SetupState
    {
        if (! $this->installed()) {
            return SetupState::Blocked;
        }

        return $this->published() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! $this->installed()) {
            return 'run `composer require laravel/fortify` — it owns the sign-in these screens draw';
        }

        return $this->published()
            ? 'its routes are registered, so the sign-in screens are reachable'
            : 'publish its config and provider, or the sign-in screens have no routes';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        // `fortify:install` publishes the two-factor columns and the passkeys
        // table whatever the application has, and an application that already
        // has either failed its next `migrate` on a column or table that exists.
        $code = $this->migrations->around(
            fn (): int => $this->artisan->call('fortify:install'),
            static fn (string $name) => $console->note("Left out Fortify's {$name} migration — this application already has it."),
        );

        if ($code !== 0) {
            $console->warn('php artisan fortify:install did not finish — run it yourself to see why.');

            return SetupOutcome::Failed;
        }

        $console->note('Published config/fortify.php and registered its provider.');

        $this->chooseFeatures($console);

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-auth';
    }

    public function sort(): int
    {
        // Before the migrations. Fortify's own migration adds the two-factor
        // columns to the users table, and a `migrate` that has already run does
        // not come back for it.
        return 40;
    }

    /**
     * Whether there is anything here to run.
     *
     * Measured off `fortify:install` being registered rather than off the
     * `Fortify` class existing, and the difference is the case that bites: a
     * package can be in `vendor` while the application has excluded its
     * provider from discovery, and then the class is autoloadable, the command
     * is not, and calling it aborts the whole run with a message about Symfony's
     * console. What this step needs to know is whether the publish can happen.
     */
    private function installed(): bool
    {
        return array_key_exists('fortify:install', $this->artisan->all());
    }

    /**
     * Whether `fortify:install` has already been run.
     *
     * The config file, because that is the file the command's own work is read
     * back from and the one every later question here needs. The provider it
     * also writes lives in the application's namespace, which nothing in a
     * package may assume the name of.
     */
    private function published(): bool
    {
        return is_file(config_path('fortify.php'));
    }

    /**
     * Ask which features this application has, and write the answer.
     *
     * Offered as the file has them — ticked where the published config has the
     * feature on — so pressing enter changes nothing, and the question is only
     * ever about the difference. Ticking one uncomments it; unticking comments
     * it out. {@see FortifyFeatures} owns what a feature's lines look like.
     *
     * Unattended runs change nothing: leaving the file as Fortify published it
     * is recoverable in one line, and switching a feature without being asked
     * is a sign-in page that has silently lost its password reset.
     */
    private function chooseFeatures(SetupConsole $console): void
    {
        if (! $console->isInteractive()) {
            return;
        }

        $path = config_path('fortify.php');
        $features = new FortifyFeatures((string) file_get_contents($path));
        $states = $features->states(array_keys(self::FEATURES));

        if ($states === []) {
            $console->note('config/fortify.php has its own feature list — left as it is.');

            return;
        }

        $on = array_keys(array_filter($states));
        $keep = $console->select(
            'Which sign-in features should this application have?',
            array_intersect_key(self::FEATURES, $states),
            $on,
        );

        $switchedOn = array_values(array_diff($keep, $on));
        $switchedOff = array_values(array_diff($on, $keep));

        if ($switchedOn === [] && $switchedOff === []) {
            return;
        }

        foreach (array_keys($states) as $feature) {
            $features->set($feature, in_array($feature, $keep, true));
        }

        if (! is_writable($path) || @file_put_contents($path, $features->contents()) === false) {
            $console->warn('Could not write config/fortify.php — switch the features there by hand.');

            return;
        }

        if ($switchedOn !== []) {
            $console->note('Switched on: '.implode(', ', $switchedOn).'.');
        }

        if ($switchedOff !== []) {
            $console->note('Switched off: '.implode(', ', $switchedOff).'.');
        }
    }
}
