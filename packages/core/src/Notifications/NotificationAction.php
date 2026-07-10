<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

/**
 * Immutable value object for a toast action button.
 *
 * Clicking the button dispatches a Livewire event (optionally with a payload)
 * that a host component can listen for — the Filament-style "Undo" affordance:
 *
 *   Notification::success('Deleted')->action(
 *       NotificationAction::make('Undo', 'restore-record')->payload(['id' => $id])
 *   );
 *
 *   // shorthand
 *   Notification::success('Deleted')->action('Undo', 'restore-record');
 *
 * The host listens with a Livewire #[On('restore-record')] handler.
 */
final class NotificationAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $label,
        public readonly string $event,
        public readonly array $payload = [],
        public readonly bool $closes = true,
        public readonly ?string $color = null,
    ) {}

    public static function make(string $label, string $event): self
    {
        return new self(label: $label, event: $event);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function payload(array $payload): self
    {
        return new self($this->label, $this->event, $payload, $this->closes, $this->color);
    }

    /**
     * Keep the toast open after the action fires (default is to dismiss it).
     */
    public function keepOpen(bool $keepOpen = true): self
    {
        return new self($this->label, $this->event, $this->payload, ! $keepOpen, $this->color);
    }

    public function color(?string $color): self
    {
        return new self($this->label, $this->event, $this->payload, $this->closes, $color);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'event' => $this->event,
            'payload' => $this->payload ?: null,
            'close' => $this->closes,
            'color' => $this->color,
        ], fn ($v) => $v !== null);
    }
}
