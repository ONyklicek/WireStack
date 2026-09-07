<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * `php artisan wire:install` — a clean Laravel to a working admin, in one pass.
 *
 * It runs each installed package's own installer rather than reimplementing
 * them: every package already knows what it publishes and what it has to say
 * afterwards, and a second copy of that here would drift from the first.
 *
 * `--dry-run` answers "what would this do to my application" without doing any
 * of it, which is the question anyone sensible asks of an installer first — and
 * it is what makes this command testable without publishing files into whatever
 * skeleton the tests are running against.
 *
 * **It never runs composer.** Offering a module that is not installed is the
 * point of the listing, and the answer is the `composer require` line to paste:
 * an artisan command that shells out to composer runs inside the application it
 * is about to change — the autoloader it is using is the one composer is
 * rewriting — and the failure modes (memory, plugins, a production image with no
 * composer at all) are exactly the ones nobody can debug from a stack trace.
 */
class WireInstallCommand extends Command
{
    protected $signature = 'wire:install
        {--all : Install every part that is present, without asking}
        {--dry-run : Show what would be set up and what is available, and change nothing}';

    protected $description = 'Set up the wire stack in this application, interactively.';

    public function handle(Catalogue $catalogue): int
    {
        $this->components->info('Installing wire');

        $installed = $catalogue->installed();
        $missing = $catalogue->missing();

        $this->listing(
            'Found in this application',
            array_map(static fn (Component $c): string => $c->label.' — '.$c->package, $installed),
        );

        $chosen = $this->option('all')
            ? $installed
            : $this->choose($installed);

        $registered = array_keys(Artisan::all());
        $dry = (bool) $this->option('dry-run');

        foreach ($chosen as $component) {
            if ($component->command === null) {
                continue;
            }

            // A class can be autoloadable while its provider is not booted — a
            // `dont-discover` entry, a package registered only in one
            // environment. Calling a command that is not there aborts the whole
            // run with a message about Symfony's console, so it is reported and
            // skipped instead.
            if (! in_array($component->command, $registered, true)) {
                $this->components->warn("{$component->label}: {$component->command} is not registered — is its provider loaded?");

                continue;
            }

            if ($dry) {
                $this->components->twoColumnDetail($component->label, "would run <fg=yellow>php artisan {$component->command}</>");

                continue;
            }

            $this->components->task($component->label, function () use ($component): bool {
                Artisan::call($component->command, [], $this->getOutput());

                return true;
            });
        }

        $this->offerMissing($missing);

        $this->newLine();

        if ($dry) {
            $this->components->info('Nothing was changed. Run it without --dry-run to set these up.');

            return self::SUCCESS;
        }

        $this->components->info('Done. What is left is yours:');
        $this->line('  • php artisan migrate');
        $this->line('  • Route::wireResources() in routes/web.php, inside the middleware you want');

        return self::SUCCESS;
    }

    /**
     * Ask which of the installed parts to set up.
     *
     * Everything is offered pre-selected: an installer whose default is "nothing"
     * makes the common case the tedious one.
     *
     * @param  array<int, Component>  $installed
     * @return array<int, Component>
     */
    protected function choose(array $installed): array
    {
        $runnable = array_values(array_filter($installed, static fn (Component $c): bool => $c->command !== null));

        if ($runnable === [] || ! $this->input->isInteractive()) {
            return $runnable;
        }

        $labels = array_map(static fn (Component $c): string => $c->label, $runnable);

        $picked = $this->choice(
            'Which parts should be set up?',
            [...$labels, 'All of them'],
            'All of them',
            null,
            true,
        );

        if (in_array('All of them', (array) $picked, true)) {
            return $runnable;
        }

        return array_values(array_filter(
            $runnable,
            static fn (Component $c): bool => in_array($c->label, (array) $picked, true),
        ));
    }

    /**
     * Show what this application does not have yet, and how to get it.
     *
     * @param  array<int, Component>  $missing
     */
    protected function offerMissing(array $missing): void
    {
        if ($missing === []) {
            return;
        }

        $this->newLine();
        $this->components->info('Available, not installed here');

        foreach ($missing as $component) {
            $this->line("  <fg=gray>{$component->description}</>");
            $this->line("  composer require {$component->package}");
            $this->newLine();
        }

        $this->line('  Run <fg=yellow>php artisan wire:install</> again afterwards to set them up.');
    }

    /**
     * @param  array<int, string>  $items
     */
    protected function listing(string $heading, array $items): void
    {
        $this->newLine();
        $this->components->info($heading);

        foreach ($items as $item) {
            $this->line("  • {$item}");
        }
    }
}
