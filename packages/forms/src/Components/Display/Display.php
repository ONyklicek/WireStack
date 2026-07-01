<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use NyonCode\WireCore\Foundation\Components\ViewComponent;

/**
 * Base class for non-input display components (Html, Placeholder, Alert,
 * ViewField).
 *
 * Display components render output-only markup and never bind to form state.
 * Grouping them under this canonical base lets tooling (e.g. wire-boost's
 * component catalog) discover them by short name the same way fields, columns
 * and filters are discovered.
 */
abstract class Display extends ViewComponent
{
    //
}
