<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireModuleMedia\Actions\MakeThumbnail;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Contracts\MakesThumbnails;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Support\GdThumbnailer;

/*
 * Thumbnails.
 *
 * The grid was loading originals: a hundred files meant a hundred full-size
 * photographs, and the browser scaled them down after paying for every byte.
 *
 * The rule that shapes all of this is that **a thumbnail is a convenience**.
 * Every reason one cannot be made — a PDF, an SVG, a server built without GD, a
 * remote disk, the feature switched off — is a normal answer, leaves the row
 * with a null `thumb_path`, and every view falls back to the original. Nothing
 * here may fail an upload.
 */

beforeEach(function () {
    Storage::fake('public');

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }
});

/* ── Making one ───────────────────────────────────────────────────────────── */

it('gives an uploaded image a scaled copy', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->image('wide.jpg', 1600, 900));

    expect($media?->thumb_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists((string) $media?->thumb_path))->toBeTrue();

    // The longest edge, aspect ratio kept — 1600×900 at a 400px bound is 400×225.
    $size = getimagesizefromstring((string) Storage::disk('public')->get((string) $media?->thumb_path));

    expect($size[0])->toBe(400)
        ->and($size[1])->toBe(225)
        // WebP whatever came in: one format is one thing for a view to reason
        // about, and the smallest of the three at preview quality.
        ->and($size['mime'])->toBe('image/webp');
});

it('leaves an image that is already small at its own size', function () {
    // Enlarging produces a bigger file that looks worse — both halves of the
    // trade going the wrong way.
    $media = (new StoreUpload)(UploadedFile::fake()->image('tiny.png', 64, 48));

    $size = getimagesizefromstring((string) Storage::disk('public')->get((string) $media?->thumb_path));

    expect($size[0])->toBe(64)->and($size[1])->toBe(48);
});

it('shows the scaled copy where a grid asks, and the original where it does not', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));

    expect($media?->previewUrl())->toContain('thumbnails/')
        ->and($media?->url())->not->toContain('thumbnails/');
});

it('falls back to the original for a file that has no thumbnail', function () {
    // A PDF, an SVG, a row from before thumbnails existed: one answer, so no
    // view needs a second code path.
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.pdf', 'name' => 'a.pdf']);

    expect($media->previewUrl())->toBe($media->url());
});

it('takes the scaled copy with the file when the file is deleted', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 500, 500));
    $thumb = (string) $media?->thumb_path;

    $media?->delete();

    // Nothing else points at it and nobody uploaded it, so leaving it behind is
    // litter that only grows.
    expect(Storage::disk('public')->exists($thumb))->toBeFalse();
});

/* ── When one cannot be made ──────────────────────────────────────────────── */

it('records no thumbnail for a file with no pixels', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->create('contract.pdf', 8, 'application/pdf'));

    expect($media)->not->toBeNull()
        ->and($media?->thumb_path)->toBeNull();
});

it('leaves an SVG alone, because a browser already scales it perfectly', function () {
    // A raster copy of a vector is strictly worse than the file it came from.
    expect((new GdThumbnailer)->supports('image/svg+xml'))->toBeFalse()
        ->and((new GdThumbnailer)->supports('image/gif'))->toBeFalse()
        ->and((new GdThumbnailer)->supports('image/jpeg'))->toBeTrue()
        ->and((new GdThumbnailer)->supports('IMAGE/PNG'))->toBeTrue();
});

it('makes none at all when the feature is switched off', function () {
    config()->set('wire-module-media.thumbnails.enabled', false);

    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));

    expect($media?->thumb_path)->toBeNull()
        ->and($media?->previewUrl())->toBe($media?->url());
});

it('does not remake one that is already there, unless asked to', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));
    $make = app(MakeThumbnail::class);

    expect($make($media))->toBeFalse()
        ->and($make($media, force: true))->toBeTrue();
});

it('answers false rather than raising for anything it cannot read', function () {
    $thumbnailer = new GdThumbnailer;
    $destination = sys_get_temp_dir().'/wire-thumb-'.bin2hex(random_bytes(4)).'.webp';

    expect($thumbnailer->make('/no/such/file', $destination, 400))->toBeFalse()
        // A width of zero is a caller's mistake, and a blank image is a worse
        // answer to it than none.
        ->and($thumbnailer->make(__FILE__, $destination, 0))->toBeFalse()
        // A file that is not an image at all — this very test file.
        ->and($thumbnailer->make(__FILE__, $destination, 400))->toBeFalse();
});

it('skips a file the disk cannot hand it as a local path', function () {
    // What S3 looks like from here: `path()` answers a key, not a filename.
    // Pulling the file back down to make a preview is a download per upload.
    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/not-really-there.png',
        'name' => 'x.png',
        'mime_type' => 'image/png',
    ]);

    expect(app(MakeThumbnail::class)($media))->toBeFalse()
        ->and($media->refresh()->thumb_path)->toBeNull();
});

it('leaves the row alone when the thumbnailer writes nothing', function () {
    // A row pointing at a thumbnail that was never made is a broken image in
    // every grid — worse than the full-size original this was meant to avoid.
    app()->bind(MakesThumbnails::class, fn () => new class implements MakesThumbnails
    {
        public function supports(string $mimeType): bool
        {
            return true;
        }

        public function make(string $source, string $destination, int $max): bool
        {
            return false;
        }
    });

    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));

    expect($media?->thumb_path)->toBeNull();
});

/* ── Catching up ──────────────────────────────────────────────────────────── */

it('makes the thumbnails the library was uploaded without', function () {
    // Thumbnails arrived after the library did, so every installation has files
    // with none — and a migration cannot resize an image.
    config()->set('wire-module-media.thumbnails.enabled', false);

    $image = (new StoreUpload)(UploadedFile::fake()->image('old.png', 900, 900));
    $document = (new StoreUpload)(UploadedFile::fake()->create('old.pdf', 4, 'application/pdf'));

    config()->set('wire-module-media.thumbnails.enabled', true);

    $this->artisan('wire-module-media:thumbnails')
        ->expectsOutputToContain('Made 1 thumbnail(s) out of 2 file(s)')
        ->assertSuccessful();

    expect($image?->refresh()->thumb_path)->not->toBeNull()
        // The difference between the two numbers is the answer, not a failure:
        // a PDF cannot have one.
        ->and($document?->refresh()->thumb_path)->toBeNull();
});

it('says so when there is nothing left to do', function () {
    $this->artisan('wire-module-media:thumbnails')
        ->expectsOutputToContain('already has one')
        ->assertSuccessful();
});

it('remakes every thumbnail when the size has changed', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 1000, 1000));

    config()->set('wire-module-media.thumbnails.width', 120);

    $this->artisan('wire-module-media:thumbnails', ['--force' => true])->assertSuccessful();

    $size = getimagesizefromstring((string) Storage::disk('public')->get((string) $media?->refresh()->thumb_path));

    expect($size[0])->toBe(120);
});

it('refuses a destination it cannot create a directory for', function () {
    // A regular file sits exactly where the directory would go, so creating it
    // fails for anybody, root included. The answer is false rather than a
    // warning on somebody's upload.
    $blocker = sys_get_temp_dir().'/wire-thumb-blocked-'.bin2hex(random_bytes(4));

    file_put_contents($blocker, 'not a directory');

    try {
        expect((new GdThumbnailer)->make(
            __DIR__.'/../fixtures/pixel.png',
            $blocker.'/inside/thumb.webp',
            400,
        ))->toBeFalse();
    } finally {
        @unlink($blocker);
    }
});

/* ── Waiting for one ──────────────────────────────────────────────────────── */

it('refreshes itself only while a thumbnail is actually coming', function () {
    // The whole polling policy, and each condition is there to stop a page that
    // asks for ever.
    config()->set('wire-module-media.thumbnails.queue', true);

    Queue::fake();

    (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));

    // Queued and recent: the file has no thumbnail yet and one is on its way.
    expect(Livewire::test(MediaManager::class)->html())->toContain('wire:poll');
});

it('does not poll for a thumbnail that is never coming', function () {
    // Inline, so a null thumbnail is a file that cannot have one — a PDF, or a
    // PNG GD refused. Asking again in three seconds would never change it.
    (new StoreUpload)(UploadedFile::fake()->create('contract.pdf', 4, 'application/pdf'));

    expect(Livewire::test(MediaManager::class)->html())->not->toContain('wire:poll');

    config()->set('wire-module-media.thumbnails.enabled', false);

    expect(Livewire::test(MediaManager::class)->html())->not->toContain('wire:poll');
});

it('stops asking about a file that has been waiting for months', function () {
    config()->set('wire-module-media.thumbnails.queue', true);

    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/old.png',
        'name' => 'old.png',
        'mime_type' => 'image/png',
    ]);

    $media->forceFill(['created_at' => now()->subMonths(2)])->save();

    // A screen left open on that folder would otherwise ask every three seconds
    // until the tab was closed.
    expect(Livewire::test(MediaManager::class)->html())->not->toContain('wire:poll');
});
