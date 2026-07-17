<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a form is asked to do something its definition does not support.
 *
 * Always an authoring mistake, caught at the moment the form is used rather
 * than left to fail obscurely mid-save.
 */
final class FormConfigurationException extends InvalidArgumentException implements WireException
{
    public static function noModel(): self
    {
        return new self('Form has no model configured. Call ->model() or ->using() before save().');
    }

    public static function unknownFormMethod(string $method, string $component): self
    {
        return new self("Form method [{$method}()] does not exist on ".$component);
    }

    public static function mixedFormMethods(string $component, string $method): self
    {
        return new self(
            'Component ['.$component.'] cannot have both form() and '.$method.'() methods. '
            .'Use either a single form() method or multiple *Form() methods, not both. '
            .'See ADR 0009 for details.'
        );
    }
}
