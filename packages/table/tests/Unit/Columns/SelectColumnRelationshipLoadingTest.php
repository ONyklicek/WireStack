<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\SelectColumn;

class SelectColumnRelationshipCategory extends Model
{
    protected $table = 'select_column_relationship_categories';

    protected $guarded = [];
}

class SelectColumnRelationshipPost extends Model
{
    protected $table = 'select_column_relationship_posts';

    protected $guarded = [];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SelectColumnRelationshipCategory::class, 'category_id');
    }
}

beforeEach(function () {
    Schema::create('select_column_relationship_categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('select_column_relationship_posts', function (Blueprint $table) {
        $table->id();
        $table->foreignId('category_id')->nullable();
        $table->timestamps();
    });

    SelectColumnRelationshipCategory::create(['id' => 1, 'name' => 'News']);
    SelectColumnRelationshipCategory::create(['id' => 2, 'name' => 'Docs']);
});

afterEach(function () {
    Schema::dropIfExists('select_column_relationship_posts');
    Schema::dropIfExists('select_column_relationship_categories');
});

it('loads relationship options from the related model', function () {
    $record = SelectColumnRelationshipPost::create(['category_id' => 1]);

    $column = SelectColumn::make('category_id')
        ->relationship('category', 'name')
        ->loadRelationshipOptions($record);

    expect($column->getOptions())->toBe([
        1 => 'News',
        2 => 'Docs',
    ]);
});
