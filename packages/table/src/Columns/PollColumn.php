<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithStateColor;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireCore\Foundation\View\Primitives;
use NyonCode\WireTable\Concerns\EvaluatesRecordClosures;
use NyonCode\WireTable\Concerns\RendersBadgeSurface;

class PollColumn extends Column
{
    use EvaluatesRecordClosures;
    use InteractsWithStateColor;
    use RendersBadgeSurface;

    /** @var int|Closure Polling interval in milliseconds */
    protected int|Closure $interval = 2000;

    /** @var Closure|null Condition to stop polling (returns true when should stop) */
    protected ?Closure $stopWhen = null;

    /** @var Closure|null Condition to keep polling (returns true when should continue) */
    protected ?Closure $pollWhile = null;

    /** @var bool Whether to poll indefinitely */
    protected bool $pollForever = false;

    /** @var int|null Maximum number of poll cycles (null = unlimited) */
    /** @var array<string, Closure> State-based display callbacks */
    protected array $stateDisplays = [];

    /** @var Closure|null Callback to determine current state name */
    protected ?Closure $stateResolver = null;

    /** @var string|Closure|null Default state when no state matches */
    protected string|Closure|null $defaultState = null;

    /** @var bool Whether to show loading indicator while polling */
    protected bool $showLoadingIndicator = true;

    /** @var string|Closure|null Loading indicator position (before, after) */
    protected string|Closure|null $loadingPosition = 'after';

    /** @var string|Closure|null Custom loading indicator HTML */
    protected string|Closure|null $loadingIndicator = null;

    /** @var Closure|null Callback when polling completes */
    /** @var bool Whether to keep last content visible while loading */
    protected bool $keepContentWhileLoading = true;

    /** @var array<string, string>|Closure CSS classes for different states */
    protected array|Closure $stateClasses = [];

    /** @var array<string, string>|Closure Icons for different states */
    protected array|Closure $stateIcons = [];

    /** @var array<string, string>|Closure Colors for different states */
    protected array|Closure $stateColors = [];

    /** @var bool Whether to animate state transitions */
    protected bool $animateTransitions = true;

    /** @var string|null Method to call for refresh (default:) */
    protected ?string $refreshMethod = null;

    /** @var bool Only poll for specific row, not whole table */
    protected bool $rowLevelPolling = true;

    protected bool $isBadge = false;

    // $size is provided by Foundation\Concerns\HasSize (via Column).

    /**
     * Set the polling interval in milliseconds.
     */
    public function interval(int|Closure $milliseconds): static
    {
        $this->interval = $milliseconds;

        return $this;
    }

    /**
     * Set the polling interval in seconds.
     */
    public function intervalSeconds(int|Closure $seconds): static
    {
        $this->interval = $seconds instanceof Closure ? fn ($record) => $seconds($record) * 1000 : $seconds * 1000;

        return $this;
    }

    /**
     * Poll indefinitely (until component is destroyed).
     */
    public function pollForever(bool $forever = true): static
    {
        $this->pollForever = $forever;

        return $this;
    }

    /**
     * Set maximum number of poll cycles.
     */
    public function badge(bool $badge = true): static
    {
        $this->isBadge = $badge;

        return $this;
    }

    // colors()/colorUsing()/getColorForState() come from InteractsWithStateColor.
    // size()/getSize() come from Foundation\Concerns\HasSize (via Column).

    /**
     * Stop when status reaches a final state.
     *
     * @param  array<int, string>  $completeStates
     */
    public function stopOnComplete(
        string $statusColumn = 'status',
        array $completeStates = ['completed', 'failed', 'cancelled'],
    ): static {
        return $this->stopWhen(fn (Model $record) => in_array($record->{$statusColumn}, $completeStates));
    }

    /**
     * Stop polling when condition is met.
     */
    public function stopWhen(Closure $callback): static
    {
        $this->stopWhen = $callback;

        return $this;
    }

    /**
     * Define multiple state displays at once.
     *
     * @param  array<string, Closure>  $displays
     */
    public function stateDisplays(array $displays): static
    {
        foreach ($displays as $state => $display) {
            $this->stateDisplays[$state] = $display;
        }

        return $this;
    }

    /**
     * Set the default state display.
     */
    public function defaultState(string|Closure $content): static
    {
        $this->defaultState = $content;

        return $this;
    }

    /**
     * Set CSS classes for different states.
     *
     * @param  array<string, string>|Closure  $classes
     */
    public function stateClasses(array|Closure $classes): static
    {
        $this->stateClasses = $classes;

        return $this;
    }

    /**
     * Configure loading indicator.
     */
    public function loadingIndicator(
        bool $show = true,
        string|Closure|null $position = 'after',
        string|Closure|null $customHtml = null,
    ): static {
        $this->showLoadingIndicator = $show;
        $this->loadingPosition = $position;
        $this->loadingIndicator = $customHtml;

        return $this;
    }

    /**
     * Hide loading indicator.
     */
    public function withoutLoadingIndicator(): static
    {
        $this->showLoadingIndicator = false;

        return $this;
    }

    /**
     * Set whether to keep content visible while loading.
     */
    public function keepContentWhileLoading(bool $keep = true): static
    {
        $this->keepContentWhileLoading = $keep;

        return $this;
    }

    /**
     * Enable/disable transition animations.
     */
    public function animateTransitions(bool $animate = true): static
    {
        $this->animateTransitions = $animate;

        return $this;
    }

    /**
     * Set custom refresh method.
     */
    public function refreshMethod(string $method): static
    {
        $this->refreshMethod = $method;

        return $this;
    }

    /**
     * Enable row-level polling (only refresh this row).
     */
    public function rowLevelPolling(bool $enabled = true): static
    {
        $this->rowLevelPolling = $enabled;

        return $this;
    }

    /**
     * Set callback when polling completes.
     */
    /**
     * Configure for job/task status polling.
     */
    public function forJobStatus(string $statusColumn = 'status'): static
    {
        return $this->resolveStateUsing(fn (Model $record) => $record->{$statusColumn})
            ->pollWhilePending($statusColumn)
            ->stateColors([
                'pending' => 'warning',
                'processing' => 'info',
                'completed' => 'success',
                'failed' => 'danger',
                'cancelled' => 'secondary',
            ])
            ->stateIcons([
                'pending' => 'clock',
                'processing' => 'refresh',
                'completed' => 'check-circle',
                'failed' => 'x-circle',
                'cancelled' => 'x',
            ]);
    }

    /**
     * Set the icon shown for each state value as a state => icon map.
     *
     * @param  array<string, string|Icon>|Closure  $icons
     */
    public function stateIcons(array|Closure $icons): static
    {
        $this->stateIcons = is_array($icons)
            ? array_map(
                static fn (string|Icon $icon): string => $icon instanceof Icon ? $icon->value() : $icon,
                $icons,
            )
            : $icons;

        return $this;
    }

    /**
     * Set the color shown for each state value as a state => color map.
     *
     * @param  array<string, string|Color>|Closure  $colors
     */
    public function stateColors(array|Closure $colors): static
    {
        $this->stateColors = is_array($colors)
            ? array_map(
                static fn (string|Color $color): string => $color instanceof Color ? $color->value : $color,
                $colors,
            )
            : $colors;

        return $this;
    }

    /**
     * Poll while status is "pending" or "processing".
     *
     * @param  array<int, string>  $pendingStates
     */
    public function pollWhilePending(
        string $statusColumn = 'status',
        array $pendingStates = ['pending', 'processing'],
    ): static {
        return $this->pollWhile(fn (Model $record) => in_array($record->{$statusColumn}, $pendingStates));
    }

    /**
     * Keep polling while condition is true.
     */
    public function pollWhile(Closure $callback): static
    {
        $this->pollWhile = $callback;

        return $this;
    }

    /**
     * Set the state resolver callback.
     */
    public function resolveStateUsing(Closure $callback): static
    {
        $this->stateResolver = $callback;

        return $this;
    }

    /**
     * Configure for progress tracking.
     */
    public function forProgress(string $progressColumn = 'progress', int $completeValue = 100): static
    {
        return $this->pollWhile(fn (Model $record) => ($record->{$progressColumn} ?? 0) < $completeValue)
            ->displayForState('progress', function (Model $record) use ($progressColumn, $completeValue) {
                $progress = $record->{$progressColumn} ?? 0;
                $percentage = min(100, ($progress / $completeValue) * 100);

                return $this->renderView('tables.columns.partials.progress', [
                    'percentage' => $percentage,
                ]);
            })
            ->html();
    }

    /**
     * Define display for a specific state.
     */
    public function displayForState(string $state, Closure $display): static
    {
        $this->stateDisplays[$state] = $display;

        return $this;
    }

    /**
     * Render content for current state.
     */
    protected function renderStateContent(Model $record, ?string $state): string
    {
        // Check for state-specific display
        if ($state !== null && isset($this->stateDisplays[$state])) {
            $content = $this->stateDisplays[$state]($record, $this);

            return $this->html ? (string) $content : e((string) $content);
        }

        // Check for default state
        if ($this->defaultState !== null) {
            $content = $this->evaluateForRecord($this->defaultState, $record);

            return $this->html ? (string) $content : e((string) $content);
        }

        // Fall back to parent rendering
        return parent::renderCell($record);
    }

    /**
     * Render the poll column cell.
     */
    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $shouldPoll = $this->shouldPoll($record);
        $state = $this->getCurrentState($record);
        $stateClasses = $this->getStateClasses($record, $state);
        // The scalar state keys the class/icon/content maps, but it has already
        // collapsed an enum cast to its value. Colour resolution gets the raw
        // state instead, so an enum can still answer through its own HasColor
        // contract the way BadgeColumn and IconColumn let it.
        $color = $this->getColorForState($this->getStateForColor($record, $state));
        $colorClass = $color ? $this->getColorClass($color) : '';

        // Wrap with state classes
        if ($this->isBadge()) {
            $transitionClasses = trim('inline-flex items-center '.$this->getSizeClasses().' '.$this->getColorClasses($color).' rounded-full font-medium');
        } else {
            $transitionClasses = $this->animateTransitions ? 'transition-all duration-300' : '';
        }

        $allClasses = implode(' ', array_filter([$stateClasses, $colorClass, $transitionClasses]));

        // Determine poll directive / wire:key (only relevant while polling)
        $pollDirective = '';
        $wireKey = '';
        if ($shouldPoll) {
            $interval = $this->getInterval($record);
            $recordKey = $record->getKey();
            // refreshMethod() names the host method a row-level poll calls;
            // refreshRow() is the WithTable default.
            $method = $this->refreshMethod ?? 'refreshRow';
            $pollDirective = $this->rowLevelPolling
                ? "wire:poll.{$interval}ms=\"{$method}('$recordKey')\""
                : "wire:poll.{$interval}ms";
            $wireKey = "poll-{$this->name}-{$recordKey}";
        }

        return $this->renderView('tables.columns.poll', [
            'stateIconHtml' => $this->renderStateIcon($record, $state),
            'content' => $this->renderStateContent($record, $state),
            'allClasses' => $allClasses,
            'shouldPoll' => $shouldPoll,
            'isBadge' => $this->isBadge(),
            'showLoadingIndicator' => $shouldPoll && $this->showLoadingIndicator,
            'keepContentWhileLoading' => $this->keepContentWhileLoading,
            'loadingIndicator' => $this->renderLoadingIndicator($record),
            'position' => $this->evaluateForRecord($this->loadingPosition, $record),
            'pollDirective' => $pollDirective,
            'wireKey' => $wireKey,
        ]);
    }

    /**
     * Check if polling should be active for this record.
     */
    protected function shouldPoll(Model $record): bool
    {
        if ($this->pollForever) {
            return true;
        }

        if ($this->stopWhen !== null) {
            return ! ($this->stopWhen)($record, $this);
        }

        if ($this->pollWhile !== null) {
            return (bool) ($this->pollWhile)($record, $this);
        }

        return false;
    }

    /**
     * Get the current state name for a record.
     */
    protected function getCurrentState(Model $record): ?string
    {
        if ($this->stateResolver !== null) {
            return ($this->stateResolver)($record, $this);
        }

        // Default: use the column value as state (enum casts collapse to a scalar).
        $value = EnumResolver::scalar($this->getState($record));

        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * The value colour resolution should see.
     *
     * A stateResolver defines the state itself, so its output is authoritative.
     * Otherwise the raw attribute is, because getCurrentState() has flattened
     * any enum away and an enum may carry its own colour.
     */
    protected function getStateForColor(Model $record, ?string $state): mixed
    {
        return $this->stateResolver !== null ? $state : $this->getState($record);
    }

    /**
     * Get classes for current state.
     */
    protected function getStateClasses(Model $record, ?string $state): string
    {
        $classes =
            $this->stateClasses instanceof Closure
                ? ($this->stateClasses)($record, $state, $this)
                : $this->stateClasses;

        return $classes[$state] ?? '';
    }

    /**
     * Render state-specific icon.
     */
    protected function renderStateIcon(Model $record, ?string $state): string
    {
        $icon = $this->getStateIcon($record, $state);
        if (! $icon) {
            return '';
        }

        $color = $this->getStateColor($record, $state);
        $colorClass = $color ? $this->getColorClass($color) : 'text-gray-400';

        return app(IconManager::class)->render($icon, 'w-4 h-4 inline-block', $colorClass);
    }

    /**
     * Get icon for current state.
     */
    protected function getStateIcon(Model $record, ?string $state): ?string
    {
        $icons = $this->stateIcons instanceof Closure ? ($this->stateIcons)($record, $state, $this) : $this->stateIcons;

        return $icons[$state] ?? null;
    }

    /**
     * Get color for current state.
     */
    protected function getStateColor(Model $record, ?string $state): ?string
    {
        $colors =
            $this->stateColors instanceof Closure ? ($this->stateColors)($record, $state, $this) : $this->stateColors;

        return $colors[$state] ?? null;
    }

    /**
     * Render the loading indicator.
     */
    protected function renderLoadingIndicator(Model $record): string
    {
        if (! $this->showLoadingIndicator) {
            return '';
        }

        $customIndicator = $this->evaluateForRecord($this->loadingIndicator, $record);
        if ($customIndicator) {
            return (string) $customIndicator;
        }

        return app(Primitives::class)->spinner('w-4 h-4 text-gray-400');
    }

    private function isBadge(): bool
    {
        return $this->isBadge;
    }

    /**
     * Get the polling interval for a record.
     */
    protected function getInterval(Model $record): int
    {
        return (int) $this->evaluateForRecord($this->interval, $record);
    }
}
