<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals;

use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Modals\Concerns\HasFooterActions;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;
use NyonCode\WireCore\Modals\Contracts\ModalContract;

/**
 * Multi-step wizard modal configuration.
 *
 * Provides a step-by-step wizard experience inside a modal.
 * Each step can have its own schema, validation, and lifecycle hooks.
 *
 * Usage:
 *   Wizard::make()
 *       ->heading('Create User')
 *       ->steps([
 *           ModalStep::make('Basic Info')
 *               ->icon('user')
 *               ->schema([...])
 *               ->validation([...]),
 *           ModalStep::make('Settings')
 *               ->schema([...]),
 *       ]);
 *
 * @phpstan-consistent-constructor
 */
class Wizard implements ModalContract
{
    use HasFooterActions;
    use HasModalProperties;

    protected ?string $icon = null;

    protected ?string $iconColor = null;

    protected ?string $color = null;

    /** @var array<int, ModalStep|array<string, mixed>> */
    protected array $steps = [];

    protected bool $skippable = false;

    public function __construct()
    {
        try {
            $this->width = config('wire-core.modals.default_width', 'md') ?? 'md';
        } catch (\Throwable) {
            // Standalone usage without Laravel container
        }
    }

    public static function make(): static
    {
        return new static;
    }

    public function icon(string|Icon|null $icon, string|Color|null $color = null): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;
        $this->iconColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /**
     * Define wizard steps.
     *
     * @param  array<int, ModalStep|array<string, mixed>>  $steps
     */
    public function steps(array $steps): static
    {
        $this->steps = $steps;

        return $this;
    }

    /**
     * Allow skipping steps (non-linear navigation).
     */
    public function skippable(bool $skippable = true): static
    {
        $this->skippable = $skippable;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getIconColor(): string
    {
        return $this->iconColor ?? Color::Gray->value;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    /**
     * @return array<int, ModalStep|array<string, mixed>>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getTotalSteps(): int
    {
        return count($this->steps);
    }

    public function isSkippable(): bool
    {
        return $this->skippable;
    }

    /**
     * Serialize steps for frontend config.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStepsConfig(mixed $context = null): array
    {
        return array_map(function ($step) use ($context) {
            if ($step instanceof ModalStep) {
                return $step->toArray($context);
            }

            return $step;
        }, $this->steps);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'heading' => $this->getHeading(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'iconColor' => $this->getIconColor(),
            'color' => $this->getColor(),
            'width' => $this->getWidth(),
            'maxHeight' => $this->getMaxHeight(),
            'closeOnClickAway' => $this->shouldCloseOnClickAway(),
            'closeOnEscape' => $this->shouldCloseOnEscape(),
            'submitLabel' => $this->getSubmitLabel(),
            'cancelLabel' => $this->getCancelLabel(),
            'stickyFooter' => $this->hasStickyFooter(),
            'stickyHeader' => $this->hasStickyHeader(),
            'steps' => $this->getStepsConfig(),
            'totalSteps' => $this->getTotalSteps(),
            'skippable' => $this->isSkippable(),
        ];
    }
}
