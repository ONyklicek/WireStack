<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Concerns\HasColor;
use NyonCode\WireCore\Actions\Concerns\HasIcons;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Class ActionGroup - Enhanced with dividers, nested groups, badges, and auto-dividers.
 *
 * Usage:
 *   ActionGroup::make([
 *       EditAction::make(),
 *       ViewAction::make(),
 *       Action::divider(),              // manual divider
 *       DeleteAction::make(),
 *   ])
 *   ->divided(true)                     // auto-insert dividers between each action
 *   ->badge(fn () => 3)                 // badge count on trigger
 *   ->badgeColor('danger')
 *   ->dropdownWidth('w-56')
 *   ->dropdownPosition('bottom-end')
 *
 * @author Ondřej Nyklíček
 *
 * @phpstan-consistent-constructor
 */
class ActionGroup implements Htmlable
{
    use HasColor;
    use HasIcons;

    /** @var array<int, Action|ActionGroup> */
    public array $actions = [];

    public ?string $label = null;

    public ?string $icon = 'dots-vertical';

    protected ?string $color = Color::Gray->value;

    protected ?string $size = 'sm';

    public ?string $tooltip = null;

    public bool $divided = false;

    public string $dropdownPosition = 'bottom-end';

    public string $dropdownWidth = 'w-48';

    // Badge
    protected int|Closure|null $badge = null;

    protected ?string $badgeColor = null;

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     */
    public function __construct(array $actions)
    {
        $this->actions = $actions;
    }

    /**
     * @param  array<int, Action|ActionGroup>  $actions
     */
    public static function make(array $actions): static
    {
        return new static($actions);
    }

    // ─── Fluent setters ─────────────────────────────────────────

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

    public function size(?string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function tooltip(?string $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function dropdownPosition(string $position): static
    {
        $this->dropdownPosition = $position;

        return $this;
    }

    public function dropdownWidth(string $width): static
    {
        $this->dropdownWidth = $width;

        return $this;
    }

    /**
     * Auto-insert dividers between each action.
     */
    public function divided(bool $divided = true): static
    {
        $this->divided = $divided;

        return $this;
    }

    /**
     * Set a badge count on the dropdown trigger button.
     *
     * @param  int|Closure|null  $count  Static count or Closure returning int
     */
    public function badge(int|Closure|null $count): static
    {
        $this->badge = $count;

        return $this;
    }

    /**
     * Set badge color.
     */
    public function badgeColor(string|Color|null $color): static
    {
        $this->badgeColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    // ─── Getters ────────────────────────────────────────────────

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColor(): string
    {
        return $this->color ?? Color::Gray->value;
    }

    public function getSize(): string
    {
        return $this->size ?? 'sm';
    }

    public function getTooltip(): ?string
    {
        return $this->tooltip;
    }

    public function isDivided(): bool
    {
        return $this->divided;
    }

    public function getDropdownPositionValue(): string
    {
        return $this->dropdownPosition;
    }

    public function getDropdownWidth(): string
    {
        return $this->dropdownWidth;
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    public function getBadgeCount(): ?int
    {
        if ($this->badge === null) {
            return null;
        }

        return $this->badge instanceof Closure
            ? ($this->badge)()
            : $this->badge;
    }

    public function getBadgeColor(): string
    {
        return $this->badgeColor ?? Color::Danger->value;
    }

    public function hasBadge(): bool
    {
        $count = $this->getBadgeCount();

        return $count !== null && $count > 0;
    }

    /**
     * Get visible actions for a record.
     * Filters out hidden actions but preserves dividers.
     */
    /**
     * @return array<int, Action|ActionGroup>
     */
    public function getVisibleActions(Model $record): array
    {
        $visible = [];

        foreach ($this->actions as $action) {
            if ($action instanceof Action && $action->isDivider()) {
                $visible[] = $action;
            } elseif ($action instanceof Action) {
                if ($action->canExecute($record)) {
                    $visible[] = $action;
                }
            } else {
                $visible[] = $action;
            }
        }

        // Remove leading/trailing dividers and consecutive dividers
        return $this->cleanDividers($visible);
    }

    /**
     * Get visible actions with auto-dividers inserted if $this->divided is true.
     */
    /**
     * @return array<int, Action|ActionGroup>
     */
    public function getVisibleActionsWithDividers(Model $record): array
    {
        $visible = $this->getVisibleActions($record);

        if (! $this->divided || count($visible) <= 1) {
            return $visible;
        }

        // Insert dividers between non-divider actions
        $result = [];
        $lastWasAction = false;

        foreach ($visible as $action) {
            $isDivider = $action instanceof Action && $action->isDivider();

            if ($lastWasAction && ! $isDivider) {
                $result[] = Action::divider();
            }

            $result[] = $action;
            $lastWasAction = ! $isDivider;
        }

        return $result;
    }

    /**
     * Remove orphaned dividers (leading, trailing, consecutive).
     */
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    protected function cleanDividers(array $actions): array
    {
        // Remove leading dividers
        while (! empty($actions) && ($first = reset($actions)) instanceof Action && $first->isDivider()) {
            array_shift($actions);
        }

        // Remove trailing dividers
        while (! empty($actions) && ($last = end($actions)) instanceof Action && $last->isDivider()) {
            array_pop($actions);
        }

        // Remove consecutive dividers
        $cleaned = [];
        $lastWasDivider = false;

        foreach ($actions as $action) {
            $isDivider = $action instanceof Action && $action->isDivider();

            if ($isDivider && $lastWasDivider) {
                continue;
            }

            $cleaned[] = $action;
            $lastWasDivider = $isDivider;
        }

        return $cleaned;
    }

    // ─── Rendering helpers ──────────────────────────────────────

    public function getTriggerClasses(): string
    {
        $base = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800';
        $sizeClasses = $this->label
            ? match ($this->getSize()) {
                'xs' => 'px-2 py-1 text-xs gap-1', 'sm' => 'px-2.5 py-1.5 text-sm gap-1.5',
                'md' => 'px-3 py-2 text-sm gap-2', 'lg' => 'px-4 py-2.5 text-base gap-2',
                default => 'px-2.5 py-1.5 text-sm gap-1.5',
            }
        : match ($this->getSize()) {
            'xs' => 'p-1', 'sm' => 'p-1.5', 'md' => 'p-2', 'lg' => 'p-2.5', default => 'p-1.5',
        };
        $colorClasses = $this->getIconButtonColorClasses();

        return "{$base} {$sizeClasses} {$colorClasses}";
    }

    public function getTriggerIconHtml(): string
    {
        if (! $this->icon) {
            return '';
        }
        $size = $this->label ? 'w-4 h-4' : 'w-5 h-5';

        return $this->renderIconSvg($this->icon, $size);
    }

    public function getChevronSvg(): string
    {
        return $this->renderIconSvg('chevron-down', 'w-4 h-4');
    }

    public function getDropdownPositionClasses(): string
    {
        return match ($this->dropdownPosition) {
            'bottom-start' => 'left-0 origin-top-left',
            'bottom-end' => 'right-0 origin-top-right',
            'top-start' => 'left-0 bottom-full origin-bottom-left',
            'top-end' => 'right-0 bottom-full origin-bottom-right',
            default => 'right-0 origin-top-right',
        };
    }

    /**
     * Get badge HTML for the trigger button.
     */
    public function getBadgeHtml(): string
    {
        if (! $this->hasBadge()) {
            return '';
        }

        $count = $this->getBadgeCount();
        $colorClasses = match ($this->getBadgeColor()) {
            'primary', 'blue' => 'bg-primary-500 text-white',
            'danger', 'red' => 'bg-red-500 text-white',
            'success', 'green' => 'bg-emerald-500 text-white',
            'warning', 'yellow' => 'bg-amber-500 text-white',
            default => 'bg-red-500 text-white',
        };

        return '<span class="absolute -top-1 -right-1 inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold rounded-full '.$colorClasses.'">'
            .($count > 99 ? '99+' : $count)
            .'</span>';
    }

    public function render(Model $record): string
    {
        $visible = $this->getVisibleActions($record);
        if (empty($visible)) {
            return '';
        }

        return view('wire-table::tables.actions.action-group', [
            'group' => $this,
            'record' => $record,
        ])->render();
    }

    public function toHtml(): string
    {
        return '';
    }
}
