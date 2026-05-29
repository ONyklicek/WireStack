<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Livewire;

use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Core\State\StateSerializer;
use NyonCode\WireTable\Concerns\TableStateSchema;

/**
 * Livewire property synthesizer for StateContainer.
 *
 * Handles the dehydration (StateContainer → JSON-safe array) and
 * hydration (JSON → StateContainer) of table state, as well as
 * wire:model dot-notation get/set operations.
 */
class TableStateSynthesizer extends Synth
{
    /** @var string */
    public static $key = 'tableState';

    /**
     * Match StateContainer instances for synthesis.
     *
     * @param  mixed  $target
     */
    public static function match($target): bool
    {
        return $target instanceof StateContainer;
    }

    private static function serializer(): StateSerializer
    {
        static $instance = null;

        return $instance ??= new StateSerializer;
    }

    /**
     * Dehydrate StateContainer into a JSON-safe array.
     *
     * @param  StateContainer  $target
     * @param  callable  $dehydrateChild
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function dehydrate($target, $dehydrateChild): array
    {
        $data = self::serializer()->serialize($target->all());

        foreach ($data as $key => $child) {
            $data[$key] = $dehydrateChild($key, $child);
        }

        return [$data, []];
    }

    /**
     * Hydrate a StateContainer from dehydrated data.
     *
     * @param  mixed  $value
     * @param  array<string, mixed>  $meta
     * @param  callable  $hydrateChild
     */
    public function hydrate($value, $meta, $hydrateChild): StateContainer
    {
        if (! is_array($value)) {
            return new StateContainer;
        }

        $serializer = self::serializer();
        $deserialized = $serializer->deserialize($value);

        // Strip keys not defined in the schema to reject corrupted or unexpected state.
        $knownKeys = array_keys(TableStateSchema::defaults());
        $deserialized = array_intersect_key($deserialized, array_flip($knownKeys));

        foreach ($deserialized as $key => $child) {
            $deserialized[$key] = $hydrateChild($key, $child);
        }

        $container = new StateContainer;
        $container->replaceClean($deserialized);

        return $container;
    }

    /**
     * Get a nested value from the StateContainer using dot-notation.
     *
     * @param  StateContainer  $target
     * @param  string  $key
     */
    public function get(&$target, $key): mixed
    {
        return $target->get($key);
    }

    /**
     * Set a nested value on the StateContainer using dot-notation.
     *
     * @param  StateContainer  $target
     * @param  string  $key
     * @param  mixed  $value
     */
    public function set(&$target, $key, $value): void
    {
        $target->set($key, $value);
    }
}
