<?php

declare(strict_types=1);

use NyonCode\WireForms\WireFormsServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the tiptap bundle is shipped inside the package (ESM code-split)', function () {
    // The editor ships as an ESM code-split bundle: a core entry, an opt-in addon
    // entry, and a shared core chunk they both import.
    $dir = WireFormsServiceProvider::ASSETS_PATH.'/tiptap';

    expect(is_file($dir.'/tiptap-editor.js'))->toBeTrue()
        ->and(file_get_contents($dir.'/tiptap-editor.js'))->toContain('tiptapEditor')
        ->and(is_file($dir.'/tiptap-editor-addons.js'))->toBeTrue()
        ->and(file_get_contents($dir.'/tiptap-editor-addons.js'))->toContain('WireTiptapAddons')
        // The core entry references a shared chunk (so enabling tables does not
        // ship a duplicate ProseMirror core).
        ->and(file_get_contents($dir.'/tiptap-editor.js'))->toMatch('/chunk-[A-Za-z0-9]+\.js/')
        ->and(glob($dir.'/chunk-*.js'))->not->toBeEmpty();
});

test('the package serves the tiptap bundle without publishing or a build step', function () {
    $response = $this->get('/wire-forms/tiptap/tiptap-editor.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('tiptapEditor');
});

test('the tiptap route serves the addon and shared chunk too', function () {
    $this->get('/wire-forms/tiptap/tiptap-editor-addons.js')->assertOk();

    $chunk = basename((string) collect(glob(WireFormsServiceProvider::ASSETS_PATH.'/tiptap/chunk-*.js'))->first());
    $this->get('/wire-forms/tiptap/'.$chunk)->assertOk();
});

test('the named tiptap route resolves', function () {
    expect(route('wire-forms.tiptap', ['file' => 'tiptap-editor.js'], false))
        ->toBe('/wire-forms/tiptap/tiptap-editor.js');
});

test('unknown tiptap files return 404, and path traversal is barred', function () {
    $this->get('/wire-forms/tiptap/does-not-exist.js')->assertNotFound();
});
