<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Livewire\MediaPicker;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;

/*
 * Two people and one library, under the policy an application actually writes.
 *
 * `MediaAccessTest` pins each check on its own, mostly with policies that take
 * no arguments. This one walks the shape nearly every application ends up with
 * — "everybody sees the library, you change your own uploads" — written the way
 * `make:policy --model=Media` scaffolds it, and drives it through every door the
 * screen has: the toolbar, the drag, cut and paste, the editor, and the picker
 * that sits on every page of the panel.
 *
 * Nothing here is new behaviour. It is the regression net under two defects
 * that were fixed separately and only mean something together: the policy used
 * to be asked about the class (so a conventional `update(User, Media)` was a
 * 500, and "your own uploads" could not be said at all), and the picker used to
 * inherit every mutation (so a chooser was a delete button on every page).
 */

final class MoOwnUploadsPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return true;
    }

    public function view(AuthUser $user, Media $media): bool
    {
        return true;
    }

    public function create(AuthUser $user): bool
    {
        return true;
    }

    public function update(AuthUser $user, Media $media): bool
    {
        return $media->uploaded_by === (string) $user->getAuthIdentifier();
    }

    public function delete(AuthUser $user, Media $media): bool
    {
        return $media->uploaded_by === (string) $user->getAuthIdentifier();
    }

    public function replace(AuthUser $user, Media $media): bool
    {
        return $media->uploaded_by === (string) $user->getAuthIdentifier();
    }
}

function moPerson(int $id): AuthUser
{
    return (new AuthUser)->forceFill(['id' => $id]);
}

/** Upload as this person, through the drop zone, and hand back the row. */
function moUpload(AuthUser $who, string $name): Media
{
    test()->be($who);

    Livewire::test(MediaManager::class)
        ->set('uploads', [UploadedFile::fake()->image($name, 8 + strlen($name), 8)]);

    return Media::query()->where('name', $name)->sole();
}

beforeEach(function () {
    Storage::fake('public');

    foreach (glob(__DIR__.'/../../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }

    Gate::policy(Media::class, MoOwnUploadsPolicy::class);

    $this->jane = moPerson(1);
    $this->sam = moPerson(2);

    $this->janes = moUpload($this->jane, 'jane.png');
    $this->sams = moUpload($this->sam, 'sam.png');

    $this->folder = MediaFolder::createIn(null, 'Shared');
});

it('files an upload under the person who made it', function () {
    // Everything below rests on this column: a policy about "your own uploads"
    // is only as good as the record of whose they are.
    expect($this->janes->uploaded_by)->toBe('1')
        ->and($this->sams->uploaded_by)->toBe('2');
});

it('shows both people the whole library', function () {
    $this->be($this->sam);

    Livewire::test(MediaManager::class)
        ->assertOk()
        ->assertSee('jane.png')
        ->assertSee('sam.png');
});

// ─── The library screen ────────────────────────────────────────────

it('lets each person rename their own file and not the other s', function () {
    $this->be($this->sam);

    Livewire::test(MediaManager::class)
        ->call('startRenaming', $this->janes->id)
        ->set('renamingName', 'hijacked.png')
        ->call('saveRename')
        ->assertOk()
        ->call('startRenaming', $this->sams->id)
        ->set('renamingName', 'sam-renamed.png')
        ->call('saveRename')
        ->assertOk();

    expect($this->janes->fresh()->name)->toBe('jane.png')
        ->and($this->sams->fresh()->name)->toBe('sam-renamed.png');
});

it('deletes only the permitted part of a mixed selection, and says so', function () {
    $this->be($this->sam);

    Livewire::test(MediaManager::class)
        ->set('selected', [$this->janes->id, $this->sams->id])
        ->call('deleteSelected')
        ->assertOk()
        ->assertSet('selected', []);

    expect(Media::find($this->janes->id))->not->toBeNull()
        ->and(Media::find($this->sams->id))->toBeNull();

    // The file went with the row it belonged to, and only that one.
    Storage::disk('public')->assertExists($this->janes->path);
    Storage::disk('public')->assertMissing($this->sams->path);
});

it('refuses the one-file delete on someone else s file', function () {
    $this->be($this->sam);

    Livewire::test(MediaManager::class)->call('deleteOne', $this->janes->id)->assertOk();

    expect(Media::find($this->janes->id))->not->toBeNull();
});

it('holds every way of moving a file to the same rule', function () {
    // A drag, the toolbar's list and cut-and-paste are three doors into one
    // move; each is a public method, so each has to answer the same way.
    $this->be($this->sam);

    Livewire::test(MediaManager::class)
        ->call('moveTo', $this->folder->id, $this->janes->id)
        ->set('selected', [$this->janes->id])
        ->call('moveSelectedTo', (string) $this->folder->id)
        ->set('selected', [$this->janes->id])
        ->call('cutSelection')
        ->call('openFolder', $this->folder->id)
        ->call('pasteHere')
        ->assertOk()
        // A paste that was refused leaves nothing to paste again.
        ->assertSet('clipboard', []);

    expect($this->janes->fresh()->folder_id)->toBeNull();

    Livewire::test(MediaManager::class)->call('moveTo', $this->folder->id, $this->sams->id);

    expect($this->sams->fresh()->folder_id)->toBe($this->folder->id);
});

it('will not save someone else s details, even with the detail panel forced open', function () {
    $this->be($this->sam);

    Livewire::test(MediaManager::class)
        ->call('showDetail', $this->janes->id)
        ->set('detailAlt', 'Defaced')
        ->call('saveDetail')
        ->assertOk()
        // And the one path a crafted request would take: skip showDetail and
        // set the id straight onto the public property.
        ->set('detailId', $this->janes->id)
        ->set('detailAlt', 'Defaced again')
        ->call('saveDetail')
        ->assertOk();

    expect($this->janes->fresh()->alt)->toBeNull();
});

it('will not replace someone else s picture, whichever way the editor was reached', function () {
    $this->be($this->sam);
    $before = Storage::disk('public')->get($this->janes->path);

    // Through the button: the editor refuses to open at all.
    Livewire::test(MediaManager::class)
        ->call('openEditor', $this->janes->id)
        ->assertSet('editingId', null);

    // Round the button: the editing id is a public property, so the check
    // that holds has to be the one at the moment the bytes arrive.
    Livewire::test(MediaManager::class)
        ->set('editingId', $this->janes->id)
        ->set('editorIntent', 'replace')
        ->set('editorUpload', UploadedFile::fake()->image('overwrite.png', 40, 40))
        ->assertOk();

    expect(Storage::disk('public')->get($this->janes->path))->toBe($before)
        ->and($this->janes->fresh()->width)->toBe($this->janes->width);
});

it('lets the owner do everything the other person was refused', function () {
    $this->be($this->jane);

    Livewire::test(MediaManager::class)
        ->call('startRenaming', $this->janes->id)
        ->set('renamingName', 'jane-final.png')
        ->call('saveRename')
        ->call('moveTo', $this->folder->id, $this->janes->id)
        ->assertOk();

    expect($this->janes->fresh())
        ->name->toBe('jane-final.png')
        ->folder_id->toBe($this->folder->id);

    Livewire::test(MediaManager::class)->call('deleteOne', $this->janes->id);

    expect(Media::find($this->janes->id))->toBeNull();
});

// ─── The picker, on every page of the panel ────────────────────────

it('lets the picker upload and choose, and nothing else — even for the owner', function () {
    // The owner may delete their file in the library. They may not do it from
    // the chooser a form field opened: the picker is a different screen with a
    // narrower job, and it is mounted on pages nobody thinks of as the library.
    $this->be($this->jane);

    $picker = Livewire::test(MediaPicker::class)
        ->set('uploads', [UploadedFile::fake()->image('from-the-form.png', 9, 9)])
        ->assertOk();

    expect(Media::query()->where('name', 'from-the-form.png')->value('uploaded_by'))->toBe('1');

    $picker
        ->set('selected', [$this->janes->id])
        ->call('deleteSelected')
        ->call('deleteOne', $this->janes->id)
        ->call('moveTo', $this->folder->id, $this->janes->id)
        ->call('startRenaming', $this->janes->id)
        ->set('renamingName', 'renamed-from-a-form.png')
        ->call('saveRename')
        ->call('renameFolder', $this->folder->id, 'Renamed from a form')
        ->call('deleteFolder', $this->folder->id)
        ->assertOk();

    expect($this->janes->fresh())
        ->not->toBeNull()
        ->name->toBe('jane.png')
        ->folder_id->toBeNull()
        ->and($this->folder->fresh()?->name)->toBe('Shared');
});

it('will not let a request turn the picker back into the library', function () {
    // `picking` is the flag every refusal above reads. If a crafted update could
    // flip it, the picker would be the library again with one request.
    $this->be($this->jane);

    Livewire::test(MediaPicker::class)->set('picking', false);
})->throws(CannotUpdateLockedPropertyException::class);
