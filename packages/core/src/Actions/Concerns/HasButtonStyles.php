<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasSize;

/**
 * Trait HasButtonStyles
 *
 * Shared button CSS class generation for all Action types.
 * Uses HasColor for color classes.
 *
 * @phpstan-require-extends BaseAction
 */
trait HasButtonStyles
{
    protected ?string $color = Color::Primary->value;

    protected ?string $size = 'sm';

    protected bool $outlined = false;

    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function size(?string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function outlined(bool $outlined = true): static
    {
        $this->outlined = $outlined;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color ?? Color::Primary->value;
    }

    public function getSize(): string
    {
        return $this->size ?? 'sm';
    }

    public function isOutlined(): bool
    {
        return $this->outlined;
    }

    protected function getButtonClasses(bool $isIconButton = false): string
    {
        $base = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800';
        $sizeClasses = $this->getButtonSizeClasses($isIconButton);
        $colorClasses = $isIconButton
            ? $this->getIconButtonColorClasses()
            : ($this->isOutlined() ? $this->getOutlinedColorClasses() : $this->getSolidColorClasses());

        return "{$base} {$sizeClasses} {$colorClasses}";
    }

    protected function getButtonSizeClasses(bool $isIconButton = false): string
    {
        return HasSize::getButtonSizeClasses($this->getSize(), $isIconButton);
    }
}
