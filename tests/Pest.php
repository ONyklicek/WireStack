<?php

declare(strict_types=1);

use NyonCode\Wire\Tests\TestCase;
use NyonCode\WireBoost\Tests\TestCase as BoostTestCase;
use NyonCode\WireCore\Tests\TestCase as CoreTestCase;
use NyonCode\WireForms\Tests\TestCase as FormsTestCase;
use NyonCode\WirePanels\Tests\TestCase as PanelsTestCase;
use NyonCode\WireSortable\Tests\TestCase as SortableTestCase;
use NyonCode\WireTable\Tests\TestCase as TableTestCase;

/*
 * One throwaway `public/` for the whole run.
 *
 * PublishedAssets mirrors each package's `dist/` into `public_path('vendor/…')` the
 * first time a bundle resolves a URL, so tests write real files — and neither the
 * testbench skeleton inside `vendor/` nor the repo should collect them. One shared
 * directory rather than one per test: the mirror then runs once and every later test
 * finds it current, which is also what the production path does.
 */
$wirePublicPath = sys_get_temp_dir().'/wire-tests-public-'.getmypid();

register_shutdown_function(static function () use ($wirePublicPath): void {
    if (! is_dir($wirePublicPath)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($wirePublicPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }

    @rmdir($wirePublicPath);
});

uses()->beforeEach(function () use ($wirePublicPath): void {
    if (isset($this->app)) {
        $this->app->usePublicPath($wirePublicPath);
    }
})->in(__DIR__.'/Integration', __DIR__.'/../packages');

uses(TestCase::class)->in(__DIR__.'/Integration');

uses(SortableTestCase::class)->in(
    __DIR__.'/../packages/sortable/tests/Feature',
);

uses(CoreTestCase::class)->in(
    __DIR__.'/../packages/core/tests/Unit',
    __DIR__.'/../packages/core/tests/Feature',
);

uses(FormsTestCase::class)->in(
    __DIR__.'/../packages/forms/tests/Unit',
    __DIR__.'/../packages/forms/tests/Feature',
    __DIR__.'/../packages/forms/tests/Standalone',
);

uses(PanelsTestCase::class)->in(
    __DIR__.'/../packages/panels/tests/Unit',
    __DIR__.'/../packages/panels/tests/Feature',
);

uses(TableTestCase::class)->in(
    __DIR__.'/../packages/table/tests/Unit',
    __DIR__.'/../packages/table/tests/Feature',
    __DIR__.'/../packages/table/tests/Benchmarks',
);

// wire-boost requires Laravel 11+ (laravel/mcp). When the package is absent
// (e.g. the Laravel 10 CI matrix), skip its bindings instead of fataling.
if (class_exists(BoostTestCase::class)) {
    uses(BoostTestCase::class)->in(
        __DIR__.'/../packages/boost/tests/Unit',
        __DIR__.'/../packages/boost/tests/Feature',
    );
}
