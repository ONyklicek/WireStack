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
 *
 * ─── Events, and where they stop working ───────────────────────
 *
 * An event is the right shape for a toast, which is raised by the very
 * component that would handle it. It is the wrong shape for a **stored**
 * notification opened three days later from the bell: the component that would
 * have listened is long gone, and `Livewire.dispatch()` then reaches nobody at
 * all — silently, which is the worst way for a button to not work.
 *
 * So an action may carry a URL instead:
 *
 *   Notification::success('Export ready')->action(
 *       NotificationAction::link('Download', route('exports.download', $export))
 *   );
 *
 * A link survives the request that raised it, which is exactly the property a
 * stored notification needs. Both kinds render on both surfaces; a stored action
 * with only an event is still rendered, because an application whose listener
 * *is* on the page is entitled to it.
 */
final class NotificationAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $label,
        public readonly ?string $event = null,
        public readonly array $payload = [],
        public readonly bool $closes = true,
        public readonly ?string $color = null,
        public readonly ?string $url = null,
    ) {}

    public static function make(string $label, string $event): self
    {
        return new self(label: $label, event: $event);
    }

    /**
     * An action that goes somewhere instead of dispatching an event.
     *
     * What a stored notification wants: see the class note. `openInNewTab()` is
     * not offered — where a link opens is the browser's business and the user's,
     * and forcing it is the kind of decision a framework should not make for a
     * notification it knows nothing about.
     */
    public static function link(string $label, string $url): self
    {
        return new self(label: $label, url: $url);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function payload(array $payload): self
    {
        return new self($this->label, $this->event, $payload, $this->closes, $this->color, $this->url);
    }

    /** Send the click to a URL rather than to a Livewire listener. */
    public function url(?string $url): self
    {
        return new self($this->label, $this->event, $this->payload, $this->closes, $this->color, $url);
    }

    /**
     * Keep the toast open after the action fires (default is to dismiss it).
     */
    public function keepOpen(bool $keepOpen = true): self
    {
        return new self($this->label, $this->event, $this->payload, ! $keepOpen, $this->color, $this->url);
    }

    public function color(?string $color): self
    {
        return new self($this->label, $this->event, $this->payload, $this->closes, $color, $this->url);
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
            'url' => $this->url,
        ], fn ($v) => $v !== null);
    }

    /**
     * The inverse of {@see toArray()}, for reading a stored notification back.
     *
     * Lenient about what it is handed, because it is handed a row: a payload
     * written by an older version of the application, or by hand, must come back
     * as a usable action or not at all — never as a TypeError inside a bell.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $label = $data['label'] ?? null;

        if (! is_string($label) || $label === '') {
            return null;
        }

        return new self(
            label: $label,
            event: is_string($data['event'] ?? null) ? $data['event'] : null,
            payload: is_array($data['payload'] ?? null) ? $data['payload'] : [],
            closes: (bool) ($data['close'] ?? true),
            color: is_string($data['color'] ?? null) ? $data['color'] : null,
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
        );
    }
}
