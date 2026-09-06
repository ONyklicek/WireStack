<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Runtime;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\Repeater;

/**
 * Applies every field's own dehydration (ADR 0021) to a bag of form data — the
 * write-path counterpart of the state hydration that fills the bag.
 *
 * This is what turns a widget's state into the value an app persists: a
 * cleared Select's `''` into `null`, a DateTimePicker's wire value into its
 * storage format and zone, a FileUpload's pending upload into a stored path.
 *
 * It is a canonical owner rather than a private helper because the schema walk
 * has more than one host. `Form::save()` runs it through {@see SaveHandler};
 * an action modal runs it on submit, before the action callback sees `$data`.
 * A host that skipped it would silently disagree with the other about what a
 * cleared field means — the same schema would write `null` through one path
 * and `''` through the other.
 */
final class StateDehydrator
{
    /**
     * Dehydrate every field in the schema that declares the contract, leaving
     * keys the schema does not name untouched.
     *
     * @param  array<int, mixed>  $schema
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function dehydrate(array $schema, array $data, ?Model $record = null): array
    {
        // Top-level fields (not nested inside a repeater).
        foreach (self::dehydratingFields($schema) as $field) {
            $name = $field->getName();

            if (! array_key_exists($name, $data)) {
                continue;
            }

            $data[$name] = $field->dehydrateState($data[$name], $record);
        }

        // Repeater children: a DehydratesState child (FileUpload storing its
        // upload, DateTimePicker applying format/timezone) lives under the
        // repeater key as an array of items, so the top-level pass never reaches
        // it. Without this a nested file is never moved to permanent storage and a
        // nested date keeps its raw wire value.
        foreach (self::dehydratingRepeaters($schema) as $repeater) {
            $name = $repeater->getName();

            if (! isset($data[$name]) || ! is_array($data[$name])) {
                continue;
            }

            $childFields = self::dehydratingFields($repeater->getSchema());

            foreach ($data[$name] as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ($childFields as $child) {
                    $childName = $child->getName();

                    if (array_key_exists($childName, $item)) {
                        $data[$name][$index][$childName] = $child->dehydrateState($item[$childName], $record);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Every field that dehydrates its own state, traversing nested layouts.
     *
     * Only a Field carries the name that keys the data array — a layout could
     * implement the contract without one.
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, Field&DehydratesState>
     */
    private static function dehydratingFields(array $schema): array
    {
        $fields = [];

        foreach ($schema as $component) {
            if ($component instanceof DehydratesState && $component instanceof Field) {
                $fields[] = $component;
            } elseif ($component instanceof LayoutComponent && ! $component instanceof Repeater) {
                // Repeaters are handled per-item by dehydratingRepeaters(); their
                // children must not be flattened into the top-level key match.
                $fields = array_merge($fields, self::dehydratingFields($component->getSchema()));
            }
        }

        return $fields;
    }

    /**
     * Repeaters anywhere in the schema (used to dehydrate their child fields).
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, Repeater>
     */
    private static function dehydratingRepeaters(array $schema): array
    {
        $repeaters = [];

        foreach ($schema as $component) {
            if ($component instanceof Repeater) {
                $repeaters[] = $component;
            } elseif ($component instanceof LayoutComponent) {
                $repeaters = array_merge($repeaters, self::dehydratingRepeaters($component->getSchema()));
            }
        }

        return $repeaters;
    }
}
