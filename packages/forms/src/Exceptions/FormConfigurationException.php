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

    /**
     * A count, length or step that has to be at least one, given as zero or less.
     *
     * Refused rather than rendered, because every one of these has a shape that
     * looks like a working field and is not: `Rating::max(0)` draws no stars,
     * `OtpInput::length(0)` draws no boxes, `Slider::step(0)` is rejected by the
     * browser and freezes the thumb. The field renders, submits nothing, and the
     * developer is left looking at CSS.
     */
    public static function boundMustBePositive(string $component, string $method, int|float $given): self
    {
        return new self(
            "[{$component}] was given {$method}({$given}), but that value has to be at least 1. "
            .'Zero or a negative count produces a field that renders and cannot be used.'
        );
    }

    /**
     * A length or count constraint given as a negative number.
     *
     * Distinct from the above because zero is meaningful here: `minItems(0)` and
     * `maxItems(0)` both say something (no floor, and an empty-only field), while
     * a negative bound becomes a validation rule — `min:-1` — that Laravel accepts
     * and nothing can ever fail.
     */
    public static function boundCannotBeNegative(string $component, string $method, int|float $given): self
    {
        return new self(
            "[{$component}] was given {$method}({$given}), but a negative bound is not a "
            .'constraint: it becomes a validation rule every value satisfies. Use 0 for '
            .'"no limit at this end", or null to remove the rule entirely.'
        );
    }

    /**
     * A minimum above its maximum.
     *
     * The pair is checked from whichever setter is called second, so the order
     * they are written in does not matter. Refused because the two become
     * validation rules that contradict each other — `min:10|max:2` is a field no
     * input can satisfy, and the first person to find out is whoever fills the
     * form in.
     */
    public static function invertedBounds(
        string $component,
        string $lowerMethod,
        int|float $lower,
        string $upperMethod,
        int|float $upper,
    ): self {
        return new self(
            "[{$component}] was given {$lowerMethod}({$lower}) and {$upperMethod}({$upper}), "
            ."but {$lowerMethod} cannot exceed {$upperMethod}: together they describe a value "
            .'that cannot exist, so every submission fails validation.'
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
