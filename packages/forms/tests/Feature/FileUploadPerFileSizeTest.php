<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * A multiple upload could bound how many files it took but not how big each one
 * was: `max:` on the field's key means a count once the state is an array, so the
 * size limit had nowhere to live. It now mounts at the wildcard item path.
 *
 * Driven through the real form runtime — a rule in an array proves nothing; only
 * validating an actual oversized file does.
 */

class PerFileSizeHost extends Component
{
    use WithForms;

    public array $data = ['photos' => []];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            FileUpload::make('photos')->multiple()->maxFiles(3)->maxSize(100),
        ]);
    }

    public function save(): void
    {
        $this->form->validate();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(fn () => Storage::fake('public'));

it('rejects a file over the per-file size limit', function () {
    Livewire::test(PerFileSizeHost::class)
        ->set('data.photos', [UploadedFile::fake()->create('big.png', 500)]) // 500 KB > 100
        ->call('save')
        ->assertHasErrors('data.photos.*');
});

it('accepts a file within the per-file size limit', function () {
    Livewire::test(PerFileSizeHost::class)
        ->set('data.photos', [UploadedFile::fake()->create('small.png', 50)])
        ->call('save')
        ->assertHasNoErrors();
});

// The distinction that made this necessary: the field's own key bounds the count,
// the item key bounds the size. One oversized file among three must still fail.
it('applies the size to each file, not to the number of them', function () {
    Livewire::test(PerFileSizeHost::class)
        ->set('data.photos', [
            UploadedFile::fake()->create('a.png', 10),
            UploadedFile::fake()->create('b.png', 500),
        ])
        ->call('save')
        ->assertHasErrors('data.photos.*');
});

it('still enforces the file count on the field itself', function () {
    Livewire::test(PerFileSizeHost::class)
        ->set('data.photos', array_map(
            fn ($i) => UploadedFile::fake()->create("f$i.png", 10),
            range(1, 4), // maxFiles(3)
        ))
        ->call('save')
        ->assertHasErrors('data.photos');
});
