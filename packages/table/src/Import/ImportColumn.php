<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import;

use Closure;
use Illuminate\Support\Str;

/**
 * Maps one column of an imported file to a model attribute.
 *
 * A column matches a file header by its label, its name, or any {@see guess()}
 * alias (case-insensitive, trimmed). The raw cell value can be transformed with
 * {@see castStateUsing()} and gated with per-cell validation {@see rules()}.
 * {@see requiredMapping()} marks a header the file must contain.
 */
class ImportColumn
{
    protected string|Closure|null $label = null;

    protected bool $requiredMapping = false;

    /** @var array<int, mixed> */
    protected array $rules = [];

    protected ?Closure $castCallback = null;

    /** @var array<int, string> */
    protected array $guesses = [];

    final public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function requiredMapping(bool $condition = true): static
    {
        $this->requiredMapping = $condition;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function castStateUsing(Closure $callback): static
    {
        $this->castCallback = $callback;

        return $this;
    }

    /**
     * Alternative header names this column also matches.
     *
     * @param  array<int, string>  $names
     */
    public function guess(array $names): static
    {
        $this->guesses = $names;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        $label = $this->label instanceof Closure ? ($this->label)() : $this->label;

        return $label ?? Str::headline($this->name);
    }

    public function isRequiredMapping(): bool
    {
        return $this->requiredMapping;
    }

    /**
     * @return array<int, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return array<int, string>
     */
    public function getGuesses(): array
    {
        return $this->guesses;
    }

    /**
     * Locate the file header this column maps to, or null when absent.
     *
     * @param  array<int, string>  $headers  Header names as they appear in the file.
     */
    public function resolveHeader(array $headers): ?string
    {
        $candidates = array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            [$this->getLabel(), $this->name, ...$this->guesses],
        );

        foreach ($headers as $header) {
            if (in_array(mb_strtolower(trim($header)), $candidates, true)) {
                return $header;
            }
        }

        return null;
    }

    /**
     * Transform a raw cell value into the value stored on the model.
     */
    public function castState(mixed $value): mixed
    {
        if ($this->castCallback !== null) {
            return ($this->castCallback)($value);
        }

        return $value;
    }
}
