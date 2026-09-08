<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a widget is handed a data series it cannot render.
 *
 * Split out of `InvalidChartDataException` when the items surface stopped being
 * a chart feature: `Widgets\Concerns\HasWidgetItems` is the one owner of "a
 * list of things, or a closure resolving one", and three widget families now
 * use it. An exception named after charts thrown by a list
 * of recent orders would be a name that lies.
 *
 * `InvalidArgumentException` is deliberately the base, which is also what
 * `InvalidChartDataException` extends — so the `catch (InvalidArgumentException)`
 * a bar chart's caller already had keeps working.
 */
final class InvalidWidgetDataException extends InvalidArgumentException implements WireException
{
    /**
     * @param  string  $widget  the widget class that was handed the series
     * @param  string  $expected  the item class every entry has to be
     */
    public static function notItems(string $widget, string $expected): self
    {
        return new self(class_basename($widget).'::items() expects an array of '.$expected.' instances.');
    }
}
