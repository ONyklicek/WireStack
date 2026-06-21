<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Layout;

use NyonCode\WireCore\Foundation\Schema\Fieldset as BaseFieldset;

/**
 * Form fieldset layout with legend and column support.
 *
 * Thin subclass of the canonical {@see BaseFieldset} schema layout; it only
 * swaps the Blade view so form fieldsets keep their form-specific markup.
 * Shared column configuration lives in the core schema layout and is reused by
 * infolists.
 *
 * @deprecated v2.0 Use {@see BaseFieldset} directly.
 */
class Fieldset extends BaseFieldset
{
    protected function viewName(): string
    {
        return 'wire-forms::layouts.fieldset';
    }
}
