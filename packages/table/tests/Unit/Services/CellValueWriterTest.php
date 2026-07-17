<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Services\CellValueWriter;

/*
 * The five ways a column can be persisted, and the order between them — which is
 * the contract, not an implementation detail. It used to live inside a
 * transaction closure inside a 213-line method inside a 3,500-line trait, so
 * none of it could be exercised on its own.
 */

class CvwAuthor extends Model
{
    protected $table = 'cvw_authors';

    protected $guarded = [];

    public function tags()
    {
        return $this->belongsToMany(CvwTag::class, 'cvw_author_tag', 'author_id', 'tag_id')
            ->withPivot('note');
    }
}

class CvwTag extends Model
{
    protected $table = 'cvw_tags';

    protected $guarded = [];
}

class CvwPost extends Model
{
    protected $table = 'cvw_posts';

    protected $guarded = [];

    public function author()
    {
        return $this->belongsTo(CvwAuthor::class, 'author_id');
    }
}

beforeEach(function () {
    Schema::create('cvw_authors', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });
    Schema::create('cvw_posts', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->foreignId('author_id')->nullable();
        $t->timestamps();
    });

    Schema::create('cvw_tags', function (Blueprint $t) {
        $t->id();
        $t->string('label');
        $t->timestamps();
    });
    Schema::create('cvw_author_tag', function (Blueprint $t) {
        $t->id();
        $t->foreignId('author_id');
        $t->foreignId('tag_id');
        $t->string('note')->nullable();
    });

    $this->writer = new CellValueWriter;
    $this->author = CvwAuthor::create(['name' => 'Alice']);
    $this->post = CvwPost::create(['title' => 'Draft', 'author_id' => $this->author->id]);
});

test('a plain attribute is written and the record comes back refreshed', function () {
    $record = $this->writer->write(Column::make('title'), $this->post, 'title', 'Published');

    expect($record->title)->toBe('Published')
        ->and(CvwPost::find($this->post->id)->title)->toBe('Published');
});

test('a relation column writes onto the related model', function () {
    $this->writer->write(Column::make('author.name'), $this->post, 'author.name', 'Bob');

    expect(CvwAuthor::find($this->author->id)->name)->toBe('Bob');
});

test('an author save callback outranks everything else', function () {
    $called = false;
    $column = Column::make('title')->editableUsing(function ($record, $value) use (&$called) {
        $called = true;
        $record->title = strtoupper($value);
        $record->save();
    });

    $this->writer->write($column, $this->post, 'title', 'shouted');

    expect($called)->toBeTrue()
        ->and(CvwPost::find($this->post->id)->title)->toBe('SHOUTED');
});

test('a relation column with no loaded relation writes nothing rather than throwing', function () {
    $orphan = CvwPost::create(['title' => 'Orphan', 'author_id' => null]);

    $record = $this->writer->write(Column::make('author.name'), $orphan, 'author.name', 'Nobody');

    expect($record->title)->toBe('Orphan');
});

test('an author save callback (saveUsing) outranks the built-in paths', function () {
    // getSaveCallback() is the highest-priority path, distinct from the legacy
    // editableUsing() callback the previous test covers.
    $seen = null;
    $column = TextInputColumn::make('title')->saveUsing(function ($record, $value, $col) use (&$seen) {
        $seen = $value;
        $record->title = "[{$value}]";
        $record->save();
    });

    $this->writer->write($column, $this->post, 'title', 'wrapped');

    expect($seen)->toBe('wrapped')
        ->and(CvwPost::find($this->post->id)->title)->toBe('[wrapped]');
});

test('a pivot column writes onto the loaded pivot record', function () {
    // Inline-editing a belongsToMany pivot attribute: the value lands on the
    // row's loaded ->pivot model, not on the related model or the parent.
    $tag = CvwTag::create(['label' => 'featured']);
    $this->author->tags()->attach($tag->id, ['note' => 'old']);

    // The record is the related model as loaded through the relation, carrying
    // its pivot.
    $loaded = $this->author->tags()->where('cvw_tags.id', $tag->id)->first();

    $this->writer->write(Column::make('tags.note')->pivot(), $loaded, 'tags.note', 'new');

    expect($this->author->tags()->where('cvw_tags.id', $tag->id)->first()->pivot->note)->toBe('new');
});
