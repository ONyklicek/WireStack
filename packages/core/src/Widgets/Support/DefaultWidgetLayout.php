<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Support;

use NyonCode\WireCore\Widgets\Widget;

/**
 * Turns a dashboard's default layout — what somebody sees before they arrange
 * anything — into a {@see WidgetLayout}.
 *
 * Without one, a customisable dashboard places everything it declares. That is
 * right for a handful of widgets and wrong for a catalogue: a dashboard that
 * offers twenty widgets in its tray should not put all twenty in front of a
 * newcomer, and which few belong there usually depends on who is looking — the
 * test bench wants its queue, the office wants the money. A default layout
 * says which, and everything else declared starts in the tray.
 *
 * ## The spec
 *
 * A list, in the order the widgets should appear:
 *
 *     ['revenue', 'orders' => [2, 1], 'queue' => 'L']
 *
 * - a bare key places the widget at the size it arrives at from the tray
 *   (its first offered size), the same as adding it by hand would;
 * - `key => [width, height]` asks for a size, snapped to the nearest one the
 *   widget offers on this grid — a default cannot give a widget a size its own
 *   declaration refuses;
 * - `key => 'L'` names one of the widget's named sizes; a name it does not
 *   declare falls back to its arrival size rather than to nothing.
 *
 * A key the dashboard does not declare is dropped, like a stale key in a stored
 * layout: the default is written once and the widgets change under it.
 */
final class DefaultWidgetLayout
{
    /**
     * @param  array<int|string, mixed>  $spec
     * @param  array<int, Widget>  $widgets  The declared widgets, keys stamped
     * @param  int  $columns  The grid they are laid out in
     */
    public static function resolve(array $spec, array $widgets, int $columns): WidgetLayout
    {
        $byKey = [];

        foreach ($widgets as $widget) {
            $key = $widget->getKey();

            if ($key !== null) {
                $byKey[$key] = $widget;
            }
        }

        $placements = [];

        foreach ($spec as $key => $size) {
            // A bare key is a list entry: the key is the value.
            [$key, $size] = is_int($key) ? [$size, null] : [$key, $size];

            $widget = is_string($key) ? ($byKey[$key] ?? null) : null;

            if ($widget === null) {
                continue;
            }

            [$width, $height] = self::sizeFor($widget, $size, $columns);

            $placements[] = ['key' => $key, 'w' => $width, 'h' => $height];
        }

        return WidgetLayout::of($placements);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function sizeFor(Widget $widget, mixed $size, int $columns): array
    {
        $offer = WidgetSizeOffer::for($widget, $columns);

        if (is_array($size) && is_int($size[0] ?? null) && is_int($size[1] ?? null)) {
            return $offer->nearest($size[0], $size[1]);
        }

        if (is_string($size)) {
            foreach ($offer->named() as $named) {
                if ($named['label'] === $size) {
                    return [$named['width'], $named['height']];
                }
            }
        }

        return $offer->arrivalSize();
    }
}
