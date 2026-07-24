<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Resolves a per-record configuration value that may be a static value or a
 * `fn (Model, $column): mixed` closure.
 *
 * A thin resolver (no business logic), the record-aware sibling of the
 * Foundation {@see EvaluatesClosures}
 * concern, shared by columns whose slots are configured per row (ButtonColumn,
 * PollColumn).
 */
trait EvaluatesRecordClosures
{
    protected function evaluateForRecord(mixed $value, Model $record): mixed
    {
        if ($value instanceof Closure) {
            return $value($record, $this);
        }

        return $value;
    }
}
