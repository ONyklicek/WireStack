<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;

/**
 * Color picker field supporting hex, hsl, rgb, rgba formats.
 */
class ColorPicker extends Field
{
    use HasExtraInputAttributes;

    protected string $format = 'hex';

    /** @var array<int, string>|Closure */
    protected array|Closure $swatches = [];

    /** Store the chosen color in hex format. */
    public function hex(): static
    {
        $this->format = 'hex';

        return $this;
    }

    /** Store the chosen color in HSL format. */
    public function hsl(): static
    {
        $this->format = 'hsl';

        return $this;
    }

    /** Store the chosen color in RGB format. */
    public function rgb(): static
    {
        $this->format = 'rgb';

        return $this;
    }

    /** Store the chosen color in RGBA format. */
    public function rgba(): static
    {
        $this->format = 'rgba';

        return $this;
    }

    /** Set the stored color format ("hex", "hsl", "rgb" or "rgba"). */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Set preset color swatches shown as clickable shortcuts.
     *
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
