<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores user-specific column ordering per model type and table.
 *
 * @property int $id
 * @property int $user_id
 * @property string $model_type
 * @property string $table_identifier
 * @property array $column_order
 */
class ReorderableColumnOrder extends Model
{
    protected $table = 'reorderable_column_orders';

    protected $fillable = [
        'user_id',
        'model_type',
        'table_identifier',
        'column_order',
    ];

    protected $casts = [
        'column_order' => 'array',
    ];

    /**
     * @return BelongsTo<Model, self>
     */
    public function user(): BelongsTo
    {
        $userModel = config('wire-sortable.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel);
    }

    /**
     * Get column order for a user + model + table combination.
     *
     * @return array<int, string>|null
     */
    public static function getOrder(int $userId, string $modelType, string $tableIdentifier): ?array
    {
        $record = static::query()
            ->where('user_id', $userId)
            ->where('model_type', $modelType)
            ->where('table_identifier', $tableIdentifier)
            ->first();

        return $record?->column_order;
    }

    /**
     * Save column order for a user + model + table combination.
     *
     * @param  array<int, string>  $columnOrder
     */
    public static function saveOrder(int $userId, string $modelType, string $tableIdentifier, array $columnOrder): void
    {
        static::query()->updateOrCreate(
            ['user_id' => $userId, 'model_type' => $modelType, 'table_identifier' => $tableIdentifier],
            ['column_order' => $columnOrder],
        );
    }

    /**
     * Delete column order for a user + model + table combination.
     */
    public static function deleteOrder(int $userId, string $modelType, string $tableIdentifier): void
    {
        static::query()
            ->where('user_id', $userId)
            ->where('model_type', $modelType)
            ->where('table_identifier', $tableIdentifier)
            ->delete();
    }
}
