<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use NyonCode\WireCore\Foundation\Support\StoredFileUrlResolver;

it('returns the default for an empty reference', function () {
    expect(StoredFileUrlResolver::resolve(null, default: '/img/placeholder.png'))
        ->toBe('/img/placeholder.png')
        ->and(StoredFileUrlResolver::resolve('', default: '/img/placeholder.png'))
        ->toBe('/img/placeholder.png')
        ->and(StoredFileUrlResolver::resolve(null))->toBeNull();
});

it('passes an inline data URI through untouched', function () {
    // FILTER_VALIDATE_URL rejects a data: URI, which would otherwise be sent
    // down the storage path and rendered as src="/storage/data:image/...".
    $uri = 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg"/>');

    expect(StoredFileUrlResolver::resolve($uri, 'media', 'private'))->toBe($uri);
});

it('passes a full external URL through untouched', function () {
    $url = 'https://cdn.example.com/a.png';

    expect(StoredFileUrlResolver::resolve($url, 'media', 'private'))->toBe($url);
});

it('builds a plain, non-expiring URL for a public disk', function () {
    Storage::fake('media');

    $url = StoredFileUrlResolver::resolve('a.png', 'media', 'public');

    expect($url)->toContain('/storage/a.png')
        ->and($url)->not->toContain('expiration=');
});

it('signs an expiring URL for a non-public disk', function () {
    Storage::fake('media');

    $url = StoredFileUrlResolver::resolve('a.png', 'media', 'private');

    // A signed URL carries an expiry; the plain one does not.
    expect($url)->toContain('expiration=');
});

it('falls back to the plain URL when the driver cannot sign one', function () {
    // The local driver (no temporary-url route) throws — a single unsignable file
    // must not fatal the whole render.
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('temporaryUrl')->once()->andThrow(new RuntimeException('This driver does not support creating temporary URLs.'));
    $disk->shouldReceive('url')->once()->with('a.png')->andReturn('/storage/a.png');
    Storage::set('unsignable', $disk);

    expect(StoredFileUrlResolver::resolve('a.png', 'unsignable', 'private'))->toBe('/storage/a.png');
});

it('resolves through the default disk when none is named', function () {
    Storage::fake();

    $url = StoredFileUrlResolver::resolve('a.png');

    expect($url)->toContain('/storage/a.png');
});

it('honours a custom signed-url expiry', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('temporaryUrl')
        ->once()
        ->with('a.png', Mockery::on(fn ($expiry): bool => $expiry->greaterThan(now()->addMinutes(29)) && $expiry->lessThan(now()->addMinutes(31))))
        ->andReturn('/signed/a.png?expiration=1');
    Storage::set('media', $disk);

    expect(StoredFileUrlResolver::resolve('a.png', 'media', 'private', 30))->toBe('/signed/a.png?expiration=1');
});
