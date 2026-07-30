<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Foundation\Assets\AssetManager;

it('ships the image-upload controller with the always-loaded set', function () {
    expect(Blade::render('@wireStackScripts'))
        ->toContain('/wire-forms/assets/image.js?id=')
        ->toContain('/wire-core/assets/dropdown.js?id=');
});

it('leaves the TipTap bundle out of the always-loaded set', function () {
    // TipTap is a heavy, code-split ESM bundle that only the editor field needs; it
    // stays on-request, served by its own route. Lazy the heavy bodies, never the
    // controllers that register them.
    expect(Blade::render('@wireStackScripts'))->not->toContain('tiptap')
        ->and(app(AssetManager::class)->getScripts('wire-forms'))->toHaveCount(1);
});
