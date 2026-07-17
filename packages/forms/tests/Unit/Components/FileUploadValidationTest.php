<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\FileUpload;

/*
 * maxSize()/minSize()/maxFiles()/minFiles() reached only the hint text under the
 * dropzone — the field advertised a limit and then validated nothing. These pin
 * that the limits are now real rules.
 */

it('turns a single upload size limit into file-size rules', function () {
    $rules = FileUpload::make('cv')->maxSize(5000)->minSize(10)->getValidationRules();

    expect($rules)->toContain('max:5000')->toContain('min:10');
});

it('turns a multiple upload count limit into array-count rules', function () {
    $rules = FileUpload::make('photos')->multiple()->maxFiles(4)->minFiles(2)->getValidationRules();

    expect($rules)->toContain('max:4')->toContain('min:2');
});

// The same word means different things to Laravel: on a file `max:` is kilobytes,
// on an array it is a count. Mixing them would silently validate the wrong thing.
it('does not apply a file-size limit as an item count on a multiple upload', function () {
    $rules = FileUpload::make('photos')->multiple()->maxSize(5000)->getValidationRules();

    expect($rules)->not->toContain('max:5000');
});

it('does not apply a file count to a single upload', function () {
    $rules = FileUpload::make('cv')->maxFiles(4)->getValidationRules();

    expect($rules)->not->toContain('max:4');
});

it('adds nothing when no limit is configured', function () {
    expect(FileUpload::make('cv')->getValidationRules())->toBe([]);
});

it('keeps an explicitly declared rule alongside the derived ones', function () {
    $rules = FileUpload::make('cv')->rules(['mimes:pdf'])->maxSize(100)->getValidationRules();

    expect($rules)->toContain('mimes:pdf')->toContain('max:100');
});

// ─── Image processing (crop / resize / avatar) ──────────────────────────────

// These four were dead setters: nothing read them, so a field configured to crop
// or downscale uploaded the untouched original.

it('reports that it processes images only when configured to', function () {
    expect(FileUpload::make('p')->image()->processesImages())->toBeFalse()
        ->and(FileUpload::make('p')->imageCropAspectRatio('16:9')->processesImages())->toBeTrue()
        ->and(FileUpload::make('p')->imageResizeTargetWidth(320)->processesImages())->toBeTrue()
        ->and(FileUpload::make('p')->imageResizeTargetHeight(240)->processesImages())->toBeTrue();
});

it('hands the browser exactly the configured processing', function () {
    $config = FileUpload::make('p')
        ->imageCropAspectRatio('4:3')
        ->imageResizeTargetWidth(800)
        ->imageResizeTargetHeight(600)
        ->getImageProcessingConfig();

    expect($config)->toBe([
        'aspectRatio' => '4:3',
        'targetWidth' => 800,
        'targetHeight' => 600,
        // Centre crop unless the field offers the frame.
        'interactive' => false,
    ]);
});

it('makes an avatar square, since that is what an avatar is', function () {
    expect(FileUpload::make('p')->avatar()->getImageCropAspectRatio())->toBe('1:1')
        ->and(FileUpload::make('p')->avatar()->processesImages())->toBeTrue();
});

it('lets an explicit ratio win over the avatar default, whichever order', function () {
    expect(FileUpload::make('p')->avatar()->imageCropAspectRatio('4:3')->getImageCropAspectRatio())->toBe('4:3')
        ->and(FileUpload::make('p')->imageCropAspectRatio('4:3')->avatar()->getImageCropAspectRatio())->toBe('4:3');
});

// cropInteractively() lets the user place the frame instead of taking the centre.
it('offers the frame only when there is a ratio to place', function () {
    expect(FileUpload::make('p')->imageCropAspectRatio('16:9')->cropInteractively()->cropsInteractively())->toBeTrue()
        ->and(FileUpload::make('p')->avatar()->cropInteractively()->cropsInteractively())->toBeTrue()
        // Without a ratio the frame would have nothing to constrain it.
        ->and(FileUpload::make('p')->cropInteractively()->cropsInteractively())->toBeFalse()
        ->and(FileUpload::make('p')->imageCropAspectRatio('16:9')->cropsInteractively())->toBeFalse();
});

it('tells the browser whether to ask for the frame', function () {
    expect(FileUpload::make('p')->imageCropAspectRatio('1:1')->cropInteractively()->getImageProcessingConfig()['interactive'])->toBeTrue()
        ->and(FileUpload::make('p')->imageCropAspectRatio('1:1')->getImageProcessingConfig()['interactive'])->toBeFalse();
});
