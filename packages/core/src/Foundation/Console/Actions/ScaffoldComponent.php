<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Console\Actions;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Console\Support\ComponentBlueprint;
use NyonCode\WireCore\Foundation\Console\Support\ScaffoldedComponent;
use NyonCode\WireCore\Foundation\Console\Support\StubWriter;

/**
 * Write a custom component from its blueprint: the class, then the view it
 * names.
 *
 * ## Why the view is never overwritten
 *
 * `--force` exists to get the class back after it was edited into a corner.
 * The markup is where the work usually went, and silently replacing a view
 * somebody wrote is not a trade a generator gets to make — the widget
 * generator settled this first, and every component generator keeps it.
 *
 * ## Why two files, when there is a view
 *
 * Because one of them alone is useless: a class that names a view nobody wrote
 * throws `View [tables.columns.money] not found` the first time it renders,
 * and the reader has to work out from the exception which file that is.
 */
final class ScaffoldComponent
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Application $app,
    ) {}

    public function execute(ComponentBlueprint $blueprint, string $input, bool $force = false): ScaffoldedComponent
    {
        $class = $blueprint->className($input);
        $namespace = rtrim($this->app->getNamespace(), '\\').'\\'.$blueprint->namespace;
        $view = $blueprint->viewName($input);
        $base = $blueprint->baseName($input);

        $replacements = [
            'namespace' => $namespace,
            'class' => $class,
            'view' => $view ?? '',
            'name' => Str::snake($base),
            'label' => Str::headline($base),
        ];

        $writer = new StubWriter($this->files);
        $classPath = app_path(str_replace('\\', '/', $blueprint->namespace).'/'.$class.'.php');

        $classWritten = $writer->write($blueprint->stubs->path($blueprint->classStub), $classPath, $replacements, $force);

        $viewPath = null;
        $viewWritten = false;

        if ($view !== null && $blueprint->viewStub !== null) {
            $viewPath = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
            $viewWritten = $writer->write($blueprint->stubs->path($blueprint->viewStub), $viewPath, $replacements);
        }

        return new ScaffoldedComponent($namespace.'\\'.$class, $classPath, $classWritten, $viewPath, $viewWritten);
    }
}
