<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\Repeater;

/**
 * A form's state on its way out: every field's own dehydration, then its
 * owner's `dehydrateStateUsing()`.
 *
 * The canonical owner of the write-path transform, because a form's state
 * leaves through more than one door. {@see SaveHandler} writes it to a record;
 * an action modal hands it to a callback instead, and for a long time that door
 * applied nothing — a cleared `Select` reached the callback as `''`, a
 * `DateTimePicker` as the raw wire value, a `FileUpload` as a pending upload
 * that was never moved. Both doors ask this class now, so a field behaves the
 * same whichever one its value goes through.
 *
 * It also owns the walk that decides *which* components key the payload, since
 * dehydration is defined over exactly that set.
 *
 * @internal This class is not part of the public API.
 */
final class StateDehydrator
{
    /**
     * Apply every field's dehydration to a state bag.
     *
     * Callers pass the original state, never the result of an earlier call —
     * the contract {@see DehydratesState} documents, and what lets a host
     * dehydrate more than once (the table validates against a dehydrated value
     * before it opens its transaction) without a transform compounding.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, mixed>  $schema
     * @return array<string, mixed>
     */
    public function dehydrate(array $data, array $schema, ?Model $record = null): array
    {
        foreach ($this->payloadComponents($schema) as $component) {
            if (! $component instanceof Field) {
                continue;
            }

            $name = $component->getName();

            if (! array_key_exists($name, $data)) {
                continue;
            }

            $data[$name] = $this->dehydrateValue($component, $data[$name], $record);
        }

        // Repeater children: a child that shapes its own state (FileUpload storing
        // its upload, DateTimePicker applying format/timezone) or carries an
        // owner's dehydrateStateUsing() lives under the repeater key as an array
        // of items, so the top-level pass never reaches it. Without this a nested
        // file is never moved to permanent storage and a nested date keeps its raw
        // wire value.
        foreach ($this->payloadRepeaters($schema) as $repeater) {
            $name = $repeater->getName();

            if (! isset($data[$name]) || ! is_array($data[$name])) {
                continue;
            }

            $childFields = array_filter(
                $this->payloadComponents($repeater->getSchema()),
                static fn (Field|Repeater $child): bool => $child instanceof Field,
            );

            foreach ($data[$name] as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ($childFields as $child) {
                    $childName = $child->getName();

                    if (array_key_exists($childName, $item)) {
                        $data[$name][$index][$childName] = $this->dehydrateValue($child, $item[$childName], $record);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Every component that keys the top-level payload: fields anywhere in the
     * layout tree, plus repeaters themselves.
     *
     * A repeater is a leaf here. Its children are keyed inside its own array
     * value, never at the top level, so flattening them in would match a child's
     * name against a parent column that happens to share it.
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, Field|Repeater>
     */
    public function payloadComponents(array $schema): array
    {
        /** @var array<int, Field|Repeater> $components */
        $components = [];

        foreach ($schema as $component) {
            if ($component instanceof Repeater) {
                $components[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $components = array_merge($components, $this->payloadComponents($component->getSchema()));
            } elseif ($component instanceof Field) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Repeaters anywhere in the schema (used to dehydrate their child fields).
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, Repeater>
     */
    private function payloadRepeaters(array $schema): array
    {
        return array_values(array_filter(
            $this->payloadComponents($schema),
            static fn (Field|Repeater $component): bool => $component instanceof Repeater,
        ));
    }

    /**
     * One field's value on the way out: the field's own transform first, the
     * owner's callback last.
     *
     * That order is the point of having both. The field type states how its
     * value is stored at all — an upload moved to permanent storage, a date in
     * its storage format and timezone — and the owner then shapes the value that
     * is actually about to be written, rather than racing the field type over a
     * raw wire value it would have replaced anyway.
     */
    private function dehydrateValue(Field $field, mixed $value, ?Model $record): mixed
    {
        if ($field instanceof DehydratesState) {
            $value = $field->dehydrateState($value, $record);
        }

        return $field->applyStateDehydration($value, $record);
    }
}
