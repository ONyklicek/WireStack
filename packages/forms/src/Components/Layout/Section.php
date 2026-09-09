<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Layout;

use NyonCode\WireCore\Foundation\Schema\Section as BaseSection;

/**
 * Form section layout.
 *
 * Thin subclass of the canonical {@see BaseSection} schema layout; it only
 * swaps the Blade view so form sections keep their form-specific chrome. The
 * shared configuration (heading, description, icon, columns, collapsible,
 * aside) lives in the core schema layout and is reused by infolists too.
 *
 * @deprecated v2.0 Use {@see BaseSection} directly.
 */
class Section extends BaseSection
{
    protected function viewName(): string
    {
        return 'wire-forms::layouts.section';
    }
}
