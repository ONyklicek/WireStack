<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Order Column
    |--------------------------------------------------------------------------
    |
    | The default database column used for storing the row sort position.
    |
    */
    'order_column' => 'sort_order',

    /*
    |--------------------------------------------------------------------------
    | SortableJS CDN
    |--------------------------------------------------------------------------
    |
    | URL for the SortableJS library. Set to null to disable CDN loading
    | (useful when you bundle it yourself via npm/yarn/bun).
    |
    */
    'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',

    /*
    |--------------------------------------------------------------------------
    | Animation Duration
    |--------------------------------------------------------------------------
    |
    | Animation duration in milliseconds for the drag & drop effect.
    |
    */
    'animation' => 150,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class for users. Used by ReorderableColumnOrder
    | for the belongsTo relationship.
    |
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | User Key Type
    |--------------------------------------------------------------------------
    |
    | The primary key type of your user model, used by the migration to type
    | the reorderable_column_orders.user_id column. Use 'uuid' or 'ulid' for
    | non-integer auth keys; 'id' (default) assumes an unsigned bigint.
    |
    | Supported: 'id', 'uuid', 'ulid'.
    |
    */
    'user_key_type' => 'id',

];
