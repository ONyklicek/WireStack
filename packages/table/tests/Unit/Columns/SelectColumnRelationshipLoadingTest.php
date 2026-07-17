<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

// Regression: ->relationship() used to set the relation name and nothing more —
// no caller ever loaded the options, so the cell rendered an empty <select>
// while the docs promised it "loads options from a related model".
it('fills the options automatically on the first render', function () {
    $record = SelectColumnRelationshipPost::create(['category_id' => 1]);

    $html = SelectColumn::make('category_id')
        ->relationship('category', 'name')
        ->renderCell($record);

    expect($html)
        ->toContain('News')
        ->toContain('Docs');
});

it('loads the relationship once per render, not once per row', function () {
    $records = collect(range(1, 5))->map(fn () => SelectColumnRelationshipPost::create(['category_id' => 1]));

    $column = SelectColumn::make('category_id')->relationship('category', 'name');

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    foreach ($records as $record) {
        $column->renderCell($record);
    }

    // One query for the whole render — the related list is the same for every row.
    expect($queries)->toBe(1);
});

it('lets an explicit options() list win over the relationship', function () {
    $record = SelectColumnRelationshipPost::create(['category_id' => 1]);

    $column = SelectColumn::make('category_id')
        ->relationship('category', 'name')
        ->options([1 => 'Custom']);

    $column->renderCell($record);

    expect($column->getOptions())->toBe([1 => 'Custom']);
});
