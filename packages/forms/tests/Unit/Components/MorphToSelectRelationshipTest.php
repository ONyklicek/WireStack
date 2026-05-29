<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\MorphToSelect\Type;

class MorphToSelectRelationshipPost extends Model
{
    protected $table = 'morph_to_select_relationship_posts';

    protected $guarded = [];
}

class MorphToSelectRelationshipVideo extends Model
{
    protected $table = 'morph_to_select_relationship_videos';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('morph_to_select_relationship_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->boolean('published')->default(false);
        $table->timestamps();
    });

    Schema::create('morph_to_select_relationship_videos', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    MorphToSelectRelationshipPost::create(['id' => 1, 'title' => 'Published Post', 'published' => true]);
    MorphToSelectRelationshipPost::create(['id' => 2, 'title' => 'Draft Post', 'published' => false]);
    MorphToSelectRelationshipVideo::create(['id' => 1, 'name' => 'Launch Video']);
});

afterEach(function () {
    Schema::dropIfExists('morph_to_select_relationship_videos');
    Schema::dropIfExists('morph_to_select_relationship_posts');
});

it('loads options for a morph type from the configured model', function () {
    $type = Type::make(MorphToSelectRelationshipPost::class)
        ->titleAttribute('title');

    expect($type->getOptions())->toBe([
        1 => 'Published Post',
        2 => 'Draft Post',
    ]);
});

it('applies morph type option query modifiers', function () {
    $type = Type::make(MorphToSelectRelationshipPost::class)
        ->titleAttribute('title')
        ->modifyOptionsQueryUsing(fn ($query) => $query->where('published', true));

    expect($type->getOptions())->toBe([
        1 => 'Published Post',
    ]);
});

it('returns id options for the selected morph type', function () {
    $field = MorphToSelect::make('commentable')
        ->types([
            Type::make(MorphToSelectRelationshipPost::class)->titleAttribute('title'),
            Type::make(MorphToSelectRelationshipVideo::class)->titleAttribute('name'),
        ]);

    expect($field->getIdOptionsForType(MorphToSelectRelationshipVideo::class))->toBe([
        1 => 'Launch Video',
    ]);
});
