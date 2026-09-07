<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Pages\CreateMedia;
use NyonCode\WireModuleMedia\Pages\ListMedia;
use NyonCode\WireModuleMedia\Pages\ViewMedia;
use NyonCode\WireModuleMedia\Resources\MediaResource;

/*
 * The file library.
 *
 * The row and the file are one thing here: an upload becomes a record, and
 * deleting the record deletes the file — a library that leaves orphans behind
 * fills a disk nobody is looking at.
 */

beforeEach(function () {
    Storage::fake('public');

    // The package's own migrations rather than a hand-written schema beside
    // them: a table declared twice drifts, and the copy in a test is the one
    // that keeps passing after the real one has changed. Alphabetical order is
    // also dependency order here — folders exist before the files that point at
    // them.
    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }
});

it('registers itself as the media module', function () {
    expect(app(PluginManager::class)->has('media'))->toBeTrue();
});

it('records what an upload actually landed as', function () {
    // The mime type and the size are read from the disk rather than from what
    // the browser reported, which is a claim rather than a fact.
    Livewire::test(CreateMedia::class)
        ->set('data.path', UploadedFile::fake()->image('photo.jpg', 12, 12))
        ->call('save')
        ->assertHasNoErrors();

    $media = Media::first();

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('public')
        ->and($media->name)->not->toBe('')
        ->and($media->mime_type)->toStartWith('image/')
        ->and($media->size)->toBeGreaterThan(0)
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('deletes the file with the row', function () {
    Livewire::test(CreateMedia::class)
        ->set('data.path', UploadedFile::fake()->image('photo.jpg'))
        ->call('save');

    $media = Media::first();
    $path = $media->path;

    $media->delete();

    expect(Storage::disk('public')->exists($path))->toBeFalse();
});

it('lists what has been uploaded', function () {
    Media::create(['disk' => 'public', 'path' => 'media/a.jpg', 'name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 2048]);

    Livewire::test(ListMedia::class)->assertOk()->assertSee('a.jpg');
});

it('reads a size a person can read', function () {
    $media = new Media(['size' => 0]);
    expect($media->humanSize())->toBe('0 B');

    $media = new Media(['size' => 2048]);
    expect($media->humanSize())->toBe('2 kB');

    $media = new Media(['size' => 5 * 1024 * 1024]);
    expect($media->humanSize())->toBe('5 MB');
});

it('knows an image from anything else', function () {
    expect((new Media(['mime_type' => 'image/png']))->isImage())->toBeTrue()
        ->and((new Media(['mime_type' => 'application/pdf']))->isImage())->toBeFalse();
});

it('answers a URL from the disk it was stored on', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.jpg', 'name' => 'a.jpg', 'size' => 1]);

    expect($media->url())->toContain('media/a.jpg');
});

it('shows one file with its preview and what was recorded', function () {
    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/a.jpg',
        'name' => 'a.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 2048,
        'width' => 1920,
        'height' => 1080,
    ]);

    $page = Livewire::test(ViewMedia::class, ['record' => $media->id])
        ->assertOk()
        ->assertSee('a.jpg')
        ->assertSee('image/jpeg');

    // The three the flat version got wrong, and each was wrong beside a screen
    // that had it right: a raw byte count next to a list saying "2 kB", an
    // unformatted timestamp, and dimensions the record carried and nobody drew.
    expect($page->html())
        ->toContain($media->humanSize())
        ->not->toContain('>2048<')
        ->and($page->html())->toContain('1920 × 1080');
});

it('heads the page with the file, not with the word "file"', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.jpg', 'name' => 'Autumn cover', 'size' => 1]);

    expect(Livewire::test(ViewMedia::class, ['record' => $media->id])->instance()->getTitle())
        ->toBe('Autumn cover');
});

it('offers a way to open and to download the file', function () {
    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/a.jpg',
        'name' => 'a.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1,
    ]);

    $html = Livewire::test(ViewMedia::class, ['record' => $media->id])->html();

    // Links, not buttons. A ViewPage composes no host trait, so it has no
    // `callInfolistAction()` for a button to dispatch to — an action rendered as
    // one here would do nothing at all.
    expect($html)->toContain('data-testid="infolist-action-open"')
        ->and($html)->toContain('data-testid="infolist-action-download"')
        ->and($html)->toContain('<a')
        ->and($html)->toContain('download="a.jpg"');
});

it('draws no broken image for a file that has no pixels', function () {
    // `<img src="…​.pdf">` is a broken-image icon claiming the file is damaged.
    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/contract.pdf',
        'name' => 'contract.pdf',
        'mime_type' => 'application/pdf',
        'size' => 4096,
    ]);

    $html = Livewire::test(ViewMedia::class, ['record' => $media->id])->assertOk()->html();

    expect($html)->toContain(__('wire-module-media::messages.no_preview'))
        ->and($html)->not->toContain('contract.pdf"'.' alt');
});

it('files an unfiled record at the root rather than showing nothing', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.jpg', 'name' => 'a.jpg', 'size' => 1]);

    expect(Livewire::test(ViewMedia::class, ['record' => $media->id])->html())
        ->toContain(__('wire-module-media::messages.library_root'));
});

it('labels the upload date rather than printing a pluralisation string', function () {
    // `uploaded` was defined twice in both language files, and the second — the
    // "{1} 1 file uploaded|…" toast — won. Three labels rendered it raw: the
    // list column, this entry, and the library's own detail panel.
    expect(__('wire-module-media::messages.uploaded'))->toBe('Uploaded')
        ->and(trans_choice('wire-module-media::messages.uploaded_count', 2, ['count' => 2]))->toBe('2 files uploaded');
});

it('offers no open or download for a file that cannot be reached at all', function () {
    // A disk the application does not publish has no address, and with the
    // module's own streaming route switched off there is nowhere to send
    // anybody — so the answer is no link, not a link to null.
    config()->set('filesystems.disks.vault', ['driver' => 'local', 'root' => storage_path('vault')]);
    config()->set('wire-module-media.route.enabled', false);

    $media = Media::create(['disk' => 'vault', 'path' => 'media/secret.pdf', 'name' => 'secret.pdf', 'size' => 1]);

    expect($media->url())->toBeNull();

    $html = Livewire::test(ViewMedia::class, ['record' => $media->id])->assertOk()->html();

    expect($html)->not->toContain('data-testid="infolist-action-open"')
        ->and($html)->not->toContain('data-testid="infolist-action-download"');
});

it('composes without a record, for a host that binds one later', function () {
    // The resource may be asked for its infolist before anything is bound to it
    // — the header actions belong to a file, so with no file there are none.
    $infolist = (new MediaResource)->infolist(Infolist::make());

    $preview = $infolist->getSchema()[0];

    expect($preview->getHeaderActions())->toBe([]);
});
