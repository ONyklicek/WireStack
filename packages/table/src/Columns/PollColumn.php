<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;

class PollColumn extends Column
{
    /** @var int|Closure Polling interval in milliseconds */
    protected int|Closure $interval = 2000;

    /** @var Closure|null Condition to stop polling (returns true when should stop) */
    protected ?Closure $stopWhen = null;

    /** @var Closure|null Condition to keep polling (returns true when should continue) */
    protected ?Closure $pollWhile = null;

    /** @var bool Whether to poll indefinitely */
    protected bool $pollForever = false;

    /** @var int|null Maximum number of poll cycles (null = unlimited) */
    protected ?int $maxPolls = null;

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
    protected ?Closure $onComplete = null;

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

    /** @var array<string, string> */
    protected array $colors = [];

    protected string $size = 'md';

    protected ?Closure $colorCallback = null;

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
    public function maxPolls(?int $max): static
    {
        $this->maxPolls = $max;

        return $this;
    }

    public function badge(bool $badge = true): static
    {
        $this->isBadge = $badge;

        return $this;
    }

    /**
     * @param  array<string, string>  $colors
     */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function colorUsing(Closure $callback): static
    {
        $this->colorCallback = $callback;

        return $this;
    }

    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

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
    public function onComplete(Closure $callback): static
    {
        $this->onComplete = $callback;

        return $this;
    }

    /**
     * Callback for when polling should complete.
     */
    public function handlePollComplete(Model $record): void
    {
        if ($this->onComplete) {
            ($this->onComplete)($record, $this);
        }
    }

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
     * Set icons for different states.
     *
     * @param  array<string, string>|Closure  $icons
     */
    public function stateIcons(array|Closure $icons): static
    {
        $this->stateIcons = $icons;

        return $this;
    }

    /**
     * Set colors for different states.
     *
     * @param  array<string, string>|Closure  $colors
     */
    public function stateColors(array|Closure $colors): static
    {
        $this->stateColors = $colors;

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

                return <<<HTML
                <div class="flex items-center gap-2">
                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: $percentage%"></div>
                    </div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">$percentage%</span>
                </div>
                HTML;
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

    protected function getSize(): string
    {
        return $this->size;
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
     * Evaluate a value that may be a Closure with record context.
     */
    protected function evaluateForRecord(mixed $value, Model $record): mixed
    {
        if ($value instanceof Closure) {
            return $value($record, $this);
        }

        return $value;
    }

    /**
     * Render the poll column cell.
     */
    public function renderCell(Model $record): string
    {
        if (! $this->canView()) {
            return '';
        }

        $shouldPoll = $this->shouldPoll($record);
        $state = $this->getCurrentState($record);
        $stateClasses = $this->getStateClasses($record, $state);
        $stateIconHtml = $this->renderStateIcon($record, $state);
        $color = $this->getColorForState($state);
        $colorClass = $color ? $this->getColorClass($color) : '';

        $colorClasses = $this->getColorClasses($color);
        $sizeClasses = $this->getSizeClasses();

        $content = $this->renderStateContent($record, $state);
        $loadingIndicator = $this->renderLoadingIndicator($record);

        // Build the cell content
        $position = $this->evaluateForRecord($this->loadingPosition, $record);

        $innerContent = '';
        if ($stateIconHtml) {
            $innerContent .= $stateIconHtml.' ';
        }
        $innerContent .= $content;

        // Wrap with state classes
        if ($this->isBadge()) {
            $transitionClasses = trim("inline-flex items-center $sizeClasses $colorClasses rounded-full font-medium");
        } else {
            $transitionClasses = $this->animateTransitions ? 'transition-all duration-300' : '';
        }

        $allClasses = implode(' ', array_filter([$stateClasses, $colorClass, $transitionClasses]));

        if ($allClasses) {
            $innerContent = '<span class="'.$allClasses.'">'.$innerContent.'</span>';
        }

        // If polling, wrap with wire:poll
        if ($shouldPoll) {
            $interval = $this->getInterval($record);
            $recordKey = $record->getKey();

            // Add loading indicator
            if ($this->showLoadingIndicator) {
                $loadingWrapper = '<span class="inline-flex items-center gap-0">'; // gap-1.5
                if ($position === 'before') {
                    $innerContent =
                        $loadingWrapper.
                        '<span wire:loading>'.
                        $loadingIndicator.
                        '</span>'.
                        $innerContent.
                        '</span>';
                } else {
                    $innerContent =
                        $loadingWrapper.
                        $innerContent.
                        '<span wire:loading class="ml-1">'.
                        $loadingIndicator.
                        '</span></span>';
                }
            }

            // Determine poll directive
            if ($this->rowLevelPolling) {
                // Row-level polling - refresh only this row's data
                $pollDirective = "wire:poll.{$interval}ms=\"refreshRow('$recordKey')\"";
            } else {
                // Component-level polling
                $pollDirective = "wire:poll.{$interval}ms";
            }

            if ($this->isBadge()) {
                return <<<HTML
                    <span $pollDirective wire:key="poll-$this->name-$recordKey" class="inline-flex items-center rounded-full font-medium">
                        {$innerContent}
                    </span>

                HTML;
            }

            return <<<HTML

            <div $pollDirective wire:key="poll-$this->name-$recordKey">
                {$innerContent}
            </div>
            HTML;
        }

        return $innerContent;
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

        // Default: use the column value as state
        $value = $this->getState($record);

        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        return null;
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

        $path = $this->getIconPath($icon);

        return '<svg class="w-4 h-4 inline-block '.
            $colorClass.
            '" fill="currentColor" viewBox="0 0 20 20">'.
            $path.
            '</svg>';
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

    public function getColorForState(mixed $state): string
    {
        if ($this->colorCallback) {
            return call_user_func($this->colorCallback, $state) ?? 'gray';
        }

        return $this->colors[$state] ?? 'gray';
    }

    public function getColorClasses(string $color): string
    {
        return match ($color) {
            'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
            'success',
            'green',
            'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'warning', 'yellow', 'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'danger', 'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'info', 'blue', 'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
            'secondary', 'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            'purple', 'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
            'pink' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
            'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            'teal' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
            'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public function getSizeClasses(): string
    {
        return match ($this->size) {
            'xs' => 'px-1.5 py-0.5 text-[10px]',
            'sm' => 'px-2 py-0.5 text-xs',
            'md' => 'px-2.5 py-1 text-xs',
            'lg' => 'px-3 py-1 text-sm',
            default => 'px-2.5 py-1 text-xs',
        };
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

        return <<<'HTML'
        <svg class="animate-spin w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        HTML;
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
