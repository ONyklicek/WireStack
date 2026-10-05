<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

use Illuminate\Contracts\Support\Htmlable;

/**
 * A settings group that shows something beside its fields.
 *
 * A rate is easier to set when you can see what it pays; a number format is
 * easier to write when you can see the number it makes. Neither is a field — it
 * is a reading of the fields — so it belongs to the group, rendered from the
 * state the form holds right now:
 *
 *   public static function screenExtension(array $data): ?Htmlable
 *   {
 *       return new HtmlString(e(OfferNumber::preview($data['format'] ?? '')));
 *   }
 *
 * Rendered under the form on every render of the page, so a `live()` field the
 * extension reads redraws it as the value changes. It reads the state and never
 * writes it: saving is the form's.
 */
interface ExtendsSettingsScreen
{
    /**
     * What to show under the form, from its current state; null for nothing.
     *
     * @param  array<string, mixed>  $data
     */
    public static function screenExtension(array $data): ?Htmlable;
}
