<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use NyonCode\WireBoost\Support\Validation\ModelIntrospector;
use NyonCode\WireBoost\Tests\Fixtures\Orphan;
use NyonCode\WireBoost\Tests\Fixtures\Post;

beforeEach(function () {
    Schema::create('boost_posts', function ($table) {
        $table->increments('id');
        $table->string('title');
        $table->boolean('published')->default(false);
        $table->unsignedInteger('author_id')->nullable();
    });

    $this->post = new ModelIntrospector(new Post);
});

afterEach(function () {
    Schema::dropIfExists('boost_posts');
});

it('reads the real columns of the table', function () {
    expect($this->post->columns())->toBe(['id', 'title', 'published', 'author_id'])
        ->and($this->post->table())->toBe('boost_posts')
        ->and($this->post->hasTable())->toBeTrue();
});

it('treats an unreachable connection as unknowable rather than failing', function () {
    $orphan = new ModelIntrospector(new Orphan);

    expect($orphan->columns())->toBe([])
        ->and($orphan->hasTable())->toBeFalse();
});

it('collects every route a model has to an attribute', function () {
    expect($this->post->attributes())
        ->toContain('title')      // real column
        ->toContain('published')  // cast
        ->toContain('excerpt')    // appended, protected Attribute accessor
        ->toContain('summary')    // protected Attribute accessor, not appended
        ->toContain('headline');  // classic getHeadlineAttribute()
});

it('resolves a real attribute', function () {
    expect($this->post->resolves('title'))->toBeTrue();
});

it('does not resolve an attribute the model lacks', function () {
    expect($this->post->resolves('titel'))->toBeFalse();
});

it('resolves an empty name rather than complaining about it', function () {
    expect($this->post->resolves(''))->toBeTrue();
});

it('resolves anything when the table cannot be inspected', function () {
    // With no schema to check against, staying silent beats guessing.
    expect((new ModelIntrospector(new Orphan))->resolves('whatever'))->toBeTrue();
});

it('resolves a relation path by its first hop', function () {
    expect($this->post->resolves('author.name'))->toBeTrue();
});

it('resolves a json path by its first hop', function () {
    expect($this->post->resolves('title.en'))->toBeTrue();
});

it('does not resolve a path whose first hop is neither attribute nor relation', function () {
    expect($this->post->resolves('nope.name'))->toBeFalse();
});

it('recognises a typed relation', function () {
    expect($this->post->isRelation('author'))->toBeTrue();
});

it('recognises an untyped relation by calling it', function () {
    expect($this->post->isRelation('tags'))->toBeTrue();
});

it('rejects a method that does not exist', function () {
    expect($this->post->isRelation('nope'))->toBeFalse();
});

it('rejects a method that returns something else', function () {
    expect($this->post->isRelation('publish'))->toBeFalse();
});

it('rejects an untyped method that throws when called', function () {
    expect($this->post->isRelation('boom'))->toBeFalse();
});

it('rejects a method that requires arguments', function () {
    expect($this->post->isRelation('scopeOfType'))->toBeFalse();
});

it('rejects a non-public method', function () {
    expect($this->post->isRelation('secret'))->toBeFalse();
});

it('suggests the attribute a typo was reaching for', function () {
    expect($this->post->suggestionsFor('titel'))->toContain('title');
});

it('suggests nothing for a name unlike any attribute', function () {
    expect($this->post->suggestionsFor('completely_unrelated'))->toBe([]);
});

it('returns at most three suggestions', function () {
    expect(count($this->post->suggestionsFor('title')))->toBeLessThanOrEqual(3);
});
