<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Optimistic-lock version token for an editable cell.
 *
 * The record's `updated_at` timestamp (as a string of seconds), or '0' when the
 * model is not timestamped. Shared by every wire:editable column so a stale
 * write can be detected against the version the client last saw.
 */
trait HasRecordVersion
{
    protected function recordVersion(Model $record): string
    {
        $updatedAt = $record->getAttribute('updated_at');

        return $updatedAt instanceof DateTimeInterface ? (string) $updatedAt->getTimestamp() : '0';
    }
}
