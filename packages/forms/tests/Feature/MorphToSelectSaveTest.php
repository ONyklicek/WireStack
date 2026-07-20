<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\MorphToSelect\Type;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Regression: a MorphToSelect's own name (e.g. `commentable`) is a morph
 * relationship, never a DB column — only `commentable_type` / `commentable_id`
 * are real. The phantom key was left in the save payload and dehydrated onto the
 * parent, fataling every save with "no such column: commentable".
 */

class MtsPost extends Model
{
    protected $table = 'mts_posts';

    protected $guarded = [];

    public $timestamps = false;
}

class MtsComment extends Model
{
    protected $table = 'mts_comments';

    protected $guarded = [];

    public $timestamps = false;

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

class MtsHost extends Component
{
    use WithForms;

    public ?int $commentId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(MtsComment::find($this->commentId) ?? MtsComment::class)
            ->statePath('data')
            ->schema([
                MorphToSelect::make('commentable')->types([
                    Type::make(MtsPost::class)->titleAttribute('title'),
                ]),
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
    Schema::create('mts_posts', function (Blueprint $t) {
        $t->id();
        $t->string('title');
    });
    Schema::create('mts_comments', function (Blueprint $t) {
        $t->id();
        $t->string('commentable_type')->nullable();
        $t->unsignedBigInteger('commentable_id')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('mts_comments');
    Schema::dropIfExists('mts_posts');
});

it('saves a MorphToSelect without fataling on the phantom relation column', function () {
    $post = MtsPost::create(['title' => 'Hello']);

    Livewire::test(MtsHost::class)
        ->set('data.commentable_type', MtsPost::class)
        ->set('data.commentable_id', $post->id)
        ->call('save');

    $comment = MtsComment::first();

    expect($comment)->not->toBeNull()
        ->and($comment->commentable_type)->toBe(MtsPost::class)
        ->and((int) $comment->commentable_id)->toBe($post->id);
});
