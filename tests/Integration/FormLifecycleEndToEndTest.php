<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\KeyValue;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Whole-system test: the full form lifecycle as one loop —
 * hydrate (fill from a record) → edit → validate → dehydrate → persist →
 * re-hydrate (remount a fresh component on the same record). It asserts the loop
 * is symmetric: an array-cast column and a relationship repeater read back into
 * the form exactly as they were persisted. Per-step unit tests never cross the
 * remount boundary where a hydrate/dehydrate asymmetry hides.
 */

class FleAuthor extends Model
{
    protected $table = 'fle_authors';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['meta' => 'array'];

    public function books(): HasMany
    {
        return $this->hasMany(FleBook::class, 'author_id');
    }
}

class FleBook extends Model
{
    protected $table = 'fle_books';

    protected $guarded = [];

    public $timestamps = false;
}

class FleHost extends Component
{
    use WithForms;

    public int $authorId;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(FleAuthor::find($this->authorId))
            ->statePath('data')
            ->schema([
                TextInput::make('name'),
                KeyValue::make('meta'),
                Repeater::make('books')->relationship('books')->schema([
                    TextInput::make('title')->required(),
                ]),
            ]);
    }

    public function mount(): void
    {
        $author = FleAuthor::with('books')->find($this->authorId);
        $this->form->fill([
            'name' => $author->name,
            'meta' => $author->meta,
            'books' => $author->books->map(fn ($b) => ['id' => $b->id, 'title' => $b->title])->all(),
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
    Schema::create('fle_authors', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->json('meta')->nullable();
    });
    Schema::create('fle_books', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->string('title');
    });
});

afterEach(function () {
    Schema::dropIfExists('fle_books');
    Schema::dropIfExists('fle_authors');
});

it('round-trips the full form lifecycle across a save and a remount', function () {
    $author = FleAuthor::create(['name' => 'Jane', 'meta' => ['genre' => 'sci-fi']]);
    $book = $author->books()->create(['title' => 'Old Title']);

    // 1. Hydrate: a fresh mount fills the form from the record — the array cast is
    //    an array in form state (not a JSON string), the child carries its id.
    $first = Livewire::test(FleHost::class, ['authorId' => $author->id]);
    expect($first->get('data.name'))->toBe('Jane')
        ->and($first->get('data.meta'))->toBe(['genre' => 'sci-fi'])
        ->and($first->get('data.books.0.title'))->toBe('Old Title')
        ->and($first->get('data.books.0.id'))->toBe($book->id);

    // 2. Edit + 3. save: change a scalar, the array-cast value and the child row.
    $first
        ->set('data.name', 'Janet')
        ->set('data.meta', ['genre' => 'fantasy', 'era' => 'modern'])
        ->set('data.books.0.title', 'New Title')
        ->call('save')
        ->assertHasNoErrors();

    // Persisted correctly: array single-encoded, same book row updated in place.
    $fresh = FleAuthor::with('books')->find($author->id);
    expect($fresh->name)->toBe('Janet')
        // toEqual: JSON object key order is driver-dependent (MySQL may reorder).
        ->and($fresh->meta)->toEqual(['genre' => 'fantasy', 'era' => 'modern'])
        ->and($fresh->books)->toHaveCount(1)
        ->and($fresh->books->first()->id)->toBe($book->id)      // updated, not recreated
        ->and($fresh->books->first()->title)->toBe('New Title');

    // 4. Re-hydrate: a brand-new component on the same record reflects the saved
    //    state — proving the hydrate/dehydrate loop is symmetric end to end.
    $second = Livewire::test(FleHost::class, ['authorId' => $author->id]);
    expect($second->get('data.name'))->toBe('Janet')
        // toEqual: re-hydrated from the DB, whose JSON key order is driver-dependent.
        ->and($second->get('data.meta'))->toEqual(['genre' => 'fantasy', 'era' => 'modern'])
        ->and($second->get('data.books.0.title'))->toBe('New Title')
        ->and($second->get('data.books.0.id'))->toBe($book->id);
});
