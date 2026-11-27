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

    /**
     * The groups whose items are actions: named for what they do, never for a
     * model attribute, and each a possible owner of a modal form.
     */
    private const ACTION_GROUPS = ['actions', 'headerActions', 'bulkActions', 'pageHeaderActions', 'pageActions'];

    public function __construct(private IconManager $icons, private ActionInspector $actions) {}

    /**
     * Validate a Livewire component that builds a wire Table, Form or Infolist,
     * or hosts actions of its own (a panel page, a `WithActions` component).
     *
     * @param  mixed  $record  A record key for a record page (view, edit): set
     *                         on the component's `$record` before anything is
     *                         built, the way the page's own mount would.
     * @param  string|null  $action  Check only this action — its icon, color and
     *                               the form its modal opens.
     * @return array<string, mixed>
     */
    public function validate(string $componentClass, mixed $record = null, ?string $action = null): array
    {
        if (! class_exists($componentClass)) {
            throw UnresolvableComponentException::classMissing($componentClass);
        }

        $kinds = $this->kinds($componentClass);
        $hostsActions = $this->actions->hostsActions($componentClass);

        if ($kinds === [] && ! $hostsActions) {
            throw UnresolvableComponentException::notAWireComponent($componentClass);
        }

        $kind = $kinds[0] ?? 'actions';

        try {
            $component = app()->make($componentClass);
        } catch (Throwable $e) {
            return $this->failed($componentClass, $kind, [
                Diagnostic::error('build-failed', $componentClass, $e->getMessage()),
            ]);
        }

        $diagnostics = $record !== null ? $this->assignRecord($component, $record) : [];

        // Each builder on its own: a form that calls a service while it builds
        // fails, and that is one finding, not a reason to say nothing about the
        // table and the actions beside it.
        $components = [];
        $tableGroups = [];
        $builderFailures = [];
        $model = null;

        foreach ($kinds as $builderKind) {
            try {
                $builder = $this->build($component, $componentClass, $builderKind);
            } catch (ComponentBuildFailedException $e) {
                $builderFailures[] = Diagnostic::error('build-failed', $builderKind.'()', $e->getMessage());

                continue;
            }

            $groups = $this->components($builder, $builderKind, $builderKind === $kind);
            $components = array_merge($components, $groups);

            if ($builderKind === 'table') {
                $tableGroups = array_keys($groups);
                $model ??= $this->model($builder);
            }
        }

        if ($hostsActions) {
            [$hostGroups, $hostDiagnostics] = $this->actions->hostActions($component);
            $components = array_merge($components, $hostGroups);
            $diagnostics = array_merge($diagnostics, $hostDiagnostics);
        }

        foreach (self::ACTION_GROUPS as $group) {
            if (isset($components[$group])) {
                $components[$group] = $this->actions->expand($components[$group]);
            }
        }

        if ($action !== null) {
            [$components, $missing] = $this->onlyAction($components, $action);
            $diagnostics = array_merge($diagnostics, $missing);
        }

        // A builder that would not build is only beside the point once the
        // action asked about has been found without it — it may be where that
        // action was declared.
        if ($action === null || $components === []) {
            $diagnostics = array_merge($builderFailures, $diagnostics);
        }

        $introspector = $model instanceof Model ? new ModelIntrospector($model) : null;

        foreach ($components as $group => $items) {
            foreach ($items as $item) {
                $target = $group.'.'.($this->name($item) ?: get_class($item));

                $diagnostics = array_merge(
                    $diagnostics,
                    $this->checkIcon($item, $target),
                    $this->checkColor($item, $target),
                    // Attribute names mean something only against the table's
                    // own model: a form field may be a confirmation nobody
                    // stores, and an action is named for what it does.
                    in_array($group, $tableGroups, true) && ! in_array($group, self::ACTION_GROUPS, true)
                        ? $this->checkAttribute($item, $target, $introspector)
                        : [],
                );
            }
        }

        [$actionForms, $formDiagnostics] = $this->checkActionForms($components, $component);
        $diagnostics = array_merge($diagnostics, $formDiagnostics);

        if ($components === [] && $actionForms === [] && $diagnostics !== []) {
            return $this->failed($componentClass, $kind, $diagnostics);
        }

        return array_filter([
            'component' => $componentClass,
            'kind' => $kind,
            'model' => $model instanceof Model ? $model::class : null,
            'record' => $record,
            'action' => $action,
            'checked' => $this->summary($components) ?: null,
            'actionForms' => $actionForms !== [] ? $actionForms : null,
            'attributesChecked' => $tableGroups === []
                ? null
                : ($introspector?->hasTable() ? $introspector->table() : 'skipped (no reachable database table)'),
            'ok' => $diagnostics === [],
            'diagnostics' => array_map(
                static fn (Diagnostic $diagnostic): array => $diagnostic->toArray(),
                $diagnostics,
            ),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Nothing could be inspected at all — only what went wrong.
     *
     * @param  array<int, Diagnostic>  $diagnostics
     * @return array<string, mixed>
     */
    private function failed(string $componentClass, string $kind, array $diagnostics): array
    {
        return [
            'component' => $componentClass,
            'kind' => $kind,
            'ok' => false,
            'diagnostics' => array_map(static fn (Diagnostic $diagnostic): array => $diagnostic->toArray(), $diagnostics),
        ];
    }

    /**
     * Put the record key where a record page reads it from.
     *
     * A record page keeps a key in its public `$record` and resolves it on
     * demand; its `mount()` does nothing more than store it and 404 on a miss.
     * Calling `mount()` here would also run whatever an application hangs off
     * it, so only the property is set.
     *
     * @return array<int, Diagnostic>
     */
    private function assignRecord(object $component, mixed $record): array
    {
        $reflection = new ReflectionClass($component);
        $property = $reflection->hasProperty('record') ? $reflection->getProperty('record') : null;

        if ($property === null || ! $property->isPublic()) {
            return [Diagnostic::warning(
                'record-ignored',
                'record',
                '['.$component::class.'] has no public $record property, so the record key was not used.',
            )];
        }

        try {
            $property->setValue($component, $record);
        } catch (Throwable $e) {
            return [Diagnostic::warning('record-ignored', 'record', $e->getMessage())];
        }

        return [];
    }

    /**
     * Keep only the action that was asked about, wherever it is declared.
     *
     * @param  array<string, array<int, object>>  $components
     * @return array{0: array<string, array<int, object>>, 1: array<int, Diagnostic>}
     */
    private function onlyAction(array $components, string $action): array
    {
        $kept = [];
        $all = [];

        foreach (self::ACTION_GROUPS as $group) {
            foreach ($components[$group] ?? [] as $item) {
                $all[] = $item;

                if ($this->name($item) === $action) {
                    $kept[$group][] = $item;
                }
            }
        }

        if ($kept !== []) {
            return [$kept, []];
        }

        return [[], [Diagnostic::error(
            'unknown-action',
            'actions.'.$action,
            "No action named [{$action}] is declared on this component, its table or its header.",
            $this->closest($action, $this->actions->names($all)),
        )]];
    }

    /**
     * Build every action's modal form and check its fields.
     *
     * @param  array<string, array<int, object>>  $components
     * @return array{0: array<string, int>, 1: array<int, Diagnostic>}
     */
    private function checkActionForms(array $components, object $component): array
    {
        $counts = [];
        $diagnostics = [];
        $context = null;
        $contextResolved = false;

        foreach (self::ACTION_GROUPS as $group) {
            foreach ($components[$group] ?? [] as $action) {
                $name = $this->name($action);

                if ($name === '' || ! method_exists($action, 'hasFormInstance') || ! $action->hasFormInstance()) {
                    continue;
                }

                if (! $contextResolved) {
                    $context = $this->actions->contextRecord($component);
                    $contextResolved = true;
                }

                $target = "{$group}.{$name}.form";

                try {
                    $forms = $this->actions->forms($action, $context);
                } catch (Throwable $e) {
                    $diagnostics[] = Diagnostic::error('build-failed', $target, $e->getMessage());

                    continue;
                }

                $fields = [];
                foreach ($forms as $form) {
                    $fields = array_merge($fields, $this->flatten($this->callArray($form, 'getSchema')));
                }

                $counts["{$group}.{$name}"] = count($fields);

                foreach ($fields as $field) {
                    $fieldTarget = $target.'.'.($this->name($field) ?: get_class($field));

                    $diagnostics = array_merge(
                        $diagnostics,
                        $this->checkIcon($field, $fieldTarget),
                        $this->checkColor($field, $fieldTarget),
                    );
                }
            }
        }

        return [$counts, $diagnostics];
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
    private function checkAttribute(object $component, string $target, ?ModelIntrospector $introspector): array
    {
        if (! $introspector instanceof ModelIntrospector || ! $introspector->hasTable()) {
            return [];
        }

        // A rollup (`->counts('clients')`) is named for the attribute Eloquent
        // writes it to — `clients_count` — which no model declares. What has to
        // exist is the relation it rolls up.
        if (method_exists($component, 'isAggregate') && $component->isAggregate()) {
            return $this->checkAggregateRelation($component, $target, $introspector);
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
     * @return array<int, Diagnostic>
     */
    private function checkAggregateRelation(object $component, string $target, ModelIntrospector $introspector): array
    {
        // Both are set together by the one writer of a rollup, so an aggregate
        // column always has them.
        [$first] = explode('.', (string) $this->call($component, 'getAggregateRelation'), 2);

        if ($introspector->isRelation($first)) {
            return [];
        }

        $function = (string) $this->call($component, 'getAggregateFunction');

        return [Diagnostic::error(
            'unknown-relation',
            $target,
            "[{$introspector->modelClass()}] has no relation [{$first}], so the {$function} cannot be loaded; the query will throw.",
            $this->closest($first, $introspector->relations()),
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
     * Build one of the component's builders.
     */
    private function build(object $component, string $componentClass, string $kind): object
    {
        $builderClass = self::BUILDERS[$kind];

        try {
            /** @var object $builder */
            $builder = $component->{$kind}($builderClass::make());
        } catch (Throwable $e) {
            throw ComponentBuildFailedException::make($componentClass, $kind, $e);
        }

        return $builder;
    }

    /**
     * The schema of a built builder, grouped by role. Extraction failures are
     * a bug in this package, not something to blame on the component.
     *
     * @return array<string, array<int, object>>
     */
    private function components(object $builder, string $kind, bool $primary): array
    {
        if ($kind !== 'table') {
            // The first builder's schema is "schema", as it always was; a second
            // one (a manage page's form beside its table) is named for its kind.
            return [$primary ? 'schema' : $kind => $this->flatten($this->callArray($builder, 'getSchema'))];
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
     * Which builders this component declares, in the order they are reported.
     *
     * The method's signature is checked, not just its name: an unrelated class
     * can easily have a `table()` that means something else entirely, and PHP
     * silently discards the extra argument, so a name-only test would "build"
     * it and report a confusing TypeError instead of "this is not a wire
     * component".
     *
     * @return array<int, string>
     */
    private function kinds(string $componentClass): array
    {
        $kinds = [];

        foreach (self::BUILDERS as $kind => $builderClass) {
            if (! class_exists($builderClass) || ! method_exists($componentClass, $kind)) {
                continue;
            }

            if ($this->builds($componentClass, $kind, $builderClass)) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
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
