<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

use function Laravel\Prompts\multiselect;

/**
 * `php artisan wire:install` — a clean Laravel to a working admin, in one pass.
 *
 * It runs each installed package's own installer rather than reimplementing
 * them: every package already knows what it publishes and what it has to say
 * afterwards, and a second copy of that here would drift from the first.
 *
 * **It only runs what has something left to do.** A part whose installer has
 * already written everything it publishes is named and skipped, because the
 * alternative is not a wasted second — `vendor:publish` re-stamps a timeless
 * migration and writes a second copy of one the application already has (see
 * {@see Setup}). `--force` sets up everything regardless and publishes over
 * what is there, which is the flag an upgrade wants.
 *
 * `--dry-run` answers "what would this do to my application" without doing any
 * of it, which is the question anyone sensible asks of an installer first.
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
        {--all : Set up every part that needs it, without asking}
        {--force : Set up every part, including those already done, publishing over what they wrote}
        {--dry-run : Show what would be set up and what is available, and change nothing}';

    protected $description = 'Set up the wire stack in this application, interactively.';

    public function handle(Catalogue $catalogue, Setup $setup, Banner $banner): int
    {
        // Only where somebody is watching. A banner in a deploy log is noise in
        // the one place the output is read by a machine.
        if ($this->input->isInteractive()) {
            foreach ($banner->lines() as $line) {
                $this->line($line);
            }
        }

        $installed = $catalogue->installed();

        $this->listing(
            'Found in this application',
            array_map(static fn (Component $c): string => $c->label.' — '.$c->package, $installed),
        );

        $commands = Artisan::all();
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');

        [$runnable, $settled] = $this->triage($installed, $commands, $setup, $force);

        $this->reportSettled($settled);

        $chosen = $this->option('all') ? $runnable : $this->choose($runnable);

        $failed = [];

        foreach ($chosen as $component) {
            /** @var string $name */
            $name = $component->command;

            if ($dry) {
                $this->components->twoColumnDetail($component->label, "would run <fg=yellow>php artisan {$name}</>");

                continue;
            }

            $options = $this->passthrough($commands[$name], $force);

            $this->components->task($component->label, function () use ($name, $options, $component, &$failed): bool {
                // The exit code is the installer's answer, and dropping it was
                // how a failed publish came out as a green tick and a zero
                // exit — the shape a CI run cannot see through.
                $code = Artisan::call($name, $options, $this->getOutput());

                if ($code !== self::SUCCESS) {
                    $failed[] = $component->label;
                }

                return $code === self::SUCCESS;
            });
        }

        $failed = array_merge($failed, $this->setUpApplication($dry));

        $this->offerMissing($catalogue->missing());

        $this->newLine();

        if ($failed !== []) {
            $this->components->error('Did not install: '.implode(', ', $failed));

            return self::FAILURE;
        }

        if ($dry) {
            $this->components->info('Nothing was changed. Run it without --dry-run to set these up.');

            return self::SUCCESS;
        }

        if ($chosen === []) {
            $this->components->info($runnable === []
                ? 'Nothing needed setting up.'
                : 'Nothing was picked, so nothing was set up.');

            return self::SUCCESS;
        }

        $this->components->info('Done.');

        return self::SUCCESS;
    }

    /**
     * The second half: not "is the package here" but "does the application work".
     *
     * Every package installer in this framework already knew what was missing
     * and said so — nine lines of "run migrate", "name an ability", "nothing is
     * being recorded yet" across seven packages, each a diagnosis with no action
     * behind it. A {@see SetupStep} is that action, and it stays with the
     * package that knows: this method collects, orders and asks, and never
     * learns what a media disk or a super-admin role is.
     *
     * @return array<int, string> The labels of the steps that failed.
     */
    protected function setUpApplication(bool $dry): array
    {
        $steps = $this->steps();

        if ($steps === []) {
            return [];
        }

        $this->newLine();
        $this->components->info('Setting up this application');

        $console = new CommandConsole($this, $this->input->isInteractive());
        $failed = [];

        foreach ($steps as $step) {
            $state = $step->state();

            // Blocked is reported and never offered. An installer that knows
            // only done and not-done has to offer "run migrations" to an
            // application with no database — and then the answer is yes and the
            // run dies inside a task spinner with a PDO exception.
            if ($state !== SetupState::Pending) {
                $this->components->twoColumnDetail(
                    $step->label(),
                    $state === SetupState::Done
                        ? '<fg=gray>'.$step->summary().'</>'
                        : '<fg=yellow>'.$step->summary().'</>',
                );

                continue;
            }

            if ($dry) {
                $this->components->twoColumnDetail($step->label(), '<fg=yellow>would '.$step->summary().'</>');

                continue;
            }

            $this->line("  <options=bold>{$step->label()}</> — <fg=gray>{$step->summary()}</>");

            if (! $console->confirm('  Do that now?')) {
                $this->line('    <fg=gray>Left alone.</>');

                continue;
            }

            if ($step->apply($console) === SetupOutcome::Failed) {
                $failed[] = $step->label();
            }
        }

        return $failed;
    }

    /**
     * Every contributed step, in the order they have to run.
     *
     * Sorted here rather than in the registry because `sort()` lives on the
     * step, and reading it means resolving it — which is also where a step
     * picks up whatever it needs from the container.
     *
     * @return array<int, SetupStep>
     */
    protected function steps(): array
    {
        $steps = array_map(
            fn (string $step): SetupStep => $this->laravel->make($step),
            SetupRegistry::instance()->all(),
        );

        usort($steps, static fn (SetupStep $a, SetupStep $b): int => $a->sort() <=> $b->sort());

        return $steps;
    }

    /**
     * Split what is installed into what still needs setting up and what does not.
     *
     * Before anything is offered, because a part with nothing left to write is
     * not a choice worth putting to anyone — and running it anyway is the bug
     * this whole pass is about.
     *
     * @param  array<int, Component>  $installed
     * @param  array<string, SymfonyCommand>  $commands
     * @return array{0: array<int, Component>, 1: array<int, Component>}
     */
    protected function triage(array $installed, array $commands, Setup $setup, bool $force): array
    {
        $runnable = [];
        $settled = [];

        foreach ($installed as $component) {
            if ($component->command === null) {
                continue;
            }

            // A class can be autoloadable while its provider is not booted — a
            // `dont-discover` entry, a package registered only in one
            // environment. Calling a command that is not there aborts the whole
            // run with a message about Symfony's console, so it is reported and
            // skipped instead.
            if (! array_key_exists($component->command, $commands)) {
                $this->components->warn("{$component->label}: {$component->command} is not registered — is its provider loaded?");

                continue;
            }

            if (! $force && $setup->pending($component, $commands[$component->command]) === []) {
                $settled[] = $component;

                continue;
            }

            $runnable[] = $component;
        }

        return [$runnable, $settled];
    }

    /**
     * What to hand the installer being run.
     *
     * `--no-interaction` is passed on rather than left behind. Every one of
     * these commands prompts before touching a production application, and the
     * prompt is drawn from inside a running task spinner on this command's own
     * output — the one place nobody can answer it. A run told not to ask must
     * mean it all the way down.
     *
     * `--force` is only offered to a command that defines it: an application
     * may name any command as a part's installer, and Symfony aborts on an
     * option that does not exist.
     *
     * @return array<string, bool>
     */
    protected function passthrough(SymfonyCommand $command, bool $force): array
    {
        $options = [];

        if ($force && $command->getDefinition()->hasOption('force')) {
            $options['--force'] = true;
        }

        if (! $this->input->isInteractive()) {
            $options['--no-interaction'] = true;
        }

        return $options;
    }

    /**
     * Ask which of the pending parts to set up.
     *
     * Everything is offered pre-selected: an installer whose default is "nothing"
     * makes the common case the tedious one. A multiselect says that in one
     * screen — the list, with every box already ticked and space to untick — where
     * a `choice()` had to carry a synthetic "All of them" row to mean the same
     * thing, and then hope nobody named a package that.
     *
     * Keyed by the installer rather than by the label, so two parts that read the
     * same are still two parts.
     *
     * @param  array<int, Component>  $runnable
     * @return array<int, Component>
     */
    protected function choose(array $runnable): array
    {
        if ($runnable === [] || ! $this->input->isInteractive()) {
            return $runnable;
        }

        $options = [];

        foreach ($runnable as $component) {
            /** @var string $name */
            $name = $component->command;
            $options[$name] = $component->label;
        }

        $picked = multiselect(
            label: 'Which parts should be set up?',
            options: $options,
            default: array_keys($options),
            // Tall enough for the whole stack and every module at once: a list
            // that scrolls hides the thing the pre-selection is trying to show.
            scroll: 15,
            hint: 'Space unticks one, enter confirms.',
        );

        return array_values(array_filter(
            $runnable,
            static fn (Component $c): bool => in_array($c->command, $picked, true),
        ));
    }

    /**
     * Name what was left alone, and how to make it run anyway.
     *
     * Silence here would read as a part having been missed, and the one case
     * where skipping is wrong — an installer whose published files are present
     * but whose `afterInstallation` hook still has work — is only recoverable if
     * the person can see it happened.
     *
     * @param  array<int, Component>  $settled
     */
    protected function reportSettled(array $settled): void
    {
        if ($settled === []) {
            return;
        }

        $this->newLine();
        $this->components->info('Already set up, left alone');

        foreach ($settled as $component) {
            $this->line("  • {$component->label}");
        }

        $this->line('  <fg=gray>Run with --force to set these up again and publish over what they wrote.</>');
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
            $this->line("  <options=bold>{$component->label}</> — <fg=gray>{$component->description}</>");
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
