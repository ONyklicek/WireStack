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
