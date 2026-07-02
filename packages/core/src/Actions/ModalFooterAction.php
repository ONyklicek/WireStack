<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * ModalFooterAction - Extra button in modal footer.
 *
 * Usage:
 *   ModalFooterAction::make('preview')
 *       ->label('Náhled')
 *       ->color('gray')
 *       ->outlined()
 *       ->action(fn ($data, $component) => $component->dispatch('preview', $data))
 *       ->position('before')  // 'before' or 'after' the main submit/cancel buttons
 *
 * @phpstan-consistent-constructor
 */
class ModalFooterAction
{
    protected string $name;

    protected ?string $label = null;

    protected ?string $icon = null;

    protected ?string $color = Color::Gray->value;

    protected bool $outlined = false;

    protected ?Closure $actionCallback = null;

    protected string $position = 'before';

    protected bool $closesModal = false;

    protected bool $submitsForm = false;

    protected bool $requiresConfirmation = false;

    protected ?string $confirmationMessage = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    // Fluent setters
    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function outlined(bool $outlined = true): static
    {
        $this->outlined = $outlined;

        return $this;
    }

    public function action(Closure $callback): static
    {
        $this->actionCallback = $callback;

        return $this;
    }

    public function position(string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function closesModal(bool $closes = true): static
    {
        $this->closesModal = $closes;

        return $this;
    }

    public function submitsForm(bool $submits = true): static
    {
        $this->submitsForm = $submits;

        return $this;
    }

    /**
     * Ask the user to confirm before the action callback runs. Rendered as a
     * native `wire:confirm` dialog on the footer button; the message defaults to
     * the shared confirm-description translation, override it with
     * {@see confirm()}.
     */
    public function requiresConfirmation(bool $condition = true): static
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    /**
     * Require confirmation with a custom message ({@see requiresConfirmation()}
     * with the given `wire:confirm` text; null keeps the translated default).
     */
    public function confirm(?string $message = null): static
    {
        $this->confirmationMessage = $message;

        return $this->requiresConfirmation();
    }

    // Getters
    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColor(): string
    {
        return $this->color ?? Color::Gray->value;
    }

    public function isOutlined(): bool
    {
        return $this->outlined;
    }

    public function getActionCallback(): ?Closure
    {
        return $this->actionCallback;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function shouldCloseModal(): bool
    {
        return $this->closesModal;
    }

    public function shouldSubmitForm(): bool
    {
        return $this->submitsForm;
    }

    public function needsConfirmation(): bool
    {
        return $this->requiresConfirmation;
    }

    /**
     * The `wire:confirm` message, or null when no confirmation is required.
     */
    public function getConfirmationMessage(): ?string
    {
        if (! $this->requiresConfirmation) {
            return null;
        }

        return $this->confirmationMessage ?? Trans::get('wire-core::actions.confirm_description');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->getLabel(),
            'icon' => $this->icon,
            'color' => $this->color,
            'outlined' => $this->outlined,
            'position' => $this->position,
            'closesModal' => $this->closesModal,
            'submitsForm' => $this->submitsForm,
            'confirmMessage' => $this->getConfirmationMessage(),
        ];
    }
}
