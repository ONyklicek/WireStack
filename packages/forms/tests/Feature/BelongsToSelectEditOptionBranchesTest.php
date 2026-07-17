<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\BelongsToSelect;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;

/**
 * The relationship-aware edit-option paths of BelongsToSelect: loading an
 * existing related record into the edit form, writing it back, and doing both
 * across repeaters and layouts. These are the branches the happy-path feature
 * test never reaches — a missing relation, a value with no record behind it, a
 * repeater nested in a section, a schema declared as a closure.
 *
 * They are driven by calling the two public entry points the Livewire host calls
 * — getEditOptionFormData() and updateOption() — directly, with the field's
 * record set as the form runtime would set it.
 */
class EobAuthor extends Model
{
    protected $table = 'eob_authors';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<EobBook, $this> */
    public function books(): HasMany
    {
        return $this->hasMany(EobBook::class, 'author_id');
    }
}

class EobPost extends Model
{
    protected $table = 'eob_posts';

    protected $guarded = [];

    public $timestamps = false;

    /** @return BelongsTo<EobAuthor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(EobAuthor::class, 'author_id');
    }
}

class EobBook extends Model
{
    protected $table = 'eob_books';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('eob_authors', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('bio')->nullable();
    });
    Schema::create('eob_posts', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('author_id')->nullable();
    });
    Schema::create('eob_books', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('author_id');
        $t->string('title');
    });

    $this->author = EobAuthor::create(['name' => 'Ada', 'bio' => 'pioneer']);
    $this->post = EobPost::create(['author_id' => $this->author->id]);
    EobBook::create(['author_id' => $this->author->id, 'title' => 'Notes']);
});

afterEach(function () {
    Schema::dropIfExists('eob_books');
    Schema::dropIfExists('eob_posts');
    Schema::dropIfExists('eob_authors');
});

/** A BelongsToSelect wired to the post→author relationship, record already set. */
function eobField(): BelongsToSelect
{
    return BelongsToSelect::make('author_id')
        ->record(test()->post)
        ->relationship('author', 'name');
}

// ─── No related model / no record behind the value ────────────

it('fills nothing when the field has no relationship to resolve', function () {
    // No ->relationship(), so resolveRelatedModel() is null: there is no related
    // model to read from, and loading must yield an empty form rather than error.
    $field = BelongsToSelect::make('author_id')
        ->record(test()->post)
        ->editOptionForm([TextInput::make('name')]);

    expect($field->getEditOptionFormData($this->author->id))->toBe([]);
});

it('writes nothing when the field has no relationship to resolve', function () {
    $field = BelongsToSelect::make('author_id')
        ->record(test()->post)
        ->editOptionForm([TextInput::make('name')]);

    $field->updateOption($this->author->id, ['name' => 'Ignored']);

    expect($this->author->fresh()->name)->toBe('Ada');
});

it('fills nothing when the value has no related record behind it', function () {
    expect(eobField()->editOptionForm([TextInput::make('name')])->getEditOptionFormData(9999))->toBe([]);
});

it('writes nothing when the value has no related record behind it', function () {
    eobField()->editOptionForm([TextInput::make('name')])->updateOption(9999, ['name' => 'Nobody']);

    expect(EobAuthor::whereKey(9999)->exists())->toBeFalse();
});

// ─── No edit form at all ──────────────────────────────────────

it('fills nothing when there is no edit-option form', function () {
    // getEditOptionFieldNames() has no form to read names from, so there is
    // nothing to load even though the related record exists.
    expect(eobField()->getEditOptionFormData($this->author->id))->toBe([]);
});

// ─── Loading and writing real fields ──────────────────────────

it('loads the related records own columns into the edit form', function () {
    $data = eobField()
        ->editOptionForm([TextInput::make('name'), TextInput::make('bio')])
        ->getEditOptionFormData($this->author->id);

    expect($data)->toBe(['name' => 'Ada', 'bio' => 'pioneer']);
});

it('writes the related records own columns back', function () {
    eobField()
        ->editOptionForm([TextInput::make('name'), TextInput::make('bio')])
        ->updateOption($this->author->id, ['name' => 'Ada L.', 'bio' => 'countess']);

    $fresh = $this->author->fresh();
    expect($fresh->name)->toBe('Ada L.')->and($fresh->bio)->toBe('countess');
});

// ─── Relationship repeaters, sections, nested repeaters ───────

it('loads a relationship repeater declared inside a section, skipping nested layouts and repeaters', function () {
    // Exercises the schema walk: the repeater is found by recursing into the
    // outer Section; its own subfields are gathered by recursing into an inner
    // Section and skipping a further-nested Repeater.
    $data = eobField()
        ->editOptionForm([
            Section::make('Books')->schema([
                Repeater::make('books')
                    ->relationship('books')
                    ->schema([
                        TextInput::make('title'),
                        Section::make('detail')->schema([TextInput::make('subtitle')]),
                        Repeater::make('chapters')->schema([TextInput::make('heading')]),
                    ]),
            ]),
        ])
        ->getEditOptionFormData($this->author->id);

    // The one existing book loaded, keyed by its id, with the schema's own
    // subfields present.
    expect($data['books'])->toHaveCount(1)
        ->and($data['books'][0])->toHaveKeys(['id', 'title', 'subtitle']);
});

it('skips a repeater whose relationship does not exist on the related model', function () {
    // `ghost` is not a relation on EobAuthor, so the repeater is collected but
    // then skipped rather than fataling on a missing method.
    $data = eobField()
        ->editOptionForm([
            TextInput::make('name'),
            Repeater::make('ghosts')->relationship('ghost')->schema([TextInput::make('x')]),
        ])
        ->getEditOptionFormData($this->author->id);

    expect($data)->toHaveKey('name')
        ->and($data)->not->toHaveKey('ghosts');
});

it('ignores a dotted field whose relation does not exist on write', function () {
    // `ghost.name` names a relation EobAuthor does not have; the auto-writer
    // skips it instead of calling a missing method.
    eobField()
        ->editOptionForm([TextInput::make('name'), TextInput::make('ghost.name')])
        ->updateOption($this->author->id, ['name' => 'Ada P.', 'ghost' => ['name' => 'nope']]);

    expect($this->author->fresh()->name)->toBe('Ada P.');
});

// ─── Closure schema ───────────────────────────────────────────

it('resolves an edit-option schema declared as a closure', function () {
    $data = eobField()
        ->editOptionForm(fn () => [TextInput::make('name')])
        ->getEditOptionFormData($this->author->id);

    expect($data)->toBe(['name' => 'Ada']);
});
