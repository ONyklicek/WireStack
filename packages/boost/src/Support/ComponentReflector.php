<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use BackedEnum;
use Illuminate\Support\Str;
use NyonCode\WireBoost\Exceptions\ComponentBuildFailedException;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Table;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * Reflects wireStack building blocks: the fluent API of a component type, and
 * the resolved schema (columns / filters / actions / fields / entries) of a
 * Livewire component that builds a wire Table, Form or Infolist.
 */
class ComponentReflector
{
    /**
     * Methods omitted from fluent API listings (base, magic, render plumbing).
     *
     * @var array<int, string>
     */
    private const METHOD_DENYLIST = [
        'make', 'toHtml', 'render', 'getView', 'viewName', 'getRuntime',
        'toArray', 'jsonSerialize', 'resolve', 'evaluate',
    ];

    /**
     * Describe the public, fluent API surface of a component type.
     *
     * @return array{class: string, name: string, exists: bool, summary?: string, methods: array<int, array<string, mixed>>}
     */
    public function describeType(string $class): array
    {
        if (! class_exists($class)) {
            return ['class' => $class, 'name' => Str::kebab(class_basename($class)), 'exists' => false, 'methods' => []];
        }

        $reflection = new ReflectionClass($class);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if ($method->isStatic() || $method->isAbstract() || $method->isConstructor()) {
                continue;
            }

            if (str_starts_with($name, '__') || in_array($name, self::METHOD_DENYLIST, true)) {
                continue;
            }

            $methods[] = array_filter([
                'name' => $name,
                'signature' => $name.'('.$this->parameters($method).')',
                'fluent' => $this->returnsSelf($method, $reflection),
                'summary' => $this->docSummary($method->getDocComment()),
                // A bare `color(string $color)` tells an agent the type but not
                // the vocabulary, which is the part it actually has to guess.
                'accepts' => $this->accepts($method),
            ], static fn (mixed $value): bool => $value !== null && $value !== []);
        }

        usort($methods, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return array_filter([
            'class' => $class,
            'name' => Str::kebab($reflection->getShortName()),
            'exists' => true,
            'summary' => $this->docSummary($reflection->getDocComment()),
            'methods' => $methods,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * The closed vocabularies a method's parameters accept, keyed by parameter.
     *
     * @return array<string, array<int, string>>
     */
    private function accepts(ReflectionMethod $method): array
    {
        $accepts = [];

        foreach ($method->getParameters() as $parameter) {
            $values = [];

            foreach ($this->typesOf($parameter->getType()) as $type) {
                $values = array_merge($values, $this->enumValues($type));
            }

            if ($values !== []) {
                $accepts[$parameter->getName()] = array_values(array_unique($values));
            }
        }

        return $accepts;
    }

    /**
     * The backing values of a backed enum, or [] for anything else.
     *
     * @return array<int, string>
     */
    private function enumValues(ReflectionNamedType $type): array
    {
        if ($type->isBuiltin() || ! enum_exists($type->getName())) {
            return [];
        }

        $values = [];

        foreach (($type->getName())::cases() as $case) {
            if ($case instanceof BackedEnum) {
                $values[] = (string) $case->value;
            }
        }

        return $values;
    }

    /**
     * Flatten a possibly-union type into its named parts.
     *
     * @return array<int, ReflectionNamedType>
     */
    private function typesOf(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type];
        }

        if ($type instanceof ReflectionUnionType) {
            return array_values(array_filter(
                $type->getTypes(),
                static fn (ReflectionType $part): bool => $part instanceof ReflectionNamedType,
            ));
        }

        return [];
    }

    /**
     * The first prose line of a doc-block — enough to say what a method is for
     * without shipping the whole comment.
     */
    private function docSummary(string|false $doc): ?string
    {
        if (! is_string($doc)) {
            return null;
        }

        foreach (preg_split('/\R/', $doc) ?: [] as $raw) {
            $line = trim(ltrim(trim($raw), '/* '));

            if ($line !== '' && ! str_starts_with($line, '@') && ! str_starts_with($line, '/')) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Resolve the columns, filters and actions of a wire-table component.
     *
     * @return array<string, mixed>
     */
    public function describeTable(string $componentClass): array
    {
        return $this->describeBuilder(
            $componentClass,
            Table::class,
            'table',
            function (object $table): array {
                return [
                    'searchable' => $this->callBool($table, 'isSearchable'),
                    'defaultSort' => $this->callValue($table, 'getDefaultSort'),
                    'defaultSortDirection' => $this->callValue($table, 'getDefaultSortDirection'),
                    'columns' => $this->mapComponents($this->callArray($table, 'getColumns')),
                    'filters' => $this->mapComponents($this->callArray($table, 'getFilters')),
                    'headerActions' => $this->mapComponents($this->callArray($table, 'getHeaderActions')),
                    'actions' => $this->mapComponents($this->callArray($table, 'getActions')),
                    'bulkActions' => $this->mapComponents($this->callArray($table, 'getBulkActions')),
                ];
            }
        );
    }

    /**
     * Resolve the (flattened) field schema of a wire-forms component.
     *
     * @return array<string, mixed>
     */
    public function describeForm(string $componentClass): array
    {
        return $this->describeBuilder(
            $componentClass,
            Form::class,
            'form',
            fn (object $form): array => [
                'fields' => $this->flattenSchema($this->callArray($form, 'getSchema')),
            ]
        );
    }

    /**
     * Resolve the (flattened) entry schema of a wire-core infolist component.
     *
     * @return array<string, mixed>
     */
    public function describeInfolist(string $componentClass): array
    {
        return $this->describeBuilder(
            $componentClass,
            Infolist::class,
            'infolist',
            fn (object $infolist): array => [
                'entries' => $this->flattenSchema($this->callArray($infolist, 'getSchema')),
            ]
        );
    }

    /**
     * Shared driver: instantiate the component, build the schema object via its
     * `table()`/`form()` method and hand it to an extractor.
     *
     * @param  callable(object): array<string, mixed>  $extract
     * @return array<string, mixed>
     *
     * @throws UnresolvableComponentException when there is nothing to describe
     * @throws ComponentBuildFailedException when the component's own code throws
     */
    private function describeBuilder(string $componentClass, string $builderClass, string $method, callable $extract): array
    {
        // Guards a host app that installs wire-boost without this builder's package.
        // @codeCoverageIgnoreStart
        if (! class_exists($builderClass)) {
            throw UnresolvableComponentException::packageMissing($builderClass);
        }
        // @codeCoverageIgnoreEnd

        if (! class_exists($componentClass)) {
            throw UnresolvableComponentException::classMissing($componentClass);
        }

        if (! method_exists($componentClass, $method)) {
            throw UnresolvableComponentException::notAWireComponent($componentClass);
        }

        try {
            $component = app()->make($componentClass);
            /** @var object $builder */
            $builder = $component->{$method}($builderClass::make());
        } catch (Throwable $e) {
            // The application's own failure, kept whole as `previous`.
            throw ComponentBuildFailedException::make($componentClass, $method, $e);
        }

        return array_merge(['component' => $componentClass], $extract($builder));
    }

    /**
     * @param  array<int, mixed>  $components
     * @return array<int, array{name: string, label: string, type: string, sortable?: bool, searchable?: bool}>
     */
    private function mapComponents(array $components): array
    {
        $mapped = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            $entry = [
                'name' => $this->callString($component, 'getName'),
                'label' => $this->callString($component, 'getLabel'),
                'type' => class_basename($component),
            ];

            if (method_exists($component, 'isSortable')) {
                $entry['sortable'] = $this->callBool($component, 'isSortable');
            }

            if (method_exists($component, 'isSearchable')) {
                $entry['searchable'] = $this->callBool($component, 'isSearchable');
            }

            $mapped[] = $entry;
        }

        return $mapped;
    }

    /**
     * Recursively flatten a form schema into a flat list of fields, recording
     * the layout component that wraps each one.
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, array{name: string, label: string, type: string, container?: string}>
     */
    private function flattenSchema(array $schema, ?string $container = null): array
    {
        $fields = [];

        foreach ($schema as $component) {
            if (! is_object($component)) {
                continue;
            }

            $children = method_exists($component, 'getSchema') ? $component->getSchema() : null;

            if (is_array($children) && $children !== []) {
                $fields = array_merge($fields, $this->flattenSchema($children, class_basename($component)));

                continue;
            }

            $field = [
                'name' => $this->callString($component, 'getName'),
                'label' => $this->callString($component, 'getLabel'),
                'type' => class_basename($component),
            ];

            if ($container !== null) {
                $field['container'] = $container;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    private function parameters(ReflectionMethod $method): string
    {
        $parts = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $prefix = $type instanceof ReflectionNamedType || $type instanceof ReflectionUnionType
                ? $this->typeName($type).' '
                : '';
            $variadic = $parameter->isVariadic() ? '...' : '';

            $parts[] = $prefix.$variadic.'$'.$parameter->getName().$this->defaultValue($parameter);
        }

        return implode(', ', $parts);
    }

    /**
     * A parameter's default, rendered as it would be written in PHP. Whether an
     * argument is optional — and what happens when it is omitted — is part of
     * the API an agent is trying to read off the signature.
     */
    private function defaultValue(ReflectionParameter $parameter): string
    {
        if (! $parameter->isDefaultValueAvailable()) {
            return '';
        }

        $default = $parameter->getDefaultValue();

        if ($default instanceof BackedEnum) {
            return ' = '.class_basename($default).'::'.$default->name;
        }

        return ' = '.match (true) {
            is_null($default) => 'null',
            is_bool($default) => $default ? 'true' : 'false',
            is_string($default) => "'".$default."'",
            is_array($default) => $default === [] ? '[]' : '[...]',
            is_scalar($default) => (string) $default,
            default => '?',
        };
    }

    private function typeName(ReflectionNamedType|ReflectionUnionType $type): string
    {
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn (ReflectionNamedType $t): string => $this->typeName($t),
                array_filter($type->getTypes(), static fn (ReflectionType $t): bool => $t instanceof ReflectionNamedType),
            ));
        }

        $name = class_basename($type->getName());

        return $type->allowsNull() && $type->getName() !== 'null' ? '?'.$name : $name;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function returnsSelf(ReflectionMethod $method, ReflectionClass $reflection): bool
    {
        $type = $method->getReturnType();

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        return in_array($type->getName(), ['static', 'self', $reflection->getName()], true);
    }

    private function callString(object $target, string $method): string
    {
        $value = $this->callValue($target, $method);

        return is_scalar($value) ? (string) $value : '';
    }

    private function callBool(object $target, string $method): bool
    {
        return (bool) $this->callValue($target, $method);
    }

    /**
     * @return array<int, mixed>
     */
    private function callArray(object $target, string $method): array
    {
        $value = $this->callValue($target, $method);

        return is_array($value) ? array_values($value) : [];
    }

    private function callValue(object $target, string $method): mixed
    {
        if (! method_exists($target, $method)) {
            return null;
        }

        try {
            return $target->{$method}();
        } catch (Throwable) {
            // A probe, not a call that must succeed: plenty of getters need a
            // record (or a closure they cannot evaluate without one), and a
            // getter that will not answer simply contributes nothing to the
            // description. The component's own failures are raised by
            // describeBuilder(), which is the part that must not stay quiet.
            return null;
        }
    }
}
