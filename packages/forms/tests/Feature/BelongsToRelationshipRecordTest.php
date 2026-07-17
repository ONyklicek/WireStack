<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\BelongsToSelect;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * A BelongsToSelect resolves its related model from the parent record, so both
 * auto-loading options and auto-creating an option (createOptionForm without an
 * explicit createOptionUsing) depend on the form runtime handing the field its
 * record. Regression: the record was documented as "set by the form runtime" but
 * never actually wired, so relationship options and relationship auto-create both
 * silently no-op'd through the host.
 */
class RelCompany extends Model
{
    protected $table = 'rel_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class RelProfile extends Model
{
    protected $table = 'rel_profiles';

    protected $guarded = [];

    public $timestamps = false;
}

class RelBook extends Model
{
    protected $table = 'rel_books';

    protected $guarded = [];

    public $timestamps = false;
}

class RelTag extends Model
{
    protected $table = 'rel_tags';

    protected $guarded = [];

    public $timestamps = false;
}

class RelNote extends Model
{
    protected $table = 'rel_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class RelLabel extends Model
{
    protected $table = 'rel_labels';

    protected $guarded = [];

    public $timestamps = false;
}

class RelArticle extends Model
{
    protected $table = 'rel_articles';

    protected $guarded = [];

    public $timestamps = false;
}

class RelChapter extends Model
{
    protected $table = 'rel_chapters';

    protected $guarded = [];

    public $timestamps = false;
}

class RelAvatar extends Model
{
    protected $table = 'rel_avatars';

    protected $guarded = [];

    public $timestamps = false;
}

class RelBadge extends Model
{
    protected $table = 'rel_badges';

    protected $guarded = [];

    public $timestamps = false;
}

class RelStamp extends Model
{
    protected $table = 'rel_stamps';

    protected $guarded = [];

    public $timestamps = false;
}

class RelSkill extends Model
{
    protected $table = 'rel_skills';

    protected $guarded = [];

    public $timestamps = false;
}

class RelTeam extends Model
{
    protected $table = 'rel_teams';

    protected $guarded = [];

    public $timestamps = false;
}

/** Custom (non-morph) pivot model for a belongsToMany, wired via ->using(). */
class RelSkillPivot extends Pivot
{
    protected $table = 'rel_author_skill';

    public $timestamps = false;

    protected $casts = ['level' => 'integer'];
}

/** Custom morph-pivot model with a cast, wired via ->using(). */
class RelBadgePivot extends MorphPivot
{
    protected $table = 'rel_badgeables';

    public $timestamps = false;

    protected $casts = ['featured' => 'boolean'];
}

/** Custom morph-pivot for the inverse (morphedByMany) side, wired via ->using(). */
class RelPinnablePivot extends MorphPivot
{
    protected $table = 'rel_pinnables';

    public $timestamps = false;

    protected $casts = ['pinned' => 'boolean'];
}

class RelAuthor extends Model
{
    protected $table = 'rel_authors';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(RelCompany::class, 'company_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(RelProfile::class, 'author_id');
    }

    public function books(): HasMany
    {
        return $this->hasMany(RelBook::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RelTag::class, 'rel_author_tag', 'author_id', 'tag_id')
            ->withPivot('role');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(RelNote::class, 'notable');
    }

    public function labels(): MorphToMany
    {
        return $this->morphToMany(RelLabel::class, 'labelable', 'rel_labelables', 'labelable_id', 'label_id')
            ->withPivot('weight');
    }

    public function articles(): MorphToMany
    {
        return $this->morphedByMany(RelArticle::class, 'authorable', 'rel_authorables', 'author_id', 'authorable_id')
            ->withPivot('position');
    }

    public function chapters(): HasManyThrough
    {
        // author -> books -> chapters
        return $this->hasManyThrough(RelChapter::class, RelBook::class, 'author_id', 'book_id', 'id', 'id');
    }

    public function firstChapter(): HasOneThrough
    {
        // author -> books -> (one) chapter
        return $this->hasOneThrough(RelChapter::class, RelBook::class, 'author_id', 'book_id', 'id', 'id');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(RelAvatar::class, 'avatarable');
    }

    public function origin(): MorphTo
    {
        return $this->morphTo('origin');
    }

    public function badges(): MorphToMany
    {
        return $this->morphToMany(RelBadge::class, 'badgeable', 'rel_badgeables', 'badgeable_id', 'badge_id')
            ->using(RelBadgePivot::class)
            ->withPivot('featured');
    }

    public function pinnedArticles(): MorphToMany
    {
        return $this->morphedByMany(RelArticle::class, 'pinnable', 'rel_pinnables', 'author_id', 'pinnable_id')
            ->using(RelPinnablePivot::class)
            ->withPivot('pinned');
    }

    public function stamps(): MorphToMany
    {
        // Ad-hoc pivot, NO ->using() model — the default MorphPivot with two
        // declared pivot columns.
        return $this->morphToMany(RelStamp::class, 'stampable', 'rel_stampables', 'stampable_id', 'stamp_id')
            ->withPivot('role', 'note');
    }

    public function skills(): BelongsToMany
    {
        // Non-polymorphic belongsToMany with a custom ->using() Pivot model.
        return $this->belongsToMany(RelSkill::class, 'rel_author_skill', 'author_id', 'skill_id')
            ->using(RelSkillPivot::class)
            ->withPivot('level');
    }

    public function teams(): BelongsToMany
    {
        // Ad-hoc pivot, NO ->using() model — the framework's base Pivot with two
        // declared pivot columns.
        return $this->belongsToMany(RelTeam::class, 'rel_author_team', 'author_id', 'team_id')
            ->withPivot('role', 'note');
    }
}

class RelPost extends Model
{
    protected $table = 'rel_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(RelAuthor::class, 'author_id');
    }
}

class RelationshipRecordHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->preload()
                    ->createOptionForm([TextInput::make('name')->required()])
                    ->editOptionForm([TextInput::make('name')->required()]),
                // No createOptionUsing()/fillEditOptionUsing()/updateOptionUsing()
                // and no manual options() — everything comes from the resolved
                // BelongsTo relationship.
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(function () {
    Schema::create('rel_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('rel_profiles', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->string('bio')->nullable();
    });
    Schema::create('rel_books', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->string('title');
    });
    Schema::create('rel_chapters', function (Blueprint $t) {
        $t->id();
        $t->foreignId('book_id');
        $t->string('title');
    });
    Schema::create('rel_avatars', function (Blueprint $t) {
        $t->id();
        $t->string('avatarable_type');
        $t->unsignedBigInteger('avatarable_id');
        $t->string('url')->nullable();
    });
    Schema::create('rel_tags', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('rel_notes', function (Blueprint $t) {
        $t->id();
        $t->string('notable_type');
        $t->unsignedBigInteger('notable_id');
        $t->string('body');
    });
    Schema::create('rel_labels', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('rel_labelables', function (Blueprint $t) {
        $t->id();
        $t->foreignId('label_id');
        $t->unsignedBigInteger('labelable_id');
        $t->string('labelable_type');
        $t->string('weight')->nullable();
    });
    Schema::create('rel_articles', function (Blueprint $t) {
        $t->id();
        $t->string('title');
    });
    Schema::create('rel_badges', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('rel_stamps', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('rel_skills', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('rel_author_skill', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->foreignId('skill_id');
        $t->integer('level')->default(0);
    });
    Schema::create('rel_teams', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('rel_author_team', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->foreignId('team_id');
        $t->string('role')->nullable();
        $t->string('note')->nullable();
    });
    Schema::create('rel_stampables', function (Blueprint $t) {
        $t->id();
        $t->foreignId('stamp_id');
        $t->unsignedBigInteger('stampable_id');
        $t->string('stampable_type');
        $t->string('role')->nullable();
        $t->string('note')->nullable();
    });
    Schema::create('rel_badgeables', function (Blueprint $t) {
        $t->id();
        $t->foreignId('badge_id');
        $t->unsignedBigInteger('badgeable_id');
        $t->string('badgeable_type');
        $t->boolean('featured')->default(false);
    });
    Schema::create('rel_pinnables', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->unsignedBigInteger('pinnable_id');
        $t->string('pinnable_type');
        $t->boolean('pinned')->default(false);
    });
    Schema::create('rel_authorables', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->unsignedBigInteger('authorable_id');
        $t->string('authorable_type');
        $t->string('position')->nullable();
    });
    Schema::create('rel_author_tag', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->foreignId('tag_id');
        $t->string('role')->nullable();
    });
    Schema::create('rel_authors', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('email')->nullable();
        $t->string('bio')->nullable();
        $t->foreignId('company_id')->nullable();
        $t->string('origin_type')->nullable();
        $t->unsignedBigInteger('origin_id')->nullable();
    });
    Schema::create('rel_posts', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('rel_posts');
    Schema::dropIfExists('rel_author_tag');
    Schema::dropIfExists('rel_tags');
    Schema::dropIfExists('rel_authorables');
    Schema::dropIfExists('rel_articles');
    Schema::dropIfExists('rel_labelables');
    Schema::dropIfExists('rel_labels');
    Schema::dropIfExists('rel_badgeables');
    Schema::dropIfExists('rel_badges');
    Schema::dropIfExists('rel_stampables');
    Schema::dropIfExists('rel_stamps');
    Schema::dropIfExists('rel_author_skill');
    Schema::dropIfExists('rel_skills');
    Schema::dropIfExists('rel_author_team');
    Schema::dropIfExists('rel_teams');
    Schema::dropIfExists('rel_pinnables');
    Schema::dropIfExists('rel_notes');
    Schema::dropIfExists('rel_avatars');
    Schema::dropIfExists('rel_chapters');
    Schema::dropIfExists('rel_books');
    Schema::dropIfExists('rel_profiles');
    Schema::dropIfExists('rel_authors');
    Schema::dropIfExists('rel_companies');
});

test('relationship + createOptionForm auto-creates the related model through the host', function () {
    $post = RelPost::create([]);

    Livewire::test(RelationshipRecordHost::class, ['postId' => $post->id])
        ->call('mountCreateOption', 'data.author_id')
        ->set('createOptionFormData.name', 'Ada Lovelace')
        ->call('createSelectOption')
        ->assertHasNoErrors()
        // The new option is created and selected on the field.
        ->assertSet('data.author_id', fn ($v) => $v !== null);

    expect(RelAuthor::where('name', 'Ada Lovelace')->exists())->toBeTrue();
});

test('the form runtime hands its parent record to relationship fields so options load', function () {
    RelAuthor::create(['name' => 'Grace Hopper']);
    $post = RelPost::create([]);

    Livewire::test(RelationshipRecordHost::class, ['postId' => $post->id])
        // A preloaded relationship select shows its options only when it can
        // resolve the related model from the record the runtime propagated.
        ->assertSee('Grace Hopper');
});

class RelationshipRepeaterHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['contacts' => [['author_id' => null]]];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                Repeater::make('contacts')->schema([
                    BelongsToSelect::make('author_id')
                        ->relationship('author', 'name')
                        ->createOptionForm([TextInput::make('name')->required()])
                        ->editOptionForm([TextInput::make('name')->required()]),
                ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('relationship + createOptionForm auto-creates from inside a Repeater item', function () {
    $post = RelPost::create([]);

    Livewire::test(RelationshipRepeaterHost::class, ['postId' => $post->id])
        // The create-enabled Select lives inside a per-item repeater path.
        ->call('mountCreateOption', 'data.contacts.0.author_id')
        ->set('createOptionFormData.name', 'Edsger Dijkstra')
        ->call('createSelectOption')
        ->assertHasNoErrors()
        ->assertSet('data.contacts.0.author_id', fn ($v) => $v !== null);

    expect(RelAuthor::where('name', 'Edsger Dijkstra')->exists())->toBeTrue();
});

test('relationship + editOptionForm auto-fills and auto-updates the related model', function () {
    $author = RelAuthor::create(['name' => 'Old Name']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipRecordHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // The edit form auto-filled from the related record (no fillEditOptionUsing).
        ->assertSet('editOptionFormData.name', 'Old Name')
        ->set('editOptionFormData.name', 'New Name')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    // The related model was updated in place (no updateOptionUsing).
    expect($author->fresh()->name)->toBe('New Name');
});

test('relationship + editOptionForm auto-updates the related model from inside a Repeater item', function () {
    $author = RelAuthor::create(['name' => 'Old Repeater']);
    $post = RelPost::create([]);

    Livewire::test(RelationshipRepeaterHost::class, ['postId' => $post->id])
        ->set('data.contacts.0.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.contacts.0.author_id')
        ->assertSet('editOptionFormData.name', 'Old Repeater')
        ->set('editOptionFormData.name', 'New Repeater')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($author->fresh()->name)->toBe('New Repeater');
});

class RelationshipSubsetEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    // Edit form declares a SUBSET of the author's columns.
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('email'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill loads only the columns the edit form declares', function () {
    $author = RelAuthor::create([
        'name' => 'Ada',
        'email' => 'ada@analytical.engine',
        'bio' => 'Countess of Lovelace',
    ]);
    $post = RelPost::create(['author_id' => $author->id]);

    $component = Livewire::test(RelationshipSubsetEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.name', 'Ada')
        ->assertSet('editOptionFormData.email', 'ada@analytical.engine');

    // Only the declared columns are pulled — not bio, id, or anything else.
    expect($component->get('editOptionFormData'))
        ->toBe(['name' => 'Ada', 'email' => 'ada@analytical.engine']);
});

test('editOptionForm subset update writes only the declared columns back', function () {
    $author = RelAuthor::create([
        'name' => 'Ada',
        'email' => 'ada@analytical.engine',
        'bio' => 'Countess of Lovelace',
    ]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipSubsetEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.name', 'Ada Lovelace')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $fresh = $author->fresh();
    expect($fresh->name)->toBe('Ada Lovelace')
        // bio was never in the edit form, so it is left untouched.
        ->and($fresh->bio)->toBe('Countess of Lovelace');
});

class RelationshipNestedEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // Dotted name reaches the author's own belongsTo company.
                        TextInput::make('company.name'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill walks a nested belongsTo via a dotted field name', function () {
    $company = RelCompany::create(['name' => 'Analytical Engines Ltd']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipNestedEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.name', 'Ada')
        // Filled through the nested relation into a nested state path.
        ->assertSet('editOptionFormData.company.name', 'Analytical Engines Ltd');
});

test('editOptionForm nested auto-fill yields null when the nested relation is empty', function () {
    $author = RelAuthor::create(['name' => 'Grace', 'company_id' => null]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipNestedEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.name', 'Grace')
        ->assertSet('editOptionFormData.company.name', null);
});

test('editOptionForm nested update writes the own column and leaves the nested relation to updateOptionUsing', function () {
    $company = RelCompany::create(['name' => 'Analytical Engines Ltd']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipNestedEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.name', 'Ada Lovelace')
        ->set('editOptionFormData.company.name', 'Renamed Ltd')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    // The author's own column is written; the nested company is untouched (a
    // dotted field's write-back is left to an explicit updateOptionUsing()).
    expect($author->fresh()->name)->toBe('Ada Lovelace')
        ->and($company->fresh()->name)->toBe('Analytical Engines Ltd');
});

class RelationshipHasOneEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // Dotted name reaches the author's OWNED hasOne profile.
                        TextInput::make('profile.bio'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill walks a nested hasOne via a dotted field name', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $author->profile()->create(['bio' => 'Countess of Lovelace']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasOneEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.name', 'Ada')
        ->assertSet('editOptionFormData.profile.bio', 'Countess of Lovelace');
});

test('editOptionForm nested hasOne update writes back through the owned relation', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $profile = $author->profile()->create(['bio' => 'Old bio']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasOneEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.name', 'Ada Lovelace')
        ->set('editOptionFormData.profile.bio', 'New bio')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    // Both the owned column and the hasOne record were written.
    expect($author->fresh()->name)->toBe('Ada Lovelace')
        ->and($profile->fresh()->bio)->toBe('New bio');
});

test('editOptionForm nested hasOne update creates the owned record when it is missing', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $post = RelPost::create(['author_id' => $author->id]);

    expect($author->profile)->toBeNull();

    Livewire::test(RelationshipHasOneEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.profile.bio', 'Fresh bio')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($author->fresh()->profile)->not->toBeNull()
        ->and($author->fresh()->profile->bio)->toBe('Fresh bio');
});

class RelationshipHasManyEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // A relationship-backed repeater for the author's hasMany books.
                        Repeater::make('books')
                            ->relationship('books')
                            ->schema([TextInput::make('title')]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill loads a hasMany into its relationship repeater with keys', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $a = $author->books()->create(['title' => 'Notes A']);
    $b = $author->books()->create(['title' => 'Notes B']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.books.0.title', 'Notes A')
        ->assertSet('editOptionFormData.books.0.id', $a->id)
        ->assertSet('editOptionFormData.books.1.title', 'Notes B')
        ->assertSet('editOptionFormData.books.1.id', $b->id);
});

test('editOptionForm hasMany write-back updates, creates, and deletes to match the repeater', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $keep = $author->books()->create(['title' => 'Keep me']);
    $remove = $author->books()->create(['title' => 'Remove me']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Keep+edit the first row, drop the second, add a third.
        ->set('editOptionFormData.books', [
            ['id' => $keep->id, 'title' => 'Kept + edited'],
            ['title' => 'Brand new'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $titles = $author->books()->orderBy('id')->pluck('title')->all();

    expect($titles)->toContain('Kept + edited')
        ->and($titles)->toContain('Brand new')
        ->and($titles)->not->toContain('Remove me')
        ->and(RelBook::whereKey($remove->id)->exists())->toBeFalse()
        // The kept row was updated in place, not recreated.
        ->and($keep->fresh()->title)->toBe('Kept + edited');
});

class RelationshipBelongsToWriteHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('company.name'),
                    ])
                    // Write the shared belongsTo back explicitly, using the record.
                    ->updateOptionUsing(function ($record, array $data): void {
                        $record->update(['name' => $data['name']]);
                        $record->company?->update(['name' => data_get($data, 'company.name')]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class RelationshipFillCallbackHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([TextInput::make('label')])
                    ->fillEditOptionUsing(fn ($record) => [
                        'label' => $record->name.' @ '.$record->company?->name,
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('updateOptionUsing receives the resolved record so a nested belongsTo can be written back', function () {
    $company = RelCompany::create(['name' => 'Old Co']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipBelongsToWriteHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Auto-fill already loaded the nested belongsTo value.
        ->assertSet('editOptionFormData.company.name', 'Old Co')
        ->set('editOptionFormData.name', 'Ada Lovelace')
        ->set('editOptionFormData.company.name', 'New Co')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    // The callback wrote both the author and its shared company.
    expect($author->fresh()->name)->toBe('Ada Lovelace')
        ->and($company->fresh()->name)->toBe('New Co');
});

test('fillEditOptionUsing also receives the resolved record', function () {
    $company = RelCompany::create(['name' => 'Acme']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipFillCallbackHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.label', 'Ada @ Acme');
});

class RelationshipBelongsToManyEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // belongsToMany: each row = related tag key + pivot column.
                        Repeater::make('tags')
                            ->relationship('tags')
                            ->schema([
                                TextInput::make('id'),   // the related tag key
                                TextInput::make('role'), // pivot column
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill loads a belongsToMany with its pivot columns', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $t1 = RelTag::create(['name' => 'Math']);
    $t2 = RelTag::create(['name' => 'Logic']);
    $author->tags()->attach($t1->id, ['role' => 'lead']);
    $author->tags()->attach($t2->id, ['role' => 'member']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipBelongsToManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.tags.0.id', $t1->id)
        ->assertSet('editOptionFormData.tags.0.role', 'lead')
        ->assertSet('editOptionFormData.tags.1.id', $t2->id)
        ->assertSet('editOptionFormData.tags.1.role', 'member');
});

test('editOptionForm belongsToMany write-back syncs the pivot (attach, detach, update pivot)', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $t1 = RelTag::create(['name' => 'Math']);
    $t2 = RelTag::create(['name' => 'Logic']);
    $t3 = RelTag::create(['name' => 'Ethics']);
    $author->tags()->attach($t1->id, ['role' => 'lead']);
    $author->tags()->attach($t2->id, ['role' => 'member']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipBelongsToManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Keep t1 but change its pivot role, detach t2, attach t3.
        ->set('editOptionFormData.tags', [
            ['id' => $t1->id, 'role' => 'owner'],
            ['id' => $t3->id, 'role' => 'guest'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $tags = $author->fresh()->tags()->orderBy('rel_tags.id')->get();

    expect($tags->pluck('id')->all())->toBe([$t1->id, $t3->id])
        ->and($tags->firstWhere('id', $t1->id)->pivot->role)->toBe('owner')
        ->and($tags->firstWhere('id', $t3->id)->pivot->role)->toBe('guest')
        // t2 was detached.
        ->and($tags->firstWhere('id', $t2->id))->toBeNull();
});

class RelationshipMorphManyEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // morphMany — same owned-rows cascade as hasMany.
                        Repeater::make('notes')
                            ->relationship('notes')
                            ->schema([TextInput::make('body')]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill loads a morphMany into its relationship repeater with keys', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $n1 = $author->notes()->create(['body' => 'First']);
    $n2 = $author->notes()->create(['body' => 'Second']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.notes.0.body', 'First')
        ->assertSet('editOptionFormData.notes.0.id', $n1->id)
        ->assertSet('editOptionFormData.notes.1.body', 'Second')
        ->assertSet('editOptionFormData.notes.1.id', $n2->id);
});

test('editOptionForm morphMany write-back updates, creates (with morph type), and deletes', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $keep = $author->notes()->create(['body' => 'Keep']);
    $remove = $author->notes()->create(['body' => 'Remove']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.notes', [
            ['id' => $keep->id, 'body' => 'Kept + edited'],
            ['body' => 'Brand new'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $notes = $author->notes()->orderBy('id')->get();

    expect($notes->pluck('body')->all())->toContain('Kept + edited')
        ->and($notes->pluck('body')->all())->toContain('Brand new')
        ->and(RelNote::whereKey($remove->id)->exists())->toBeFalse()
        ->and($keep->fresh()->body)->toBe('Kept + edited');

    // The created row carries the polymorphic type + id.
    $new = $notes->firstWhere('body', 'Brand new');
    expect($new->notable_type)->toBe(RelAuthor::class)
        ->and((int) $new->notable_id)->toBe($author->id);
});

class RelationshipMorphToManyEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // morphToMany — same pivot sync as belongsToMany.
                        Repeater::make('labels')
                            ->relationship('labels')
                            ->schema([
                                TextInput::make('id'),     // related label key
                                TextInput::make('weight'), // pivot column
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill loads a morphToMany with its pivot columns', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $l1 = RelLabel::create(['name' => 'Urgent']);
    $l2 = RelLabel::create(['name' => 'Review']);
    $author->labels()->attach($l1->id, ['weight' => 'high']);
    $author->labels()->attach($l2->id, ['weight' => 'low']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphToManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.labels.0.id', $l1->id)
        ->assertSet('editOptionFormData.labels.0.weight', 'high')
        ->assertSet('editOptionFormData.labels.1.id', $l2->id)
        ->assertSet('editOptionFormData.labels.1.weight', 'low');
});

test('editOptionForm morphToMany write-back syncs the pivot with the polymorphic type', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $l1 = RelLabel::create(['name' => 'Urgent']);
    $l2 = RelLabel::create(['name' => 'Review']);
    $l3 = RelLabel::create(['name' => 'Later']);
    $author->labels()->attach($l1->id, ['weight' => 'high']);
    $author->labels()->attach($l2->id, ['weight' => 'low']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphToManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Keep l1 (change weight), detach l2, attach l3.
        ->set('editOptionFormData.labels', [
            ['id' => $l1->id, 'weight' => 'top'],
            ['id' => $l3->id, 'weight' => 'mid'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $labels = $author->fresh()->labels()->orderBy('rel_labels.id')->get();

    expect($labels->pluck('id')->all())->toBe([$l1->id, $l3->id])
        ->and($labels->firstWhere('id', $l1->id)->pivot->weight)->toBe('top')
        ->and($labels->firstWhere('id', $l3->id)->pivot->weight)->toBe('mid')
        ->and($labels->firstWhere('id', $l2->id))->toBeNull();

    // The pivot rows carry the polymorphic type.
    $type = DB::table('rel_labelables')->where('label_id', $l3->id)->value('labelable_type');
    expect($type)->toBe(RelAuthor::class);
});

class RelationshipMorphedByManyEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // morphedByMany (inverse of morphToMany) — same pivot sync.
                        Repeater::make('articles')
                            ->relationship('articles')
                            ->schema([
                                TextInput::make('id'),       // related article key
                                TextInput::make('position'), // pivot column
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill loads a morphedByMany with its pivot columns', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $a1 = RelArticle::create(['title' => 'Engines']);
    $a2 = RelArticle::create(['title' => 'Numbers']);
    $author->articles()->attach($a1->id, ['position' => 'lead']);
    $author->articles()->attach($a2->id, ['position' => 'body']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphedByManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.articles.0.id', $a1->id)
        ->assertSet('editOptionFormData.articles.0.position', 'lead')
        ->assertSet('editOptionFormData.articles.1.id', $a2->id)
        ->assertSet('editOptionFormData.articles.1.position', 'body');
});

test('editOptionForm morphedByMany write-back syncs the pivot with the related morph type', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $a1 = RelArticle::create(['title' => 'Engines']);
    $a2 = RelArticle::create(['title' => 'Numbers']);
    $a3 = RelArticle::create(['title' => 'Later']);
    $author->articles()->attach($a1->id, ['position' => 'lead']);
    $author->articles()->attach($a2->id, ['position' => 'body']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphedByManyEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Keep a1 (change position), detach a2, attach a3.
        ->set('editOptionFormData.articles', [
            ['id' => $a1->id, 'position' => 'top'],
            ['id' => $a3->id, 'position' => 'mid'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $articles = $author->fresh()->articles()->orderBy('rel_articles.id')->get();

    expect($articles->pluck('id')->all())->toBe([$a1->id, $a3->id])
        ->and($articles->firstWhere('id', $a1->id)->pivot->position)->toBe('top')
        ->and($articles->firstWhere('id', $a3->id)->pivot->position)->toBe('mid')
        ->and($articles->firstWhere('id', $a2->id))->toBeNull();

    // Inverse morph: the pivot's *_type refers to the RELATED (article) side.
    $type = DB::table('rel_authorables')->where('authorable_id', $a3->id)->value('authorable_type');
    expect($type)->toBe(RelArticle::class);
});

class RelationshipHasManyThroughEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // hasManyThrough is read-through: the repeater displays it,
                        // but the auto write-back deliberately skips it.
                        Repeater::make('chapters')
                            ->relationship('chapters')
                            ->schema([TextInput::make('title')]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class RelationshipHasManyThroughWriteHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        Repeater::make('chapters')
                            ->relationship('chapters')
                            ->schema([TextInput::make('title')]),
                    ])
                    // The sanctioned way to write a read-through relation: do it
                    // explicitly, so the ambiguous "which intermediate" is the
                    // caller's choice, not a dangerous guess.
                    ->updateOptionUsing(function ($record, array $data): void {
                        $record->update(['name' => $data['name']]);

                        foreach ($data['chapters'] ?? [] as $row) {
                            if (! empty($row['id'])) {
                                RelChapter::whereKey($row['id'])->update(['title' => $row['title']]);
                            }
                        }
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill loads a hasManyThrough (read-through) into its repeater', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $book = $author->books()->create(['title' => 'Vol 1']);
    $c1 = RelChapter::create(['book_id' => $book->id, 'title' => 'Intro']);
    $c2 = RelChapter::create(['book_id' => $book->id, 'title' => 'Body']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasManyThroughEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.chapters.0.title', 'Intro')
        ->assertSet('editOptionFormData.chapters.0.id', $c1->id)
        ->assertSet('editOptionFormData.chapters.1.title', 'Body')
        ->assertSet('editOptionFormData.chapters.1.id', $c2->id);
});

test('editOptionForm hasManyThrough write-back is a safe no-op (no orphaned or deleted rows)', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $book = $author->books()->create(['title' => 'Vol 1']);
    $c1 = RelChapter::create(['book_id' => $book->id, 'title' => 'Intro']);
    $c2 = RelChapter::create(['book_id' => $book->id, 'title' => 'Body']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasManyThroughEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Try to edit + drop + add through the read-through repeater.
        ->set('editOptionFormData.chapters', [
            ['id' => $c1->id, 'title' => 'Changed'],
            ['title' => 'Orphan attempt'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    // Nothing was written through the read-through relation: no update, no delete,
    // and crucially no orphaned RelChapter created without a book link.
    expect($c1->fresh()->title)->toBe('Intro')
        ->and(RelChapter::whereKey($c2->id)->exists())->toBeTrue()
        ->and(RelChapter::count())->toBe(2)
        ->and(RelChapter::whereNull('book_id')->orWhere('book_id', 0)->count())->toBe(0);
});

test('a hasManyThrough can still be written explicitly via updateOptionUsing($record)', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $book = $author->books()->create(['title' => 'Vol 1']);
    $c1 = RelChapter::create(['book_id' => $book->id, 'title' => 'Intro']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasManyThroughWriteHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.name', 'Ada Lovelace')
        ->set('editOptionFormData.chapters.0.title', 'Explicit edit')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($author->fresh()->name)->toBe('Ada Lovelace')
        ->and($c1->fresh()->title)->toBe('Explicit edit');
});

class RelationshipMorphOneEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // Dotted name reaches the author's OWNED morphOne avatar.
                        TextInput::make('avatar.url'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill walks a nested morphOne via a dotted field name', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $author->avatar()->create(['url' => 'ada.png']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphOneEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.name', 'Ada')
        ->assertSet('editOptionFormData.avatar.url', 'ada.png');
});

test('editOptionForm nested morphOne update writes back through the owned relation', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $avatar = $author->avatar()->create(['url' => 'old.png']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphOneEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.avatar.url', 'new.png')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($avatar->fresh()->url)->toBe('new.png');
});

test('editOptionForm nested morphOne update creates the owned record (with morph type) when missing', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $post = RelPost::create(['author_id' => $author->id]);

    expect($author->avatar)->toBeNull();

    Livewire::test(RelationshipMorphOneEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.avatar.url', 'fresh.png')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $avatar = $author->fresh()->avatar;

    expect($avatar)->not->toBeNull()
        ->and($avatar->url)->toBe('fresh.png')
        // The morph create set the polymorphic type/id on its own.
        ->and($avatar->avatarable_type)->toBe(RelAuthor::class)
        ->and((int) $avatar->avatarable_id)->toBe($author->id);
});

class RelationshipMorphToEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // morphTo is a polymorphic belongsTo: a shared parent.
                        TextInput::make('origin.name'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class RelationshipMorphToWriteHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('origin.name'),
                    ])
                    ->updateOptionUsing(function ($record, array $data): void {
                        $record->update(['name' => $data['name']]);
                        $record->origin?->update(['name' => data_get($data, 'origin.name')]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill walks a morphTo (polymorphic belongsTo) via a dotted field name', function () {
    $company = RelCompany::create(['name' => 'Origin Co']);
    $author = RelAuthor::create(['name' => 'Ada', 'origin_type' => RelCompany::class, 'origin_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphToEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.name', 'Ada')
        // Filled through the polymorphic parent.
        ->assertSet('editOptionFormData.origin.name', 'Origin Co');
});

test('a morphTo auto write-back leaves the shared polymorphic parent untouched', function () {
    $company = RelCompany::create(['name' => 'Origin Co']);
    $author = RelAuthor::create(['name' => 'Ada', 'origin_type' => RelCompany::class, 'origin_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphToEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.name', 'Ada Lovelace')
        ->set('editOptionFormData.origin.name', 'Hacked')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    // Own column written; the shared morph parent is NOT auto-mutated.
    expect($author->fresh()->name)->toBe('Ada Lovelace')
        ->and($company->fresh()->name)->toBe('Origin Co');
});

test('a morphTo can be written explicitly via updateOptionUsing($record)', function () {
    $company = RelCompany::create(['name' => 'Origin Co']);
    $author = RelAuthor::create(['name' => 'Ada', 'origin_type' => RelCompany::class, 'origin_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphToWriteHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.origin.name', 'Renamed Co')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($company->fresh()->name)->toBe('Renamed Co');
});

class RelationshipCustomPivotEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // morphToMany with a custom ->using() pivot model (cast pivot).
                        Repeater::make('badges')
                            ->relationship('badges')
                            ->schema([
                                TextInput::make('id'),
                                TextInput::make('featured'),
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill reads pivot columns through a custom pivot model (with casts)', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $b1 = RelBadge::create(['name' => 'Gold']);
    $b2 = RelBadge::create(['name' => 'Silver']);
    $author->badges()->attach($b1->id, ['featured' => true]);
    $author->badges()->attach($b2->id, ['featured' => false]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipCustomPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.badges.0.id', $b1->id)
        // The custom pivot's boolean cast is applied on read.
        ->assertSet('editOptionFormData.badges.0.featured', true)
        ->assertSet('editOptionFormData.badges.1.featured', false);
});

test('editOptionForm write-back syncs pivot columns through a custom pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $b1 = RelBadge::create(['name' => 'Gold']);
    $b2 = RelBadge::create(['name' => 'Silver']);
    $b3 = RelBadge::create(['name' => 'Bronze']);
    $author->badges()->attach($b1->id, ['featured' => true]);
    $author->badges()->attach($b2->id, ['featured' => false]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipCustomPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Flip b1's pivot flag, detach b2, attach b3 as featured.
        ->set('editOptionFormData.badges', [
            ['id' => $b1->id, 'featured' => false],
            ['id' => $b3->id, 'featured' => true],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $badges = $author->fresh()->badges()->orderBy('rel_badges.id')->get();

    expect($badges->pluck('id')->all())->toBe([$b1->id, $b3->id])
        ->and($badges->firstWhere('id', $b2->id))->toBeNull()
        // Pivot columns round-trip through the custom pivot's cast.
        ->and($badges->firstWhere('id', $b1->id)->pivot->featured)->toBeFalse()
        ->and($badges->firstWhere('id', $b3->id)->pivot->featured)->toBeTrue()
        // The loaded pivot is the custom class.
        ->and($badges->first()->pivot)->toBeInstanceOf(RelBadgePivot::class);
});

class RelationshipInversePivotEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // morphedByMany (inverse) with a custom ->using() pivot model.
                        Repeater::make('pinnedArticles')
                            ->relationship('pinnedArticles')
                            ->schema([
                                TextInput::make('id'),
                                TextInput::make('pinned'),
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill reads morphedByMany pivot columns through a custom pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $a1 = RelArticle::create(['title' => 'Engines']);
    $a2 = RelArticle::create(['title' => 'Numbers']);
    $author->pinnedArticles()->attach($a1->id, ['pinned' => true]);
    $author->pinnedArticles()->attach($a2->id, ['pinned' => false]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipInversePivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.pinnedArticles.0.id', $a1->id)
        // The custom pivot's boolean cast is applied on read.
        ->assertSet('editOptionFormData.pinnedArticles.0.pinned', true)
        ->assertSet('editOptionFormData.pinnedArticles.1.pinned', false);
});

test('editOptionForm write-back syncs morphedByMany pivot columns through a custom pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $a1 = RelArticle::create(['title' => 'Engines']);
    $a2 = RelArticle::create(['title' => 'Numbers']);
    $a3 = RelArticle::create(['title' => 'Later']);
    $author->pinnedArticles()->attach($a1->id, ['pinned' => true]);
    $author->pinnedArticles()->attach($a2->id, ['pinned' => false]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipInversePivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Flip a1's pivot flag, detach a2, attach a3 as pinned.
        ->set('editOptionFormData.pinnedArticles', [
            ['id' => $a1->id, 'pinned' => false],
            ['id' => $a3->id, 'pinned' => true],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $articles = $author->fresh()->pinnedArticles()->orderBy('rel_articles.id')->get();

    expect($articles->pluck('id')->all())->toBe([$a1->id, $a3->id])
        ->and($articles->firstWhere('id', $a2->id))->toBeNull()
        // Pivot cast round-trips through the custom pivot model.
        ->and($articles->firstWhere('id', $a1->id)->pivot->pinned)->toBeFalse()
        ->and($articles->firstWhere('id', $a3->id)->pivot->pinned)->toBeTrue()
        ->and($articles->first()->pivot)->toBeInstanceOf(RelPinnablePivot::class);

    // Inverse morph: the pivot's *_type refers to the RELATED (article) side.
    $type = DB::table('rel_pinnables')->where('pinnable_id', $a3->id)->value('pinnable_type');
    expect($type)->toBe(RelArticle::class);
});

class RelationshipHasOneThroughEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // hasOneThrough is read-through: the dotted field displays it,
                        // but the auto write-back deliberately skips it.
                        TextInput::make('firstChapter.title'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class RelationshipHasOneThroughWriteHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('firstChapter.title'),
                    ])
                    // The sanctioned way to write a read-through relation: explicitly.
                    ->updateOptionUsing(function ($record, array $data): void {
                        $record->update(['name' => $data['name']]);
                        $record->firstChapter?->update(['title' => data_get($data, 'firstChapter.title')]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill walks a hasOneThrough (read-through) via a dotted field', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $book = $author->books()->create(['title' => 'Vol 1']);
    RelChapter::create(['book_id' => $book->id, 'title' => 'Intro']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasOneThroughEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.name', 'Ada')
        ->assertSet('editOptionFormData.firstChapter.title', 'Intro');
});

test('a hasOneThrough auto write-back is a safe no-op (deferred to updateOptionUsing)', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $book = $author->books()->create(['title' => 'Vol 1']);
    $chapter = RelChapter::create(['book_id' => $book->id, 'title' => 'Intro']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasOneThroughEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.name', 'Ada Lovelace')
        ->set('editOptionFormData.firstChapter.title', 'Changed')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    // Own column written; the read-through chapter is untouched, no orphans.
    expect($author->fresh()->name)->toBe('Ada Lovelace')
        ->and($chapter->fresh()->title)->toBe('Intro')
        ->and(RelChapter::count())->toBe(1);
});

test('a hasOneThrough can be written explicitly via updateOptionUsing($record)', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $book = $author->books()->create(['title' => 'Vol 1']);
    $chapter = RelChapter::create(['book_id' => $book->id, 'title' => 'Intro']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipHasOneThroughWriteHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.firstChapter.title', 'Explicit edit')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($chapter->fresh()->title)->toBe('Explicit edit');
});

class RelationshipAdHocPivotEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // morphToMany with an ad-hoc pivot (no ->using()) and two
                        // declared pivot columns.
                        Repeater::make('stamps')
                            ->relationship('stamps')
                            ->schema([
                                TextInput::make('id'),
                                TextInput::make('role'),
                                TextInput::make('note'),
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill reads several ad-hoc pivot columns without a pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $s1 = RelStamp::create(['name' => 'Approved']);
    $s2 = RelStamp::create(['name' => 'Draft']);
    $author->stamps()->attach($s1->id, ['role' => 'lead', 'note' => 'first']);
    $author->stamps()->attach($s2->id, ['role' => 'aux', 'note' => 'second']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipAdHocPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.stamps.0.id', $s1->id)
        ->assertSet('editOptionFormData.stamps.0.role', 'lead')
        ->assertSet('editOptionFormData.stamps.0.note', 'first')
        ->assertSet('editOptionFormData.stamps.1.role', 'aux')
        ->assertSet('editOptionFormData.stamps.1.note', 'second');
});

test('editOptionForm write-back syncs several ad-hoc pivot columns without a pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $s1 = RelStamp::create(['name' => 'Approved']);
    $s2 = RelStamp::create(['name' => 'Draft']);
    $s3 = RelStamp::create(['name' => 'Final']);
    $author->stamps()->attach($s1->id, ['role' => 'lead', 'note' => 'first']);
    $author->stamps()->attach($s2->id, ['role' => 'aux', 'note' => 'second']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipAdHocPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Change s1's pivot columns, detach s2, attach s3.
        ->set('editOptionFormData.stamps', [
            ['id' => $s1->id, 'role' => 'owner', 'note' => 'edited'],
            ['id' => $s3->id, 'role' => 'reviewer', 'note' => 'fresh'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $stamps = $author->fresh()->stamps()->orderBy('rel_stamps.id')->get();

    expect($stamps->pluck('id')->all())->toBe([$s1->id, $s3->id])
        ->and($stamps->firstWhere('id', $s2->id))->toBeNull()
        ->and($stamps->firstWhere('id', $s1->id)->pivot->role)->toBe('owner')
        ->and($stamps->firstWhere('id', $s1->id)->pivot->note)->toBe('edited')
        ->and($stamps->firstWhere('id', $s3->id)->pivot->role)->toBe('reviewer')
        ->and($stamps->firstWhere('id', $s3->id)->pivot->note)->toBe('fresh')
        // No custom pivot model: the pivot is the framework's base MorphPivot.
        ->and($stamps->first()->pivot)->toBeInstanceOf(MorphPivot::class)
        ->and($stamps->first()->pivot::class)->toBe(MorphPivot::class);
});

class RelationshipBtmCustomPivotEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // Non-polymorphic belongsToMany with a custom ->using() Pivot.
                        Repeater::make('skills')
                            ->relationship('skills')
                            ->schema([
                                TextInput::make('id'),
                                TextInput::make('level'),
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill reads a belongsToMany pivot through a custom (non-morph) pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $k1 = RelSkill::create(['name' => 'PHP']);
    $k2 = RelSkill::create(['name' => 'SQL']);
    $author->skills()->attach($k1->id, ['level' => 3]);
    $author->skills()->attach($k2->id, ['level' => 1]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipBtmCustomPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.skills.0.id', $k1->id)
        // The custom pivot's integer cast is applied on read.
        ->assertSet('editOptionFormData.skills.0.level', 3)
        ->assertSet('editOptionFormData.skills.1.level', 1);
});

test('editOptionForm write-back syncs a belongsToMany pivot through a custom (non-morph) pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $k1 = RelSkill::create(['name' => 'PHP']);
    $k2 = RelSkill::create(['name' => 'SQL']);
    $k3 = RelSkill::create(['name' => 'Rust']);
    $author->skills()->attach($k1->id, ['level' => 3]);
    $author->skills()->attach($k2->id, ['level' => 1]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipBtmCustomPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.skills', [
            ['id' => $k1->id, 'level' => 5],
            ['id' => $k3->id, 'level' => 2],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $skills = $author->fresh()->skills()->orderBy('rel_skills.id')->get();

    expect($skills->pluck('id')->all())->toBe([$k1->id, $k3->id])
        ->and($skills->firstWhere('id', $k2->id))->toBeNull()
        // Pivot cast round-trips through the custom Pivot model.
        ->and($skills->firstWhere('id', $k1->id)->pivot->level)->toBe(5)
        ->and($skills->firstWhere('id', $k3->id)->pivot->level)->toBe(2)
        ->and($skills->first()->pivot)->toBeInstanceOf(RelSkillPivot::class);
});

class RelationshipBtmAdHocPivotEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // belongsToMany with an ad-hoc pivot (no ->using()), two columns.
                        Repeater::make('teams')
                            ->relationship('teams')
                            ->schema([
                                TextInput::make('id'),
                                TextInput::make('role'),
                                TextInput::make('note'),
                            ]),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('editOptionForm auto-fill reads several ad-hoc belongsToMany pivot columns without a pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $t1 = RelTeam::create(['name' => 'Core']);
    $t2 = RelTeam::create(['name' => 'Docs']);
    $author->teams()->attach($t1->id, ['role' => 'lead', 'note' => 'first']);
    $author->teams()->attach($t2->id, ['role' => 'aux', 'note' => 'second']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipBtmAdHocPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.teams.0.id', $t1->id)
        ->assertSet('editOptionFormData.teams.0.role', 'lead')
        ->assertSet('editOptionFormData.teams.0.note', 'first')
        ->assertSet('editOptionFormData.teams.1.role', 'aux')
        ->assertSet('editOptionFormData.teams.1.note', 'second');
});

test('editOptionForm write-back syncs several ad-hoc belongsToMany pivot columns without a pivot model', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $t1 = RelTeam::create(['name' => 'Core']);
    $t2 = RelTeam::create(['name' => 'Docs']);
    $t3 = RelTeam::create(['name' => 'Ops']);
    $author->teams()->attach($t1->id, ['role' => 'lead', 'note' => 'first']);
    $author->teams()->attach($t2->id, ['role' => 'aux', 'note' => 'second']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipBtmAdHocPivotEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.teams', [
            ['id' => $t1->id, 'role' => 'owner', 'note' => 'edited'],
            ['id' => $t3->id, 'role' => 'reviewer', 'note' => 'fresh'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $teams = $author->fresh()->teams()->orderBy('rel_teams.id')->get();

    expect($teams->pluck('id')->all())->toBe([$t1->id, $t3->id])
        ->and($teams->firstWhere('id', $t2->id))->toBeNull()
        ->and($teams->firstWhere('id', $t1->id)->pivot->role)->toBe('owner')
        ->and($teams->firstWhere('id', $t1->id)->pivot->note)->toBe('edited')
        ->and($teams->firstWhere('id', $t3->id)->pivot->role)->toBe('reviewer')
        ->and($teams->firstWhere('id', $t3->id)->pivot->note)->toBe('fresh')
        // No custom pivot model: the pivot is the framework's base Pivot.
        ->and($teams->first()->pivot)->toBeInstanceOf(Pivot::class)
        ->and($teams->first()->pivot::class)->toBe(Pivot::class);
});

class RelationshipFkColumnEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // The belongsTo foreign key is itself an own column.
                        TextInput::make('company_id'),
                    ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class RelationshipAssociateEditHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('company_id'),
                    ])
                    // Re-point the belongsTo idiomatically via associate()/dissociate().
                    ->updateOptionUsing(function ($record, array $data): void {
                        $target = $data['company_id'] ?? null;

                        if ($target) {
                            $record->company()->associate(RelCompany::find($target));
                        } else {
                            $record->company()->dissociate();
                        }

                        $record->name = $data['name'];
                        $record->save();
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('belongsTo re-associates by editing the foreign-key own column (auto write-back)', function () {
    $a = RelCompany::create(['name' => 'Alpha']);
    $b = RelCompany::create(['name' => 'Beta']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $a->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipFkColumnEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.company_id', $a->id)
        ->set('editOptionFormData.company_id', $b->id)
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($author->fresh()->company_id)->toBe($b->id)
        ->and($author->fresh()->company->name)->toBe('Beta');
});

test('belongsTo re-associates via updateOptionUsing($record) with associate()', function () {
    $a = RelCompany::create(['name' => 'Alpha']);
    $b = RelCompany::create(['name' => 'Beta']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $a->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipAssociateEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.company_id', $b->id)
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $fresh = $author->fresh();
    expect($fresh->company_id)->toBe($b->id)
        ->and($fresh->company->name)->toBe('Beta');
});

test('belongsTo dissociates by nulling the foreign-key own column (auto write-back)', function () {
    $a = RelCompany::create(['name' => 'Alpha']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $a->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipFkColumnEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.company_id', $a->id)
        // Null the foreign key through the plain own-column write-back.
        ->set('editOptionFormData.company_id', null)
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $fresh = $author->fresh();
    expect($fresh->company_id)->toBeNull()
        ->and($fresh->company)->toBeNull();
});

test('belongsTo can be dissociated via updateOptionUsing($record)', function () {
    $a = RelCompany::create(['name' => 'Alpha']);
    $author = RelAuthor::create(['name' => 'Ada', 'company_id' => $a->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipAssociateEditHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.company_id', '')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $fresh = $author->fresh();
    expect($fresh->company_id)->toBeNull()
        ->and($fresh->company)->toBeNull();
});

class RelationshipMorphToAssociateHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // Pick a RelStamp to become the new polymorphic origin.
                        TextInput::make('stamp_id'),
                    ])
                    // associate() on a morphTo sets BOTH *_type and *_id.
                    ->updateOptionUsing(function ($record, array $data): void {
                        $stampId = $data['stamp_id'] ?? null;

                        if ($stampId) {
                            $record->origin()->associate(RelStamp::find($stampId));
                        } else {
                            $record->origin()->dissociate();
                        }

                        $record->name = $data['name'];
                        $record->save();
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('morphTo re-associates to a different type via updateOptionUsing($record) with associate()', function () {
    $company = RelCompany::create(['name' => 'Origin Co']);
    $stamp = RelStamp::create(['name' => 'Origin Stamp']);
    $author = RelAuthor::create(['name' => 'Ada', 'origin_type' => RelCompany::class, 'origin_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphToAssociateHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.stamp_id', $stamp->id)
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $fresh = $author->fresh();
    // associate() re-pointed BOTH the polymorphic type and the id.
    expect($fresh->origin_type)->toBe(RelStamp::class)
        ->and((int) $fresh->origin_id)->toBe($stamp->id)
        ->and($fresh->origin)->toBeInstanceOf(RelStamp::class)
        ->and($fresh->origin->name)->toBe('Origin Stamp');
});

test('morphTo can be dissociated via updateOptionUsing($record)', function () {
    $company = RelCompany::create(['name' => 'Origin Co']);
    $author = RelAuthor::create(['name' => 'Ada', 'origin_type' => RelCompany::class, 'origin_id' => $company->id]);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphToAssociateHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.stamp_id', '')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $fresh = $author->fresh();
    // dissociate() nulls BOTH the type and the id.
    expect($fresh->origin_type)->toBeNull()
        ->and($fresh->origin_id)->toBeNull()
        ->and($fresh->origin)->toBeNull();
});

class RelationshipMorphOneSaveHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('avatar.url'),
                    ])
                    // Persist the owned morphOne explicitly via the relation's save():
                    // it sets the morph *_type/*_id on the (new or existing) record.
                    ->updateOptionUsing(function ($record, array $data): void {
                        $avatar = $record->avatar ?? new RelAvatar;
                        $avatar->url = data_get($data, 'avatar.url');
                        $record->avatar()->save($avatar);

                        $record->update(['name' => $data['name']]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('morphOne write-back via $record->avatar()->save() updates the existing owned record', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $avatar = $author->avatar()->create(['url' => 'old.png']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipMorphOneSaveHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.avatar.url', 'old.png')
        ->set('editOptionFormData.avatar.url', 'new.png')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    expect($avatar->fresh()->url)->toBe('new.png')
        // Still exactly one owned record — updated in place, not duplicated.
        ->and(RelAvatar::count())->toBe(1);
});

test('morphOne write-back via save() creates the owned record with the morph type', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $post = RelPost::create(['author_id' => $author->id]);

    expect($author->avatar)->toBeNull();

    Livewire::test(RelationshipMorphOneSaveHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.avatar.url', 'fresh.png')
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $avatar = $author->fresh()->avatar;

    expect($avatar)->not->toBeNull()
        ->and($avatar->url)->toBe('fresh.png')
        // save() on the morphOne set the polymorphic type/id itself.
        ->and($avatar->avatarable_type)->toBe(RelAuthor::class)
        ->and((int) $avatar->avatarable_id)->toBe($author->id);
});

class RelationshipSaveManyHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        Repeater::make('books')
                            ->relationship('books')
                            ->schema([TextInput::make('title')]),
                    ])
                    // Persist the owned hasMany explicitly via saveMany(): each model
                    // gets its author_id set. (saveMany creates/updates but does not
                    // delete — that trade-off is the caller's to make.)
                    ->updateOptionUsing(function ($record, array $data): void {
                        $books = collect($data['books'] ?? [])
                            ->map(function (array $row): RelBook {
                                $book = ! empty($row['id']) ? RelBook::find($row['id']) : new RelBook;
                                $book->title = $row['title'];

                                return $book;
                            })
                            ->all();

                        $record->books()->saveMany($books);
                        $record->update(['name' => $data['name']]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('hasMany write-back via saveMany() creates multiple owned rows with the foreign key set', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipSaveManyHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->set('editOptionFormData.books', [
            ['title' => 'Notes A'],
            ['title' => 'Notes B'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $books = $author->fresh()->books()->orderBy('id')->get();

    expect($books->pluck('title')->all())->toBe(['Notes A', 'Notes B'])
        // Each row was linked to the parent via the relation's foreign key.
        ->and($books->every(fn ($b) => $b->author_id === $author->id))->toBeTrue();
});

test('hasMany saveMany() persists a mix of existing (updated) and new rows', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $keep = $author->books()->create(['title' => 'Original']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipSaveManyHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // The existing row was filled with its key, so saveMany updates it in place.
        ->assertSet('editOptionFormData.books.0.id', $keep->id)
        ->set('editOptionFormData.books', [
            ['id' => $keep->id, 'title' => 'Updated in place'],
            ['title' => 'Added'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $titles = $author->fresh()->books()->orderBy('id')->pluck('title')->all();

    expect($keep->fresh()->title)->toBe('Updated in place')
        ->and($titles)->toContain('Added')
        ->and($author->fresh()->books()->count())->toBe(2);
});

class RelationshipSyncWithoutDetachingHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        Repeater::make('tags')
                            ->relationship('tags')
                            ->schema([
                                TextInput::make('id'),
                                TextInput::make('role'),
                            ]),
                    ])
                    // Non-destructive pivot write: attach new + update listed pivots,
                    // but never detach an existing association that was left out.
                    ->updateOptionUsing(function ($record, array $data): void {
                        $sync = collect($data['tags'] ?? [])
                            ->filter(fn (array $row): bool => ! empty($row['id']))
                            ->mapWithKeys(fn (array $row): array => [$row['id'] => ['role' => $row['role'] ?? null]])
                            ->all();

                        $record->tags()->syncWithoutDetaching($sync);
                        $record->update(['name' => $data['name']]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('pivot write-back via syncWithoutDetaching() attaches and updates but never detaches', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $t1 = RelTag::create(['name' => 'Math']);
    $t2 = RelTag::create(['name' => 'Logic']);
    $t3 = RelTag::create(['name' => 'Ethics']);
    $author->tags()->attach($t1->id, ['role' => 'lead']);
    $author->tags()->attach($t2->id, ['role' => 'member']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipSyncWithoutDetachingHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Update t1's pivot, add t3 — and deliberately leave t2 out of the list.
        ->set('editOptionFormData.tags', [
            ['id' => $t1->id, 'role' => 'owner'],
            ['id' => $t3->id, 'role' => 'guest'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $tags = $author->fresh()->tags()->orderBy('rel_tags.id')->get();

    // t1 updated, t3 attached — and t2 SURVIVES (syncWithoutDetaching never detaches).
    expect($tags->pluck('id')->all())->toBe([$t1->id, $t2->id, $t3->id])
        ->and($tags->firstWhere('id', $t1->id)->pivot->role)->toBe('owner')
        ->and($tags->firstWhere('id', $t2->id)->pivot->role)->toBe('member')
        ->and($tags->firstWhere('id', $t3->id)->pivot->role)->toBe('guest');
});

class RelationshipToggleHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        // Plain (non-relationship) repeater: the ids to flip.
                        Repeater::make('toggleTags')
                            ->schema([TextInput::make('id')]),
                    ])
                    // Flip membership of exactly the listed ids: attach the missing,
                    // detach the present, leave everything else alone.
                    ->updateOptionUsing(function ($record, array $data): void {
                        $ids = collect($data['toggleTags'] ?? [])
                            ->pluck('id')
                            ->filter()
                            ->all();

                        $record->tags()->toggle($ids);
                        $record->update(['name' => $data['name']]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('pivot write-back via toggle() flips membership of the listed ids only', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $t1 = RelTag::create(['name' => 'Math']);
    $t2 = RelTag::create(['name' => 'Logic']);
    $t3 = RelTag::create(['name' => 'Ethics']);
    $author->tags()->attach($t1->id, ['role' => 'lead']);
    $author->tags()->attach($t2->id, ['role' => 'member']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipToggleHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        // Toggle t1 (attached -> detach) and t3 (missing -> attach); leave t2 alone.
        ->set('editOptionFormData.toggleTags', [
            ['id' => $t1->id],
            ['id' => $t3->id],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $ids = $author->fresh()->tags()->orderBy('rel_tags.id')->pluck('rel_tags.id')->all();

    // t1 detached, t3 attached, t2 untouched.
    expect($ids)->toBe([$t2->id, $t3->id]);
});

class RelationshipUpdatePivotHost extends Component
{
    use WithForms;

    public ?int $postId = null;

    /** @var array<string, mixed> */
    public array $data = ['author_id' => null];

    public function form(Form $form): Form
    {
        return $form
            ->model(RelPost::find($this->postId))
            ->statePath('data')
            ->schema([
                BelongsToSelect::make('author_id')
                    ->relationship('author', 'name')
                    ->editOptionForm([
                        TextInput::make('name')->required(),
                        Repeater::make('tags')
                            ->relationship('tags')
                            ->schema([
                                TextInput::make('id'),
                                TextInput::make('role'),
                            ]),
                    ])
                    // Update the pivot columns of already-attached rows only; an id
                    // that is not attached is a no-op (never attaches).
                    ->updateOptionUsing(function ($record, array $data): void {
                        foreach ($data['tags'] ?? [] as $row) {
                            if (! empty($row['id'])) {
                                $record->tags()->updateExistingPivot($row['id'], ['role' => $row['role'] ?? null]);
                            }
                        }

                        $record->update(['name' => $data['name']]);
                    }),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('pivot write-back via updateExistingPivot() updates existing pivots only (never attaches)', function () {
    $author = RelAuthor::create(['name' => 'Ada']);
    $t1 = RelTag::create(['name' => 'Math']);
    $t2 = RelTag::create(['name' => 'Logic']);
    $t3 = RelTag::create(['name' => 'Ethics']);
    $author->tags()->attach($t1->id, ['role' => 'lead']);
    $author->tags()->attach($t2->id, ['role' => 'member']);
    $post = RelPost::create(['author_id' => $author->id]);

    Livewire::test(RelationshipUpdatePivotHost::class, ['postId' => $post->id])
        ->set('data.author_id', (string) $author->id)
        ->call('mountEditOption', 'data.author_id')
        ->assertSet('editOptionFormData.tags.0.role', 'lead')
        // Change t1 & t2 pivot roles; include t3 (not attached) as a no-op.
        ->set('editOptionFormData.tags', [
            ['id' => $t1->id, 'role' => 'owner'],
            ['id' => $t2->id, 'role' => 'reviewer'],
            ['id' => $t3->id, 'role' => 'ignored'],
        ])
        ->call('updateSelectOption')
        ->assertHasNoErrors();

    $tags = $author->fresh()->tags()->orderBy('rel_tags.id')->get();

    // Existing pivots updated; t3 was NOT attached (updateExistingPivot never attaches).
    expect($tags->pluck('id')->all())->toBe([$t1->id, $t2->id])
        ->and($tags->firstWhere('id', $t1->id)->pivot->role)->toBe('owner')
        ->and($tags->firstWhere('id', $t2->id)->pivot->role)->toBe('reviewer');
});

test('the auto-create logic itself requires a resolvable record', function () {
    $post = RelPost::create([]);

    $withRecord = BelongsToSelect::make('author_id')
        ->relationship('author', 'name')
        ->createOptionForm([TextInput::make('name')])
        ->record($post);

    $withoutRecord = BelongsToSelect::make('author_id')
        ->relationship('author', 'name')
        ->createOptionForm([TextInput::make('name')]);

    expect($withRecord->createOption(['name' => 'Katherine']))->toBeInstanceOf(RelAuthor::class)
        ->and($withoutRecord->createOption(['name' => 'Nobody']))->toBeNull();
});
