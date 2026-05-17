<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores user-specific column ordering per model type.
 *
 * @property int $id
 * @property int $user_id
 * @property string $model_type
 * @property array $column_order
 */
class ReorderableColumnOrder extends Model
{
    protected $table = 'reorderable_column_orders';

    protected $fillable = [
        'user_id',
        'model_type',
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
     * Get column order for a user + model combination.
     *
     * @return array<int, string>|null
     */
    public static function getOrder(int $userId, string $modelType): ?array
    {
        $record = static::query()
            ->where('user_id', $userId)
            ->where('model_type', $modelType)
            ->first();

        return $record?->column_order;
    }

    /**
     * Save column order for a user + model combination.
     *
     * @param  array<int, string>  $columnOrder
     */
    public static function saveOrder(int $userId, string $modelType, array $columnOrder): void
    {
        static::query()->updateOrCreate(
            ['user_id' => $userId, 'model_type' => $modelType],
            ['column_order' => $columnOrder],
        );
    }

    /**
     * Delete column order for a user + model combination.
     */
    public static function deleteOrder(int $userId, string $modelType): void
    {
        static::query()
            ->where('user_id', $userId)
            ->where('model_type', $modelType)
            ->delete();
    }
}
