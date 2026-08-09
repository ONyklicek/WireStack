<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireTable\Events\TableRecordsChanged;
use NyonCode\WireTable\Support\PollingConfig;
use NyonCode\WireTable\Table;

/**
 * The table's side of polling: the fluent surface, and where a table keeps its
 * settings.
 *
 * Configuration only — {@see PollingConfig} owns the vocabulary, the interval
 * validation and the `wire:poll` attribute. Every method here reads or writes
 * that object and nothing else.
 *
 * The namesake in `WireCore\Widgets\Concerns` is a different trait for a
 * different surface: a widget polls when it has an interval, a table polls when
 * it was told to. What the two genuinely share is the attribute, and that lives
 * in `Foundation\ValueObjects\PollDirective`.
 *
 * @phpstan-require-extends Table
 */
trait HasPolling
{
    protected ?PollingConfig $polling = null;

    /**
     * @deprecated Use poll() instead. Will be removed in v2.0.
     */
    public function polling(string $interval = '5s'): static
    {
        Deprecation::method('polling', 'poll');

        return $this->poll($interval);
    }

    /**
     * Enable polling with specified interval.
     *
     * @param  string  $interval  Interval in Livewire format (e.g., '5s', '10s', '30s', '1m')
     */
    public function poll(string $interval = '5s'): static
    {
        $this->pollingConfig()->every($interval);

        return $this;
    }

    /**
     * Keep this table showing what the database currently holds, for everyone
     * looking at it.
     *
     * One call, because "live" is a policy rather than a setting: a short poll
     * interval on its own is a query per client per tick, and change detection
     * on its own does nothing without one. Together they are cheap — each tick
     * is a COUNT + MAX(updated_at) over the filtered set, and the render (query,
     * summaries, morph) only happens when that answer moved.
     *
     *   $table->live();               // every 5s
     *   $table->live('2s');
     *   $table->live(broadcast: true) // …and immediately, where Echo is set up
     *
     * `broadcast: true` adds a push on top, it does not replace the interval:
     * {@see TableRecordsChanged} is a nudge to
     * re-read, so a client with no Echo, a dropped socket or an app with no
     * broadcast connection quietly falls back to the tick instead of going
     * stale. It needs `Broadcast::channel()` authorization for
     * {@see TableRecordsChanged::channelFor()} in the
     * app.
     *
     * A tick never disturbs what the user is in the middle of: a re-render
     * leaves an editable cell's own state alone (`wire:ignore.self`), the cell
     * refuses to reconcile a value being typed or a write still in flight, and
     * an open row context menu is put back where it was afterwards — it closes
     * on a tick only when the row it belongs to has left the page.
     *
     * Change detection reads the parent rows, so a table whose visible data
     * lives in child rows (a rollup, a sum over a relation) should pass its own
     * detector to {@see pollChangeDetection()} after this.
     */
    public function live(string $interval = '5s', bool $broadcast = false): static
    {
        $this->pollingConfig()
            ->every($interval)
            ->detectChanges()
            ->broadcast($broadcast);

        return $this;
    }

    /** Whether writes to this table's records are announced to other sessions. */
    public function shouldBroadcastChanges(): bool
    {
        return $this->pollingConfig()->isBroadcasting();
    }

    /**
     * Set polling to keep connection alive (no timeout).
     */
    public function pollKeepAlive(bool $keepAlive = true): static
    {
        $this->pollingConfig()->keepAlive($keepAlive);

        return $this;
    }

    /**
     * Set condition for when polling should be active.
     *
     * @param  Closure  $condition  Receives $livewire component, returns bool
     */
    public function pollWhen(Closure $condition): static
    {
        $this->pollingConfig()->when($condition);

        return $this;
    }

    /**
     * Set polling method.
     *
     * @param  string  $method  'refresh' (soft refresh) or 'reload' (full page reload)
     */
    public function pollMethod(string $method): static
    {
        $this->pollingConfig()->method($method);

        return $this;
    }

    /**
     * Poll only when browser tab is visible.
     */
    public function pollOnlyVisible(bool $onlyVisible = true): static
    {
        $this->pollingConfig()->onlyVisible($onlyVisible);

        return $this;
    }

    /**
     * Skip the poll re-render when the underlying data has not changed.
     *
     * With `true`, a cheap checksum (COUNT(*) + MAX(updated_at) of the
     * filtered query) is compared between polls; an unchanged checksum
     * skips the full query + render cycle. Models without timestamps fall
     * back to always rendering.
     *
     * Pass a closure for a custom checksum when parent timestamps don't
     * capture relevant changes (e.g. rollup sums over child rows):
     *
     *   ->pollChangeDetection(fn ($query) => (string) $query->max('synced_at'))
     */
    public function pollChangeDetection(bool|Closure $detector = true): static
    {
        $this->pollingConfig()->detectChanges($detector);

        return $this;
    }

    /**
     * Get the poll change detection setting (false = disabled).
     */
    public function getPollChangeDetection(): bool|Closure
    {
        return $this->pollingConfig()->getChangeDetection();
    }

    /**
     * Check if polling is enabled.
     */
    public function isPolling(): bool
    {
        return $this->pollingConfig()->isEnabled();
    }

    /**
     * Get polling interval.
     */
    public function getPollingInterval(): ?string
    {
        return $this->pollingConfig()->interval();
    }

    /**
     * Check if polling should keep connection alive.
     */
    public function isPollingKeepAlive(): bool
    {
        return $this->pollingConfig()->isKeepAlive();
    }

    /**
     * Get polling condition callback.
     */
    public function getPollingCondition(): ?Closure
    {
        return $this->pollingConfig()->getCondition();
    }

    /**
     * Get polling method.
     */
    public function getPollingMethod(): string
    {
        return $this->pollingConfig()->getMethod();
    }

    /**
     * Check if polling should only work when tab is visible.
     */
    public function isPollingOnlyVisible(): bool
    {
        return $this->pollingConfig()->isOnlyVisible();
    }

    /**
     * Get full polling config for view.
     *
     * @return array<string, mixed>
     */
    public function getPollingConfig(): array
    {
        return $this->pollingConfig()->toArray();
    }

    /**
     * Get wire:poll directive string.
     */
    public function getPollingDirective(): ?string
    {
        return $this->pollingConfig()->directive();
    }

    /** This table's polling settings, created on first use. */
    protected function pollingConfig(): PollingConfig
    {
        return $this->polling ??= new PollingConfig;
    }
}
