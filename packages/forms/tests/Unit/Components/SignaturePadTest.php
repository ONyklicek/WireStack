<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use NyonCode\WireForms\Components\SignaturePad;

/** A one-pixel transparent PNG, as the canvas would hand it over. */
function signatureDataUri(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
}

test('it draws in body ink at a readable width by default', function () {
    $field = SignaturePad::make('signature');

    expect($field->getHeight())->toBe(180)
        ->and($field->getPenColor())->toBe('#111827')
        ->and($field->getPenWidth())->toBe(2)
        ->and($field->getBackgroundColor())->toBeNull()
        ->and($field->storesFile())->toBeFalse();
});

test('the pen and canvas colours go through the canonical CSS colour guard', function () {
    $field = SignaturePad::make('signature')
        ->penColor('rgb(0 0 255)')
        ->backgroundColor('#fffbeb');

    expect($field->penWidth(4)->getPenWidth())->toBe(4)
        ->and($field->getPenColor())->toBe('rgb(0 0 255)')
        ->and($field->getBackgroundColor())->toBe('#fffbeb');

    // A value that is not a colour renders no colour at all rather than
    // smuggling declarations into the canvas config.
    $unsafe = SignaturePad::make('signature')
        ->penColor('red; background-image: url(https://evil.test/x)')
        ->backgroundColor('javascript:alert(1)');

    expect($unsafe->getPenColor())->toBe('#111827')
        ->and($unsafe->getBackgroundColor())->toBeNull();
});

test('a closure resolves for both colours', function () {
    $field = SignaturePad::make('signature')
        ->penColor(fn (): string => '#ff0000')
        ->backgroundColor(fn (): string => '#000000');

    expect($field->getPenColor())->toBe('#ff0000')
        ->and($field->getBackgroundColor())->toBe('#000000');
});

test('the drawing is what the column holds until a disk is named', function () {
    $field = SignaturePad::make('signature');

    expect($field->dehydrateState(signatureDataUri()))->toBe(signatureDataUri())
        ->and($field->dehydrateState(''))->toBeNull()
        ->and($field->dehydrateState(null))->toBeNull();
});

test('a named disk gets the bytes, and the column gets the path', function () {
    Storage::fake('public');

    $field = SignaturePad::make('signature')->storeOn('public', 'signatures');
    $path = $field->dehydrateState(signatureDataUri());

    expect($path)->toBeString()
        ->toStartWith('signatures/')
        ->toEndWith('.png');

    Storage::disk('public')->assertExists($path);
});

test('dehydrating the same drawing twice writes one file', function () {
    // The contract lets a host dehydrate twice per save; a second write would
    // orphan a copy and hand back a path the first caller never saw.
    Storage::fake('public');

    $field = SignaturePad::make('signature')->storeOn('public', 'signatures');

    expect($field->dehydrateState(signatureDataUri()))
        ->toBe($field->dehydrateState(signatureDataUri()));

    expect(Storage::disk('public')->allFiles('signatures'))->toHaveCount(1);
});

test('a signature nobody re-drew is left where it is', function () {
    Storage::fake('public');

    $field = SignaturePad::make('signature')->storeOn('public', 'signatures');

    // State holding a stored path is an untouched field: writing it out again
    // would leave a second copy of the same image on every save.
    expect($field->dehydrateState('signatures/existing.png'))->toBe('signatures/existing.png')
        ->and(Storage::disk('public')->allFiles('signatures'))->toBe([]);
});

test('a drawing that is not base64 stores nothing', function () {
    Storage::fake('public');

    $field = SignaturePad::make('signature')->storeOn('public', 'signatures');

    expect($field->dehydrateState('data:image/png;base64,!!!!'))->toBeNull()
        ->and(Storage::disk('public')->allFiles('signatures'))->toBe([]);
});

test('storeOn falls back to the disk uploads already use', function () {
    $field = SignaturePad::make('signature')->storeOn();

    expect($field->getDisk())->toBe(config('wire-forms.file_upload.disk', 'public'))
        ->and($field->getDirectory())->toBe(config('wire-forms.file_upload.directory', 'uploads'));
});

test('an existing signature resolves to something an img can show', function () {
    Storage::fake('public');
    Storage::disk('public')->put('signatures/existing.png', 'bytes');

    $stored = SignaturePad::make('signature')->storeOn('public', 'signatures');
    $inline = SignaturePad::make('signature');

    expect($stored->getImageUrl('signatures/existing.png'))->toContain('signatures/existing.png')
        // A data URI is already a source; the resolver leaves it alone.
        ->and($inline->getImageUrl(signatureDataUri()))->toBe(signatureDataUri())
        ->and($inline->getImageUrl(null))->toBeNull()
        ->and($inline->getImageUrl(''))->toBeNull();
});
