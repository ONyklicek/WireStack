<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Install;

use Illuminate\Support\Facades\Process;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/**
 * The build without which the admin renders, correctly, with no styling at all.
 *
 * This package's installer points Tailwind at `vendor/nyoncode` and defines the
 * `primary` palette, and both are instructions to a build that has not run. Until
 * it does, every class in every packaged view is one Tailwind never compiled:
 * the shell has no width, no colour and no spacing, and nothing anywhere says
 * so. It is the most alarming way a correct installation can look.
 *
 * ## Why this one may run a subprocess when the composer rule says not to
 *
 * `wire:install` refuses to shell out to composer, and the reason is specific
 * rather than general: composer rewrites the autoloader of the very process
 * calling it. `npm` does not. It writes into `public/build` and touches nothing
 * PHP is holding, so the objection does not carry over — and an installer that
 * can leave the admin looking finished is worth the subprocess.
 *
 * It still only runs what is there. No `npm` on the path is a deployment or a
 * container, not a broken install, and the answer there is the two commands
 * rather than a failure.
 */
final class BuildFrontend implements SetupStep
{
    public function label(): string
    {
        return 'Frontend build';
    }

    public function state(): SetupState
    {
        if (! is_file(base_path('package.json'))) {
            // Nothing to build. An application serving prebuilt assets, or one
            // that is not a Laravel skeleton, is complete as it stands.
            return SetupState::Done;
        }

        return $this->built() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! is_file(base_path('package.json'))) {
            return 'no package.json, so there is nothing to build';
        }

        return $this->built()
            ? 'assets are built'
            : 'build the assets, or the admin renders with no styling and no error';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        if (! $this->hasNpm()) {
            $console->warn('No npm on this machine. Run `npm install && npm run build` where there is one.');

            return SetupOutcome::Skipped;
        }

        if (! is_dir(base_path('node_modules'))) {
            $console->note('Installing node modules — this takes a minute.');

            if (! $this->run('npm install')) {
                $console->warn('`npm install` did not finish. Run it yourself to see why.');

                return SetupOutcome::Failed;
            }
        }

        $console->note('Building.');

        if (! $this->run('npm run build')) {
            $console->warn('`npm run build` did not finish. Run it yourself to see why.');

            return SetupOutcome::Failed;
        }

        $console->note('Assets built.');

        return SetupOutcome::Applied;
    }

    public function sort(): int
    {
        // Last. It is the slowest thing here and the only one that needs
        // nothing else to have worked, so it is the one to be waiting on while
        // everything else is already done.
        return 900;
    }

    /**
     * Whether Vite has written a manifest.
     *
     * The manifest rather than the directory: `public/build` survives a failed
     * build, a cleared `rm -rf public/build/assets`, and an application that
     * once had one — the manifest is what `@vite` actually reads.
     */
    private function built(): bool
    {
        $directory = public_path('build');

        return is_file($directory.'/manifest.json') || is_file($directory.'/.vite/manifest.json');
    }

    private function hasNpm(): bool
    {
        return Process::run((PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v').' npm')->successful();
    }

    private function run(string $command): bool
    {
        return Process::path(base_path())->timeout(600)->run($command)->successful();
    }
}
