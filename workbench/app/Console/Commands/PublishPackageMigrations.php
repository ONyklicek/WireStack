<?php

declare(strict_types=1);

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Put the packages' migrations where an application would have them.
 *
 * This workbench used to keep its own copy of eleven of them, and the copies had
 * to go: `wire:install` publishes the same files, so every table already existed
 * under a different migration name and `php artisan migrate` met each of them
 * twice — `table "audit_logs" already exists`, on a correct install, which is
 * the one thing a workbench for an installer must not do.
 *
 * Registering the packages' directories in `testbench.yaml` instead only moved
 * the collision: the installer still publishes copies, and `migrate` then sees
 * each migration twice over — once as the source and once as the published file,
 * under two different names. Measured, on a real run: 33 pending where there
 * were 11.
 *
 * So the workbench takes the third way, which is the one an application takes.
 * It publishes them, and the published copies are its schema. Running
 * `wire:install` afterwards republishes the same files onto themselves —
 * `laravel-package-toolkit` 2.5.2 matches a timeless migration by the name under
 * its timestamp — so there is nothing new to run and the installer reports what
 * it should: every migration has run.
 *
 * Migrations only. Publishing a package's *views* here would put them in
 * `resources/views/vendor/wire-*`, where Laravel resolves them ahead of the
 * package's own — and a Blade edit would then appear to do nothing for everyone.
 */
#[AsCommand(name: 'workbench:publish-package-migrations')]
class PublishPackageMigrations extends Command
{
    protected $signature = 'workbench:publish-package-migrations';

    protected $description = 'Publish the wire packages\' migrations into the workbench, the way an application has them.';

    /** Every package in this repository that ships one. */
    private const TAGS = [
        'wire-core::migrations',
        'wire-sortable::migrations',
        'wire-module-settings::migrations',
        'wire-module-media::migrations',
        'wire-module-auth::migrations',
    ];

    public function handle(): int
    {
        // Cleared first, and that is not tidiness. The timestamp on a published
        // timeless migration is assigned when the publish mapping is built, so a
        // directory carrying yesterday's copies would collect today's beside
        // them — the very duplication this command exists to stop.
        File::ensureDirectoryExists(database_path('migrations'));

        foreach (File::glob(database_path('migrations/*.php')) as $stale) {
            File::delete($stale);
        }

        foreach (self::TAGS as $tag) {
            Artisan::call('vendor:publish', ['--tag' => $tag, '--force' => true]);
        }

        $this->components->info(
            'Published '.count(File::glob(database_path('migrations/*.php'))).' package migrations.'
        );

        return self::SUCCESS;
    }
}
