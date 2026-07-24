<?php

declare(strict_types=1);

namespace NyonCode\WireForms;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Actions\Contracts\ModalFormFactory;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\Support\FormModalFormFactory;
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
                // wire-core resolves this to build action-modal forms without
                // naming Form (keeps the core → forms dependency inverted-free).
                $this->app->singleton(ModalFormFactory::class, FormModalFormFactory::class);
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
            ->hasAbout()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfig()
                    ->publishViews()
                    ->publishTranslations()
                    ->publishAssets();
            });
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

        // The TipTap editor ships as an ESM code-split bundle (entry + shared core
        // chunk + opt-in addon entry); serve any .js from dist/tiptap by filename so
        // an entry's relative `import "./chunk-<hash>.js"` resolves. basename() bars
        // path traversal; the hashed chunk name is its own cache key.
        Route::get('/wire-forms/tiptap/{file}', function (string $file): BinaryFileResponse {
            $path = self::ASSETS_PATH.'/tiptap/'.basename($file);

            abort_unless(is_file($path) && str_ends_with($path, '.js'), 404);

            return response()
                ->file($path, ['Content-Type' => 'application/javascript; charset=utf-8'])
                ->setPublic()
                ->setMaxAge(31536000);
        })
            ->where('file', '[A-Za-z0-9_.-]+')
            ->name('wire-forms.tiptap');
    }
}
