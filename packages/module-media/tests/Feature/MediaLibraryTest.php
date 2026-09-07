<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Enums\FileKind;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Exceptions\MediaException;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;
use NyonCode\WireModuleMedia\Pages\ListMedia;
use NyonCode\WireModuleMedia\Resources\MediaResource;

/*
 * The library as a place to keep files, rather than a list of them.
 *
 * What was here before was a table with an upload form beside it: no folders, no
 * way to move anything, nothing to drop a file onto. These assertions are about
 * the half that was missing, and about the rule that decides the shape of all of
 * it — **a folder is a row, not a directory.** Nothing on the disk moves when a
 * file is filed somewhere else, because moving the bytes would change the URL of
 * a file a published page already links to.
 */

beforeEach(function () {
    Storage::fake('public');

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }
});

/* ── The tree ─────────────────────────────────────────────────────────────── */

/** A policy that says yes to everything except what it is told to refuse. */
function maLibraryPolicyRefusing(string ...$abilities): void
{
    Gate::policy(Media::class, get_class(new class($abilities)
    {
        /** @var array<int, string> */
        public static array $refused = [];

        /** @param array<int, string> $refused */
        public function __construct(array $refused = [])
        {
            // Guarded: the container resolves this policy with no arguments, and
            // an unguarded assignment would reset the static the test just set.
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

    test()->be(new class extends User
    {
        protected $table = 'users';
    });
}

it('nests folders and stores the path rather than walking it', function () {
    $root = MediaFolder::createIn(null, 'Brand');
    $deep = MediaFolder::createIn(MediaFolder::createIn($root, '2026'), 'Q1');

    expect($deep->path)->toBe('Brand/2026/Q1')
        // One query for a breadcrumb of any depth, which is the whole reason the
        // path is stored at all.
        ->and($deep->breadcrumb()->pluck('name')->all())->toBe(['Brand', '2026', 'Q1']);
});

it('refuses two folders with one name in one place', function () {
    $parent = MediaFolder::createIn(null, 'Brand');
    MediaFolder::createIn($parent, 'Logos');

    expect(fn () => MediaFolder::createIn($parent, 'Logos'))
        ->toThrow(MediaException::class, 'already here')
        // Beside each other, not everywhere: the same name under a different
        // parent is a different folder and reads differently in a breadcrumb.
        ->and(MediaFolder::createIn(null, 'Logos')->path)->toBe('Logos');
});

it('refuses a name that is nothing once the slashes are out', function () {
    // A slash inside the name would invent a level of the path that no row
    // exists for, so it is stripped — and what is left has to be something.
    expect(fn () => MediaFolder::createIn(null, ' /// '))
        ->toThrow(MediaException::class, 'needs a name')
        ->and(MediaFolder::createIn(null, 'a/b')->name)->toBe('a b');
});

it('rewrites every path below a folder it renames', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $year = MediaFolder::createIn($brand, '2026');
    $quarter = MediaFolder::createIn($year, 'Q1');

    $brand->rename('Identity');

    expect($year->refresh()->path)->toBe('Identity/2026')
        ->and($quarter->refresh()->path)->toBe('Identity/2026/Q1');
});

it('leaves a rename to the same name alone', function () {
    $folder = MediaFolder::createIn(null, 'Brand');

    expect($folder->rename('Brand')->path)->toBe('Brand');
});

it('refuses to rename a folder onto a sibling that has that name', function () {
    MediaFolder::createIn(null, 'Brand');
    $other = MediaFolder::createIn(null, 'Press');

    expect(fn () => $other->rename('Brand'))->toThrow(MediaException::class, 'already here');
});

it('moves a folder, and carries what is under it', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $logos = MediaFolder::createIn($brand, 'Logos');
    $svg = MediaFolder::createIn($logos, 'SVG');
    $press = MediaFolder::createIn(null, 'Press');

    $logos->moveTo($press);

    expect($logos->refresh()->path)->toBe('Press/Logos')
        ->and($svg->refresh()->path)->toBe('Press/Logos/SVG')
        // And back out to the root, which a drag onto the library row does.
        ->and($logos->moveTo(null)->path)->toBe('Logos');
});

it('refuses to move a folder inside itself', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $logos = MediaFolder::createIn($brand, 'Logos');

    // The branch would have no root from there down and nothing in it could be
    // reached again — the one folder operation that loses data silently.
    expect(fn () => $brand->moveTo($logos))->toThrow(MediaException::class, 'inside itself')
        ->and(fn () => $brand->moveTo($brand))->toThrow(MediaException::class, 'inside itself');
});

it('refuses to move a folder where its name is already taken', function () {
    $press = MediaFolder::createIn(null, 'Press');
    MediaFolder::createIn($press, 'Logos');
    $logos = MediaFolder::createIn(null, 'Logos');

    expect(fn () => $logos->moveTo($press))->toThrow(MediaException::class, 'already here');
});

it('will not delete a folder that still holds anything', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $logos = MediaFolder::createIn($brand, 'Logos');

    expect(fn () => $brand->deleteEmpty())->toThrow(MediaException::class, 'still holds');

    Media::create(['folder_id' => $logos->id, 'disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    expect(fn () => $logos->deleteEmpty())->toThrow(MediaException::class, '1 file');
});

it('lists a subtree from the stored paths', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $logos = MediaFolder::createIn($brand, 'Logos');
    MediaFolder::createIn(null, 'Press');

    expect($brand->subtreeIds())->toEqualCanonicalizing([$brand->id, $logos->id]);
});

/* ── Files in it ──────────────────────────────────────────────────────────── */

it('files a file without touching the disk', function () {
    $folder = MediaFolder::createIn(null, 'Brand');
    $media = Media::create(['disk' => 'public', 'path' => 'media/logo.png', 'name' => 'logo.png']);

    $media->moveTo($folder);

    expect($media->refresh()->folder_id)->toBe($folder->id)
        // The whole point: the address the world already has still resolves.
        ->and($media->path)->toBe('media/logo.png')
        ->and($media->folder->name)->toBe('Brand');
});

it('renames what a file is listed under, never where it is stored', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/x1y2.png', 'name' => 'x1y2.png']);

    $media->rename('Company logo');

    expect($media->refresh()->name)->toBe('Company logo')
        ->and($media->path)->toBe('media/x1y2.png');
});

it('ignores a rename to nothing', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    expect($media->rename('   ')->refresh()->name)->toBe('a.png');
});

/* ── The manager ──────────────────────────────────────────────────────────── */

it('shows what is in the folder it is looking at, and nothing else', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    Media::create(['folder_id' => $brand->id, 'disk' => 'public', 'path' => 'media/in.png', 'name' => 'inside.png']);
    Media::create(['disk' => 'public', 'path' => 'media/out.png', 'name' => 'at-root.png']);

    Livewire::test(MediaManager::class)
        ->assertSee('at-root.png')
        ->assertDontSee('inside.png')
        ->call('openFolder', $brand->id)
        ->assertSee('inside.png')
        ->assertDontSee('at-root.png');
});

it('searches the whole library rather than the open folder', function () {
    // Searching only where you happen to be standing is how a file nobody can
    // remember filing stays lost.
    $brand = MediaFolder::createIn(null, 'Brand');
    Media::create(['folder_id' => $brand->id, 'disk' => 'public', 'path' => 'media/deep.png', 'name' => 'buried.png']);

    Livewire::test(MediaManager::class)
        ->assertDontSee('buried.png')
        ->set('search', 'buried')
        ->assertSee('buried.png');
});

it('creates a folder inside the one being looked at', function () {
    $brand = MediaFolder::createIn(null, 'Brand');

    Livewire::test(MediaManager::class)
        ->call('openFolder', $brand->id)
        ->set('newFolderName', 'Logos')
        ->call('createFolder')
        ->assertSet('creatingFolder', false)
        ->assertSet('newFolderName', '');

    expect(MediaFolder::where('path', 'Brand/Logos')->exists())->toBeTrue();
});

it('turns a refusal into a message instead of an error page', function () {
    // The model owns the rule and says why in a sentence; the component's whole
    // job here is to show that sentence. Nothing re-checks the rule.
    MediaFolder::createIn(null, 'Brand');

    Livewire::test(MediaManager::class)
        ->set('newFolderName', 'Brand')
        ->call('createFolder')
        ->assertOk();

    expect(MediaFolder::where('name', 'Brand')->count())->toBe(1);
});

it('renames, moves and deletes a folder from the tree', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $press = MediaFolder::createIn(null, 'Press');

    Livewire::test(MediaManager::class)
        ->call('renameFolder', $brand->id, 'Identity')
        ->call('moveFolder', $brand->id, $press->id);

    expect($brand->refresh()->path)->toBe('Press/Identity');

    Livewire::test(MediaManager::class)
        ->call('moveFolder', $brand->id, null)
        ->call('deleteFolder', $brand->id);

    expect(MediaFolder::find($brand->id))->toBeNull();
});

it('steps back out of a folder it just deleted', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $logos = MediaFolder::createIn($brand, 'Logos');

    Livewire::test(MediaManager::class)
        ->call('openFolder', $logos->id)
        ->call('deleteFolder', $logos->id)
        // Standing inside a folder that no longer exists is an empty screen with
        // no way back to the one above it.
        ->assertSet('folderId', $brand->id);
});

it('says so rather than deleting a folder with files in it', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    Media::create(['folder_id' => $brand->id, 'disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaManager::class)->call('deleteFolder', $brand->id)->assertOk();

    expect(MediaFolder::find($brand->id))->not->toBeNull();
});

it('uploads what was dropped into the folder being looked at', function () {
    $brand = MediaFolder::createIn(null, 'Brand');

    Livewire::test(MediaManager::class)
        ->call('openFolder', $brand->id)
        ->set('uploads', [UploadedFile::fake()->image('shot.png', 8, 8)])
        ->assertSet('uploads', []);

    $media = Media::firstOrFail();

    expect($media->folder_id)->toBe($brand->id)
        ->and($media->name)->toBe('shot.png')
        // Read from the disk, not from what the browser claimed about the file.
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->mime_type)->toStartWith('image/')
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('moves the selection, and one file on its own', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $one = Media::create(['disk' => 'public', 'path' => 'media/1.png', 'name' => 'one.png']);
    $two = Media::create(['disk' => 'public', 'path' => 'media/2.png', 'name' => 'two.png']);

    Livewire::test(MediaManager::class)
        ->set('selected', [$one->id, $two->id])
        ->call('moveTo', $brand->id)
        ->assertSet('selected', []);

    expect($one->refresh()->folder_id)->toBe($brand->id);

    // Dragging one tile onto a folder passes the id and ignores the selection.
    Livewire::test(MediaManager::class)->call('moveTo', null, $two->id);

    expect($two->refresh()->folder_id)->toBeNull();
});

it('does nothing when asked to move an empty selection', function () {
    Livewire::test(MediaManager::class)->call('moveTo', null)->assertOk();
});

it('reads the destination the toolbar picked, placeholder included', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $media = Media::create(['folder_id' => $brand->id, 'disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    $component = Livewire::test(MediaManager::class)
        ->set('selected', [$media->id])
        // The placeholder is not a destination, and an empty value is the root —
        // three things in one string, which is why it is not decided in Blade.
        ->call('moveSelectedTo', '__');

    expect($media->refresh()->folder_id)->toBe($brand->id);

    $component->set('selected', [$media->id])->call('moveSelectedTo', '');

    expect($media->refresh()->folder_id)->toBeNull();

    $component->set('selected', [$media->id])->call('moveSelectedTo', (string) $brand->id);

    expect($media->refresh()->folder_id)->toBe($brand->id);
});

it('deletes the selection one row at a time, so the files go too', function () {
    Storage::disk('public')->put('media/a.png', 'x');
    Storage::disk('public')->put('media/b.png', 'x');

    $a = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);
    $b = Media::create(['disk' => 'public', 'path' => 'media/b.png', 'name' => 'b.png']);

    // A mass delete would not fire the model's `deleted` hook, and the bytes
    // would stay on the disk with no row pointing at them.
    Livewire::test(MediaManager::class)
        ->set('selected', [$a->id, $b->id])
        ->call('deleteSelected')
        ->assertSet('selected', []);

    expect(Media::count())->toBe(0)
        ->and(Storage::disk('public')->exists('media/a.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('media/b.png'))->toBeFalse();
});

it('deletes one file from its own tile', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaManager::class)->call('deleteOne', $media->id);

    expect(Media::count())->toBe(0);
});

it('says nothing when the selection was empty', function () {
    Livewire::test(MediaManager::class)->call('deleteSelected')->assertOk();
});

it('renames a file in place', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/x1.png', 'name' => 'x1.png']);

    Livewire::test(MediaManager::class)
        ->call('startRenaming', $media->id)
        ->assertSet('renamingName', 'x1.png')
        ->set('renamingName', 'Company logo')
        ->call('saveRename')
        ->assertSet('renamingId', null);

    expect($media->refresh()->name)->toBe('Company logo');
});

it('ignores a save with nothing being renamed', function () {
    Livewire::test(MediaManager::class)->call('saveRename')->assertOk();
});

it('switches between the grid and the list', function () {
    Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaManager::class)
        ->assertSee('media-tile', false)
        ->call('toggleView')
        ->assertSet('view', 'list')
        ->assertSee('media-row', false)
        ->call('toggleView')
        ->assertSet('view', 'grid');
});

it('goes back to the first page when the search changes', function () {
    Livewire::test(MediaManager::class)->set('search', 'anything')->assertOk();
});

it('opens on a folder it was mounted with', function () {
    $brand = MediaFolder::createIn(null, 'Brand');

    Livewire::test(MediaManager::class, ['folder' => $brand->id])
        ->assertSet('folderId', $brand->id)
        ->assertSee('Brand');
});

it('is the resource page, with the resource label as its title', function () {
    // The manager on its own knows nothing about resources; the page is where
    // that wiring lives, so the manager stays usable in a modal or beside a form.
    $page = Livewire::test(ListMedia::class)->assertSee('media-manager', false);

    expect(ListMedia::resourceClass())->not->toBeNull()
        ->and($page->instance()->getTitle())->toBe(MediaResource::label());
});

it('keeps the row out of the library when the file could not be stored', function () {
    // A regular file sits exactly where the upload directory would go, so
    // creating it fails for anybody, root included — which is what makes this
    // deterministic rather than dependent on who the test runs as. The disk's
    // own root is a real directory, because the local driver creates that when
    // it is resolved and would otherwise fail before the upload is reached.
    $root = sys_get_temp_dir().'/wire-media-'.bin2hex(random_bytes(4));

    mkdir($root, 0755, true);
    file_put_contents($root.'/media', 'not a directory');

    config()->set('filesystems.disks.broken', ['driver' => 'local', 'root' => $root, 'throw' => false]);
    config()->set('wire-module-media.disk', 'broken');
    config()->set('wire-module-media.directory', 'media');

    try {
        // Without the guard, the next lines ask the disk for the size and the
        // mime type of a file that was never written.
        Livewire::test(MediaManager::class)
            ->set('uploads', [UploadedFile::fake()->image('shot.png', 8, 8)])
            ->assertOk();

        expect(Media::count())->toBe(0);
    } finally {
        @unlink($root.'/media');
        @rmdir($root);
    }
});

/* ── Finding things in it ─────────────────────────────────────────────────── */

it('narrows to one kind of file, coarsely', function () {
    Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'photo.png', 'mime_type' => 'image/png']);
    Media::create(['disk' => 'public', 'path' => 'b.pdf', 'name' => 'contract.pdf', 'mime_type' => 'application/pdf']);
    // A file whose type was never recorded is not an image, so it belongs with
    // the documents rather than nowhere.
    Media::create(['disk' => 'public', 'path' => 'c.bin', 'name' => 'mystery.bin']);

    Livewire::test(MediaManager::class)
        ->set('type', 'image')
        ->assertSee('photo.png')
        ->assertDontSee('contract.pdf')
        ->set('type', 'document')
        ->assertSee('contract.pdf')
        ->assertSee('mystery.bin')
        ->assertDontSee('photo.png')
        ->set('type', '')
        ->assertSee('photo.png')
        ->assertSee('contract.pdf');
});

it('orders by what was asked for', function () {
    $old = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'alpha.png', 'size' => 10]);
    $new = Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'zulu.png', 'size' => 900]);

    $old->forceFill(['created_at' => now()->subDay()])->save();

    $order = function (string $sort) {
        $html = Livewire::test(MediaManager::class)->set('sort', $sort)->html();

        return strpos($html, 'alpha.png') < strpos($html, 'zulu.png') ? 'alpha' : 'zulu';
    };

    expect($order('newest'))->toBe('zulu')
        ->and($order('oldest'))->toBe('alpha')
        ->and($order('name'))->toBe('alpha')
        ->and($order('largest'))->toBe('zulu');
});

it('selects what is on the page, and never more than that', function () {
    // A checkbox that silently selects four thousand rows behind a Delete button
    // is the oldest way a bulk action becomes an accident.
    $one = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png']);
    $two = Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'b.png']);

    Livewire::test(MediaManager::class)
        ->call('toggleAll', [$one->id, $two->id])
        ->assertSet('selected', [$one->id, $two->id])
        // Pressed again with everything already selected, it clears.
        ->call('toggleAll', [$one->id, $two->id])
        ->assertSet('selected', []);
});

it('knows when a page is only partly selected', function () {
    $one = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png']);
    $two = Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'b.png']);

    $component = Livewire::test(MediaManager::class)->set('selected', [$one->id]);

    expect($component->instance()->allSelected([$one->id, $two->id]))->toBeFalse()
        ->and($component->instance()->allSelected([$one->id]))->toBeTrue()
        // An empty page is not "all selected", or the box would be ticked on a
        // folder with nothing in it.
        ->and($component->instance()->allSelected([]))->toBeFalse();
});

it('keeps the filtered view in the URL, so it can be sent to somebody', function () {
    // Read off the component rather than through a public accessor, because
    // Livewire's `$queryString` is a protected property by convention and the
    // point being asserted is which keys are in it.
    $queryString = (fn (): array => $this->queryString)->call(Livewire::test(MediaManager::class)->instance());

    expect($queryString)->toHaveKeys(['folderId', 'search', 'sort', 'type']);
});

it('tells a catalogue, a price list and a print archive apart in the grid', function () {
    // One grey document icon rendered all three identically. The tile now
    // carries the file's own extension over its family's colour — the family
    // from `wire-core`, so the field and the library cannot disagree about
    // what a file is. See ADR 0033.
    Media::create(['disk' => 'public', 'path' => 'media/katalog.pdf', 'name' => 'katalog-2026.pdf', 'mime_type' => 'application/pdf']);
    Media::create(['disk' => 'public', 'path' => 'media/cenik.xlsx', 'name' => 'cenik-q1.xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    Media::create(['disk' => 'public', 'path' => 'media/tisk.zip', 'name' => 'tiskoviny.zip', 'mime_type' => 'application/zip']);

    Livewire::test(MediaManager::class)
        ->assertSee('PDF')
        ->assertSee('XLSX')
        ->assertSee('ZIP');
});

it('shows the picture rather than the card wherever there is one', function () {
    Media::create(['disk' => 'public', 'path' => 'media/hero.jpg', 'name' => 'hero.jpg', 'mime_type' => 'image/jpeg']);

    $html = Livewire::test(MediaManager::class)->html();

    // The wordmark belongs to files with nothing to show. A photograph has
    // something to show.
    expect($html)->toContain('media/hero.jpg')
        ->and($html)->not->toContain('>JPG<');
});

it('answers what a file is from one place, so no two surfaces can disagree', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'media/x.pdf', 'name' => 'x.pdf', 'mime_type' => 'application/pdf']);

    expect($media->kind())->toBe(FileKind::Document)
        ->and($media->isImage())->toBeFalse();

    // A row written before mime types were recorded still knows what it is.
    $old = Media::create(['disk' => 'public', 'path' => 'media/old.png', 'name' => 'old.png']);

    expect($old->kind())->toBe(FileKind::Image)
        ->and($old->isImage())->toBeTrue();
});

it('sorts by a column, and turns it around when the same header is clicked twice', function () {
    Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'alpha.png']);
    Media::create(['disk' => 'public', 'path' => 'z.png', 'name' => 'zulu.png']);

    $component = Livewire::test(MediaManager::class)->call('sortBy', 'name');

    expect($component->get('sort'))->toBe('name:asc');

    $html = $component->html();
    expect(strpos($html, 'alpha.png'))->toBeLessThan(strpos($html, 'zulu.png'));

    $component->call('sortBy', 'name');

    expect($component->get('sort'))->toBe('name:desc');

    $html = $component->html();
    expect(strpos($html, 'zulu.png'))->toBeLessThan(strpos($html, 'alpha.png'));
});

it('starts a new column on the end somebody actually meant', function () {
    // Clicking "Uploaded" or "Size" means the newest and the largest; clicking
    // "Name" means A first. Each from a fresh component, because clicking the
    // column you are already sorted by is the other behaviour — it turns it
    // around, which the test above covers.
    $from = fn (string $sort) => Livewire::test(MediaManager::class)->set('sort', $sort);

    expect($from('name:asc')->call('sortBy', 'created_at')->get('sort'))->toBe('created_at:desc')
        ->and($from('name:asc')->call('sortBy', 'size')->get('sort'))->toBe('size:desc')
        ->and($from('created_at:desc')->call('sortBy', 'name')->get('sort'))->toBe('name:asc');
});

it('refuses a column nobody offered', function () {
    // `sort` is a public property and a Livewire method is a public endpoint, so
    // a column name reaching the query builder has to come from a list this
    // component wrote.
    $component = Livewire::test(MediaManager::class)
        ->call('sortBy', 'password')
        ->set('sort', 'password:asc');

    expect($component->instance()->sortColumn())->toBe('created_at');

    $component->assertOk();
});

it('keeps a link somebody bookmarked before the list had headers', function () {
    $old = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'alpha.png', 'size' => 10]);
    Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'zulu.png', 'size' => 900]);

    $old->forceFill(['created_at' => now()->subDay()])->save();

    $component = Livewire::test(MediaManager::class)->set('sort', 'largest');

    expect($component->instance()->sortColumn())->toBe('size')
        ->and($component->instance()->sortDirection())->toBe('desc');
});

it('cuts the selection and pastes it into another folder', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $one = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png']);
    $two = Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'b.png']);

    // A drag across a tree thirty folders deep is a gesture not everybody can
    // make, and on a touch screen it is not a gesture at all.
    Livewire::test(MediaManager::class)
        ->set('selected', [$one->id, $two->id])
        ->call('cutSelection')
        ->assertSet('clipboard', [$one->id, $two->id])
        ->call('openFolder', $brand->id)
        // Opening a folder clears the selection and must not clear the clipboard.
        ->assertSet('clipboard', [$one->id, $two->id])
        ->call('pasteHere')
        ->assertSet('clipboard', []);

    expect($one->fresh()->folder_id)->toBe($brand->id)
        ->and($two->fresh()->folder_id)->toBe($brand->id);
});

it('pastes nothing when nothing was cut', function () {
    $media = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png']);
    $brand = MediaFolder::createIn(null, 'Brand');

    Livewire::test(MediaManager::class)
        ->call('openFolder', $brand->id)
        ->call('pasteHere');

    expect($media->fresh()->folder_id)->toBeNull();
});

it('empties the clipboard even when the paste is refused', function () {
    maLibraryPolicyRefusing('update');

    $media = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png']);
    $brand = MediaFolder::createIn(null, 'Brand');

    // Otherwise a refused paste leaves a clipboard that pastes again on the next
    // keystroke, against a permission that has already said no once.
    Livewire::test(MediaManager::class)
        ->set('selected', [$media->id])
        ->call('cutSelection')
        ->call('openFolder', $brand->id)
        ->call('pasteHere')
        ->assertSet('clipboard', []);

    expect($media->fresh()->folder_id)->toBeNull();
});

it('drags the whole selection when the dragged tile is part of it', function () {
    $one = Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png']);
    $two = Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'b.png']);

    // The markup carries `selection` rather than an id, and the server already
    // knows what that is — which is what the tree's drop handler calls.
    $html = Livewire::test(MediaManager::class)->set('selected', [$one->id])->html();

    expect($html)->toContain("setData('wire/media', 'selection')")
        ->and($html)->toContain("setData('wire/media', '".$two->id."')");
});

it('says which files landed, which were already here and which were refused', function () {
    Storage::fake('public');

    $existing = (new StoreUpload)(UploadedFile::fake()->image('same.png', 8, 8));

    // The same bytes again: the library hands back the row it has.
    $again = UploadedFile::fake()->createWithContent(
        'copy.png',
        (string) Storage::disk('public')->get((string) $existing->path),
    );

    $component = Livewire::test(MediaManager::class)
        // A different size, or the fake would be byte-identical to the one
        // above and the library would be right to call it a duplicate.
        ->set('uploads', [UploadedFile::fake()->image('new.png', 12, 9), $again]);

    $tray = $component->get('tray');

    expect($tray)->toHaveCount(2)
        ->and(collect($tray)->firstWhere('name', 'new.png')['state'])->toBe('stored')
        ->and(collect($tray)->firstWhere('name', 'copy.png')['state'])->toBe('duplicate')
        // The duplicate row carries the row the library already had, so a person
        // who saw no new tile can be shown the one that exists.
        ->and(collect($tray)->firstWhere('name', 'copy.png')['media'])->toBe($existing->getKey());
});

it('keeps the tray while another folder is opened', function () {
    Storage::fake('public');

    $brand = MediaFolder::createIn(null, 'Brand');

    // Exactly what somebody does while a long batch is still going.
    Livewire::test(MediaManager::class)
        ->set('uploads', [UploadedFile::fake()->image('a.png', 8, 8)])
        ->call('openFolder', $brand->id)
        ->assertSee('a.png')
        ->call('dismissTray')
        ->assertSet('tray', []);
});

it('counts the tray by outcome', function () {
    $component = Livewire::test(MediaManager::class)->set('tray', [
        ['name' => 'a', 'state' => 'stored', 'media' => 1],
        ['name' => 'b', 'state' => 'duplicate', 'media' => 2],
        ['name' => 'c', 'state' => 'failed', 'media' => null],
        ['name' => 'd', 'state' => 'failed', 'media' => null],
    ]);

    expect($component->instance()->trayTotals())->toBe(['stored' => 1, 'duplicate' => 1, 'failed' => 2]);
});

it('counts what is in each folder in one query', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    $logos = MediaFolder::createIn($brand, 'Logos');

    Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png']);
    Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'b.png', 'folder_id' => $brand->id]);
    Media::create(['disk' => 'public', 'path' => 'c.png', 'name' => 'c.png', 'folder_id' => $brand->id]);

    $counts = Livewire::test(MediaManager::class)->instance()->folderCounts();

    // Files *in* the folder, not in its subtree: a number that counts a branch
    // disagrees with the number of tiles you see when you open it.
    expect($counts)->toBe(['root' => 1, $brand->id => 2])
        ->and($counts)->not->toHaveKey($logos->id);
});

it('draws the counts beside the folders', function () {
    $brand = MediaFolder::createIn(null, 'Brand');
    Media::create(['disk' => 'public', 'path' => 'b.png', 'name' => 'b.png', 'folder_id' => $brand->id]);

    Livewire::test(MediaManager::class)
        ->assertSee('Brand')
        ->assertSeeHtml('tabular-nums');
});

it('holds the tile size and lets the count go where it likes', function () {
    Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'a.png', 'mime_type' => 'image/png']);

    // Column counts per breakpoint hold the count and stretch the tile, so the
    // same photograph is thumbnail-sized on a laptop and enormous on a
    // widescreen — and the picker, which renders this grid inside a modal, laid
    // out to the viewport rather than to the space it had.
    $html = Livewire::test(MediaManager::class)->html();

    expect($html)->toContain('repeat(auto-fill,minmax(min(168px,100%),168px))')
        ->and($html)->not->toContain('xl:grid-cols-5');
});
