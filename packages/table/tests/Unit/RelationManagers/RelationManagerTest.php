<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Table;

class RmAuthor extends Model
{
    protected $table = 'rm_authors';

    protected $guarded = [];

    public $timestamps = false;

    public function posts(): HasMany
    {
        return $this->hasMany(RmPost::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RmTag::class, 'rm_author_tag', 'author_id', 'tag_id');
    }

    public function notARelation(): string
    {
        return 'nope';
    }
}

class RmPost extends Model
{
    protected $table = 'rm_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(RmAuthor::class, 'author_id');
    }
}

class RmTag extends Model
{
    protected $table = 'rm_tags';

    protected $guarded = [];

    public $timestamps = false;
}

class RmPostsRelationManager extends RelationManager
{
    protected string $relationship = 'posts';

    protected ?string $title = 'Posts';

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')]);
    }
}

class RmTagsRelationManager extends RelationManager
{
    protected string $relationship = 'tags';

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }
}

class RmAuthorRelationManager extends RelationManager
{
    protected string $relationship = 'author';

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }
}

class RmNoRelationshipManager extends RelationManager
{
    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')]);
    }
}

class RmNotARelationManager extends RelationManager
{
    protected string $relationship = 'notARelation';

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')]);
    }
}

function makeRelationManager(string $class, ?Model $owner): RelationManager
{
    $rm = new $class;
    $rm->ownerRecord = $owner;

    return $rm;
}

beforeEach(function () {
    Schema::create('rm_authors', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('rm_posts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('author_id');
        $table->string('title');
    });

    Schema::create('rm_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('rm_author_tag', function (Blueprint $table) {
        $table->foreignId('author_id');
        $table->foreignId('tag_id');
    });

    $this->author = RmAuthor::create(['name' => 'Alice']);
    $this->other = RmAuthor::create(['name' => 'Bob']);

    RmPost::create(['author_id' => $this->author->id, 'title' => 'Alice One']);
    RmPost::create(['author_id' => $this->author->id, 'title' => 'Alice Two']);
    RmPost::create(['author_id' => $this->other->id, 'title' => 'Bob One']);
});

afterEach(function () {
    Schema::dropIfExists('rm_author_tag');
    Schema::dropIfExists('rm_tags');
    Schema::dropIfExists('rm_posts');
    Schema::dropIfExists('rm_authors');
});

// ─── Query scoping ───────────────────────────────────────────────

test('the table query is scoped to the owner relationship', function () {
    $rm = makeRelationManager(RmPostsRelationManager::class, $this->author);

    $titles = $rm->getTable()->getQuery()->pluck('title')->all();

    expect($titles)->toBe(['Alice One', 'Alice Two']);
});

test('a different owner scopes to its own related rows', function () {
    $rm = makeRelationManager(RmPostsRelationManager::class, $this->other);

    expect($rm->getTable()->getQuery()->pluck('title')->all())->toBe(['Bob One']);
});

// ─── Accessors ───────────────────────────────────────────────────

test('exposes the owner record and relationship', function () {
    $rm = makeRelationManager(RmPostsRelationManager::class, $this->author);

    expect($rm->getOwnerRecord()->is($this->author))->toBeTrue()
        ->and($rm->getRelationshipName())->toBe('posts')
        ->and($rm->getRelationship())->toBeInstanceOf(HasMany::class);
});

test('getOwnerRecord throws when no owner is set', function () {
    $rm = makeRelationManager(RmPostsRelationManager::class, null);

    expect(fn () => $rm->getOwnerRecord())->toThrow(RuntimeException::class, 'requires an ownerRecord');
});

test('getRelationshipName throws when the relationship is undefined', function () {
    $rm = makeRelationManager(RmNoRelationshipManager::class, $this->author);

    expect(fn () => $rm->getRelationshipName())->toThrow(RuntimeException::class, 'must define a $relationship');
});

test('getRelationship throws when the named method is not an Eloquent relation', function () {
    $rm = makeRelationManager(RmNotARelationManager::class, $this->author);

    expect(fn () => $rm->getRelationship())->toThrow(RuntimeException::class, 'is not an Eloquent relationship');
});

// ─── createRelatedRecord ─────────────────────────────────────────

test('createRelatedRecord creates a has-many child with the foreign key set', function () {
    $rm = makeRelationManager(RmPostsRelationManager::class, $this->author);

    $post = $rm->createRelatedRecord(['title' => 'Alice Three']);

    expect($post)->toBeInstanceOf(RmPost::class)
        ->and($post->author_id)->toBe($this->author->id)
        ->and(RmPost::where('author_id', $this->author->id)->count())->toBe(3);
});

test('createRelatedRecord creates and attaches a belongs-to-many record', function () {
    $rm = makeRelationManager(RmTagsRelationManager::class, $this->author);

    $tag = $rm->createRelatedRecord(['name' => 'Laravel']);

    expect($tag)->toBeInstanceOf(RmTag::class)
        ->and($this->author->tags()->pluck('name')->all())->toBe(['Laravel']);
});

test('createRelatedRecord throws for an unsupported relationship', function () {
    $post = RmPost::first();
    $rm = makeRelationManager(RmAuthorRelationManager::class, $post);

    expect(fn () => $rm->createRelatedRecord(['name' => 'X']))
        ->toThrow(RuntimeException::class, 'does not support creating');
});

// ─── attach / detach (belongs-to-many) ───────────────────────────

test('attachRelated and detachRelated manage belongs-to-many links', function () {
    $rm = makeRelationManager(RmTagsRelationManager::class, $this->author);
    $tag = RmTag::create(['name' => 'PHP']);

    $rm->attachRelated($tag->id);
    expect($this->author->tags()->pluck('name')->all())->toBe(['PHP']);

    $rm->detachRelated($tag->id);
    expect($this->author->tags()->count())->toBe(0);
});

test('detachRelated with no argument detaches all links', function () {
    $rm = makeRelationManager(RmTagsRelationManager::class, $this->author);
    $rm->attachRelated(RmTag::create(['name' => 'A'])->id);
    $rm->attachRelated(RmTag::create(['name' => 'B'])->id);

    $rm->detachRelated();

    expect($this->author->tags()->count())->toBe(0);
});

test('attachRelated throws on a non belongs-to-many relationship', function () {
    $rm = makeRelationManager(RmPostsRelationManager::class, $this->author);

    expect(fn () => $rm->attachRelated(1))
        ->toThrow(RuntimeException::class, 'requires a belongs-to-many');
});

// ─── Rendering ───────────────────────────────────────────────────

test('it renders the relationship-scoped table with its title', function () {
    Livewire::test(RmPostsRelationManager::class, ['ownerRecord' => $this->author])
        ->assertOk()
        ->assertSee('Posts')
        ->assertSee('Alice One')
        ->assertSee('Alice Two')
        ->assertDontSee('Bob One');
});
