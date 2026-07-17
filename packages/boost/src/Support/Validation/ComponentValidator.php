<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Validation;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireBoost\Exceptions\ComponentBuildFailedException;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Table;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Builds a wire component and checks it against the canonical vocabularies —
 * the Color enum, the registered icon set, and the model's real attributes.
 *
 * These three failures share a property that makes them worth a dedicated tool:
 * none of them throws. An unknown color greys out (Color::resolve falls back to
 * gray), an attribute that does not exist renders an empty cell, and both sail
 * through a test that only asserts the component renders. They are exactly the
 * mistakes generated code makes and exactly the ones a green test suite hides.
 */
class ComponentValidator
{
    /**
     * Builder method => the builder class it must produce.
     *
     * @var array<string, class-string>
     */
    private const BUILDERS = [
        'table' => Table::class,
        'form' => Form::class,
        'infolist' => Infolist::class,
    ];

    public function __construct(private IconManager $icons) {}

    /**
     * Validate a Livewire component that builds a wire Table, Form or Infolist.
     *
     * @return array<string, mixed>
     */
    public function validate(string $componentClass): array
    {
        if (! class_exists($componentClass)) {
            throw UnresolvableComponentException::classMissing($componentClass);
        }

        $kind = $this->kind($componentClass);

        if ($kind === null) {
            throw UnresolvableComponentException::notAWireComponent($componentClass);
        }

        try {
            $built = $this->build($componentClass, $kind);
        } catch (ComponentBuildFailedException $e) {
            // Reporting problems is this tool's job, so a component that cannot
            // be built is a finding here rather than a failed call — unlike
            // every other tool, which needs a working component to say anything.
            return [
                'component' => $componentClass,
                'kind' => $kind,
                'ok' => false,
                'diagnostics' => [
                    Diagnostic::error('build-failed', $kind.'()', $e->getMessage())->toArray(),
                ],
            ];
        }

        [$builder, $components] = $built;

        $model = $this->model($builder);
        $introspector = $model instanceof Model ? new ModelIntrospector($model) : null;

        $diagnostics = [];

        foreach ($components as $group => $items) {
            foreach ($items as $component) {
                $target = $group.'.'.($this->name($component) ?: get_class($component));

                $diagnostics = array_merge(
                    $diagnostics,
                    $this->checkIcon($component, $target),
                    $this->checkColor($component, $target),
                    $this->checkAttribute($component, $target, $group, $introspector),
                );
            }
        }

        return array_filter([
            'component' => $componentClass,
            'kind' => $kind,
            'model' => $model instanceof Model ? $model::class : null,
            'checked' => $this->summary($components),
            'attributesChecked' => $introspector?->hasTable()
                ? $introspector->table()
                : 'skipped (no reachable database table)',
            'ok' => $diagnostics === [],
            'diagnostics' => array_map(
                static fn (Diagnostic $diagnostic): array => $diagnostic->toArray(),
                $diagnostics,
            ),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<int, Diagnostic>
     */
    private function checkIcon(object $component, string $target): array
    {
        $icon = $this->call($component, 'getIcon');

        if (! is_string($icon) || $icon === '' || $this->icons->has($icon)) {
            return [];
        }

        return [Diagnostic::error(
            'unknown-icon',
            $target,
            "Icon [{$icon}] is not registered with the IconManager.",
            $this->closest($icon, $this->icons->allNames()),
        )];
    }

    /**
     * @return array<int, Diagnostic>
     */
    private function checkColor(object $component, string $target): array
    {
        $color = $this->call($component, 'getColor');

        if (! is_string($color) || $color === '' || Color::tryResolve($color) instanceof Color) {
            return [];
        }

        return [Diagnostic::error(
            'unknown-color',
            $target,
            "Color [{$color}] is not a wire color; it will silently render as gray.",
            $this->closest($color, Color::values()),
        )];
    }

    /**
     * @return array<int, Diagnostic>
     */
    private function checkAttribute(object $component, string $target, string $group, ?ModelIntrospector $introspector): array
    {
        if (! $introspector instanceof ModelIntrospector || ! $introspector->hasTable()) {
            return [];
        }

        // Actions are named for what they do ("delete"), not for an attribute
        // they read, so their name is never a model attribute.
        if (in_array($group, ['actions', 'headerActions', 'bulkActions'], true)) {
            return [];
        }

        $name = $this->name($component);

        if ($name === '' || $this->hasCustomState($component)) {
            return [];
        }

        if ($introspector->resolves($name)) {
            return [];
        }

        return [Diagnostic::warning(
            'unknown-attribute',
            $target,
            "[{$introspector->table()}] has no attribute [{$name}]; it will render blank.",
            $introspector->suggestionsFor($name),
        )];
    }

    /**
     * Whether the component computes its own value, in which case its name need
     * not exist on the model at all.
     */
    private function hasCustomState(object $component): bool
    {
        foreach (['stateCallback', 'getStateUsing', 'formatCallback'] as $property) {
            if ($this->closureProperty($component, $property)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read a protected callback property. Reflection is the right tool here:
     * these are internals with no public probe, and introspecting internals is
     * this package's entire job.
     */
    private function closureProperty(object $component, string $property): bool
    {
        $reflection = new ReflectionClass($component);

        if (! $reflection->hasProperty($property)) {
            return false;
        }

        $target = $reflection->getProperty($property);

        return $target->isInitialized($component) && $target->getValue($component) instanceof Closure;
    }

    /**
     * @return array{0: object, 1: array<string, array<int, object>>}
     */
    private function build(string $componentClass, string $kind): array
    {
        $builderClass = self::BUILDERS[$kind];

        try {
            $component = app()->make($componentClass);

            /** @var object $builder */
            $builder = $component->{$kind}($builderClass::make());
        } catch (Throwable $e) {
            throw ComponentBuildFailedException::make($componentClass, $kind, $e);
        }

        // Outside the try: a failure in our own extraction is a bug in this
        // package, not something to blame on the component being inspected.
        return [$builder, $this->components($builder, $kind)];
    }

    /**
     * The schema of a built builder, grouped by role.
     *
     * @return array<string, array<int, object>>
     */
    private function components(object $builder, string $kind): array
    {
        if ($kind !== 'table') {
            return ['schema' => $this->flatten($this->callArray($builder, 'getSchema'))];
        }

        return [
            'columns' => $this->callArray($builder, 'getColumns'),
            'filters' => $this->callArray($builder, 'getFilters'),
            'actions' => $this->callArray($builder, 'getActions'),
            'headerActions' => $this->callArray($builder, 'getHeaderActions'),
            'bulkActions' => $this->callArray($builder, 'getBulkActions'),
        ];
    }

    /**
     * @param  array<int, object>  $schema
     * @return array<int, object>
     */
    private function flatten(array $schema): array
    {
        $flat = [];

        foreach ($schema as $component) {
            $children = method_exists($component, 'getSchema') ? $component->getSchema() : null;

            if (is_array($children) && $children !== []) {
                $flat = array_merge($flat, $this->flatten(array_values(array_filter($children, 'is_object'))));

                continue;
            }

            $flat[] = $component;
        }

        return $flat;
    }

    /**
     * The model behind a builder, or null — a form or infolist has no query, and
     * a table may be built from neither a model nor a query.
     */
    private function model(object $builder): ?Model
    {
        $query = $this->call($builder, 'getQuery');
        $model = is_object($query) && method_exists($query, 'getModel') ? $query->getModel() : null;

        return $model instanceof Model ? $model : null;
    }

    /**
     * Which builder this component declares, if any.
     *
     * The method's signature is checked, not just its name: an unrelated class
     * can easily have a `table()` that means something else entirely, and PHP
     * silently discards the extra argument, so a name-only test would "build"
     * it and report a confusing TypeError instead of "this is not a wire
     * component".
     */
    private function kind(string $componentClass): ?string
    {
        foreach (self::BUILDERS as $kind => $builderClass) {
            if (! class_exists($builderClass) || ! method_exists($componentClass, $kind)) {
                continue;
            }

            if ($this->builds($componentClass, $kind, $builderClass)) {
                return $kind;
            }
        }

        return null;
    }

    private function builds(string $componentClass, string $method, string $builderClass): bool
    {
        $reflection = new ReflectionMethod($componentClass, $method);
        $return = $reflection->getReturnType();

        if ($return instanceof ReflectionNamedType) {
            return ! $return->isBuiltin() && is_a($return->getName(), $builderClass, true);
        }

        // Untyped return: fall back to the parameter it accepts.
        $parameter = $reflection->getParameters()[0] ?? null;
        $type = $parameter?->getType();

        return $type instanceof ReflectionNamedType
            && ! $type->isBuiltin()
            && is_a($type->getName(), $builderClass, true);
    }

    /**
     * @param  array<string, array<int, object>>  $components
     * @return array<string, int>
     */
    private function summary(array $components): array
    {
        return array_filter(array_map('count', $components), static fn (int $count): bool => $count > 0);
    }

    /**
     * @param  array<int, string>  $vocabulary
     * @return array<int, string>
     */
    private function closest(string $value, array $vocabulary): array
    {
        $scored = [];

        foreach ($vocabulary as $candidate) {
            $distance = levenshtein(strtolower($value), strtolower($candidate));

            if ($distance <= max(2, (int) floor(strlen($value) / 3))) {
                $scored[$candidate] = $distance;
            }
        }

        asort($scored);

        return array_slice(array_keys($scored), 0, 3);
    }

    private function name(object $component): string
    {
        $name = $this->call($component, 'getName');

        return is_string($name) ? $name : '';
    }

    /**
     * @return array<int, object>
     */
    private function callArray(object $target, string $method): array
    {
        $value = $this->call($target, $method);

        return is_array($value) ? array_values(array_filter($value, 'is_object')) : [];
    }

    private function call(object $target, string $method): mixed
    {
        if (! method_exists($target, $method)) {
            return null;
        }

        try {
            return $target->{$method}();
        } catch (Throwable) {
            // A getter that needs a record (or a closure it cannot evaluate
            // without one) simply yields nothing to check.
            return null;
        }
    }
}
