<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals;

use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Modals\Concerns\HasFooterActions;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;
use NyonCode\WireCore\Modals\Contracts\ModalContract;

/**
 * Confirmation dialog configuration.
 *
 * A specialized modal for yes/no confirmation prompts.
 * Provides presets for common confirmation patterns (delete, danger, warning).
 *
 * Usage:
 *   ConfirmationDialog::make()
 *       ->heading('Delete record?')
 *       ->description('This action cannot be undone.')
 *       ->icon('trash', 'danger')
 *       ->danger();
 *
 * Presets:
 *   ConfirmationDialog::delete('User');
 *   ConfirmationDialog::danger('Are you sure?', 'This is dangerous.');
 *
 * @phpstan-consistent-constructor
 */
class ConfirmationDialog implements ModalContract
{
    use HasFooterActions;
    use HasModalProperties;

    protected ?string $icon = null;

    protected ?string $iconColor = 'warning';

    protected ?string $color = null;

    protected bool $isDanger = false;

    protected bool $isInformative = false;

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

    // ─── Presets ─────────────────────────────────────────────────

    /**
     * Preset: Delete confirmation.
     */
    public static function delete(?string $recordName = null): static
    {
        $description = $recordName
            ? Trans::get('wire-core::actions.delete_description_named', ['name' => $recordName])
            : Trans::get('wire-core::actions.delete_description');

        return static::make()
            ->heading(Trans::get('wire-core::actions.delete_heading'))
            ->description($description)
            ->icon('trash', 'danger')
            ->submitLabel(Trans::get('wire-core::actions.delete_submit'))
            ->danger();
    }

    /**
     * Preset: Generic danger confirmation.
     */
    public static function makeDanger(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->description($description)
            ->icon('warning', 'danger')
            ->danger();
    }

    /**
     * Preset: Warning confirmation.
     */
    public static function makeWarning(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->description($description)
            ->icon('warning', 'warning');
    }

    /**
     * Preset: Informative (no submit button, just info with a close button).
     */
    public static function makeInfo(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->description($description)
            ->icon('info', 'info')
            ->informative();
    }

    /**
     * Preset: Success info.
     */
    public static function makeSuccess(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->description($description)
            ->icon('check-circle', 'success')
            ->informative();
    }

    // ─── Fluent setters ─────────────────────────────────────────

    public function icon(?string $icon, ?string $color = null): static
    {
        $this->icon = $icon;
        if ($color !== null) {
            $this->iconColor = $color;
        }

        return $this;
    }

    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function danger(bool $danger = true): static
    {
        $this->isDanger = $danger;
        if ($danger) {
            $this->color = 'danger';
        }

        return $this;
    }

    public function informative(bool $informative = true): static
    {
        $this->isInformative = $informative;
        if ($informative) {
            $this->cancelLabel = $this->cancelLabel ?? Trans::get('wire-core::actions.confirm_close');
        }

        return $this;
    }

    // ─── Getters ────────────────────────────────────────────────

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getIconColor(): string
    {
        return $this->iconColor ?? 'warning';
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isDanger(): bool
    {
        return $this->isDanger;
    }

    public function isInformative(): bool
    {
        return $this->isInformative;
    }

    public function getSubmitLabel(): ?string
    {
        if ($this->isInformative) {
            return null;
        }

        return $this->submitLabel ?? Trans::get('wire-core::actions.confirm_submit');
    }

    public function getCancelLabel(): string
    {
        if ($this->isInformative) {
            return $this->cancelLabel ?? Trans::get('wire-core::actions.confirm_close');
        }

        return $this->cancelLabel ?? Trans::get('wire-core::actions.confirm_cancel');
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
            'closeOnClickAway' => $this->shouldCloseOnClickAway(),
            'closeOnEscape' => $this->shouldCloseOnEscape(),
            'submitLabel' => $this->getSubmitLabel(),
            'cancelLabel' => $this->getCancelLabel(),
            'isDanger' => $this->isDanger(),
            'isInformative' => $this->isInformative(),
            'stickyFooter' => $this->hasStickyFooter(),
            'stickyHeader' => $this->hasStickyHeader(),
        ];
    }
}
