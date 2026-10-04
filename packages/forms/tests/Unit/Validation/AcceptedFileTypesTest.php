<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Validation\Rules\AcceptedFileTypes;

/*
 * acceptedFileTypes() used to reach only the input's `accept` attribute — a
 * hint to the browser's picker that a dropped or crafted upload never passes
 * through. It is now a rule the server checks.
 */

function aftPasses(array $accepted, mixed $value): bool
{
    return Validator::make(['file' => $value], ['file' => [new AcceptedFileTypes($accepted)]])->passes();
}

it('accepts a file whose type is listed, wildcards included', function () {
    expect(aftPasses(['image/*'], UploadedFile::fake()->image('a.png')))->toBeTrue()
        ->and(aftPasses(['image/png'], UploadedFile::fake()->image('a.png')))->toBeTrue();
});

it('refuses a file whose type is not listed', function () {
    expect(aftPasses(['image/*'], UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')))->toBeFalse();
});

it('reads an entry without a slash as an extension, with or without its dot', function () {
    $pdf = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf');

    expect(aftPasses(['pdf'], $pdf))->toBeTrue()
        ->and(aftPasses(['.pdf'], $pdf))->toBeTrue()
        ->and(aftPasses(['.docx'], $pdf))->toBeFalse();
});

it('passes a file either kind of entry accepts', function () {
    expect(aftPasses(['image/*', '.pdf'], UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')))->toBeTrue()
        ->and(aftPasses(['image/*', '.pdf'], UploadedFile::fake()->image('a.png')))->toBeTrue()
        ->and(aftPasses(['image/*', '.pdf'], UploadedFile::fake()->create('a.txt', 1, 'text/plain')))->toBeFalse();
});

it('lets a stored path through, as an edit form holds one', function () {
    expect(aftPasses(['image/*'], 'avatars/ada.png'))->toBeTrue();
});

it('takes anything when nothing is listed', function () {
    $rule = new AcceptedFileTypes(['', '  ']);

    expect($rule->isEmpty())->toBeTrue()
        ->and(aftPasses([], UploadedFile::fake()->create('a.txt', 1, 'text/plain')))->toBeTrue();
});

it('names what it accepts when it refuses', function () {
    $validator = Validator::make(
        ['file' => UploadedFile::fake()->create('a.txt', 1, 'text/plain')],
        ['file' => [new AcceptedFileTypes(['image/*', '.pdf'])]],
    );

    expect($validator->errors()->first('file'))->toContain('image/*, pdf');
});

it('makes a single upload check its accepted types on the server', function () {
    $rules = FileUpload::make('avatar')->acceptedFileTypes(['image/*'])->implicitValidationRules();

    expect(collect($rules)->contains(fn (mixed $rule): bool => $rule instanceof AcceptedFileTypes))->toBeTrue()
        ->and(FileUpload::make('avatar')->implicitValidationRules())->toBe([]);
});

it('makes every file of a multiple upload check its accepted types', function () {
    $rules = FileUpload::make('docs')->multiple()->acceptedFileTypes(['.pdf'])->itemValidationRules();

    expect(collect($rules)->contains(fn (mixed $rule): bool => $rule instanceof AcceptedFileTypes))->toBeTrue()
        ->and(FileUpload::make('docs')->multiple()->itemValidationRules())->toBe([]);
});
