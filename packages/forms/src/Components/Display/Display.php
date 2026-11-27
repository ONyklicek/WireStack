<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use NyonCode\WireCore\Foundation\Components\ViewComponent;
use NyonCode\WireCore\Foundation\Concerns\HasContent;
use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;
use NyonCode\WireForms\Concerns\InteractsWithFormState;

/**
 * Base class for non-input display components (Html, Placeholder, Alert,
 * ViewField).
 *
 * Display components render output-only markup and never bind to form state.
 * Grouping them under this canonical base lets tooling (e.g. wire-boost's
 * component catalog) discover them by short name the same way fields, columns
 * and filters are discovered.
 *
 * They still *read* it. A placeholder that previews what the user typed
 * (`content(fn ($get) => …)`) or hides until a toggle is on
 * (`visible(fn ($get) => …)`) needs the same `$get` / `$set` / `$state` a field's
 * closures get, resolved against the same bag — so it takes them from the same
 * owner, {@see InteractsWithFormState}, rather than a second resolver.
 */
abstract class Display extends ViewComponent implements HasStateAccessors
{
    use HasContent;
    use InteractsWithFormState;
}
