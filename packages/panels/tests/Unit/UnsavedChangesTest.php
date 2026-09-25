<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;

/*
 * The warning before a form page's unsaved input is left behind.
 *
 * The warning is the browser's (`wireUnsavedChanges`, driven by
 * verify-unsaved-changes.mjs); what PHP decides is whether a page asks for it,
 * and with which state path and save method — the two things the controller
 * cannot know and the page does.
 */
class UcNote extends Model
{
    protected $table = 'uc_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class UcNoteResource implements DescribesResource, ProvidesResourceForm
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return UcNote::class;
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('body')]);
    }
}

class UcCreateNote extends CreatePage
{
    protected static ?string $resource = UcNoteResource::class;
}

class UcEditNote extends EditPage
{
    protected static ?string $resource = UcNoteResource::class;
}

class UcQuietEditNote extends EditPage
{
    protected static ?string $resource = UcNoteResource::class;

    protected function warnsAboutUnsavedChanges(): bool
    {
        return false;
    }
}

beforeEach(function () {
    Schema::create('uc_notes', function (Blueprint $table) {
        $table->id();
        $table->string('body')->nullable();
    });
});

it('arms the warning on a create page', function () {
    $html = Livewire::test(UcCreateNote::class)->html();

    preg_match('/x-data="wireUnsavedChanges\(([^"]*)\)"/', $html, $match);

    expect($match)->not->toBeEmpty()
        ->and(html_entity_decode($match[1]))->toContain('data')->toContain('save');
});

it('arms the warning on an edit page', function () {
    $note = UcNote::query()->create(['body' => 'Draft']);

    expect(Livewire::test(UcEditNote::class, ['record' => $note->getKey()])->html())
        ->toContain('x-data="wireUnsavedChanges(');
});

it('carries the message in the application locale', function () {
    app()->setLocale('cs');

    $html = Livewire::test(UcCreateNote::class)->html();

    // `@js` writes non-ASCII as \u escapes on some Laravel versions and as-is on
    // others; either is the Czech message.
    $escaped = trim(json_encode('neuložené změny'), '"');

    expect(str_contains($html, 'neuložené změny') || str_contains($html, $escaped))->toBeTrue();
});

it('lets a page turn the warning off', function () {
    $note = UcNote::query()->create(['body' => 'Draft']);

    expect(Livewire::test(UcQuietEditNote::class, ['record' => $note->getKey()])->html())
        ->not->toContain('wireUnsavedChanges');
});
