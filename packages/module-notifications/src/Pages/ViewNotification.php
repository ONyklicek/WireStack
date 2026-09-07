<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications\Pages;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleNotifications\Resources\NotificationResource;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * One notification — and opening it is what marks it read.
 *
 * That is the behaviour a person expects from every inbox there has ever been,
 * and doing it in the page's own record hook rather than in a button means the
 * unread count is right without anyone remembering to press anything.
 */
class ViewNotification extends ViewPage
{
    protected static ?string $resource = NotificationResource::class;

    protected function mountedRecord(): void
    {
        $record = $this->resolveRecord();

        if ($record instanceof Model && $record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }
    }
}
