<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

/**
 * Minimum / maximum item-count constraints for multi-value fields.
 *
 * Shared by every field that caps how many entries a value may hold
 * (Select in `multiple()` mode, Tags, Repeater). Pure configuration
 * plumbing — the fields keep their own validation-rule assembly.
 */
trait HasItemLimits
{
    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    /** Require at least this many items. */
    public function minItems(?int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    /** Allow at most this many items. */
    public function maxItems(?int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }
}
