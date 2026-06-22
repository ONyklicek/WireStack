<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\State;

use BackedEnum;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use Stringable;
use UnitEnum;

/**
 * Serializes and deserializes state for Livewire wire transfer.
 *
 * Converts complex objects into primitive representations suitable
 * for JSON transport and restores them on the way back.
 */
final class StateSerializer
{
    /**
     * Prepare state array for dehydration (convert objects to primitives).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function serialize(array $state): array
    {
        $serialized = [];

        foreach ($state as $key => $value) {
            $serialized[$key] = $this->serializeValue($value);
        }

        return $serialized;
    }

    /**
     * Restore state from hydrated data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function deserialize(array $data): array
    {
        $deserialized = [];

        foreach ($data as $key => $value) {
            $deserialized[$key] = $this->deserializeValue($value);
        }

        return $deserialized;
    }

    /**
     * Serialize a single value to a wire-safe representation.
     */
    public function serializeValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.up');
        }

        if ($value instanceof UnitEnum) {
            // Backed and unit enums collapse to their canonical scalar form via the
            // single owner so serialization stays in lockstep with display surfaces.
            return EnumResolver::scalar($value);
        }

        if ($value instanceof Jsonable) {
            return json_decode($value->toJson(), true);
        }

        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        if ($value instanceof Arrayable) {
            return $this->serialize($value->toArray());
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            return $this->serialize($value);
        }

        if (is_object($value)) {
            return (array) $value;
        }

        return $value;
    }

    /**
     * Deserialize a single value, optionally using a type hint for restoration.
     */
    public function deserializeValue(mixed $value, ?string $type = null): mixed
    {
        if ($value === null || is_scalar($value)) {
            if ($type === null) {
                return $value;
            }

            return $this->castToType($value, $type);
        }

        if (is_array($value)) {
            return $this->deserialize($value);
        }

        return $value;
    }

    /**
     * Cast a scalar value to the given type.
     */
    private function castToType(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'date', 'datetime', Carbon::class, DateTimeInterface::class => Carbon::parse((string) $value),
            default => $this->castToEnum($value, $type),
        };
    }

    /**
     * Attempt to cast a value to a backed enum.
     */
    private function castToEnum(mixed $value, string $type): mixed
    {
        if (! enum_exists($type)) {
            return $value;
        }

        if (is_subclass_of($type, BackedEnum::class)) {
            if (! is_int($value) && ! is_string($value)) {
                return $value;
            }

            return $type::tryFrom($value) ?? $value;
        }

        // Unit enum - match by name
        foreach ($type::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        return $value;
    }
}
