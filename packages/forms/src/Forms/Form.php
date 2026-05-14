<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use NyonCode\WireForms\Forms\Config\ConfigBuilder;
use NyonCode\WireForms\Forms\Config\FormConfig;
use NyonCode\WireForms\Forms\Runtime\FormRuntime;
use NyonCode\WireForms\Forms\Runtime\StateManager;
use NyonCode\WireForms\Rendering\FormRenderer;
use NyonCode\WireForms\Validation\FormValidationResolver;

/**
 * Public form API. Users interact only with this class.
 *
 * Internally delegates to ConfigBuilder (fluent accumulation),
 * FormRuntime (validate, save, state), and FormRenderer (Blade output).
 */
class Form implements Htmlable
{
    private ConfigBuilder $configBuilder;

    private ?FormConfig $config = null;

    private ?FormRuntime $runtime = null;

    private StateManager $stateManager;

    private ?FormRenderer $renderer = null;

    public function __construct()
    {
        $this->configBuilder = new ConfigBuilder;
        $this->stateManager = new StateManager;
    }

    public static function make(): static
    {
        return app(static::class);
    }

    // ─── Livewire binding ──────────────────────────────────────────

    public function livewire(Component $component): static
    {
        $this->stateManager->setLivewire($component);

        return $this;
    }

    // ─── Schema & state ────────────────────────────────────────────

    /**
     * @param  array<int, mixed>  $components
     */
    public function schema(array $components): static
    {
        $this->configBuilder->schema($components);
        $this->invalidateConfig();

        return $this;
    }

    public function statePath(string $path): static
    {
        $this->configBuilder->statePath($path);
        $this->stateManager->setStatePath($path);
        $this->invalidateConfig();

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fill(array $data): static
    {
        $this->getRuntime()->fill($data);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function state(array $data): static
    {
        return $this->fill($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->getRuntime()->getState();
    }

    // ─── Model & save ──────────────────────────────────────────────

    public function model(string|Model|null $model): static
    {
        $this->configBuilder->model($model);
        $this->invalidateConfig();

        return $this;
    }

    public function save(): mixed
    {
        return $this->getRuntime()->save();
    }

    public function using(Closure $fn): static
    {
        $this->configBuilder->using($fn);
        $this->invalidateConfig();

        return $this;
    }

    public function mutateDataBeforeSave(Closure $fn): static
    {
        $this->configBuilder->mutateDataBeforeSave($fn);
        $this->invalidateConfig();

        return $this;
    }

    public function beforeSave(Closure $fn): static
    {
        $this->configBuilder->beforeSave($fn);
        $this->invalidateConfig();

        return $this;
    }

    public function afterSave(Closure $fn): static
    {
        $this->configBuilder->afterSave($fn);
        $this->invalidateConfig();

        return $this;
    }

    public function successMessage(string|Closure|null $message): static
    {
        $this->configBuilder->successMessage($message);
        $this->invalidateConfig();

        return $this;
    }

    public function disableSuccessNotification(): static
    {
        return $this->successMessage(null);
    }

    // ─── Validation ────────────────────────────────────────────────

    /**
     * @param  array<string, string>  $messages
     */
    public function validationMessages(array $messages): static
    {
        $this->configBuilder->validationMessages($messages);
        $this->invalidateConfig();

        return $this;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function getValidationRules(): array
    {
        $resolver = new FormValidationResolver(
            $this->getFlatComponents(),
            $this->getConfig()->statePath,
            $this->getConfig()->validationMessages,
        );

        return $resolver->getRules();
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        return $this->getRuntime()->validate();
    }

    // ─── State ─────────────────────────────────────────────────────

    public function disabled(bool $disabled = true): static
    {
        $this->configBuilder->disabled($disabled);
        $this->invalidateConfig();

        return $this;
    }

    // ─── Introspection ─────────────────────────────────────────────

    public function isCreating(): bool
    {
        return $this->getConfig()->isCreating();
    }

    public function isEditing(): bool
    {
        return $this->getConfig()->isEditing();
    }

    public function getModel(): string|Model|null
    {
        return $this->getConfig()->model;
    }

    /**
     * @return array<int, \NyonCode\WireCore\Foundation\Components\Component>
     */
    public function getFlatComponents(): array
    {
        return $this->getRuntime()->getFlatComponents();
    }

    /**
     * @return array<int, mixed>
     */
    public function getSchema(): array
    {
        return $this->configBuilder->getSchema();
    }

    // ─── Rendering ─────────────────────────────────────────────────

    public function toHtml(): string
    {
        return $this->getRenderer()->toHtml();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    // ─── Internal ──────────────────────────────────────────────────

    private function getConfig(): FormConfig
    {
        if ($this->config === null) {
            $this->config = $this->configBuilder->build();
        }

        return $this->config;
    }

    private function getRuntime(): FormRuntime
    {
        if ($this->runtime === null) {
            $this->runtime = new FormRuntime(
                $this->getConfig(),
                $this->stateManager,
            );
        }

        return $this->runtime;
    }

    private function getRenderer(): FormRenderer
    {
        if ($this->renderer === null) {
            $this->renderer = new FormRenderer(
                $this->getConfig(),
                $this->getRuntime(),
            );
        }

        return $this->renderer;
    }

    private function invalidateConfig(): void
    {
        $this->config = null;
        $this->runtime = null;
        $this->renderer = null;
    }
}
