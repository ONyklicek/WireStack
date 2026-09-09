<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted per-user table preferences (backs {@see DatabasePreferenceDriver}).
 *
 * One row per (user_id, table_key) — see the `create_table_preferences_table`
 * migration. The `preferences` column is a JSON bag holding, today, the hidden
 * column set: `['columns' => ['hidden' => [...]]]`.
 *
 * @property int $id
 * @property int|string|null $user_id
 * @property string $table_key
 * @property array<string, mixed> $preferences
 */
class TablePreference extends Model
{
    protected $table = 'table_preferences';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'preferences' => 'array',
    ];
}
