<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireForms\Components\DateTimePicker;

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

    public static function builderHasNoTableLayout(string $name): self
    {
        return new self(
            "Builder [{$name}] cannot use table(): the table layout gives one schema ".
            'one column per field, and every builder item carries a different block\'s '.
            'schema, so there are no shared columns to head.'
        );
    }

    public static function blockIsNotRenderable(string $name): self
    {
        return new self(
            "Block [{$name}] is a Builder block definition and cannot be placed in a ".
            'schema directly. Pass it to Builder::make(…)->blocks([…]) instead.'
        );
    }

    public static function unknownBlock(string $name, string $builder): self
    {
        return new self(
            "Builder [{$builder}] has no block named [{$name}]. Check the stored ".
            'item type against the blocks the builder declares.'
        );
    }

    /**
     * A field that exists to be one mode was asked to become another.
     *
     * Thrown by the mode-locked picker facades (TimePicker, …), whose class name
     * is the promise the mode setter would otherwise break.
     */
    public static function fixedPickerMode(string $component, string $mode, string $attempted): self
    {
        return new self(
            "[{$component}] is locked to the [{$mode}] picker mode and cannot be switched to [{$attempted}]. "
            .'Use '.DateTimePicker::class.' when the mode has to vary.'
        );
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
