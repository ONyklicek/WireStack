<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'import.configuring' hook.
 *
 * The other half of {@see ExportConfiguringPayload}. The two are declared the
 * same way — an `ExportAction` and an `ImportAction` in the same `headerActions()`
 * — so a table whose export could be adjusted and whose import could not was an
 * asymmetry with nothing behind it.
 *
 * It needed no new composition point, which is the difference from its
 * counterpart: a queued import re-enters through the very `importTable()` the
 * streamed one calls, so one dispatch already covers both deliveries.
 *
 * ```php
 * $payload->import->updateExisting(['email']);
 * $payload->columns = [...$payload->columns, ImportColumn::make('imported_at')];
 * ```
 *
 * The path is **read-only**. A callback that could repoint it would turn a
 * header action into a way of reading any file the server can open, and the
 * authorization check that guards the real one runs before this.
 *
 * Typed only.
 */
final class ImportConfiguringPayload implements HasHookTarget
{
    /**
     * @param  object  $import  The import config the action declared
     * @param  array<int, mixed>  $columns  The columns the file is mapped onto (modifiable)
     * @param  string  $path  The file about to be read
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $import,
        public array $columns,
        public readonly string $path,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'import' => $this->import,
            'columns' => $this->columns,
            'path' => $this->path,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
