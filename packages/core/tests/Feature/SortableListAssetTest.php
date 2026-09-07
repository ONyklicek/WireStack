<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use NyonCode\WireCore\WireCoreServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The drag controller behind every positional list in the stack — a Repeater's
 * cards, its table rows, a Builder's blocks.
 *
 * It exists because those views shipped `x-sortable` against a directive nothing
 * registered: the handle rendered, the cursor said `grab`, and dragging did
 * nothing. So the assertions here are about *delivery* — that the factory the
 * markup names is actually defined somewhere the browser can reach.
 */
test('the sortable-list bundle is shipped inside the package', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-sortable-list.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireSortableList');
});

test('the package serves it without publishing or a build step', function () {
    $response = $this->get('/wire-core/assets/sortable-list.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toContain('wireSortableList');
});

test('the bundle registers the factory on both the cold load and a wire:navigate', function () {
    // `alpine:init` fires once per document, and a wire:navigate visit never
    // restarts Alpine — so a bundle that only listens for that event registers
    // nothing when it arrives with a new page, and every `x-data` naming the
    // factory fails silently. Fails if dist has drifted from source
    // (`npm run build:core-assets`).
    $bundle = (string) file_get_contents(WireCoreServiceProvider::ASSETS_PATH.'/wire-core-sortable-list.js');

    expect($bundle)
        ->toContain('alpine:init')
        ->toContain('wireSortableList')
        // SortableJS is compiled in rather than fetched: the package has to work
        // offline and under a strict CSP.
        ->toContain('sortable');
});

test('it is not part of the aggregate every application ships', function () {
    // 38 kB of compiled SortableJS in the <head> of every page of every wire-core
    // app is exactly what giving this its own bundle was meant to avoid. It is
    // reached only by the surfaces that include the partial.
    $html = (string) Blade::render('@wireStackScripts');

    expect($html)->toContain('wire-core-dropdown.js')
        ->and($html)->not->toContain('wire-core-sortable-list.js');
});

test('the per-surface partial points at the served bundle', function () {
    // `@assets` hands its body to Livewire's registry rather than echoing it —
    // which is the point, since the tag has to land in the document head once per
    // request however many repeaters ask for it. So the registry is where the
    // emitted tag is read back from.
    Blade::render("@include('wire-core::partials.sortable-list-assets')");
    SupportScriptsAndAssets::processNonLivewireAssets();

    $html = implode('', SupportScriptsAndAssets::getAssets());

    expect($html)->toContain('/wire-core/assets/sortable-list.js')
        // Cache-busted by mtime, so a rebuild is picked up without a version bump.
        ->and($html)->toMatch('#sortable-list\.js\?id=\d+#')
        // And the ghost/drag classes, which Tailwind never scans because JS
        // applies them to elements JS creates.
        ->and($html)->toContain('wire-sortable-list-ghost');
});
