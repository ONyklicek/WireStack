<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\State;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Hydrates state from Livewire request data.
 *
 * Converts raw request values into properly typed state
 * based on type definitions provided by the component.
 */
final class StateHydrator
{
    /**
     * Convert request data into a typed state array.
     *
     * @param  array<string, mixed>  $requestData
     * @param  array<string, string>  $stateDefinitions  Map of path => type hint
     * @return array<string, mixed>
     */
    public function hydrate(array $requestData, array $stateDefinitions = []): array
    {
        $state = [];

        foreach ($requestData as $key => $value) {
            $type = $stateDefinitions[$key] ?? null;

            $state[$key] = $type !== null
                ? $this->hydrateValue($value, $type)
                : $value;
        }

        return $state;
    }

    /**
     * Convert a single value based on a type hint.
     */
    public function hydrateValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'array' => (array) $value,
            'date', 'datetime', Carbon::class, DateTimeInterface::class => $this->hydrateDate($value),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    /**
     * Hydrate a date value from string or timestamp.
     */
    private function hydrateDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
