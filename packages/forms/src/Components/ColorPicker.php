<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Color picker field supporting hex, hsl, rgb, rgba formats.
 */
class ColorPicker extends Field
{
    protected string $format = 'hex';

    /** @var array<int, string>|Closure */
    protected array|Closure $swatches = [];

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

    /**
     * @param  array<int, string>|Closure  $colors  Hex colour strings shown as clickable swatches.
     */
    public function swatches(array|Closure $colors): static
    {
        $this->swatches = $colors;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return array<int, string>
     */
    public function getSwatches(): array
    {
        return $this->evaluate($this->swatches);
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.color-picker';
    }
}
