<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;

/**
 * Rating column — renders a numeric state as a row of filled/empty stars.
 *
 * The read-only table counterpart of the `Rating` form field, sharing its
 * vocabulary (`max()`, `allowHalf()`, `color()`). Editing a rating stays with
 * the form field; a table cell displays it.
 */
class RatingColumn extends Column
{
    protected int $max = 5;

    protected bool $allowHalf = false;

    protected ?string $ratingColor = 'warning';

    protected string $filledIcon = 'star';

    protected string $emptyIcon = 'outline:star';

    protected bool $showValue = false;

    /** Total number of stars/icons. */
    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    /** Render a half-filled star for a .5 value. */
    public function allowHalf(bool $condition = true): static
    {
        $this->allowHalf = $condition;

        return $this;
    }

    /** Set the color of the filled stars. */
    public function color(string|Color|null $color): static
    {
        $this->ratingColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    /** Set the icons used for a filled and an empty position. */
    public function icons(string|Icon $filled, string|Icon $empty): static
    {
        $this->filledIcon = $filled instanceof Icon ? $filled->value() : $filled;
        $this->emptyIcon = $empty instanceof Icon ? $empty->value() : $empty;

        return $this;
    }

    /** Also print the numeric value next to the stars. */
    public function showValue(bool $condition = true): static
    {
        $this->showValue = $condition;

        return $this;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function isAllowHalf(): bool
    {
        return $this->allowHalf;
    }

    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);

        if ($state === null || $state === '' || ! is_numeric($state)) {
            return $this->getEmptyCellText();
        }

        $rating = (float) $state;

        // §7: a rating is low-cardinality by construction (max+1 whole values, or
        // 2×max+1 with halves) — memoise the view render by its data so rows
        // sharing a rating reuse one render.
        return $this->renderViewCached('tables.columns.rating', [
            'starsHtml' => $this->starsHtml($rating),
            'displayValue' => $this->showValue ? $this->formatValue($state, $record) : '',
            'label' => __('wire-table::messages.rating_of_max', [
                'rating' => $this->allowHalf ? round($rating, 1) : (int) round($rating),
                'max' => $this->max,
            ]),
        ]);
    }

    /**
     * The star row as resolved SVG markup.
     *
     * A half position is drawn by clipping a filled star to half its width over
     * the empty one, so a half star needs no third icon in the set.
     */
    protected function starsHtml(float $rating): string
    {
        $icons = app(IconManager::class);
        $colorClass = self::getTextColorClasses($this->ratingColor ?? 'warning');
        $emptyClass = 'text-gray-300 dark:text-gray-600';

        $filled = $icons->render($this->filledIcon, 'w-4 h-4');
        $empty = $icons->render($this->emptyIcon, 'w-4 h-4');

        $html = '';

        for ($position = 1; $position <= $this->max; $position++) {
            $isHalf = $this->allowHalf && $rating > $position - 1 && $rating < $position;

            if ($rating >= $position) {
                $html .= '<span class="'.$colorClass.'">'.$filled.'</span>';
            } elseif ($isHalf) {
                $html .= '<span class="relative inline-flex">'
                    .'<span class="'.$emptyClass.'">'.$empty.'</span>'
                    .'<span class="absolute inset-y-0 left-0 w-1/2 overflow-hidden '.$colorClass.'">'.$filled.'</span>'
                    .'</span>';
            } else {
                $html .= '<span class="'.$emptyClass.'">'.$empty.'</span>';
            }
        }

        return $html;
    }
}
