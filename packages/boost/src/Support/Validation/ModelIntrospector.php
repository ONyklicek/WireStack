<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Validation;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Answers "can this model actually produce a value for that name?".
 *
 * A column or field naming an attribute the model does not have is the single
 * most common way generated wire code fails, and it fails *quietly*: the cell
 * renders blank rather than throwing, so tests that only assert the page loads
 * stay green.
 *
 * Every check is deliberately permissive. A false "this does not exist" would
 * teach an agent to rewrite working code, which is worse than staying silent, so
 * anything that cannot be decided confidently resolves to true.
 */
class ModelIntrospector
{
    /** @var array<int, string>|null */
    private ?array $columns = null;

    /** @var array<int, string>|null */
    private ?array $attributes = null;

    public function __construct(private Model $model) {}

    /**
     * Whether the database table backing this model is reachable. Without it,
     * attribute checks are skipped rather than guessed at.
     */
    public function hasTable(): bool
    {
        return $this->columns() !== [];
    }

    public function table(): string
    {
        return $this->model->getTable();
    }

    /**
     * Real database columns, or [] when the table cannot be inspected (no
     * connection, no migration run yet).
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        try {
            $this->columns = Schema::connection($this->model->getConnectionName())
                ->getColumnListing($this->model->getTable());
        } catch (Throwable) {
            $this->columns = [];
        }

        return $this->columns;
    }

    /**
     * Every name the model can resolve without traversing a relation: real
     * columns, cast keys, appended accessors and accessor methods.
     *
     * @return array<int, string>
     */
    public function attributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }

        $this->attributes = array_values(array_unique(array_merge(
            $this->columns(),
            array_keys($this->model->getCasts()),
            $this->appends(),
            $this->accessors(),
        )));

        return $this->attributes;
    }

    /**
     * Whether a name — plain ("email") or a relation path ("author.name") —
     * can be resolved from this model.
     */
    public function resolves(string $name): bool
    {
        if ($name === '' || ! $this->hasTable()) {
            return true;
        }

        if (! str_contains($name, '.')) {
            return in_array($name, $this->attributes(), true);
        }

        [$first] = explode('.', $name, 2);

        // Only the first hop is checked. Verifying the whole chain means
        // instantiating each related model, and an aggregate tail
        // ("posts.count") or a JSON path ("meta.theme") is legitimate but
        // indistinguishable from a relation here — so a first-hop match is
        // treated as good enough.
        return $this->isRelation($first) || in_array($first, $this->attributes(), true);
    }

    /**
     * Names close enough to a typo to be worth suggesting.
     *
     * @return array<int, string>
     */
    public function suggestionsFor(string $name): array
    {
        $candidates = [];

        foreach ($this->attributes() as $attribute) {
            $distance = levenshtein(strtolower($name), strtolower($attribute));

            if ($distance <= max(2, (int) floor(strlen($name) / 3))) {
                $candidates[$attribute] = $distance;
            }
        }

        asort($candidates);

        return array_slice(array_keys($candidates), 0, 3);
    }

    public function isRelation(string $name): bool
    {
        if (! method_exists($this->model, $name)) {
            return false;
        }

        $method = new ReflectionMethod($this->model, $name);

        if ($method->getNumberOfRequiredParameters() > 0 || ! $method->isPublic()) {
            return false;
        }

        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            return is_a($type->getName(), Relation::class, true);
        }

        // Untyped relation methods still exist in the wild; calling one is the
        // only way to know, and a throwing method simply is not a relation.
        try {
            return $this->model->{$name}() instanceof Relation;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function appends(): array
    {
        // Every Eloquent model declares $appends (on the HasAttributes trait),
        // so this always resolves; the cast just keeps a hand-mangled value from
        // reaching array_filter.
        $value = (array) (new ReflectionClass($this->model))->getProperty('appends')->getValue($this->model);

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * Accessor-backed attributes, in both the `getFooAttribute()` and the
     * modern `foo(): Attribute` styles.
     *
     * @return array<int, string>
     */
    private function accessors(): array
    {
        $names = [];

        // Visibility is not filtered: Laravel resolves both `public
        // getFooAttribute()` and the documented `protected foo(): Attribute`,
        // so a public-only scan would report real accessors as missing.
        foreach ((new ReflectionClass($this->model))->getMethods() as $method) {
            $name = $method->getName();

            if (preg_match('/^get(.+)Attribute$/', $name, $matches) === 1) {
                $names[] = Str::snake($matches[1]);

                continue;
            }

            $type = $method->getReturnType();

            if ($type instanceof ReflectionNamedType && $type->getName() === Attribute::class) {
                $names[] = Str::snake($name);
            }
        }

        return $names;
    }
}
