<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Resources\Workspace;

/**
 * The optional admin shell.
 *
 * The top of the graph — it requires `wire-panels` and everything under it, and
 * nothing requires it. That direction is the opt-in: three ADRs (0020, 0026,
 * 0027) held that the owner layer holds no shell, so an application installing
 * `wire-panels` for its pages and its routing macro keeps getting exactly that.
 * `composer require` is the only switch, and it needs no config key to turn off.
 *
 * Nothing here sets `livewire.component_layout`. Installing the package must not
 * be the same act as adopting its chrome — an application may want the sidebar
 * inside a layout of its own, and which layout a page renders in stays its
 * decision (ADR 0028 §2).
 *
 * What it ships is markup: `<x-wire-admin::layout>` and
 * `<x-wire-admin::sidebar>`, over the seams that already existed. There is no
 * `Panel` object and no registry — the shell reads {@see Workspace}, and
 * `vendor:publish` is how an application changes any of it.
 */
class WireAdminServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireAdmin')
            ->hasShortName('wire-admin')
            ->bootedPackage(function (): void {
                // Class-based, the way core registers its own tags: the layout
                // and the sidebar both resolve services, and a component class
                // is where that belongs rather than in a Blade file.
                Blade::componentNamespace('NyonCode\\WireAdmin\\View', 'wire-admin');
            })
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
            'Navigation groups' => (string) count(app(Workspace::class)->navigation()),
        ];
    }
}
