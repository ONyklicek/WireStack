<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Events;

/**
 * Dispatched after a group's values are written.
 *
 * The hook an application needs and could not have: settings are the values
 * something else is configured *from*, so changing one usually has to reach
 * something — a cached config to rebuild, a mail transport to re-resolve, a
 * search index to re-tag. Polling for that is not possible and overriding the
 * page to bolt it on means every application forks the screen.
 *
 *   Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
 *       if ($event->group === 'mail') {
 *           Artisan::call('config:clear');
 *       }
 *   });
 *
 * `$values` is what was written in this call, not the whole group: a listener
 * that wants the rest asks {@see Settings::all()}, which by then answers with
 * these in it.
 */
final readonly class SettingsSaved
{
    /**
     * @param  string  $group  The storage group written to.
     * @param  array<string, mixed>  $values  The keys and values this write carried.
     */
    public function __construct(
        public string $group,
        public array $values = [],
    ) {}
}
