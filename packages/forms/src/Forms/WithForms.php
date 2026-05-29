<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms;

use InvalidArgumentException;
use Livewire\Component;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * @phpstan-require-extends Component
 *
 * Livewire trait for form integration.
 *
 * Supports single form ($this->form) and multi-form ($this->profileForm).
 * Forms are lazily resolved and cached for the request lifecycle.
 */
trait WithForms
{
    /** @var array<string, Form> */
    protected array $cachedForms = [];

    /** @var array<string>|null */
    private ?array $resolvedFormNames = null;

    public function bootWithForms(): void
    {
        // Livewire 3 lifecycle hook — validate form coexistence
        $this->validateFormCoexistence();
    }

    /**
     * Magic access: $this->form, $this->profileForm, etc.
     */
    public function __get($name): mixed
    {
        if (is_string($name) && $this->isFormProperty($name)) {
            return $this->resolveForm($name);
        }

        return parent::__get($name);
    }

    protected function resolveForm(string $name): Form
    {
        if (isset($this->cachedForms[$name])) {
            return $this->cachedForms[$name];
        }

        $methodName = $this->getFormMethodName($name);

        if (! method_exists($this, $methodName)) {
            throw new InvalidArgumentException("Form method [{$methodName}()] does not exist on ".static::class);
        }

        $form = app(Form::class);
        $form->livewire($this);

        $form = $this->{$methodName}($form);

        return $this->cachedForms[$name] = $form;
    }

    /**
     * Override to explicitly register forms.
     * If not overridden, auto-detect is used.
     *
     * @return array<string>
     */
    protected function getForms(): array
    {
        return $this->autoDetectForms();
    }

    /**
     * @return array<string>
     */
    protected function autoDetectForms(): array
    {
        // Single form
        if (method_exists($this, 'form') && $this->isFormMethod('form')) {
            return ['form'];
        }

        // Multi-form: methods ending with 'Form'
        $forms = [];

        foreach (get_class_methods($this) as $method) {
            if ($method === 'form') {
                continue;
            }

            if (str_ends_with($method, 'Form') && $this->isFormMethod($method)) {
                $forms[] = $method;
            }
        }

        return $forms;
    }

    protected function isFormProperty(string $name): bool
    {
        if ($this->resolvedFormNames === null) {
            $this->resolvedFormNames = $this->getForms();
        }

        // Direct match: 'form' or 'profileForm'
        if (in_array($name, $this->resolvedFormNames, true)) {
            return true;
        }

        // Check if method name matches
        $methodName = $this->getFormMethodName($name);

        return in_array($methodName, $this->resolvedFormNames, true);
    }

    protected function getFormMethodName(string $propertyName): string
    {
        // 'form' → 'form', 'profileForm' → 'profileForm'
        return $propertyName;
    }

    private function isFormMethod(string $method): bool
    {
        if (! method_exists($this, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($this, $method);

        // Must be public
        if (! $reflection->isPublic()) {
            return false;
        }

        // Must accept one parameter of type Form
        $params = $reflection->getParameters();
        if (count($params) !== 1) {
            return false;
        }

        $paramType = $params[0]->getType();
        if (! $paramType instanceof ReflectionNamedType) {
            return false;
        }

        if ($paramType->getName() !== Form::class) {
            return false;
        }

        // Must return Form
        $returnType = $reflection->getReturnType();
        if (! $returnType instanceof ReflectionNamedType) {
            return false;
        }

        return $returnType->getName() === Form::class;
    }

    // ─── Repeater actions ─────────────────────────────────────────

    public function addRepeaterItem(string $statePath): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items)) {
            $items = [];
        }

        $items[] = [];
        data_set($this, $statePath, $items);
    }

    public function removeRepeaterItem(string $statePath, int $index): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items)) {
            return;
        }

        unset($items[$index]);
        data_set($this, $statePath, array_values($items));
    }

    public function reorderRepeaterItems(string $statePath, array $order): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items)) {
            return;
        }

        $reordered = [];
        foreach ($order as $oldIndex) {
            if (isset($items[$oldIndex])) {
                $reordered[] = $items[$oldIndex];
            }
        }

        data_set($this, $statePath, $reordered);
    }

    private function validateFormCoexistence(): void
    {
        $hasSingleForm = method_exists($this, 'form') && $this->isFormMethod('form');

        if (! $hasSingleForm) {
            return;
        }

        // Check for multi-form methods
        foreach (get_class_methods($this) as $method) {
            if ($method === 'form') {
                continue;
            }

            if (str_ends_with($method, 'Form') && $this->isFormMethod($method)) {
                throw new InvalidArgumentException(
                    'Component ['.static::class.'] cannot have both form() and '.$method.'() methods. '
                    .'Use either a single form() method or multiple *Form() methods, not both. '
                    .'See ADR 0009 for details.'
                );
            }
        }
    }
}
