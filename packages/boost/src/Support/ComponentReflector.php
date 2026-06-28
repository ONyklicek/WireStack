<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use Illuminate\Support\Str;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Table;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
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
     * @return array{class: string, name: string, exists: bool, methods: array<int, array{name: string, signature: string, fluent: bool}>}
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

            $methods[] = [
                'name' => $name,
                'signature' => $name.'('.$this->parameters($method).')',
                'fluent' => $this->returnsSelf($method, $reflection),
            ];
        }

        usort($methods, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return [
            'class' => $class,
            'name' => Str::kebab($reflection->getShortName()),
            'exists' => true,
            'methods' => $methods,
        ];
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
     */
    private function describeBuilder(string $componentClass, string $builderClass, string $method, callable $extract): array
    {
        // Guards a host app that installs wire-boost without this builder's package.
        // @codeCoverageIgnoreStart
        if (! class_exists($builderClass)) {
            return ['component' => $componentClass, 'error' => "Package providing [{$builderClass}] is not installed."];
        }
        // @codeCoverageIgnoreEnd

        if (! class_exists($componentClass)) {
            return ['component' => $componentClass, 'error' => "Class [{$componentClass}] does not exist."];
        }

        if (! method_exists($componentClass, $method)) {
            return ['component' => $componentClass, 'error' => "Component does not define a [{$method}()] method."];
        }

        try {
            $component = app()->make($componentClass);
            /** @var object $builder */
            $builder = $component->{$method}($builderClass::make());

            return array_merge(['component' => $componentClass], $extract($builder));
        } catch (Throwable $e) {
            return ['component' => $componentClass, 'error' => $e->getMessage()];
        }
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
            $parts[] = $prefix.$variadic.'$'.$parameter->getName();
        }

        return implode(', ', $parts);
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
            return null;
        }
    }
}
