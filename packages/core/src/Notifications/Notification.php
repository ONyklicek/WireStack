<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications;

use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Immutable value object representing a notification.
 *
 * Carries all metadata a notification driver might need:
 * type, message, title, duration, and arbitrary extra data.
 *
 * Usage in actions:
 *   ->successNotification('Uloženo')
 *   ->successNotification(
 *       Notification::make('success', 'Uloženo')->title('Hotovo')->duration(5000)
 *   )
 */
final class Notification
{
    private function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly ?string $title = null,
        public readonly ?int $duration = null,
        public readonly ?string $icon = null,
        public readonly ?string $position = null,
        /** @var array<string, mixed> */
        public readonly array $extra = [],
    ) {}

    /**
     * Create a new notification.
     */
    public static function make(string $type, string $message): self
    {
        return new self(type: $type, message: $message);
    }

    // ─── Shortcuts ─────────────────────────────────────────────

    public static function success(string $message): self
    {
        return new self(type: 'success', message: $message);
    }

    public static function error(string $message): self
    {
        return new self(type: 'error', message: $message);
    }

    public static function warning(string $message): self
    {
        return new self(type: 'warning', message: $message);
    }

    public static function info(string $message): self
    {
        return new self(type: 'info', message: $message);
    }

    // ─── Fluent modifiers (return new instance — immutable) ────

    public function title(?string $title): self
    {
        return new self($this->type, $this->message, $title, $this->duration, $this->icon, $this->position,
            $this->extra);
    }

    public function duration(?int $milliseconds): self
    {
        return new self($this->type, $this->message, $this->title, $milliseconds, $this->icon, $this->position,
            $this->extra);
    }

    public function icon(string|Icon|null $icon): self
    {
        $resolved = $icon instanceof Icon ? $icon->value() : $icon;

        return new self($this->type, $this->message, $this->title, $this->duration, $resolved, $this->position,
            $this->extra);
    }

    public function position(?string $position): self
    {
        return new self($this->type, $this->message, $this->title, $this->duration, $this->icon, $position,
            $this->extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function extra(array $extra): self
    {
        return new self($this->type, $this->message, $this->title, $this->duration, $this->icon, $this->position,
            array_merge($this->extra, $extra));
    }

    // ─── Serialization ─────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'message' => $this->message,
            'title' => $this->title,
            'duration' => $this->duration,
            'icon' => $this->icon,
            'position' => $this->position,
            'extra' => $this->extra ?: null,
        ], fn ($v) => $v !== null);
    }
}
