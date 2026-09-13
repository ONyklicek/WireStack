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
use Throwable;

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
        $commands = Artisan::all();
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');

        [$runnable, $settled] = $this->triage($installed, $commands, $setup, $force);

        $chosen = $this->option('all') ? $runnable : $this->choose($runnable);

        $failed = $this->install($installed, $chosen, $settled, $commands, $force, $dry);

        $applied = 0;
        $failed = array_merge($failed, $this->setUpApplication($dry, $applied));

        $this->offerMissing($catalogue->missing());

        $this->newLine();

        if ($failed !== []) {
            $this->components->error('Did not finish: '.implode(', ', $failed));

            return self::FAILURE;
        }

        if ($dry) {
            $this->components->info('Nothing was changed. Run it without --dry-run to set these up.');

            return self::SUCCESS;
        }

        // Only when nothing happened at all. Saying "everything was already set
        // up" after creating the first administrator is the command describing
        // a run it did not have.
        if ($settled !== [] && $chosen === [] && $applied === 0) {
            $this->components->info('Everything was already set up. Run with --force to do it again.');

            return self::SUCCESS;
        }

        $this->components->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Install the parts that were chosen, and account for every part that was not.
     *
     * One line per installed part, in catalogue order, each ending in what
     * happened to it. This used to be three lists — everything found, then
     * everything already set up, then everything being run — which named most
     * parts twice and some of them three times, in three different vocabularies.
     * A reader wanting to know the state of one package had to find it in all
     * three and work it out.
     *
     * @param  array<int, Component>  $installed
     * @param  array<int, Component>  $chosen
     * @param  array<int, Component>  $settled
     * @param  array<string, SymfonyCommand>  $commands
     * @return array<int, string> The labels of the parts that failed.
     */
    protected function install(array $installed, array $chosen, array $settled, array $commands, bool $force, bool $dry): array
    {
        $this->newLine();
        $this->components->info('Installing packages');

        $failed = [];

        foreach ($installed as $component) {
            $name = $this->name($component);

            if ($component->command === null) {
                // Present and nothing to run: `wire-panels` publishes nothing of
                // its own, and `wire-boost` asks which AI agents to configure,
                // which is not a question to answer on anybody's behalf.
                $this->report($name, 'gray', 'NOTHING TO RUN');

                continue;
            }

            // A class can be autoloadable while its provider is not booted — a
            // `dont-discover` entry, a package registered only in one
            // environment. Calling a command that is not there aborts the whole
            // run with a message about Symfony's console.
            if (! array_key_exists($component->command, $commands)) {
                $this->report($name, 'yellow', 'NOT REGISTERED');

                continue;
            }

            if (in_array($component, $settled, true)) {
                $this->report($name, 'gray', 'ALREADY DONE');

                continue;
            }

            if (! in_array($component, $chosen, true)) {
                $this->report($name, 'gray', 'LEFT ALONE');

                continue;
            }

            if ($dry) {
                $this->report($name, 'yellow', 'WOULD RUN');

                continue;
            }

            $options = $this->passthrough($commands[$component->command], $force);

            $this->components->task($name, function () use ($component, $options, &$failed): bool {
                // Into a buffer rather than onto this command's output. Each of
                // these installers prints a banner, a numbered step per tag, a
                // tick per published file and a "Next steps" list — a dozen
                // lines per package, in the middle of a table of one line per
                // package. Handing them `$this->getOutput()` is what made the
                // listing unreadable, and worse, what left a row rendered with
                // no label at all when the spinner came back to a cursor
                // somebody else had moved.
                //
                // The exit code is the installer's answer, and dropping it was
                // how a failed publish came out as a green tick and a zero
                // exit — the shape a CI run cannot see through.
                $code = Artisan::call((string) $component->command, $options);

                if ($code !== self::SUCCESS) {
                    $failed[] = $component->label;
                }

                // Kept where it can be read: quiet while it works, and the whole
                // of what it said the moment it does not. `-v` asks for it
                // anyway, which is what somebody debugging an install reaches
                // for before anything else.
                if ($code !== self::SUCCESS || $this->output->isVerbose()) {
                    $this->line(Artisan::output());
                }

                return $code === self::SUCCESS;
            });
        }

        if ($settled !== [] && ! $force) {
            $this->line('  <fg=gray>Run with --force to set up the parts marked ALREADY DONE again.</>');
        }

        return $failed;
    }

    /**
     * What a part is called in the listing: its label, and the line to paste.
     *
     * The composer name earns its place here and nowhere else — this is the one
     * moment somebody is deciding what this application is made of.
     */
    protected function name(Component $component): string
    {
        // Separated by a dash rather than by colour alone: the colour is gone
        // the moment this is piped into a file, and `Core nyoncode/wire-core`
        // reads as one mangled word.
        return $component->label.' <fg=gray>— '.$component->package.'</>';
    }

    /**
     * One row of the listing, in the shape `task()` leaves behind.
     */
    protected function report(string $name, string $colour, string $status): void
    {
        $this->components->twoColumnDetail($name, "<fg={$colour};options=bold>{$status}</>");
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
    protected function setUpApplication(bool $dry, int &$applied = 0): array
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
                        ? '<fg=gray>'.$step->summary().'</> <fg=green;options=bold>DONE</>'
                        : '<fg=gray>'.$step->summary().'</> <fg=yellow;options=bold>WAITING</>',
                );

                continue;
            }

            if ($dry) {
                $this->components->twoColumnDetail(
                    $step->label(),
                    '<fg=gray>'.$step->summary().'</> <fg=yellow;options=bold>WOULD RUN</>',
                );

                continue;
            }

            // The label and what it would do on one aligned line, then one
            // question. Reading a column of labels and finding the state at the
            // end of each is how every other Laravel installer reads, and it is
            // the difference between a report and a wall of prose.
            $this->components->twoColumnDetail($step->label(), '<fg=gray>'.$step->summary().'</>');

            if (! $console->confirm('Set it up now?')) {
                $this->components->twoColumnDetail($step->label(), '<fg=gray;options=bold>LEFT ALONE</>');

                continue;
            }

            try {
                $outcome = $step->apply($console);
            } catch (Throwable $e) {
                // A step is a package's code, and `Blocked` only covers what it
                // could see coming. Everything else — a migration that collides
                // with one already in the database, a disk that is not writable —
                // arrives as an exception, and one escaping here takes the whole
                // installer with it and prints a stack trace over the listing.
                $console->warn($e->getMessage());

                $outcome = SetupOutcome::Failed;
            }

            if ($outcome === SetupOutcome::Applied) {
                $applied++;
            }

            if ($outcome === SetupOutcome::Failed) {
                $failed[] = $step->label();
            }

            $this->components->twoColumnDetail($step->label(), match ($outcome) {
                SetupOutcome::Applied => '<fg=green;options=bold>DONE</>',
                SetupOutcome::Skipped => '<fg=yellow;options=bold>SKIPPED</>',
                SetupOutcome::Failed => '<fg=red;options=bold>FAILED</>',
            });
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

            // Reported by the listing rather than here: this only decides what
            // can be offered.
            if (! array_key_exists($component->command, $commands)) {
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
}
