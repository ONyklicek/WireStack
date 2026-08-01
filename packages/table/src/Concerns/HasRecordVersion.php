<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Support\RecordVersion;

/**
 * Optimistic-lock version token for an editable cell, as the client should hold it.
 *
 * Delegates to {@see RecordVersion}, the canonical owner of the convention — the
 * same object the server compares against in CellEditPipeline::commit(). It has
 * to be the same one: this trait used to read the literal `updated_at` attribute,
 * so a model naming its timestamp something else (`const UPDATED_AT`) rendered
 * the '0' sentinel, `conflicts()` reads '0' as "the client never had a version",
 * and every inline edit on such a model went through unguarded. The panel side
 * was moved onto RecordVersion when that was found; the table side was not.
 *
 * '0' remains the wire form of "no version" — a model without timestamps has
 * nothing to guard with, and the client must send something.
 */
trait HasRecordVersion
{
    protected function recordVersion(Model $record): string
    {
        return app(RecordVersion::class)->stamp($record) ?? '0';
    }
}
