<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Extra HTML attributes for the component's own input element.
 *
 * Deliberately separate from {@see HasExtraAttributes}, which every component
 * gets: that one describes the outer element, which everything has. An *input*
 * is not universal — a Placeholder, an Alert, a widget and an infolist entry
 * have none, and a Radio or CheckboxList has one per option, with no single
 * element the attributes could mean.
 *
 * So this is mixed in only where exactly one element carries the field's value.
 * It lived on the shared concern until 2026-08-01, which put it on 49 types and
 * implemented it on none — a fluent setter that silently did nothing everywhere.
 */
trait HasExtraInputAttributes
{
    /** @var array<string, mixed>|Closure */
    protected array|Closure $extraInputAttributes = [];

    /**
     * Set extra HTML attributes merged onto the field's input element.
     *
     * @param  array<string, mixed>|Closure  $attributes
     */
    public function extraInputAttributes(array|Closure $attributes): static
    {
        $this->extraInputAttributes = $attributes;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraInputAttributes(): array
    {
        return $this->evaluate($this->extraInputAttributes) ?? [];
    }

    /**
     * The attributes as a ready-to-echo HTML fragment, escaped.
     *
     * Resolved in PHP so each view echoes one string rather than repeating the
     * same loop, and so escaping cannot be forgotten in one of them.
     */
    public function getExtraInputAttributesHtml(): string
    {
        $out = [];

        foreach ($this->getExtraInputAttributes() as $attribute => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $out[] = $value === true
                ? e((string) $attribute)
                : e((string) $attribute).'="'.e((string) $value).'"';
        }

        return implode(' ', $out);
    }
}
