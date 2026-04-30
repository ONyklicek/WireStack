<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Concerns;

use Closure;

/**
 * Shared modal properties: heading, description, width, close behavior.
 *
 * Used by Modal, ConfirmationDialog, SlideOver, Wizard.
 */
trait HasModalProperties
{
    protected ?string $heading = null;

    protected ?Closure $headingCallback = null;

    protected ?string $description = null;

    protected ?Closure $descriptionCallback = null;

    protected string $width = 'md';

    protected bool $closeOnClickAway = true;

    protected bool $closeOnEscape = true;

    protected ?string $maxHeight = null;

    protected ?string $id = null;

    public function heading(string|Closure|null $heading): static
    {
        if ($heading instanceof Closure) {
            $this->headingCallback = $heading;
        } else {
            $this->heading = $heading;
        }

        return $this;
    }

    public function description(string|Closure|null $description): static
    {
        if ($description instanceof Closure) {
            $this->descriptionCallback = $description;
        } else {
            $this->description = $description;
        }

        return $this;
    }

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function closeOnClickAway(bool $close = true): static
    {
        $this->closeOnClickAway = $close;

        return $this;
    }

    public function closeOnEscape(bool $close = true): static
    {
        $this->closeOnEscape = $close;

        return $this;
    }

    public function maxHeight(string $maxHeight): static
    {
        $this->maxHeight = $maxHeight;

        return $this;
    }

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getHeading(mixed $context = null): ?string
    {
        if ($this->headingCallback && $context) {
            return call_user_func($this->headingCallback, $context);
        }

        return $this->heading;
    }

    public function getDescription(mixed $context = null): ?string
    {
        if ($this->descriptionCallback && $context) {
            return call_user_func($this->descriptionCallback, $context);
        }

        return $this->description;
    }

    public function getWidth(): string
    {
        return $this->width;
    }

    public function shouldCloseOnClickAway(): bool
    {
        return $this->closeOnClickAway;
    }

    public function shouldCloseOnEscape(): bool
    {
        return $this->closeOnEscape;
    }

    public function getMaxHeight(): ?string
    {
        return $this->maxHeight;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
