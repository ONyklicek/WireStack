<?php

declare(strict_types=1);

use NyonCode\WireForms\WireFormsServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the tiptap bundle is shipped inside the package', function () {
    $bundle = WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-tiptap.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('tiptapEditor');
});

test('the package serves the tiptap bundle without publishing or a build step', function () {
    $response = $this->get('/wire-forms/assets/tiptap.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('tiptapEditor');
});

test('the named asset route resolves', function () {
    expect(route('wire-forms.asset', ['asset' => 'tiptap'], false))
        ->toBe('/wire-forms/assets/tiptap.js');
});

test('unknown assets return 404', function () {
    $this->get('/wire-forms/assets/does-not-exist.js')->assertNotFound();
});
