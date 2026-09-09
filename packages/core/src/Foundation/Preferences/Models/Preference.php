<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Preferences\Models;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Preferences\Drivers\DatabasePreferenceDriver;

/**
 * A user's stored state for one surface (backs {@see DatabasePreferenceDriver}).
 *
 * One row per (user_id, surface_key, view) — see the
 * `create_wire_preferences_table` migration. `preferences` is an open JSON bag:
 * a table stores its hidden columns and its row-expansion baseline, a dashboard
 * its widget layout, and neither knows the other exists.
 *
 * @property int $id
 * @property int|string|null $user_id
 * @property string $surface_key
 * @property string $view
 * @property array<string, mixed> $preferences
 */
class Preference extends Model
{
    protected $table = 'wire_preferences';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'preferences' => 'array',
    ];
}
