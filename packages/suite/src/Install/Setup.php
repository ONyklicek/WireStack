<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

use Illuminate\Support\ServiceProvider;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * What a part's installer would still write into this application.
 *
 * This is the question `wire:install` has to answer before it runs anything,
 * because `vendor:publish` is not idempotent in the one place it matters. A
 * migration shipped without a date prefix — which is all of them in this
 * repository — is stamped with the time the *publish mapping was registered*,
 * so its destination path is different in every process. `vendor:publish`
 * checks that path, finds nothing, and writes a second copy of a migration the
 * application already has. Two `create_wire_preferences_table` files, and
 * `php artisan migrate` fails on the second.
 *
 * So the installer asks first and runs only what has something left to do.
 * Re-running `wire:install` after adding a module — which the docs tell people
 * to do — is then what it looks like: the module is set up, and every part that
 * was already there is named and left alone.
 *
 * **Ignorance is never "done".** Every path out of here that cannot see what a
 * command would write returns `null`, and `null` means run it. A part is only
 * settled when it declared publishable tags, every one of them resolved, and
 * every destination is on disk.
 */
class Setup
{
    /**
     * The destinations this part's installer would write that are not there yet.
     *
     * Empty means there is nothing left to do. `null` means that cannot be
     * known — an installer that is not the toolkit's, one that declares no tags
     * and does its work in hooks, or a tag registered under a publish group
     * this does not recognise — and the honest answer to not knowing is to run
     * it.
     *
     * @return array<int, string>|null
     */
    public function pending(Component $component, ?SymfonyCommand $command): ?array
    {
        if ($component->command === null || ! $command instanceof InstallCommand) {
            return null;
        }

        $tags = $command->getPublishTags();

        // An installer whose whole job is an `afterInstallation` hook publishes
        // nothing, so "every destination exists" is vacuously true and would
        // settle it forever. It has no destinations to be done with.
        if ($tags === []) {
            return null;
        }

        // `wire-core:install` → `wire-core`, which is the short name the toolkit
        // builds both the signature and the publish tags from. `explode` rather
        // than a guarded `strstr`, because a command named without a colon is a
        // shape an application can reach and a branch nothing can exercise: it
        // falls out here as the whole name, finds no publish group, and leaves
        // by the same door as any other tag this cannot place.
        $shortName = explode(':', $component->command)[0];

        $pending = [];

        foreach ($tags as $tag) {
            $group = $shortName.'::'.$tag;

            if (! array_key_exists($group, ServiceProvider::$publishGroups)) {
                // The tag is declared but nothing registered it under the name
                // built here. Rather than read that as "nothing to publish",
                // stop claiming to know anything about this part.
                return null;
            }

            foreach (ServiceProvider::pathsToPublish(null, $group) as $destination) {
                if ($this->exists($tag, $destination)) {
                    continue;
                }

                $pending[] = $destination;
            }
        }

        return $pending;
    }

    /**
     * Whether what would be written to this destination is already there.
     *
     * `file_exists` rather than `is_file`: translations and views publish a
     * directory, config and providers a file, and both are "already there".
     */
    protected function exists(string $tag, string $destination): bool
    {
        return $tag === 'migrations'
            ? $this->migrationExists($destination)
            : file_exists($destination);
    }

    /**
     * Whether this migration has been published under any timestamp.
     *
     * The stamp is assigned when the publish mapping is built, so the path
     * offered here is one this process invented and no earlier run can have
     * used. What "already published" means is the name underneath it.
     */
    protected function migrationExists(string $destination): bool
    {
        $directory = dirname($destination);
        $name = (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($destination));

        return is_file($directory.'/'.$name)
            || (glob($directory.'/*_'.$name) ?: []) !== [];
    }
}
