<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * avatar() was a dead setter — isAvatar() had no consumer, so a field declared as
 * an avatar looked exactly like any other upload. It now drives both the 1:1 crop
 * (applied in the browser) and the round preview asserted here.
 *
 * Rendered through a real host: the preview markup only exists once the field
 * holds a file, and the view reads that state off $this. An empty field would
 * pass this test no matter what the shape logic did.
 */

class AvatarPreviewHost extends Component
{
    use WithForms;

    public array $data = ['photo' => 'me.png'];

    public bool $asAvatar = true;

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            $this->asAvatar
                ? FileUpload::make('photo')->avatar()
                : FileUpload::make('photo')->image(),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function () {
    Storage::fake('public');
    Storage::disk('public')->put('me.png', 'x');
});

it('shows an avatar preview round', function () {
    Livewire::test(AvatarPreviewHost::class)->assertSee('rounded-full', false);
});

it('shows an ordinary image preview square', function () {
    Livewire::test(AvatarPreviewHost::class, ['asAvatar' => false])
        ->assertDontSee('rounded-full', false);
});

it('draws the picture itself, not a file listed under a drop target', function () {
    // The shape of the thumbnail was all `avatar()` used to change, so a profile
    // page asking for one picture of one person got the same full-width dashed
    // dropzone as a document library, with the face listed beneath it as a file
    // with a filename and a link. One image, of a known size and shape, on a
    // screen asking what you look like, is a picture with a button beside it.
    $html = Livewire::test(AvatarPreviewHost::class)->html();

    expect($html)->toContain('form-file-data.photo-avatar')
        ->and($html)->toContain('Change photo')
        // The file-list row and its "open in a new tab" link are what the compact
        // control replaces; a filename under an avatar is noise.
        ->and($html)->not->toContain('Pending upload')
        ->and($html)->not->toContain('or drag and drop');
});

it('offers an upload button when there is no picture yet', function () {
    $host = Livewire::test(AvatarPreviewHost::class);

    $host->set('data.photo', null);

    expect($host->html())->toContain('Upload a photo')
        // Nothing to remove, so nothing offering to.
        ->and($host->html())->not->toContain('form-file-data.photo-remove');
});

it('keeps the same picker and drop target the other layout has', function () {
    // Both layouts hold the same inputs, inside the same Alpine root: what
    // changes is where you click, not what happens then. Drag and drop still
    // works, because the picture is the drop target.
    $html = Livewire::test(AvatarPreviewHost::class)->html();

    expect($html)->toContain('form-file-data.photo-dropzone')
        ->and($html)->toContain('form-file-data.photo-picker')
        ->and($html)->toContain('handleDrop($event)');
});
