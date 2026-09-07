<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireForms\Contracts\SavesAfterRecord;
use NyonCode\WireModuleMedia\Actions\StoreUpload;
use NyonCode\WireModuleMedia\Concerns\HasMedia;
use NyonCode\WireModuleMedia\Forms\MediaField;
use NyonCode\WireModuleMedia\Livewire\MediaManager;
use NyonCode\WireModuleMedia\Livewire\MediaPicker;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Models\MediaFolder;
use NyonCode\WireModuleMedia\Support\StoredFileFacts;

/*
 * The library used from the rest of the system.
 *
 * A media module that is only a screen is a screen: the files it holds are worth
 * something once a post, a product and a rich text editor can all point at the
 * *same* row. So this file is about the seams — the pivot any model can use, the
 * form field that writes it, the picker other packages open without being able
 * to name it, and the one rule that keeps a file from becoming three files.
 */

beforeEach(function () {
    Storage::fake('public');

    foreach (glob(__DIR__.'/../../database/migrations/*.php') ?: [] as $migration) {
        (include $migration)->up();
    }

    Schema::create('me_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title')->nullable();
        $table->timestamps();
    });
});

/** A model in somebody else's package that wants files. */
class MePost extends Model
{
    use HasMedia;

    protected $table = 'me_posts';

    protected $guarded = [];
}

function meFile(string $name = 'a.png'): Media
{
    return Media::create(['disk' => 'public', 'path' => 'media/'.$name, 'name' => $name]);
}

/* ── Attaching ────────────────────────────────────────────────────────────── */

it('attaches the same file to two records, without copying it', function () {
    // The whole reason the pivot exists. A file upload column would have made
    // this two files with two URLs and two descriptions, and the descriptions
    // are what drift.
    $file = meFile();
    $one = MePost::create(['title' => 'One']);
    $two = MePost::create(['title' => 'Two']);

    $one->attachMedia($file);
    $two->attachMedia($file);

    expect($one->media()->pluck('id')->all())->toBe([$file->id])
        ->and($two->media()->pluck('id')->all())->toBe([$file->id])
        ->and(Media::count())->toBe(1);
});

it('keeps collections apart on one record', function () {
    $cover = meFile('cover.png');
    $shot = meFile('shot.png');
    $post = MePost::create();

    $post->attachMedia($cover, 'cover');
    $post->attachMedia($shot, 'gallery');

    expect($post->media('cover')->pluck('id')->all())->toBe([$cover->id])
        ->and($post->media('gallery')->pluck('id')->all())->toBe([$shot->id])
        ->and($post->firstMedia('cover')?->id)->toBe($cover->id)
        ->and($post->firstMedia('nothing'))->toBeNull()
        ->and($post->allMedia()->count())->toBe(2);
});

it('attaching the same file twice is not two of it', function () {
    // Every picker double-fires eventually, and the result renders as the same
    // photo twice with nothing to say which copy to remove.
    $file = meFile();
    $post = MePost::create();

    $post->attachMedia($file)->attachMedia($file->id);

    expect($post->media()->count())->toBe(1);
});

it('syncs a collection to exactly what it was given, in that order', function () {
    $a = meFile('a.png');
    $b = meFile('b.png');
    $c = meFile('c.png');
    $post = MePost::create();

    $post->syncMedia([$a, $b, $c], 'gallery');
    $post->syncMedia([$c->id, $a->id], 'gallery');

    // Order is part of the answer, which is why this replaces rather than diffs.
    expect($post->media('gallery')->pluck('id')->all())->toBe([$c->id, $a->id]);
});

it('detaches the link and leaves the file in the library', function () {
    $file = meFile();
    $post = MePost::create();

    $post->attachMedia($file)->detachMedia($file);

    expect($post->media()->count())->toBe(0)
        // The file is the library's, not the record's: the record deleted last
        // must not take everybody else's image with it.
        ->and(Media::find($file->id))->not->toBeNull();
});

it('takes the links with the file when the file itself goes', function () {
    $file = meFile();
    $post = MePost::create();
    $post->attachMedia($file);

    $file->delete();

    // The opposite direction, and the opposite answer: a link to a file that no
    // longer exists is a broken image on a page nobody remembers publishing.
    expect($post->media()->count())->toBe(0);
});

/* ── The field ────────────────────────────────────────────────────────────── */

it('is a field whose name is a collection, not a column', function () {
    $field = MediaField::make('gallery');

    // Declared rather than enumerated in SaveHandler, which is the only way a
    // field from a package wire-forms has never heard of can join in.
    expect($field)->toBeInstanceOf(SavesAfterRecord::class)
        ->and($field->getCollection())->toBe('gallery')
        ->and(MediaField::make('images')->collection('gallery')->getCollection())->toBe('gallery');
});

it('reads whatever the browser gave it as a list of ids', function () {
    $field = MediaField::make('gallery');

    // One value when it takes one file, a list when it takes several, strings
    // either way — decided here so nothing downstream has to guess.
    expect($field->normalise(null))->toBe([])
        ->and($field->normalise(''))->toBe([])
        ->and($field->normalise('7'))->toBe([7])
        ->and($field->normalise([3, '4']))->toBe([3, 4])
        ->and($field->normalise(['0', 'x', 5]))->toBe([5]);
});

it('writes the collection once the record has a key', function () {
    $a = meFile('a.png');
    $b = meFile('b.png');
    $post = MePost::create();

    MediaField::make('gallery')->saveAfterRecord($post, [$b->id, $a->id]);

    expect($post->media('gallery')->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('leaves a model that never opted in alone', function () {
    // Asked for rather than assumed: a model without the trait has no method to
    // call, and failing on that would make the field unusable on half a schema.
    Schema::create('me_plain', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    $plain = new class extends Model
    {
        protected $table = 'me_plain';

        protected $guarded = [];
    };

    $record = $plain::create();

    MediaField::make('gallery')->saveAfterRecord($record, [meFile()->id]);

    expect(true)->toBeTrue();
});

it('shows the chosen files in the order they are held', function () {
    $a = meFile('a.png');
    $b = meFile('b.png');

    $field = MediaField::make('gallery');

    // `whereIn` does not promise an order, and the order is the person's answer.
    expect(array_map(
        static fn (Media $media): string => (string) $media->name,
        $field->getSelectedMedia([$b->id, $a->id]),
    ))->toBe(['b.png', 'a.png'])
        ->and($field->getSelectedMedia([]))->toBe([])
        // A row deleted from the library between two renders disappears from the
        // field rather than rendering as a broken tile.
        ->and($field->getSelectedMedia([$a->id, 99999]))->toHaveCount(1);
});

/* ── The picker ───────────────────────────────────────────────────────────── */

it('puts its picker where the shell will find it', function () {
    // Neither end names the other: the shell sits above this package and cannot
    // name its views, and this package cannot reach into the shell's layout.
    expect(app(PageChrome::class)->has('wire-module-media::picker-modal'))->toBeTrue();
});

it('takes the terms of the choice from whoever opened it', function () {
    Livewire::test(MediaPicker::class)
        ->assertSet('picking', true)
        ->dispatch('wire-media-picker-configure', multiple: true, accepts: 'image/')
        ->assertSet('multiple', true)
        ->assertSet('accepts', 'image/');
});

it('offers only what the caller can use', function () {
    Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'photo.png', 'mime_type' => 'image/png']);
    Media::create(['disk' => 'public', 'path' => 'media/b.pdf', 'name' => 'contract.pdf', 'mime_type' => 'application/pdf']);

    // A picker that offers a PDF where an image is meant produces a broken page
    // later, and later is after somebody published it.
    Livewire::test(MediaPicker::class)
        ->dispatch('wire-media-picker-configure', multiple: false, accepts: 'image/')
        ->assertSee('photo.png')
        ->assertDontSee('contract.pdf');
});

it('answers with one click when only one file may be chosen', function () {
    $file = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png', 'alt' => 'A logo']);

    Livewire::test(MediaPicker::class)
        ->call('pick', $file->id)
        ->assertDispatched('wire-media-picked', function (string $event, array $params) use ($file): bool {
            $picked = $params['files'][0];

            // The alt text travels with the file, because it was written once
            // about the file itself — asking again at every insertion is how
            // half the images on a site end up with none.
            return $picked['id'] === $file->id && $picked['alt'] === 'A logo';
        });
});

it('collects a selection before answering, when several may be chosen', function () {
    $a = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);
    $b = Media::create(['disk' => 'public', 'path' => 'media/b.png', 'name' => 'b.png']);

    Livewire::test(MediaPicker::class)
        ->set('multiple', true)
        ->call('pick', $a->id)
        ->call('pick', $b->id)
        ->assertSet('selected', [$a->id, $b->id])
        // Clicking a chosen one again takes it back out.
        ->call('pick', $a->id)
        ->assertSet('selected', [$b->id])
        ->assertNotDispatched('wire-media-picked')
        ->call('confirmPick')
        ->assertDispatched('wire-media-picked')
        ->assertSet('selected', []);
});

it('offers no checkbox where a click is the whole answer', function () {
    // The dead end this closes: a single-file picker rendered the multi-select
    // checkbox, ticking it selected the file, and nothing in the modal could act
    // on a selection — the confirm bar only exists for a multiple choice.
    $file = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaPicker::class)
        ->dispatch('wire-media-picker-configure', multiple: false)
        ->assertDontSeeHtml('data-testid="media-select"')
        ->assertDontSeeHtml('data-testid="media-select-all"')
        // The one click that does answer is still there.
        ->assertSeeHtml('wire:click="pick('.$file->id.')"');
});

it('keeps the checkboxes where a selection is the answer', function () {
    Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaPicker::class)
        ->dispatch('wire-media-picker-configure', multiple: true)
        ->assertSeeHtml('data-testid="media-select"')
        ->assertSeeHtml('data-testid="media-select-all"');
});

it('keeps the checkboxes on the library screen, which is not picking at all', function () {
    Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png']);

    Livewire::test(MediaManager::class)
        ->assertSeeHtml('data-testid="media-select"')
        ->assertSeeHtml('data-testid="media-select-all"');
});

it('does not offer to rename or delete a folder while a file is being chosen', function () {
    // Housekeeping has nothing to do with the question the modal asked, and a
    // delete two pixels from the folder somebody meant to open is an accident.
    MediaFolder::createIn(null, 'Brand');

    Livewire::test(MediaPicker::class)
        ->dispatch('wire-media-picker-configure', multiple: false)
        ->assertDontSeeHtml('data-testid="media-folder-rename"')
        ->assertDontSeeHtml('data-testid="media-folder-delete"')
        // Opening one is the point of a tree, and uploading is why you might be
        // here at all — neither goes away.
        ->assertSeeHtml('data-testid="media-folder-open"')
        ->assertSeeHtml('data-testid="media-upload"');
});

it('still offers folder housekeeping on the library screen', function () {
    MediaFolder::createIn(null, 'Brand');

    Livewire::test(MediaManager::class)
        ->assertSeeHtml('data-testid="media-folder-rename"')
        ->assertSeeHtml('data-testid="media-folder-delete"');
});

it('falls back to the file name when nobody wrote alt text', function () {
    // An image with no alt at all is worse than one described by its file name,
    // and the file name is at least what the person who uploaded it called it.
    $file = Media::create(['disk' => 'public', 'path' => 'media/x.png', 'name' => 'Team photo.png']);

    expect($file->altText())->toBe('Team photo.png');
});

/* ── One file, once ───────────────────────────────────────────────────────── */

it('hands back the file it already holds instead of storing it twice', function () {
    $store = new StoreUpload;
    $folder = MediaFolder::createIn(null, 'Brand');

    $first = $store(UploadedFile::fake()->image('logo.png', 12, 12), $folder, $wasDuplicate);
    expect($wasDuplicate)->toBeFalse();

    // The same bytes, uploaded again from somewhere else in the system.
    $again = $store(UploadedFile::fake()->image('logo.png', 12, 12), null, $wasDuplicate);

    expect($wasDuplicate)->toBeTrue()
        ->and($again?->id)->toBe($first?->id)
        ->and(Media::count())->toBe(1)
        // And the redundant copy is not left on the disk with nothing pointing
        // at it.
        ->and(count(Storage::disk('public')->files('media')))->toBe(1);
});

it('records what a file is, read from the disk', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 640, 480));

    expect($media?->width)->toBe(640)
        ->and($media?->height)->toBe(480)
        ->and($media?->dimensions())->toBe('640 × 480')
        ->and($media?->checksum)->toHaveLength(64)
        ->and($media?->mime_type)->toStartWith('image/');
});

it('has no dimensions for a file that has no pixels', function () {
    $media = (new StoreUpload)(UploadedFile::fake()->create('contract.pdf', 4, 'application/pdf'));

    expect($media?->width)->toBeNull()
        ->and($media?->dimensions())->toBeNull()
        // Still hashed, so a second copy of the same contract is still caught.
        ->and($media?->checksum)->toHaveLength(64);
});

it('tells a person that a dropped file was already there', function () {
    Storage::fake('public');

    Livewire::test(MediaManager::class)
        ->set('uploads', [UploadedFile::fake()->image('same.png', 10, 10)])
        ->set('uploads', [UploadedFile::fake()->image('same.png', 10, 10)])
        ->assertOk();

    // Not an error and not a silence: a person who drops a file and sees nothing
    // appear assumes the upload failed.
    expect(Media::count())->toBe(1);
});

/* ── Details ──────────────────────────────────────────────────────────────── */

it('keeps the description on the file, where it is true', function () {
    $file = meFile();

    Livewire::test(MediaManager::class)
        ->call('showDetail', $file->id)
        ->assertSet('detailAlt', '')
        ->set('detailAlt', 'The team, 2026')
        ->set('detailTitle', 'Team')
        ->call('saveDetail')
        ->call('closeDetail')
        ->assertSet('detailId', null);

    expect($file->refresh()->alt)->toBe('The team, 2026')
        ->and($file->title)->toBe('Team');
});

it('empties a description rather than storing an empty string', function () {
    $file = Media::create(['disk' => 'public', 'path' => 'media/a.png', 'name' => 'a.png', 'alt' => 'Something']);

    Livewire::test(MediaManager::class)
        ->call('showDetail', $file->id)
        ->set('detailAlt', '   ')
        ->call('saveDetail');

    expect($file->refresh()->alt)->toBeNull();
});

it('ignores a save with no file open', function () {
    Livewire::test(MediaManager::class)->call('saveDetail')->assertOk();
});

it('takes the terms of the field as they were declared', function () {
    $field = MediaField::make('gallery')->multiple()->accepts('image/,application/pdf');

    expect($field->isMultiple())->toBeTrue()
        ->and($field->getAccepts())->toBe('image/,application/pdf')
        ->and(MediaField::make('cover')->isMultiple())->toBeFalse()
        ->and(MediaField::make('cover')->getAccepts())->toBe('')
        // The view is the module's, not the form package's: the field is defined
        // where the library is, so `wire-forms` never learns it exists.
        ->and((fn () => $this->viewName())->call($field))->toBe('wire-module-media::components.media-field');
});

it('offers more than one kind when the caller asks for more than one', function () {
    Media::create(['disk' => 'public', 'path' => 'a.png', 'name' => 'photo.png', 'mime_type' => 'image/png']);
    Media::create(['disk' => 'public', 'path' => 'b.pdf', 'name' => 'contract.pdf', 'mime_type' => 'application/pdf']);
    Media::create(['disk' => 'public', 'path' => 'c.zip', 'name' => 'archive.zip', 'mime_type' => 'application/zip']);

    // The second prefix and everything after it is an `orWhere`, which is the
    // whole reason the loop counts: written as `where` throughout it would
    // narrow to nothing and the picker would look empty.
    Livewire::test(MediaPicker::class)
        ->dispatch('wire-media-picker-configure', multiple: true, accepts: 'image/,application/pdf')
        ->assertSee('photo.png')
        ->assertSee('contract.pdf')
        ->assertDontSee('archive.zip');
});

it('records nothing about a file it cannot reach on the disk', function () {
    // A remote disk answers a *key* from `path()`, not a filename, so there is
    // nothing local to hash or measure. Duplicate detection is a convenience,
    // and one that downloads every upload twice to provide it is not one.
    $root = sys_get_temp_dir().'/wire-media-remote-'.bin2hex(random_bytes(4));

    config()->set('filesystems.disks.remote', ['driver' => 'local', 'root' => $root, 'throw' => false]);
    config()->set('wire-module-media.disk', 'remote');

    $media = (new StoreUpload)(UploadedFile::fake()->image('shot.png', 20, 20));

    // The file is there and the row is written; only the two things that need
    // the bytes in hand are left unanswered.
    expect($media)->not->toBeNull();

    // Simulate the remote case by asking about a path that is not a file, which
    // is exactly what `path()` returns on S3. The two answers moved out of
    // StoreUpload's private methods when replacing an original needed to
    // establish the same facts about the same file (ADR 0035) — one owner, so
    // an upload and a replacement cannot describe a file differently.
    expect(StoredFileFacts::checksum('/no/such/file'))->toBeNull()
        ->and(StoredFileFacts::dimensions('/no/such/file'))->toBe([null, null]);
});
