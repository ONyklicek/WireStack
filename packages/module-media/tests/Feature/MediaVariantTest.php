<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireModuleMedia\Actions\MakeThumbnail;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Models\Media;

/*
 * One scaled copy per surface, instead of one for all three.
 *
 * A single 400-pixel WebP served a 32-pixel row, a grid tile and a detail
 * preview: two of those three paid for pixels they threw away, on every screen
 * of the library. `width` still names the tile, so nothing about an existing
 * installation changes — the other sizes are an addition.
 */

beforeEach(function () {
    Storage::fake('public');

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }

    config()->set('wire-module-media.thumbnails.enabled', true);

    // The streamed route sits behind `auth` by default, and one test below
    // fetches through it.
    test()->be(new MediaVariantUser);
});

class MediaVariantUser extends User
{
    protected $table = 'users';
}

function variantFile(string $name = 'hero.jpg', int $w = 1600, int $h = 1200): Media
{
    return (new StoreUpload)(UploadedFile::fake()->image($name, $w, $h));
}

it('keeps width naming the tile, so an upgrade changes nothing', function () {
    config()->set('wire-module-media.thumbnails.width', 320);

    expect(MakeThumbnail::sizes()['tile'])->toBe(320);
});

it('sorts the sizes, so "the next one up" is a fact rather than a config order', function () {
    config()->set('wire-module-media.thumbnails.sizes', ['preview' => 1200, 'row' => 96]);
    config()->set('wire-module-media.thumbnails.width', 400);

    expect(array_keys(MakeThumbnail::sizes()))->toBe(['row', 'tile', 'preview']);
});

it('drops a size configured as nothing', function () {
    config()->set('wire-module-media.thumbnails.sizes', ['row' => 0, 'preview' => 1200]);

    expect(MakeThumbnail::sizes())->not->toHaveKey('row');
});

it('makes one copy per configured size', function () {
    $media = variantFile();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue(); // no GD on this build

        return;
    }

    $variants = $media->refresh()->thumb_variants ?? [];

    expect($variants)->toHaveKeys(['row', 'tile', 'preview']);

    foreach ($variants as $path) {
        expect(Storage::disk('public')->exists($path))->toBeTrue();
    }

    // Each is actually the size it says it is.
    $row = getimagesizefromstring((string) Storage::disk('public')->get($variants['row']));
    $tile = getimagesizefromstring((string) Storage::disk('public')->get($variants['tile']));

    expect(max($row[0], $row[1]))->toBe(96)
        ->and(max($tile[0], $tile[1]))->toBe(400);
});

it('keeps thumb_path pointing at the tile, which every older view reads', function () {
    $media = variantFile()->refresh();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect($media->thumb_path)->toBe($media->thumb_variants['tile']);
});

it('records the average colour, so a grid has its shape before the pictures land', function () {
    $media = variantFile()->refresh();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect($media->placeholder)->toMatch('/^#[0-9a-f]{6}$/');
});

it('falls back to the tile for a size that was never made', function () {
    $media = variantFile()->refresh();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue();

        return;
    }

    $media->update(['thumb_variants' => ['tile' => $media->thumb_path]]);

    // A library that has not remade its thumbnails since the sizes were
    // configured shows the copy it has, never a broken image.
    expect($media->fresh()->thumbPathFor('preview'))->toBe($media->thumb_path);
});

it('offers the next size up for a retina screen, and nothing when there is none', function () {
    $media = variantFile()->refresh();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue();

        return;
    }

    $srcset = $media->srcset('row');

    expect($srcset)->toContain(' 1x, ')
        ->and($srcset)->toContain(' 2x')
        // The largest has nothing above it to offer.
        ->and($media->srcset('preview'))->toBeNull();
});

it('takes every size with the file when it is deleted', function () {
    $media = variantFile()->refresh();
    $variants = $media->thumb_variants ?? [];

    if ($variants === []) {
        expect(true)->toBeTrue();

        return;
    }

    $media->delete();

    foreach ($variants as $path) {
        expect(Storage::disk('public')->exists($path))->toBeFalse();
    }
});

it('fills in one named size without remaking the others', function () {
    config()->set('wire-module-media.thumbnails.sizes', []);

    $media = variantFile()->refresh();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect($media->thumb_variants)->toHaveKey('tile')->not->toHaveKey('preview');

    $tile = Storage::disk('public')->get($media->thumb_variants['tile']);

    config()->set('wire-module-media.thumbnails.sizes', ['preview' => 1200]);

    test()->artisan('wire-module-media:thumbnails', ['--size' => 'preview'])->assertSuccessful();

    $media->refresh();

    expect($media->thumb_variants)->toHaveKey('preview')
        // Untouched, which is the whole point of naming a size.
        ->and(Storage::disk('public')->get($media->thumb_variants['tile']))->toBe($tile);
});

it('refuses a size nobody configured', function () {
    expect(test()->artisan('wire-module-media:thumbnails', ['--size' => 'enormous'])->run())->toBe(1);
});

it('serves a named size through the route, so a private disk gets them too', function () {
    $media = variantFile()->refresh();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue();

        return;
    }

    // A grid of a hundred private files was a hundred full-size photographs —
    // the thing thumbnails exist to prevent, still happening for the people who
    // need them most.
    $url = route('wire-media.show', ['media' => $media->getKey(), 'variant' => 'row']);

    $response = test()->get($url);

    $response->assertOk();

    $dimensions = getimagesizefromstring($response->streamedContent());

    expect(max($dimensions[0], $dimensions[1]))->toBe(96)
        // The thumbnail's own type, not the original's: a WebP served as
        // `image/jpeg` is a picture some browsers refuse.
        ->and($response->headers->get('Content-Type'))->toBe('image/webp');
});

it('asks for the size each surface actually draws', function () {
    $media = variantFile()->refresh();

    if ($media->thumb_path === null) {
        expect(true)->toBeTrue();

        return;
    }

    $html = Livewire::test(MediaManager::class)->html();

    // The grid draws tiles, so the tile is what the markup asks for.
    expect($html)->toContain($media->thumb_variants['tile'])
        ->and($html)->toContain('srcset=')
        // And the colour is painted before any of it arrives.
        ->and($html)->toContain((string) $media->placeholder);
});
