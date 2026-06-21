<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

/**
 * Key-value entry — renders an array (or JSON-cast attribute) state as a
 * two-column key/value table.
 */
class KeyValueEntry extends Entry
{
    protected string $keyLabel = 'Key';

    protected string $valueLabel = 'Value';

    public function keyLabel(string $label): static
    {
        $this->keyLabel = $label;

        return $this;
    }

    public function getKeyLabel(): string
    {
        return $this->keyLabel;
    }

    public function valueLabel(string $label): static
    {
        $this->valueLabel = $label;

        return $this;
    }

    public function getValueLabel(): string
    {
        return $this->valueLabel;
    }

    /**
     * The state as an associative array of pairs.
     *
     * @return array<int|string, mixed>
     */
    public function getPairs(): array
    {
        $state = $this->getState();

        if (is_iterable($state) && ! is_array($state)) {
            $state = iterator_to_array($state);
        }

        return is_array($state) ? $state : [];
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.key-value';
    }
}
