<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels\Components;

use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Select entry — an inline dropdown that writes the chosen value directly to the
 * record. Options accept a plain value => label map or a backed-enum class name
 * (resolved through the canonical {@see EnumResolver}, matching Select fields and
 * SelectColumn).
 */
class SelectEntry extends EditableEntry
{
    protected string $editableType = 'select';

    /** @var array<int|string, mixed>|string */
    protected array|string $options = [];

    /**
     * @param  array<int|string, mixed>|string  $options  value => label map, or a backed-enum class name
     */
    public function options(array|string $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getOptions(): array
    {
        if (is_string($this->options)) {
            return EnumResolver::isEnumClass($this->options)
                ? EnumResolver::options($this->options)
                : [];
        }

        return $this->options;
    }

    protected function viewName(): string
    {
        return 'wire-core::panels.entries.select';
    }
}
