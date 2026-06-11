<?php

declare(strict_types=1);

namespace NyonCode\WireForms;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Integration\ActionMacros;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WireFormsServiceProvider extends PackageServiceProvider
{
    /** Absolute path to the pre-bundled, self-registering field assets. */
    public const ASSETS_PATH = __DIR__.'/../dist';

    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireForms')
            ->hasShortName('wire-forms')
            ->registeredPackage(function ($packager) {
                $this->app->bind(Form::class, fn () => new Form);
            })
            ->bootedPackage(function ($packager) {
                Blade::componentNamespace('NyonCode\\WireForms\\Components', 'wire-forms');
                ActionMacros::register();

                $this->registerAssetRoutes();
            })
            ->hasConfig()
            ->hasViews()
            ->hasAssets('dist')
            ->hasTranslations('resources/lang')
            ->hasAbout();
    }

    /**
     * Serve the package's pre-bundled JS directly so field views can inject it
     * without the consumer running npm, a build step, or `vendor:publish`.
     */
    protected function registerAssetRoutes(): void
    {
        Route::get('/wire-forms/assets/{asset}.js', function (string $asset): BinaryFileResponse {
            $file = self::ASSETS_PATH.'/wire-forms-'.basename($asset).'.js';

            abort_unless(is_file($file), 404);

            return response()
                ->file($file, ['Content-Type' => 'application/javascript; charset=utf-8'])
                ->setPublic()
                ->setMaxAge(31536000);
        })
            ->where('asset', '[A-Za-z0-9_-]+')
            ->name('wire-forms.asset');
    }
}
