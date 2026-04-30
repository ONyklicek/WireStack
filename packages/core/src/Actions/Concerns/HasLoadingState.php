<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

/**
 * Trait HasLoadingState
 *
 * Provides loading indicator, debounce (double-click protection), and timeout
 * configuration for actions.
 *
 * Usage:
 *   Action::make('export')
 *       ->loadingIndicator(true)            // show spinner during execution
 *       ->loadingIndicator('Exportuji...')   // custom loading text
 *       ->debounce(300)                     // 300ms debounce (anti double-click)
 *       ->timeout(30)                       // 30s timeout for long operations
 *
 * Blade template reads these via getRenderData() and applies:
 *   - wire:loading on button for spinner
 *   - x-data with debounce for double-click protection
 *   - wire:timeout for long operations
 */
trait HasLoadingState
{
    protected bool $showLoadingIndicator = false;

    protected ?string $loadingText = null;

    protected ?int $debounceMs = null;

    protected ?int $timeoutSeconds = null;

    /**
     * Enable loading indicator during action execution.
     *
     * @param  bool|string  $indicator  true to show spinner, or string for custom loading text
     */
    public function loadingIndicator(bool|string $indicator = true): static
    {
        if (is_string($indicator)) {
            $this->showLoadingIndicator = true;
            $this->loadingText = $indicator;
        } else {
            $this->showLoadingIndicator = $indicator;
            $this->loadingText = null;
        }

        return $this;
    }

    /**
     * Set debounce in milliseconds to prevent double-clicking.
     *
     * @param  int  $milliseconds  Debounce interval (recommended: 200-500)
     */
    public function debounce(int $milliseconds): static
    {
        $this->debounceMs = $milliseconds;

        return $this;
    }

    /**
     * Set timeout in seconds for long-running actions.
     * After this timeout, the action is considered failed.
     *
     * @param  int  $seconds  Timeout in seconds
     */
    public function timeout(int $seconds): static
    {
        $this->timeoutSeconds = $seconds;

        return $this;
    }

    // ─── Getters ────────────────────────────────────────────────

    public function hasLoadingIndicator(): bool
    {
        return $this->showLoadingIndicator;
    }

    public function getLoadingText(): ?string
    {
        return $this->loadingText;
    }

    public function getDebounceMs(): ?int
    {
        return $this->debounceMs;
    }

    public function getTimeoutSeconds(): ?int
    {
        return $this->timeoutSeconds;
    }

    /**
     * Get wire:click modifier string based on configuration.
     *
     * Examples:
     *   - No config: "" (empty)
     *   - Debounce 300: ".debounce.300ms"
     *   - Timeout 30: "" (handled via JS)
     */
    public function getWireClickModifiers(): string
    {
        $modifiers = '';

        if ($this->debounceMs) {
            $modifiers .= ".debounce.{$this->debounceMs}ms";
        }

        return $modifiers;
    }

    /**
     * Get loading state data for Blade templates.
     *
     * @return array<string, mixed>
     */
    public function getLoadingStateData(): array
    {
        return [
            'showLoading' => $this->showLoadingIndicator,
            'loadingText' => $this->loadingText,
            'debounceMs' => $this->debounceMs,
            'timeoutSeconds' => $this->timeoutSeconds,
            'wireModifiers' => $this->getWireClickModifiers(),
        ];
    }
}
