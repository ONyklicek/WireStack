<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Concerns\CanBeLive;
use NyonCode\WireCore\Foundation\Concerns\CanBeReadOnly;
use NyonCode\WireCore\Foundation\Concerns\HasDebounce;
use NyonCode\WireCore\Foundation\Concerns\HasPlaceholder;
use NyonCode\WireCore\Foundation\Concerns\HasPrefixAndSuffix;
use NyonCode\WireCore\Foundation\Concerns\HasTooltip;
use NyonCode\WireForms\Concerns\CanBeAutofocused;
use NyonCode\WireForms\Concerns\HasFormValidation;
use NyonCode\WireForms\Contracts\HasValidation;

/**
 * Base class for all wire-forms input field components.
 *
 * Extends core Component with validation, live bindings,
 * prefix/suffix, placeholder, read-only, and autofocus.
 */
abstract class Field extends Component implements HasValidation
{
    use CanBeAutofocused;
    use CanBeLive;
    use CanBeReadOnly;
    use HasDebounce;
    use HasFormValidation;
    use HasPlaceholder;
    use HasPrefixAndSuffix;
    use HasTooltip;
}
