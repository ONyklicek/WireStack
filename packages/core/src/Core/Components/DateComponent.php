<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Components;

/**
 * Shared date-specific behavior for date columns and date pickers.
 */
class DateComponent extends DataComponent
{
    protected ?string $format = null;

    protected ?string $timezone = null;

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }
}
