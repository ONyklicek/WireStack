<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

it('ships the image-upload controller with the always-loaded set', function () {
    expect(Blade::render('@wireStackScripts'))
        ->toContain('/vendor/wire-forms/wire-forms-image.js?id=')
        ->toContain('/vendor/wire-core/wire-core-dropdown.js?id=');
});

it('ships the field controllers with the always-loaded set', function () {
    // The date/time pickers, tags, rating and the two editors register their
    // Alpine components from here. A registrar delivered late registers nothing
    // on a wire:navigate visit (architecture/assets.md), so this one is never
    // on-request.
    expect(Blade::render('@wireStackScripts'))
        ->toContain('/vendor/wire-forms/wire-forms-fields.js?id=');
});

it('leaves the TipTap bundle out of the always-loaded set', function () {
    // TipTap is a heavy, code-split ESM bundle that only the editor field needs; it
    // stays on-request, served by its own route. Lazy the heavy bodies, never the
    // controllers that register them.
    //
    // Two entries: the image processor and the field controllers. TipTap makes a
    // third only if someone declares it, which is the mistake this guards.
    expect(Blade::render('@wireStackScripts'))->not->toContain('tiptap')
        ->and(app(PackageAssets::class)->resolution('wire-forms'))->toHaveCount(2);
});
