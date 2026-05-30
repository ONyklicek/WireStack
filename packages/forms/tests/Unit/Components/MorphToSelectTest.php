<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\MorphToSelect\Type;

test('make creates morph-to-select with name', function () {
    $field = MorphToSelect::make('commentable');

    expect($field->getName())->toBe('commentable');
});

test('types can be set', function () {
    $field = MorphToSelect::make('commentable')
        ->types([
            Type::make('App\\Models\\Post')->titleAttribute('title'),
            Type::make('App\\Models\\Video')->titleAttribute('name'),
        ]);

    expect($field->getTypes())->toHaveCount(2);
});

test('type options returns model class to label mapping', function () {
    $field = MorphToSelect::make('commentable')
        ->types([
            Type::make('App\\Models\\Post')->titleAttribute('title')->label('Posts'),
            Type::make('App\\Models\\Video')->titleAttribute('name')->label('Videos'),
        ]);

    $options = $field->getTypeOptions();

    expect($options)->toBe([
        'App\\Models\\Post' => 'Posts',
        'App\\Models\\Video' => 'Videos',
    ]);
});

test('type state path appends type suffix', function () {
    $field = MorphToSelect::make('commentable');

    expect($field->getTypeStatePath())->toBe('commentable_type');
});

test('id state path appends id suffix', function () {
    $field = MorphToSelect::make('commentable');

    expect($field->getIdStatePath())->toBe('commentable_id');
});

test('custom column suffixes', function () {
    $field = MorphToSelect::make('commentable')
        ->typeColumnSuffix('_morph_type')
        ->idColumnSuffix('_morph_id');

    expect($field->getTypeStatePath())->toBe('commentable_morph_type')
        ->and($field->getIdStatePath())->toBe('commentable_morph_id');
});

test('state path includes parent path', function () {
    $field = MorphToSelect::make('commentable');
    $field->statePath('data');

    expect($field->getTypeStatePath())->toBe('data.commentable_type')
        ->and($field->getIdStatePath())->toBe('data.commentable_id');
});

test('find type returns matching type config', function () {
    $postType = Type::make('App\\Models\\Post')->titleAttribute('title');
    $videoType = Type::make('App\\Models\\Video')->titleAttribute('name');

    $field = MorphToSelect::make('commentable')
        ->types([$postType, $videoType]);

    expect($field->findType('App\\Models\\Post'))->toBe($postType)
        ->and($field->findType('App\\Models\\Video'))->toBe($videoType)
        ->and($field->findType('App\\Models\\Unknown'))->toBeNull();
});

test('get id options for unknown type returns empty', function () {
    $field = MorphToSelect::make('commentable')
        ->types([
            Type::make('App\\Models\\Post')->titleAttribute('title'),
        ]);

    expect($field->getIdOptionsForType('App\\Models\\Unknown'))->toBe([]);
});

test('renders both type and id selects', function () {
    // Share $errors as Laravel's middleware normally does
    view()->share('errors', new ViewErrorBag);

    // No titleAttribute → getOptions() returns [] without instantiating models
    $field = MorphToSelect::make('commentable')
        ->types([
            Type::make('App\\Models\\Post')->label('Posts'),
            Type::make('App\\Models\\Video')->label('Videos'),
        ]);
    $field->statePath('data');

    $html = $field->toHtml();

    expect($html)
        ->toContain('commentable_type')
        ->and($html)->toContain('commentable_id')
        ->and($html)->toContain('Posts')
        ->and($html)->toContain('Videos');
});

// ─── Type tests ────────────────────────────────────────────────────

test('type make creates with model class', function () {
    $type = Type::make('App\\Models\\Post');

    expect($type->getModelClass())->toBe('App\\Models\\Post');
});

test('type auto-generates label from class name', function () {
    $type = Type::make('App\\Models\\Post');

    expect($type->getLabel())->toBe('Posts');
});

test('type custom label', function () {
    $type = Type::make('App\\Models\\Post')->label('Blog Posts');

    expect($type->getLabel())->toBe('Blog Posts');
});

test('type title attribute', function () {
    $type = Type::make('App\\Models\\Post')->titleAttribute('title');

    expect($type->getTitleAttribute())->toBe('title');
});

test('type get options returns empty without title attribute', function () {
    $type = Type::make('App\\Models\\Post');

    expect($type->getOptions())->toBe([]);
});

test('type modify options query can be set', function () {
    $callback = fn ($query) => $query->where('published', true);
    $type = Type::make('App\\Models\\Post')
        ->modifyOptionsQueryUsing($callback);

    // Just verify no error
    expect($type)->toBeInstanceOf(Type::class);
});
