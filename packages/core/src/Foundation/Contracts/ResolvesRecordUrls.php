<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Where a record can be read — asked without knowing what answers.
 *
 * Foundation code has records to link to (a mention, a log entry) and no
 * business knowing about resources, pages or routing, all of which live above
 * it. So it asks this, and whoever owns screens answers: the default
 * implementation walks the resource registry to a record's own view page, an
 * application may bind its own, and "nothing is routed" is a null, not a
 * failure (ADR 0027).
 */
interface ResolvesRecordUrls
{
    /**
     * @param  string|null  $zone  The mount point the calling page read in `mount()`.
     */
    public function urlForRecord(Model $record, ?string $zone = null): ?string;
}
