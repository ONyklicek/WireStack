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
            return ($this->headingCallback)($context);
        }

        return $this->heading;
    }

    public function getDescription(mixed $context = null): ?string
    {
        if ($this->descriptionCallback && $context) {
            return ($this->descriptionCallback)($context);
        }

        return $this->description;
    }

    public function getWidth(): string
    {
        return $this->width;
    }

    /**
     * Canonical modal max-width class for a width token.
     *
     * Single source for the modal/dialog/slide-over `max-w-*` scale so the three
     * view surfaces stay in sync instead of each re-encoding it. Centered dialogs
     * gate the width at the `sm:` breakpoint (full width on mobile); slide-over
     * panels apply it unconditionally (`$responsive: false`). Class strings are
     * kept literal for Tailwind's JIT scanner; an unknown token falls back to
     * `max-w-md`.
     */
    public static function getMaxWidthClass(string $width, bool $responsive = true): string
    {
        if ($responsive) {
            return match ($width) {
                'sm' => 'sm:max-w-sm',
                'lg' => 'sm:max-w-lg',
                'xl' => 'sm:max-w-xl',
                '2xl' => 'sm:max-w-2xl',
                '3xl' => 'sm:max-w-3xl',
                '4xl' => 'sm:max-w-4xl',
                '5xl' => 'sm:max-w-5xl',
                '6xl' => 'sm:max-w-6xl',
                '7xl' => 'sm:max-w-7xl',
                'full' => 'sm:max-w-full',
                default => 'sm:max-w-md',
            };
        }

        return match ($width) {
            'sm' => 'max-w-sm',
            'lg' => 'max-w-lg',
            'xl' => 'max-w-xl',
            '2xl' => 'max-w-2xl',
            '3xl' => 'max-w-3xl',
            '4xl' => 'max-w-4xl',
            '5xl' => 'max-w-5xl',
            '6xl' => 'max-w-6xl',
            '7xl' => 'max-w-7xl',
            'full' => 'max-w-full',
            default => 'max-w-md',
        };
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
