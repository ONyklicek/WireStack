<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use NyonCode\WireTable\Columns\ImageColumn;

/*
 * ->stacked() / ->stackLimit() / ->visibility() used to be fluent setters that
 * nothing ever read: the cell rendered exactly one <img> built from a plain
 * Storage URL. These cover the behaviour they now actually drive.
 */

function imageRecord(array $attributes): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill($attributes);

    return $record;
}

// ─── Gallery ────────────────────────────────────────────────────────────────

it('renders a single image for a scalar state', function () {
    $html = ImageColumn::make('avatar')->renderCell(imageRecord(['avatar' => 'https://example.test/a.png']));

    expect(substr_count($html, '<img'))->toBe(1)
        ->and($html)->toContain('https://example.test/a.png');
});

it('renders an array state as a gallery', function () {
    $html = ImageColumn::make('photos')->renderCell(imageRecord(['photos' => [
        'https://example.test/a.png',
        'https://example.test/b.png',
    ]]));

    expect(substr_count($html, '<img'))->toBe(2)
        ->and($html)->toContain('flex-wrap');
});

it('reads a JSON array state, as an array-cast column delivers it', function () {
    $html = ImageColumn::make('photos')->renderCell(imageRecord([
        'photos' => '["https://example.test/a.png","https://example.test/b.png"]',
    ]));

    expect(substr_count($html, '<img'))->toBe(2);
});

it('overlaps the images when stacked', function () {
    $html = ImageColumn::make('photos')->stacked()->renderCell(imageRecord(['photos' => [
        'https://example.test/a.png',
        'https://example.test/b.png',
    ]]));

    expect($html)->toContain('-space-x-2')
        ->and($html)->toContain('ring-2');
});

it('caps a stack at stackLimit and summarises the rest', function () {
    $urls = array_map(fn ($i) => "https://example.test/$i.png", range(1, 7));

    $html = ImageColumn::make('photos')->stacked()->stackLimit(3)->renderCell(imageRecord(['photos' => $urls]));

    expect(substr_count($html, '<img'))->toBe(3)
        ->and($html)->toContain('data-testid="image-stack-overflow"')
        ->and($html)->toContain('+4');
});

it('shows no overflow chip when the stack fits', function () {
    $html = ImageColumn::make('photos')->stacked()->stackLimit(3)->renderCell(imageRecord(['photos' => [
        'https://example.test/a.png',
    ]]));

    expect($html)->not->toContain('image-stack-overflow');
});

it('does not cap an unstacked gallery', function () {
    $urls = array_map(fn ($i) => "https://example.test/$i.png", range(1, 5));

    $html = ImageColumn::make('photos')->stackLimit(2)->renderCell(imageRecord(['photos' => $urls]));

    expect(substr_count($html, '<img'))->toBe(5)
        ->and($html)->not->toContain('image-stack-overflow');
});

it('falls back to the placeholder for an empty array', function () {
    $html = ImageColumn::make('photos')->placeholder('—')->renderCell(imageRecord(['photos' => []]));

    expect($html)->toBe('—');
});

// ─── Visibility ─────────────────────────────────────────────────────────────

it('builds a plain URL by default', function () {
    Storage::fake('media');

    $html = ImageColumn::make('avatar')->disk('media')->renderCell(imageRecord(['avatar' => 'a.png']));

    expect($html)->toContain('/storage/a.png')
        ->and($html)->not->toContain('expiration=');
});

it('is public by default so an existing disk column keeps its URL', function () {
    expect(ImageColumn::make('avatar')->getVisibility())->toBe('public');
});

it('asks the disk for a temporary URL when not public', function () {
    Storage::fake('media');

    $html = ImageColumn::make('avatar')->disk('media')->visibility('private')
        ->renderCell(imageRecord(['avatar' => 'a.png']));

    // A signed URL carries an expiry; the plain one does not — assert the
    // difference, not merely that some URL came back.
    expect($html)->toContain('expiration=');
});

it('exposes a configurable signed-url expiry', function () {
    expect(ImageColumn::make('avatar')->getUrlExpiry())->toBe(5)
        ->and(ImageColumn::make('avatar')->urlExpiry(30)->getUrlExpiry())->toBe(30);
});

it('falls back to the plain URL when the driver cannot sign one', function () {
    // A driver that cannot sign (the local one, without the temporary-url route)
    // throws — one unsignable image must not take the whole table down.
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('temporaryUrl')->once()->andThrow(new RuntimeException('This driver does not support creating temporary URLs.'));
    $disk->shouldReceive('url')->once()->with('a.png')->andReturn('/storage/a.png');
    Storage::set('unsignable', $disk);

    $html = ImageColumn::make('avatar')->disk('unsignable')->visibility('private')
        ->renderCell(imageRecord(['avatar' => 'a.png']));

    expect($html)->toContain('/storage/a.png');
});

it('passes an inline data URI through untouched', function () {
    // FILTER_VALIDATE_URL rejects a data: URI, which used to send it down the
    // storage path and render src="/storage/data:image/svg+xml,...".
    $uri = 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg"/>');

    $html = ImageColumn::make('avatar')->renderCell(imageRecord(['avatar' => $uri]));

    expect($html)->toContain('src="'.e($uri).'"')
        ->and($html)->not->toContain('/storage/data:');
});
