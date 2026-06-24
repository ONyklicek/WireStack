<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
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

    public function getTriggerIconHtml(): Htmlable
    {
        if (! $this->icon) {
            return new HtmlString('');
        }
        $size = $this->label ? 'w-4 h-4' : 'w-5 h-5';

        return new HtmlString($this->renderIconSvg($this->icon, $size));
    }

    public function getChevronSvg(): Htmlable
    {
        return new HtmlString($this->renderIconSvg('chevron-down', 'w-4 h-4'));
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
     * Tailwind transform-origin for the dropdown panel.
     *
     * Positioning itself is delegated to Floating UI (so the teleported panel
     * escapes the table's overflow context); only the scale transition origin
     * still needs a static class.
     */
    public function getDropdownOriginClass(): string
    {
        return match ($this->dropdownPosition) {
            'bottom-start' => 'origin-top-left',
            'top-start' => 'origin-bottom-left',
            'top-end' => 'origin-bottom-right',
            default => 'origin-top-right',
        };
    }

    /**
     * Config consumed by the `wireDropdown` Alpine component (Floating UI).
     *
     * Placement maps 1:1 to the fluent dropdownPosition() vocabulary; the offset
     * is the gap (px) between trigger and panel.
     *
     * @return array{placement: string, offset: int}
     */
    public function getDropdownConfig(): array
    {
        $placement = match ($this->dropdownPosition) {
            'bottom-start', 'bottom-end', 'top-start', 'top-end' => $this->dropdownPosition,
            default => 'bottom-end',
        };

        return ['placement' => $placement, 'offset' => 6];
    }

    /**
     * Get badge HTML for the trigger button.
     *
     * Colour resolves through the canonical soft badge palette so the group
     * trigger badge matches the header-action badge and every other pill.
     */
    public function getBadgeHtml(): Htmlable
    {
        if (! $this->hasBadge()) {
            return new HtmlString('');
        }

        return new HtmlString(view('wire-core::actions.partials.badge', [
            'count' => $this->getBadgeCount(),
            'color' => $this->getBadgeColor(),
        ])->render());
    }

    /**
     * Count only executable actions, ignoring dividers and nested empties.
     *
     * A group with one real action plus dividers should still collapse to a
     * single inline button rather than a dropdown.
     *
     * @param  array<int, Action|ActionGroup>  $items
     */
    public function countExecutableActions(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if ($item instanceof Action && $item->isDivider()) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Render the single visible action inline (used when the group collapses).
     */
    public function getSingleActionHtml(Model $record): Htmlable
    {
        foreach ($this->getVisibleActions($record) as $item) {
            if ($item instanceof Action && $item->isDivider()) {
                continue;
            }

            return new HtmlString($item->render($record));
        }

        return new HtmlString('');
    }

    /**
     * Render every dropdown menu item (actions + dividers) as one HTML fragment.
     *
     * This is the canonical owner of dropdown body markup. Auto-dividers
     * (divided()) and manual Action::divider() entries are resolved here so the
     * group views only emit {{ $group->getDropdownItemsHtml($record) }}.
     */
    public function getDropdownItemsHtml(Model $record): Htmlable
    {
        $html = '';

        foreach ($this->getVisibleActionsWithDividers($record) as $item) {
            $html .= $item instanceof self
                ? $item->render($record)
                : $item->renderForDropdown($record);
        }

        return new HtmlString($html);
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
