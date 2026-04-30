<?php

declare(strict_types=1);

namespace NyonCode\WireForms;

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Integration\ActionMacros;

class WireFormsServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireForms')
            ->hasShortName('wire-forms')
            ->hasConfig()
            ->hasViews()
            ->hasTranslations('resources/lang')
            ->hasAbout();
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(Form::class, fn () => new Form);
    }

    public function boot(): void
    {
        parent::boot();

        Blade::componentNamespace('NyonCode\\WireForms\\Components', 'wire-forms');
        ActionMacros::register();
    }
}
