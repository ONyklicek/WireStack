<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleNotifications\Resources\NotificationResource;
use NyonCode\WirePanels\Resources\Concerns\ResolvesScopedRecord;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * One notification — and opening it is what marks it read.
 *
 * That is the behaviour a person expects from every inbox there has ever been,
 * and doing it in the page's own record hook rather than in a button means the
 * unread count is right without anyone remembering to press anything.
 *
 * **Which is exactly why the lookup has to be scoped.** This page used to
 * resolve its record the inherited way — `find()` on the key in the URL — while
 * only the list went through {@see NotificationResource::scopeToViewer()}. So an
 * id was permission: another person's notification rendered here in full, and
 * the hook below then marked it read, which took it out of *their* unread tab.
 * A read they never made, on a message they never saw.
 */
class ViewNotification extends ViewPage
{
    use ResolvesScopedRecord;

    protected static ?string $resource = NotificationResource::class;

    protected function mountedRecord(): void
    {
        $record = $this->resolveRecord();

        if ($record instanceof Model && $record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * The same scope the list draws, so the page and the list agree.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeRecordQuery(Builder $query): Builder
    {
        return NotificationResource::scopeToViewer($query);
    }
}
