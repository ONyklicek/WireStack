<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NyonCode\WireSortable\Models\ReorderableColumnOrder;
use Workbench\App\Models\User;

it('saves, reads and overwrites column order', function () {
    expect(ReorderableColumnOrder::getOrder(1, 'App\\Post', 'posts'))->toBeNull();

    ReorderableColumnOrder::saveOrder(1, 'App\\Post', 'posts', ['title', 'created_at']);

    expect(ReorderableColumnOrder::getOrder(1, 'App\\Post', 'posts'))
        ->toBe(['title', 'created_at']);

    // updateOrCreate overwrites the existing row rather than duplicating it.
    ReorderableColumnOrder::saveOrder(1, 'App\\Post', 'posts', ['created_at', 'title']);

    expect(ReorderableColumnOrder::getOrder(1, 'App\\Post', 'posts'))
        ->toBe(['created_at', 'title'])
        ->and(ReorderableColumnOrder::query()->count())->toBe(1);
});

it('scopes orders per user, model and table', function () {
    ReorderableColumnOrder::saveOrder(1, 'App\\Post', 'posts', ['a']);
    ReorderableColumnOrder::saveOrder(2, 'App\\Post', 'posts', ['b']);

    expect(ReorderableColumnOrder::getOrder(2, 'App\\Post', 'posts'))->toBe(['b'])
        ->and(ReorderableColumnOrder::getOrder(1, 'App\\Other', 'posts'))->toBeNull();
});

it('deletes a stored column order', function () {
    ReorderableColumnOrder::saveOrder(1, 'App\\Post', 'posts', ['a']);

    ReorderableColumnOrder::deleteOrder(1, 'App\\Post', 'posts');

    expect(ReorderableColumnOrder::getOrder(1, 'App\\Post', 'posts'))->toBeNull();
});

it('casts column_order to an array and exposes a user relation', function () {
    config()->set('wire-sortable.user_model', User::class);

    $record = ReorderableColumnOrder::create([
        'user_id' => 5,
        'model_type' => 'App\\Post',
        'table_identifier' => 'posts',
        'column_order' => ['x', 'y'],
    ]);

    expect($record->column_order)->toBe(['x', 'y'])
        ->and($record->user())->toBeInstanceOf(BelongsTo::class);
});
