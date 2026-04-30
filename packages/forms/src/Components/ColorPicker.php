<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

/**
 * Color picker field supporting hex, hsl, rgb, rgba formats.
 */
class ColorPicker extends Field
{
    protected string $format = 'hex';

    public function hex(): static
    {
        $this->format = 'hex';

        return $this;
    }

    public function hsl(): static
    {
        $this->format = 'hsl';

        return $this;
    }

    public function rgb(): static
    {
        $this->format = 'rgb';

        return $this;
    }

    public function rgba(): static
    {
        $this->format = 'rgba';

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.color-picker';
    }
}
