<?php

declare(strict_types=1);

namespace NyonCode\WirePanels;

use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;

/**
 * The application owner layer.
 *
 * Sits above every component package — it requires wire-table, wire-forms and
 * wire-core, and nothing requires it. That direction is the whole point: a
 * resource composes the primitives, so the package that owns resources has to be
 * the one that may name all of them, and none of them may name it back.
 *
 * What lives here is only the part that needs a component type. The identity
 * half of a resource (`DescribesResource`, `DescribesRecords`,
 * `ResourceRegistry`) is in wire-core and the per-surface contracts live with
 * the types they name — `ProvidesResourceForm` in wire-forms,
 * `ProvidesResourceInfolist` beside Infolists — so an application that has a
 * form-only resource installs wire-forms and never sees a table package.
 */
class WirePanelsServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WirePanels')
            ->hasShortName('wire-panels')
            ->hasViews()
            ->hasTranslations()
            ->hasAbout();
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Resources' => (string) count(app(ResourceRegistry::class)->all()),
        ];
    }
}
