<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Mentions;

use NyonCode\WireCore\Foundation\Support\MorphedModels;

/**
 * One mention as the stored document holds it: what it points at, never what it
 * says.
 *
 * The `type` is written from `getMorphClass()`, so it is a class name or a morph
 * alias depending on the application ({@see MorphedModels}).
 * The `label` is the text that was on screen when the mention was inserted — it
 * is a *fallback*, read only when the record can no longer be resolved, and
 * silently discarded on every render where it can.
 */
final class MentionReference
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $trigger,
        public readonly string $label,
    ) {}

    /** Identity within one render pass: what the resolved map is keyed by. */
    public function key(): string
    {
        return $this->type.':'.$this->id;
    }

    /** What the mention read as when it was written, trigger included. */
    public function fallbackText(): string
    {
        return $this->label !== '' ? $this->label : $this->trigger.$this->id;
    }
}
