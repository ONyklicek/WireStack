<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

/**
 * Trait HasButtonStyles
 *
 * Shared button CSS class generation for all Action types.
 * Uses HasColor for color classes.
 */
trait HasButtonStyles
{
    protected ?string $color = 'primary';

    protected ?string $size = 'sm';

    protected bool $outlined = false;

    public function color(?string $color): static
    {
        $this->color = $color;

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
        return $this->color ?? 'primary';
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
        if ($isIconButton) {
            return match ($this->getSize()) {
                'xs' => 'p-1',
                'sm' => 'p-1.5',
                'md' => 'p-2',
                'lg' => 'p-2.5',
                default => 'p-1.5',
            };
        }

        return match ($this->getSize()) {
            'xs' => 'px-2 py-1 text-xs gap-1',
            'sm' => 'px-2.5 py-1.5 text-sm gap-1.5',
            'md' => 'px-3 py-2 text-sm gap-2',
            'lg' => 'px-4 py-2.5 text-base gap-2',
            default => 'px-2.5 py-1.5 text-sm gap-1.5',
        };
    }
}
