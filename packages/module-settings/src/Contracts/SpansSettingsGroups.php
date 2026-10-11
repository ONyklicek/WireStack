<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

use NyonCode\WireModuleSettings\Events\SettingsSaved;

/**
 * A settings group edited as one screen and stored as several.
 *
 * Storage groups are cached and announced one by one ({@see SettingsSaved}),
 * so the code reading a value is arranged by what reads it — the timer reads
 * `timers`, the reports read `reports` — while the person changing them thinks of
 * one page: how work is reported. A screen per storage group would split that
 * page in two; one storage group for both would make the timer read the reports'
 * settings.
 *
 *   public static function storage(): array
 *   {
 *       return ['timers' => ['check_interval', 'shift_start', 'shift_end']];
 *   }
 *
 * Every key listed is read from and written to the storage group it is listed
 * under; every other key stays in the group's own {@see SettingsGroup::group()}.
 * A save writes all of them in **one transaction**, so the page still never has
 * to say that half of it saved.
 */
interface SpansSettingsGroups
{
    /**
     * Keys stored outside the group's own storage, by the storage group that holds them.
     *
     * @return array<string, list<string>>
     */
    public static function storage(): array;
}
