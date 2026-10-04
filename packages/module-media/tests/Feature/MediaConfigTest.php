<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use NyonCode\WireModuleMedia\Actions\ReplaceOriginal;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Jobs\GenerateThumbnail;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;
use NyonCode\WireModuleMedia\Pages\CreateMedia;
use NyonCode\WireModuleMedia\Support\Thumbnails;
use NyonCode\WireModuleMedia\Support\UploadLimits;

/*
 * What `config/wire-module-media.php` promises, held on every path a file takes.
 *
 * Each key here used to reach one surface and miss the others: the table names
 * reached the models and not the migrations, the upload limits reached the
 * create page and not the drop zone, and the thumbnail queue reached a new
 * upload and not a replaced original.
 */

function mcMigrate(): void
{
    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }
}

beforeEach(function () {
    Storage::fake('public');
});

/* ── Table names ──────────────────────────────────────────────────────────── */

it('migrates the tables the config names, which are the ones the models query', function () {
    config()->set('wire-module-media.table', 'media');
    config()->set('wire-module-media.folders_table', 'media_folders');

    mcMigrate();

    expect(Schema::hasTable('media'))->toBeTrue()
        ->and(Schema::hasTable('media_folders'))->toBeTrue()
        ->and(Schema::hasTable('wire_media'))->toBeFalse()
        ->and(Schema::hasColumns('media', ['derived_from_id', 'thumb_variants', 'placeholder']))->toBeTrue();

    $folder = MediaFolder::createIn(null, 'Brand');
    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 8, 8), $folder);

    expect($media?->getTable())->toBe('media')
        ->and($media?->folder_id)->toBe($folder->id);
});

it('leaves a table that is already there alone', function () {
    mcMigrate();
    mcMigrate();

    expect(Schema::hasTable('wire_media'))->toBeTrue();
});

it('drops the tables the config names', function () {
    config()->set('wire-module-media.table', 'media');
    config()->set('wire-module-media.folders_table', 'media_folders');

    mcMigrate();

    $migrations = array_reverse(glob(__DIR__.'/../../database/migrations/*.php') ?: []);

    // SQLite cannot drop a constrained column, which is what the two column
    // migrations undo; their own tables going is what this asks about.
    foreach ($migrations as $migration) {
        if (str_contains($migration, 'create_')) {
            (include $migration)->down();
        }
    }

    expect(Schema::hasTable('media'))->toBeFalse()
        ->and(Schema::hasTable('media_folders'))->toBeFalse();
});

/* ── What may be uploaded ─────────────────────────────────────────────────── */

it('refuses a file over max_size before writing a byte', function () {
    mcMigrate();
    config()->set('wire-module-media.max_size', 1);

    expect(fn () => (new StoreUpload)(UploadedFile::fake()->create('big.pdf', 50, 'application/pdf')))
        ->toThrow(ValidationException::class);

    expect(Media::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses a file outside accepts, read from its contents', function () {
    mcMigrate();
    config()->set('wire-module-media.accepts', ['image/*']);

    expect(fn () => (new StoreUpload)(UploadedFile::fake()->create('notes.pdf', 1, 'application/pdf')))
        ->toThrow(ValidationException::class);

    expect((new StoreUpload)(UploadedFile::fake()->image('shot.png', 8, 8)))->not->toBeNull();
});

it('takes an extension in accepts, with or without its dot', function () {
    mcMigrate();
    config()->set('wire-module-media.accepts', ['.png']);

    expect((new StoreUpload)(UploadedFile::fake()->image('shot.png', 8, 8)))->not->toBeNull()
        ->and(fn () => (new StoreUpload)(UploadedFile::fake()->create('notes.pdf', 1, 'application/pdf')))
        ->toThrow(ValidationException::class);
});

it('takes a file either list in accepts allows, when both kinds are given', function () {
    mcMigrate();
    config()->set('wire-module-media.accepts', ['image/*', 'pdf']);

    expect((new StoreUpload)(UploadedFile::fake()->image('shot.png', 8, 8)))->not->toBeNull()
        ->and((new StoreUpload)(UploadedFile::fake()->create('notes.pdf', 1, 'application/pdf')))->not->toBeNull()
        ->and(fn () => (new StoreUpload)(UploadedFile::fake()->create('song.mp3', 1, 'audio/mpeg')))
        ->toThrow(ValidationException::class, 'image/*, pdf');
});

it('puts accepts on the file inputs, and nothing when anything goes', function () {
    config()->set('wire-module-media.accepts', ['image/*', 'pdf', '.svg']);

    expect(UploadLimits::acceptAttribute())->toBe('image/*,.pdf,.svg');

    config()->set('wire-module-media.accepts', []);

    expect(UploadLimits::acceptAttribute())->toBeNull();
});

it('tells the drop zone which rule refused a file, and offers no retry for it', function () {
    mcMigrate();
    config()->set('wire-module-media.max_size', 1);

    $component = Livewire::test(MediaManager::class)
        ->set('uploads', [UploadedFile::fake()->create('big.pdf', 50, 'application/pdf')]);

    $entry = $component->get('tray')[0];

    expect($entry['state'])->toBe('failed')
        ->and($entry['reason'])->toContain('big.pdf')
        ->and(Media::count())->toBe(0);

    $component->assertSee($entry['reason'])->assertDontSeeHtml('data-testid="media-tray-retry"');
});

it('refuses an edited copy or a replacement over the limit, and says so', function () {
    mcMigrate();
    $original = (new StoreUpload)(UploadedFile::fake()->image('hero.jpg', 20, 20));
    config()->set('wire-module-media.max_size', 1);

    expect(fn () => app(ReplaceOriginal::class)($original, UploadedFile::fake()->create('big.jpg', 50, 'image/jpeg')))
        ->toThrow(ValidationException::class);

    foreach (['new', 'replace'] as $intent) {
        Livewire::test(MediaManager::class)
            ->call('openEditor', $original->getKey())
            ->set('editorIntent', $intent)
            ->set('editorUpload', UploadedFile::fake()->create('big.jpg', 50, 'image/jpeg'))
            ->assertOk();
    }

    // No derivative written, and the original still the file it was.
    expect(Media::count())->toBe(1)
        ->and($original->fresh()->size)->toBe($original->size);
});

/* ── Thumbnails ───────────────────────────────────────────────────────────── */

it('reads the thumbnail queue the way the environment writes it', function (mixed $value, ?string $queue) {
    config()->set('wire-module-media.thumbnails.queue', $value);

    expect(Thumbnails::queue())->toBe($queue)
        ->and(Thumbnails::queued())->toBe($queue !== null);
})->with([
    'off' => [false, null],
    'null' => [null, null],
    'env false' => ['false', null],
    'env 0' => ['0', null],
    'env empty' => ['', null],
    'on' => [true, ''],
    'env true' => ['true', ''],
    'env 1' => ['1', ''],
    'a named queue' => ['media', 'media'],
]);

it('queues a replaced original\'s thumbnail when thumbnails are queued', function () {
    mcMigrate();
    $media = (new StoreUpload)(UploadedFile::fake()->image('hero.jpg', 20, 20));
    config()->set('wire-module-media.thumbnails.queue', 'media');

    Queue::fake();

    app(ReplaceOriginal::class)($media, UploadedFile::fake()->image('other.jpg', 30, 10));

    Queue::assertPushedOn('media', GenerateThumbnail::class, fn (GenerateThumbnail $job): bool => $job->force);
});

it('queues a new upload on the default queue when the env says true', function () {
    mcMigrate();
    config()->set('wire-module-media.thumbnails.queue', 'true');

    Queue::fake();

    (new StoreUpload)(UploadedFile::fake()->image('shot.png', 8, 8));

    Queue::assertPushed(GenerateThumbnail::class, fn (GenerateThumbnail $job): bool => $job->queue === null);
});

it('makes the create page\'s row the way every other upload is made', function () {
    mcMigrate();
    config()->set('wire-module-media.thumbnails.queue', true);

    Queue::fake();

    Livewire::test(CreateMedia::class)
        ->set('data.path', UploadedFile::fake()->image('photo.jpg', 12, 10))
        ->call('save')
        ->assertHasNoErrors();

    $media = Media::firstOrFail();

    expect($media->checksum)->not->toBeNull()
        ->and($media->width)->toBe(12)
        ->and($media->height)->toBe(10);

    Queue::assertPushed(GenerateThumbnail::class);
});

it('records a row for a stored path that is gone without throwing', function () {
    mcMigrate();

    $media = (new StoreUpload)->record('public', 'media/missing.png', '');

    expect($media->name)->toBe('missing.png')
        ->and($media->size)->toBe(0)
        ->and($media->mime_type)->toBeNull();
});
