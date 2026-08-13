<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A file upload inside an ACTION MODAL, where the field state lives in the
 * StateContainer rather than in a plain array property on the component.
 *
 * `InteractsWithFileUploads` covers both hosts — the standalone form component
 * and the modal one — but every upload test in the repo used the standalone
 * host, so the modal path through StateContainer was carried entirely by
 * reading. That matters more than it sounds: the trait writes through
 * `StateContainer::writeInto()` precisely because `data_set()` cannot write
 * through the container's ArrayAccess, so "who performs the write" is the whole
 * question on this path, and it is the question the Livewire 4 floor changes.
 *
 * Pinned here on Livewire 3 first, so it records the behaviour that exists
 * rather than the behaviour we are about to arrange.
 */

class AmfuRow extends Model
{
    protected $table = 'amfu_rows';

    protected $guarded = [];
}

class AmfuHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(AmfuRow::class)
            ->columns([Column::make('title')])
            ->headerActions([
                HeaderAction::make('attach')
                    ->modalHeading('Attach files')
                    ->form([
                        FileUpload::make('gallery')->image()->multiple(),
                        FileUpload::make('avatar')->avatar(),
                    ])
                    ->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** The modal form's state path for the first open action. */
function amfuPath(string $field): string
{
    return "tableState.modal.actions.0.data.{$field}";
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('amfu_rows', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->timestamps();
    });

    AmfuRow::insert([['title' => 'Alpha', 'created_at' => now(), 'updated_at' => now()]]);
});

afterEach(fn () => Schema::dropIfExists('amfu_rows'));

it('appends an upload to a multiple field held in the modal state container', function () {
    Storage::fake('public');

    $test = Livewire::test(AmfuHost::class)
        ->call('openHeaderActionModal', 'attach')
        ->set(amfuPath('gallery'), ['uploads/a.png', 'uploads/b.png', 'uploads/c.png'])
        ->set(amfuPath('gallery'), [UploadedFile::fake()->image('d.png')]);

    $gallery = $test->instance()->tableState->get('modal.actions.0.data.gallery');

    // Existing three paths kept, the new upload appended exactly once.
    expect($gallery)->toHaveCount(4)
        ->and(array_slice($gallery, 0, 3))->toBe(['uploads/a.png', 'uploads/b.png', 'uploads/c.png'])
        ->and($gallery[3])->toBeInstanceOf(TemporaryUploadedFile::class);

    // Store-on-submit: nothing has been moved to permanent storage yet.
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('replaces the value of a single-file field held in the modal state container', function () {
    Storage::fake('public');

    $test = Livewire::test(AmfuHost::class)
        ->call('openHeaderActionModal', 'attach')
        ->set(amfuPath('avatar'), 'uploads/me.png')
        ->set(amfuPath('avatar'), UploadedFile::fake()->image('new.png'));

    expect($test->instance()->tableState->get('modal.actions.0.data.avatar'))
        ->toBeInstanceOf(TemporaryUploadedFile::class);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('removes one entry from a multiple field without disturbing the rest', function () {
    Storage::fake('public');

    $test = Livewire::test(AmfuHost::class)
        ->call('openHeaderActionModal', 'attach')
        ->set(amfuPath('gallery'), ['uploads/a.png', 'uploads/b.png', 'uploads/c.png'])
        ->call('removeUploadedFile', 'tableState.modal.actions.0.data.gallery', 1);

    expect($test->instance()->tableState->get('modal.actions.0.data.gallery'))
        ->toBe(['uploads/a.png', 'uploads/c.png']);
});
