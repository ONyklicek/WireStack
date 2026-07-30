<?php

declare(strict_types=1);

use NyonCode\WireForms\WireFormsServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the image-processing bundle is shipped inside the package', function () {
    $bundle = WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-image.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireImageUpload');
});

test('the package serves the image-processing bundle without publishing or a build step', function () {
    $response = $this->get('/wire-forms/assets/image.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('wireImageUpload');
});

test('the image source registers unconditionally so it survives a wire:navigate', function () {
    $source = file_get_contents(
        dirname(WireFormsServiceProvider::ASSETS_PATH).'/resources/js/image-processor.js'
    );

    // `alpine:init` fires exactly once per document. A file-upload field reached
    // through a `wire:navigate` loads this bundle after that event, so the
    // listener may only be the cold-load fallback for an idempotent registrar
    // that also runs straight away when Alpine is already up.
    expect($source)
        ->not->toMatch("/addEventListener\('alpine:init',\s*\(\s*\)\s*=>/")
        ->toMatch("/if \(window\.Alpine\) \{\s*(?:\/\/[^\n]*\n\s*)*registerWireImageUpload\(\);/")
        ->toMatch("/document\.addEventListener\('alpine:init', registerWireImageUpload\);/")
        ->toContain('if (registered || ! window.Alpine) return;');
});

test('the shipped image bundle carries the SPA-proof registration', function () {
    $bundle = WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-image.js';

    // Minified shape of the idiom above:
    //   window.Alpine?f():document.addEventListener("alpine:init",f)
    //   f=()=>{u||!window.Alpine||(u=!0, …)}
    // Fails if the dist drifts from source (needs `npm run build:forms-assets`).
    expect(file_get_contents($bundle))
        ->toMatch('/window\.Alpine\?\w+\(\):document\.addEventListener\("alpine:init",\w+\)/')
        ->toMatch('/\w+\|\|!window\.Alpine\|\|\(\w+=!0,/');
});

test('the tiptap bundle keeps the same SPA-proof registration', function () {
    $bundle = WireFormsServiceProvider::ASSETS_PATH.'/tiptap/tiptap-editor.js';

    // The in-repo precedent for the idiom; it must not regress either.
    expect(file_get_contents($bundle))
        ->toMatch('/window\.Alpine\?\w+\(\):document\.addEventListener\("alpine:init",\w+\)/')
        ->toMatch('/\w+\|\|!window\.Alpine\|\|\(\w+=!0,/');
});
