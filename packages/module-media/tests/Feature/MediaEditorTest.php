<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireModuleMedia\Actions\ReplaceOriginal;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Support\MediaAccess;

/*
 * Editing an image, and replacing an original.
 *
 * The library's oldest refusal was to do this at all, and the danger it named is
 * real: replacing bytes changes every use of a file at once, including the ones
 * nobody can see. What makes it defensible is that the warning in front of it is
 * true (ADR 0034) and that the ordinary outcome is a new file (ADR 0035).
 */

beforeEach(function () {
    Storage::fake('public');

    // Signed in, because Laravel's gate denies a guest before it reaches a
    // policy method — so a guest would make the two policy tests below pass for
    // the wrong reason.
    test()->be(new MediaEditorUser);

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }
});

function edFile(string $name = 'hero.jpg', int $w = 60, int $h = 40): Media
{
    return (new StoreUpload)(UploadedFile::fake()->image($name, $w, $h));
}

/* ── Opening it ───────────────────────────────────────────────────────────── */

it('will not open on a file with no pixels to resample', function () {
    $pdf = Media::create(['disk' => 'public', 'path' => 'media/a.pdf', 'name' => 'a.pdf', 'mime_type' => 'application/pdf']);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $pdf->id)
        ->assertSet('editingId', null);
});

it('will not open on an SVG, which has nothing to resample either', function () {
    $svg = Media::create(['disk' => 'public', 'path' => 'media/logo.svg', 'name' => 'logo.svg', 'mime_type' => 'image/svg+xml']);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $svg->id)
        ->assertSet('editingId', null);
});

it('opens on a photograph', function () {
    $media = edFile();

    Livewire::test(MediaManager::class)
        ->call('openEditor', $media->id)
        ->assertSet('editingId', $media->id)
        // Opening is not yet a decision to overwrite anything.
        ->assertSet('editorIntent', 'new');
});

/* ── Saving as a new file ─────────────────────────────────────────────────── */

it('writes a new row that points back at the original', function () {
    $original = edFile('podzim.jpg');
    $original->update(['alt' => 'Podzimní kolekce']);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $original->id)
        ->set('editorIntent', 'new')
        ->set('editorUpload', UploadedFile::fake()->image('podzim.jpg', 30, 30));

    $derived = Media::query()->where('derived_from_id', $original->id)->first();

    expect($derived)->not->toBeNull()
        ->and($derived->name)->toBe('podzim (edited).jpg')
        // A crop of a photograph is still that photograph.
        ->and($derived->alt)->toBe('Podzimní kolekce')
        ->and($derived->folder_id)->toBe($original->folder_id);

    // The original is untouched, which is the whole point of this outcome.
    expect($original->fresh()->width)->toBe(60);
});

it('closes the editor once the file has landed', function () {
    $original = edFile();

    Livewire::test(MediaManager::class)
        ->call('openEditor', $original->id)
        ->set('editorUpload', UploadedFile::fake()->image('x.jpg', 30, 30))
        ->assertSet('editingId', null);
});

it('keeps a derivative when its original is deleted', function () {
    $original = edFile('one.jpg');

    Livewire::test(MediaManager::class)
        ->call('openEditor', $original->id)
        ->set('editorUpload', UploadedFile::fake()->image('one.jpg', 30, 30));

    $derived = Media::query()->where('derived_from_id', $original->id)->firstOrFail();

    $original->delete();

    // The derivative is a file in its own right — it has its own URL, its own
    // alt text and quite possibly its own uses. Losing it because somebody
    // tidied up the photograph it was cut from is exactly the silent damage
    // this module exists to avoid.
    expect($derived->fresh())->not->toBeNull()
        ->and($derived->fresh()->derived_from_id)->toBeNull();
});

/* ── Replacing the original ───────────────────────────────────────────────── */

it('writes new bytes under the same row, at the same path', function () {
    $media = edFile('hero.jpg', 60, 40);
    $path = $media->path;
    $before = $media->size;

    $ok = app(ReplaceOriginal::class)($media, UploadedFile::fake()->image('anything.jpg', 200, 120));

    $media->refresh();

    expect($ok)->toBeTrue()
        // The path is kept on purpose: moving the bytes would change the URL of
        // a file a published page already links to.
        ->and($media->path)->toBe($path)
        ->and($media->width)->toBe(200)
        ->and($media->height)->toBe(120)
        ->and($media->size)->not->toBe($before);
});

it('makes the row describe the file it now holds, not the one it used to', function () {
    $media = edFile('hero.jpg', 60, 40);
    $checksum = $media->checksum;

    app(ReplaceOriginal::class)($media, UploadedFile::fake()->image('other.jpg', 90, 90));

    // A row whose checksum still describes the old bytes lies to duplicate
    // detection first.
    expect($media->fresh()->checksum)->not->toBe($checksum);
});

it('remakes the thumbnail, which is otherwise of the old picture', function () {
    config()->set('wire-module-media.thumbnails.enabled', true);

    $media = edFile('hero.jpg', 800, 600);
    $thumb = $media->fresh()->thumb_path;

    if ($thumb === null) {
        // No GD on this build; the row still has to come back correct.
        expect(true)->toBeTrue();

        return;
    }

    $before = Storage::disk('public')->get($thumb);

    app(ReplaceOriginal::class)($media, UploadedFile::fake()->image('other.jpg', 800, 200));

    expect(Storage::disk('public')->get($media->fresh()->thumb_path))->not->toBe($before);
});

it('puts a version on the address, so a cache cannot serve the old picture', function () {
    $media = edFile();

    $before = $media->url();

    // Same path, different content, is precisely what a browser and a CDN get
    // wrong — so the address carries the row's updated_at.
    $media->update(['updated_at' => now()->addMinute()]);

    expect($before)->toContain('v=')
        ->and($media->fresh()->url())->not->toBe($before);
});

it('changes nothing when the disk will not take the new bytes', function () {
    $media = edFile();
    $checksum = $media->checksum;

    $upload = UploadedFile::fake()->image('x.jpg', 20, 20);
    @unlink($upload->getRealPath());

    // A failed replacement must not read as a lost original.
    expect(app(ReplaceOriginal::class)($media, $upload))->toBeFalse()
        ->and($media->fresh()->checksum)->toBe($checksum);
});

it('replaces through the screen as well', function () {
    $media = edFile('hero.jpg', 60, 40);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $media->id)
        ->set('editorIntent', 'replace')
        ->set('editorUpload', UploadedFile::fake()->image('hero.jpg', 120, 80));

    expect($media->fresh()->width)->toBe(120)
        // One row, not two: this outcome writes no derivative.
        ->and(Media::query()->count())->toBe(1);
});

/* ── Who may do it ────────────────────────────────────────────────────────── */

it('asks the policy about replacing, and falls back to update', function () {
    // A policy written before the editor existed has no `replace` method, and
    // Laravel's gate denies an ability nobody defined — which would take the
    // feature away from every library that upgraded.
    Gate::policy(Media::class, MediaOnlyUpdatePolicy::class);

    expect(MediaAccess::allows('replace', edFile()))->toBeTrue();
});

it('obeys a policy that does define replacing', function () {
    Gate::policy(Media::class, MediaRefusesReplacePolicy::class);

    expect(MediaAccess::allows('replace', edFile()))->toBeFalse()
        ->and(MediaAccess::allows('update', edFile()))->toBeTrue();
});

it('refuses a replacement the policy refused', function () {
    $media = edFile('hero.jpg', 60, 40);

    Gate::policy(Media::class, MediaRefusesReplacePolicy::class);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $media->id)
        ->set('editorIntent', 'replace')
        ->set('editorUpload', UploadedFile::fake()->image('hero.jpg', 120, 80));

    expect($media->fresh()->width)->toBe(60);
});

/* ── When it cannot be done ───────────────────────────────────────────────── */

it('will not open the editor for somebody who may not change the file', function () {
    $media = edFile();

    Gate::policy(Media::class, MediaRefusesUpdatePolicy::class);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $media->id)
        ->assertSet('editingId', null);
});

it('closes itself when the upload arrives with nothing to apply it to', function () {
    edFile();

    // No editor was opened, so there is no row for the file to be applied to.
    // The hook has to put everything away rather than reach for a null.
    Livewire::test(MediaManager::class)
        ->set('editorUpload', UploadedFile::fake()->image('x.jpg', 20, 20))
        ->assertSet('editingId', null)
        ->assertSet('editorUpload', null);

    // And nothing was written on the way past.
    expect(Media::query()->count())->toBe(1);
});

it('leaves the original alone when a replacement answers false', function () {
    $media = edFile('hero.jpg', 60, 40);

    app()->bind(ReplaceOriginal::class, fn (): object => new MediaReplacementThatFails);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $media->id)
        ->set('editorIntent', 'replace')
        ->set('editorUpload', UploadedFile::fake()->image('hero.jpg', 120, 80));

    // The old file is still there and the row still describes it: a failed
    // replacement must not read as a lost original, and must not quietly become
    // the other outcome by writing a derivative instead.
    expect($media->fresh()->width)->toBe(60)
        ->and(Media::query()->count())->toBe(1);
});

it('will not write a derivative for somebody who may not add files', function () {
    $media = edFile('hero.jpg', 60, 40);

    Gate::policy(Media::class, MediaRefusesCreatePolicy::class);

    Livewire::test(MediaManager::class)
        ->call('openEditor', $media->id)
        ->set('editorIntent', 'new')
        ->set('editorUpload', UploadedFile::fake()->image('hero.jpg', 30, 30));

    expect(Media::query()->count())->toBe(1);
});

it('says so when the disk will not take the edited copy', function () {
    $media = edFile('hero.jpg', 60, 40);

    // A disk nothing may write into: the store answers null and the editor has
    // a failure to report rather than a row to point at.
    $readonly = storage_path('framework/testing/readonly');
    @mkdir($readonly, 0777, true);
    chmod($readonly, 0555);
    config()->set('filesystems.disks.readonly', ['driver' => 'local', 'root' => $readonly]);
    config()->set('wire-module-media.disk', 'readonly');

    Livewire::test(MediaManager::class)
        ->call('openEditor', $media->id)
        ->set('editorIntent', 'new')
        ->set('editorUpload', UploadedFile::fake()->image('hero.jpg', 30, 30));

    chmod($readonly, 0755);

    expect(Media::query()->count())->toBe(1);
})->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root writes into a read-only directory anyway');

it('hands back the row it has when the same crop is made twice', function () {
    $original = edFile('hero.jpg', 60, 40);
    $first = (new StoreUpload)(UploadedFile::fake()->image('crop.jpg', 30, 30));

    // The same bytes again, which is what making one crop twice produces.
    $again = UploadedFile::fake()->createWithContent(
        'crop.jpg',
        (string) Storage::disk('public')->get((string) $first->path),
    );

    Livewire::test(MediaManager::class)
        ->call('openEditor', $original->id)
        ->set('editorIntent', 'new')
        ->set('editorUpload', $again);

    // Two rows, not three — and the one that already existed is not quietly
    // re-pointed at the original this editor happened to be open on.
    expect(Media::query()->count())->toBe(2)
        ->and($first->fresh()->derived_from_id)->toBeNull();
});

/* ── The action's own edges ───────────────────────────────────────────────── */

it('changes nothing when the upload cannot be opened for reading', function () {
    $media = edFile();
    $checksum = $media->checksum;

    $upload = UploadedFile::fake()->image('x.jpg', 20, 20);
    chmod((string) $upload->getRealPath(), 0000);

    expect(app(ReplaceOriginal::class)($media, $upload))->toBeFalse()
        ->and($media->fresh()->checksum)->toBe($checksum);

    chmod((string) $upload->getRealPath(), 0644);
})->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can read a file with no permissions');

it('changes nothing when the disk raises rather than answers', function () {
    // `throw` is a disk setting an application may well have on, and it turns
    // every failed write in here into an exception instead of a false. Catching
    // it is what keeps a refused replacement from taking the request down.
    Storage::fake('public', ['throw' => true]);

    $media = edFile();
    $checksum = $media->checksum;

    $storage = Storage::disk('public');
    $storage->delete((string) $media->path);
    $storage->makeDirectory((string) $media->path);

    expect(app(ReplaceOriginal::class)($media, UploadedFile::fake()->image('x.jpg', 20, 20)))->toBeFalse()
        ->and($media->fresh()->checksum)->toBe($checksum);
});

class MediaEditorUser extends User
{
    protected $table = 'users';
}

class MediaOnlyUpdatePolicy
{
    public function update(): bool
    {
        return true;
    }
}

class MediaRefusesReplacePolicy
{
    public function update(): bool
    {
        return true;
    }

    public function replace(): bool
    {
        return false;
    }
}

class MediaRefusesUpdatePolicy
{
    public function update(): bool
    {
        return false;
    }
}

class MediaRefusesCreatePolicy
{
    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return true;
    }
}

/** Stands in for the action so the screen's answer to a false can be asserted. */
class MediaReplacementThatFails
{
    public function __invoke(Media $media, mixed $upload): bool
    {
        return false;
    }
}
