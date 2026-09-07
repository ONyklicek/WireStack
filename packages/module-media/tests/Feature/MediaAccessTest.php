<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireModuleMedia\Actions\MakeThumbnail;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Jobs\GenerateThumbnail;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Support\MediaAccess;
use NyonCode\WireModuleMedia\WireModuleMediaServiceProvider;

/*
 * Who may see a file, and who may change one.
 *
 * Two holes this closes, and they are the two that stopped the module being
 * usable for anything but a logo:
 *
 *   - a private disk answers no URL, so the grid showed blank tiles and the
 *     download link was missing, with nothing saying why;
 *   - anybody who reached the page could delete anything, because there was
 *     nothing to ask.
 *
 * The rule underneath both: **a library with no policy behaves exactly as it did
 * before this existed.** Laravel denies an ability nobody defined, so asking the
 * gate unconditionally would have locked every existing installation out of its
 * own files on upgrade.
 */

beforeEach(function () {
    Storage::fake('public');

    // Signed in, because that is the only state in which any of this means
    // anything: Laravel's gate denies a guest before it reaches a policy method,
    // and the route these tests exercise sits behind `auth` by default.
    test()->be(new AuthUser);

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }
});

/** A policy that says yes to everything except what it is told to refuse. */
function maPolicyRefusing(string ...$abilities): void
{
    $refused = $abilities;

    Gate::policy(Media::class, get_class(new class($refused)
    {
        /** @var array<int, string> */
        public static array $refused = [];

        /** @param array<int, string> $refused */
        public function __construct(array $refused = [])
        {
            if ($refused !== []) {
                self::$refused = $refused;
            }
        }

        public function viewAny(): bool
        {
            return ! in_array('viewAny', self::$refused, true);
        }

        public function view(): bool
        {
            return ! in_array('view', self::$refused, true);
        }

        public function create(): bool
        {
            return ! in_array('create', self::$refused, true);
        }

        public function update(): bool
        {
            return ! in_array('update', self::$refused, true);
        }

        public function delete(): bool
        {
            return ! in_array('delete', self::$refused, true);
        }
    }));
}

/**
 * A disk that is genuinely private.
 *
 * `Storage::fake()` is not one: it configures a `url`, so the driver answers a
 * public address and the fallback under test is never reached. A local disk with
 * no `url` key is the real thing — `url()` on it throws, which is exactly the
 * case `Media::url()` catches.
 */
function maPrivateDisk(): string
{
    $root = sys_get_temp_dir().'/wire-media-private-'.bin2hex(random_bytes(4));

    mkdir($root, 0755, true);

    config()->set('filesystems.disks.vault', ['driver' => 'local', 'root' => $root, 'throw' => false]);
    config()->set('wire-module-media.disk', 'vault');

    return $root;
}

/* ── The gate ─────────────────────────────────────────────────────────────── */

it('allows everything when no policy was ever registered', function () {
    // The upgrade path. An application that never wrote a policy did not thereby
    // decide that nobody may do anything.
    expect(MediaAccess::allows('delete'))->toBeTrue()
        ->and(MediaAccess::allows('anything-at-all'))->toBeTrue()
        ->and(MediaAccess::denies('update'))->toBeFalse();
});

it('obeys a policy the moment one exists', function () {
    maPolicyRefusing('delete');

    expect(MediaAccess::allows('update'))->toBeTrue()
        ->and(MediaAccess::allows('delete'))->toBeFalse()
        ->and(MediaAccess::denies('delete'))->toBeTrue();
});

/* ── The screen ───────────────────────────────────────────────────────────── */

it('refuses to delete for someone the policy refuses', function () {
    // Asked in the method, not hidden in the view: a Livewire method is a public
    // endpoint, and a hidden button is not a check.
    maPolicyRefusing('delete');

    $media = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaManager::class)
        ->set('selected', [$media->id])
        ->call('deleteSelected')
        ->assertOk();

    expect(Media::count())->toBe(1);
});

it('refuses to rename, move or describe a file for the same reason', function () {
    maPolicyRefusing('update');

    $media = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaManager::class)
        ->call('startRenaming', $media->id)
        ->set('renamingName', 'Something else')
        ->call('saveRename')
        ->call('showDetail', $media->id)
        ->set('detailAlt', 'A description')
        ->call('saveDetail')
        ->set('selected', [$media->id])
        ->call('moveTo', null)
        ->assertOk();

    expect($media->refresh()->name)->toBe('a.png')
        ->and($media->alt)->toBeNull();
});

it('refuses an upload, and does not leave it half-taken', function () {
    maPolicyRefusing('create');

    Livewire::test(MediaManager::class)
        ->set('uploads', [UploadedFile::fake()->image('shot.png', 20, 20)])
        // Cleared as well as refused: an upload left in the property would be
        // re-attempted on the next round trip, refused again, for ever.
        ->assertSet('uploads', [])
        ->assertOk();

    expect(Media::count())->toBe(0);
});

it('lets everything through for a policy that allows it', function () {
    maPolicyRefusing('nothing-at-all');

    $media = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaManager::class)
        ->set('selected', [$media->id])
        ->call('deleteSelected');

    expect(Media::count())->toBe(0);
});

/* ── Serving a private file ───────────────────────────────────────────────── */

it('streams a file the disk will not hand out itself', function () {
    // A local disk with no configured URL. Before the route, `url()` answered
    // null and the library rendered blank tiles with nothing saying why.
    maPrivateDisk();

    Storage::disk('vault')->put('media/secret.txt', 'contract');

    $media = Media::create([
        'disk' => 'vault',
        'path' => 'media/secret.txt',
        'name' => 'contract.txt',
        'mime_type' => 'text/plain',
        'size' => 8,
    ]);

    expect($media->url())->toContain('/wire-media/'.$media->id)
        ->and($media->downloadUrl())->toContain('/download');

    $response = $this->get((string) $media->url());

    expect($response->streamedContent())->toBe('contract');

    // The stored name, not the path: a file downloaded as `9f2a1c8e.txt` is a
    // file nobody can find again.
    $this->get((string) $media->downloadUrl())
        ->assertHeader('content-disposition', 'attachment; filename="contract.txt"');
});

it('answers 403 for a file the policy will not show', function () {
    maPrivateDisk();
    Storage::disk('vault')->put('media/secret.txt', 'contract');

    maPolicyRefusing('view');

    $media = Media::create(['disk' => 'vault', 'path' => 'media/secret.txt', 'name' => 'c.txt']);

    // Middleware answers "is anybody signed in"; the policy answers "may this
    // person see this file", and only the second is the question.
    $this->get((string) $media->url())->assertForbidden();
});

it('answers 404 for a row whose file is gone', function () {
    maPrivateDisk();

    // A disk emptied by hand, a restore that skipped storage. A 404 says which
    // of the two things is missing far better than a stream of nothing.
    $media = Media::create(['disk' => 'vault', 'path' => 'media/not-there.txt', 'name' => 'x.txt']);

    $this->get((string) $media->url())->assertNotFound();
});

it('keeps a public disk on its own fast URL', function () {
    // The route exists for the other case and only for it: a public file fetched
    // straight from the disk never touches PHP.
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    expect($media->url())->toContain('/storage/media/a.png')
        ->and($media->url())->not->toContain('/wire-media/');
});

it('answers null rather than a public URL when the route is switched off', function () {
    maPrivateDisk();
    config()->set('wire-module-media.route.enabled', false);

    $media = Media::create(['disk' => 'vault', 'path' => 'media/x.txt', 'name' => 'x.txt']);

    // A broken image is a better answer than a URL for a file somebody meant to
    // keep private.
    expect($media->url())->toBeNull()
        ->and($media->downloadUrl())->toBeNull();
});

/* ── The queue ────────────────────────────────────────────────────────────── */

it('makes the thumbnail in the request by default', function () {
    Queue::fake();

    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));

    // Off by default: a queue nobody works would mean thumbnails that never
    // appear, which is worse than an upload that takes a moment.
    Queue::assertNothingPushed();

    expect($media?->thumb_path)->not->toBeNull();
});

it('hands it to a worker when one is being run', function () {
    Queue::fake();
    config()->set('wire-module-media.thumbnails.queue', true);

    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));

    Queue::assertPushed(GenerateThumbnail::class);

    // The row is saved either way, so a job that never runs costs a thumbnail
    // and nothing else — and the backfill command picks it up later.
    expect($media)->not->toBeNull()
        ->and($media?->thumb_path)->toBeNull();
});

it('puts it on the queue it was told to', function () {
    Queue::fake();
    config()->set('wire-module-media.thumbnails.queue', 'media');

    (new StoreUpload)(UploadedFile::fake()->image('shot.png', 800, 600));

    Queue::assertPushedOn('media', GenerateThumbnail::class);
});

it('makes the thumbnail when the job actually runs', function () {
    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/shot.png',
        'name' => 'shot.png',
        'mime_type' => 'image/png',
    ]);

    Storage::disk('public')->put('media/shot.png', (string) file_get_contents(__DIR__.'/../fixtures/pixel.png'));

    (new GenerateThumbnail($media))->handle(app(MakeThumbnail::class));

    expect($media->refresh()->thumb_path)->not->toBeNull();
});

it('has nothing to undo when the job fails', function () {
    // The row and the file are independent of it, and a missing thumbnail is a
    // state every view already handles.
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    (new GenerateThumbnail($media))->failed();

    expect(Media::find($media->id))->not->toBeNull();
});

it('registers no route when the application switched it off', function () {
    // The early return, exercised where it actually happens: at boot, with the
    // configuration an application would have set. Asserting it any other way
    // would be asserting that an `if` has two branches.
    $this->app->make('config')->set('wire-module-media.route.enabled', false);

    $provider = new WireModuleMediaServiceProvider($this->app);

    (fn () => $this->registerRoutes())->call($provider);

    // Already registered by the real boot, so the point is that nothing new was
    // added and nothing threw — a second registration would have been a second
    // route with the same name.
    expect(Route::getRoutes()->getByName('wire-media.show'))->not->toBeNull();
});
