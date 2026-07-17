<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NyonCode\WireForms\Components\FileUpload;

beforeEach(fn () => Storage::fake('media'));

// ─── Defaults (backwards compatibility) ───────────────────────────

it('stores under a hashed name in the directory by default', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('uploads')
        ->storeUploadedFile(UploadedFile::fake()->image('original.png'));

    expect($path)->toStartWith('uploads/')
        ->and($path)->not->toContain('original.png')   // hashed, not the client name
        ->and(Storage::disk('media')->exists($path))->toBeTrue();
});

it('keeps the original client name with preserveFilenames', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('uploads')->preserveFilenames()
        ->storeUploadedFile(UploadedFile::fake()->image('original.png'));

    expect($path)->toBe('uploads/original.png')
        ->and(Storage::disk('media')->exists($path))->toBeTrue();
});

it('stores a private-visibility file under a hashed name', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('secure')->visibility('private')
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($path)->toStartWith('secure/')
        ->and($path)->not->toContain('scan.png')
        ->and(Storage::disk('media')->exists($path))->toBeTrue();
});

it('stores a private-visibility file under a custom name', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('secure')->visibility('private')
        ->fileNameUsing(fn (UploadedFile $file) => 'doc.png')
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($path)->toBe('secure/doc.png')
        ->and(Storage::disk('media')->exists('secure/doc.png'))->toBeTrue();
});

// ─── fileNameUsing ────────────────────────────────────────────────

it('names the stored file via fileNameUsing, receiving the upload', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('invoices')
        ->fileNameUsing(fn (UploadedFile $file) => 'inv-42.'.$file->extension())
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($path)->toBe('invoices/inv-42.png')
        ->and(Storage::disk('media')->exists('invoices/inv-42.png'))->toBeTrue();
});

it('falls back to the hashed default when fileNameUsing returns empty', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('uploads')
        ->fileNameUsing(fn (UploadedFile $file) => '')
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($path)->toStartWith('uploads/')
        ->and($path)->not->toContain('scan.png');
});

it('lets fileNameUsing win over preserveFilenames', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('uploads')
        ->preserveFilenames()
        ->fileNameUsing(fn (UploadedFile $file) => 'custom.png')
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($path)->toBe('uploads/custom.png');
});

// ─── storeFileUsing ───────────────────────────────────────────────

it('owns the whole path via storeFileUsing, receiving the file and disk', function () {
    $seenDisk = null;

    $path = FileUpload::make('doc')->disk('media')->directory('ignored')
        ->storeFileUsing(function (UploadedFile $file, string $disk) use (&$seenDisk) {
            $seenDisk = $disk;

            return $file->storeAs('reports/2024', 'q3.png', $disk);
        })
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($seenDisk)->toBe('media')
        ->and($path)->toBe('reports/2024/q3.png')
        ->and(Storage::disk('media')->exists('reports/2024/q3.png'))->toBeTrue();
});

it('lets storeFileUsing take precedence over directory and fileNameUsing', function () {
    $path = FileUpload::make('doc')->disk('media')->directory('ignored')
        ->preserveFilenames()
        ->fileNameUsing(fn (UploadedFile $file) => 'also-ignored.png')
        ->storeFileUsing(fn (UploadedFile $file, string $disk) => $file->storeAs('final', 'x.png', $disk))
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($path)->toBe('final/x.png');
});

it('treats a non-string storeFileUsing result as an empty stored path', function () {
    $path = FileUpload::make('doc')->disk('media')
        ->storeFileUsing(fn (UploadedFile $file, string $disk) => null)
        ->storeUploadedFile(UploadedFile::fake()->image('scan.png'));

    expect($path)->toBe('');
});
