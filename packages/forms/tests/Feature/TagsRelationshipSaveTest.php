<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Tags;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Regression: a relationship-bound Tags field's key names a relation, not a
 * column. It was dehydrated onto the parent and fataled the save with
 * "no such column: tags". The field is stripped from the parent write so the
 * rest of the form still saves. (Full tag syncing is a separate feature.)
 */

class TrsPost extends Model
{
    protected $table = 'trs_posts';

    protected $guarded = [];

    public $timestamps = false;
}

class TrsHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(TrsPost::class)
            ->statePath('data')
            ->schema([
                TextInput::make('title'),
                Tags::make('tags')->relationship('tags', 'name'),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('trs_posts', function (Blueprint $t) {
        $t->id();
        $t->string('title');
    });
});

afterEach(function () {
    Schema::dropIfExists('trs_posts');
});

it('saves a form with a relationship Tags field without fataling on the phantom column', function () {
    Livewire::test(TrsHost::class)
        ->set('data.title', 'Hello')
        ->set('data.tags', ['php', 'laravel'])
        ->call('save')
        ->assertHasNoErrors();

    $post = TrsPost::first();

    expect($post)->not->toBeNull()
        ->and($post->title)->toBe('Hello');
});
