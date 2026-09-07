<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Models;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleSettings\Support\Settings;

/**
 * One stored setting.
 *
 * The value is JSON, so what went in comes back out: a boolean stays a boolean
 * and an array survives. A settings table with a string column makes every
 * reader cast by hand, and they disagree.
 */
class Setting extends Model
{
    protected $guarded = [];

    /**
     * Any write drops the group's cached values.
     *
     * On the model rather than only in {@see Settings}, because the model is
     * what everything ends up going through and the support class is not: a
     * seeder, a factory, a data migration or a console command writing a row
     * directly would otherwise leave the cache holding the old value forever —
     * `rememberForever` means forever. Invalidation belongs where the write
     * happens.
     */
    protected static function booted(): void
    {
        $forget = static function (self $setting): void {
            Settings::forget((string) $setting->getAttribute('group'));
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public function getTable(): string
    {
        return (string) config('wire-module-settings.table', 'wire_settings');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
