<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use NyonCode\WireCore\Actions\Contracts\ModalForm;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Forms\Config\ConfigBuilder;
use NyonCode\WireForms\Forms\Config\FormConfig;
use NyonCode\WireForms\Forms\Runtime\FormRuntime;
use NyonCode\WireForms\Forms\Runtime\StaleModelException;
use NyonCode\WireForms\Forms\Runtime\StateManager;
use NyonCode\WireForms\Rendering\FormRenderer;
use NyonCode\WireForms\Validation\FormValidationResolver;

/**
 * Public form API. Users interact only with this class.
 *
 * Internally delegates to ConfigBuilder (fluent accumulation),
 * FormRuntime (validate, save, state), and FormRenderer (Blade output).
 */
class Form implements Htmlable, ModalForm
{
    private ConfigBuilder $configBuilder;

    private ?FormConfig $config = null;

    private ?FormRuntime $runtime = null;

    private StateManager $stateManager;

    private ?FormRenderer $renderer = null;

    private bool $usePolicy = false;

    private ?Closure $authorizeUsingCallback = null;

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

    /**
     * The schema-derived initial state: every field seeded with its ->default()
     * or type-correct blank. Hosts (e.g. action modals) use this to seed the
     * Livewire state bag so array fields never start missing/null, then layer
     * their own record/fillFormUsing data on top.
     *
     * @return array<string, mixed>
     */
    public function getInitialState(): array
    {
        return $this->getRuntime()->getInitialState();
    }

    // ─── Model & save ──────────────────────────────────────────────

    public function model(string|Model|null $model): static
    {
        $this->configBuilder->model($model);
        $this->invalidateConfig();

        return $this;
    }

    /**
     * @throws AuthorizationException
     */
    public function save(): mixed
    {
        if (! $this->canSave()) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        return $this->getRuntime()->save();
    }

    public function using(Closure $fn): static
    {
        $this->configBuilder->using($fn);
        $this->invalidateConfig();

        return $this;
    }

    /**
     * Enable optimistic locking on updates.
     *
     * The lock column's value is captured when the form is filled from the
     * record. On save the current database value is re-read and, if it no longer
     * matches the captured baseline, the save is aborted with a
     * {@see StaleModelException} so a stale
     * write never silently overwrites a concurrent change.
     *
     * Set `->model($record)` before `->fill()` so the baseline can be captured.
     */
    public function optimisticLock(?string $column = 'updated_at'): static
    {
        $this->configBuilder->optimisticLock($column);
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
            $this->getRuntime()->getRepeaters(),
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

    // ─── Wizard steps ──────────────────────────────────────────────

    /**
     * Validate only the fields inside one wizard step (see
     * {@see Wizard}), so step navigation
     * can gate on the current step without flagging later ones.
     *
     * @return array<string, mixed>
     */
    public function validateWizardStep(int $stepIndex, ?string $wizard = null): array
    {
        return $this->getRuntime()->validateWizardStep($stepIndex, $wizard);
    }

    /**
     * Whether the schema contains a wizard — optionally one with the given name.
     */
    public function hasWizard(?string $wizard = null): bool
    {
        return $this->getRuntime()->hasWizard($wizard);
    }

    /**
     * Absolute state paths owned by one wizard step's fields (wildcards for
     * repeaters). Used by hosts to clear a step's stale error-bag entries.
     *
     * @return array<int, string>
     */
    public function getWizardStepFieldPaths(int $stepIndex, ?string $wizard = null): array
    {
        return $this->getRuntime()->getWizardStepFieldPaths($stepIndex, $wizard);
    }

    // ─── State ─────────────────────────────────────────────────────

    public function disabled(bool $disabled = true): static
    {
        $this->configBuilder->disabled($disabled);
        $this->invalidateConfig();

        return $this;
    }

    /**
     * Force wire:model.live on all fields.
     *
     * Required when the form is embedded in a component with polling — deferred
     * wire:model values are not included in poll requests, so Livewire re-renders
     * with empty server state and morphdom resets the inputs.
     */
    public function live(bool $condition = true): static
    {
        $this->configBuilder->live($condition);
        $this->invalidateConfig();

        return $this;
    }

    // ─── Authorization ────────────────────────────────────────────

    /**
     * Enable model policy auto-resolution.
     *
     * When enabled, the form auto-detects if the user has 'create' or 'update'
     * permission on the model. If denied, the form becomes read-only and
     * the save button is hidden.
     */
    public function authorize(bool $usePolicy = true): static
    {
        $this->usePolicy = $usePolicy;

        return $this;
    }

    /**
     * Override authorization with a custom callback.
     *
     * Example: ->authorizeUsing(fn (User $user) => $user->hasRole('editor'))
     */
    public function authorizeUsing(?Closure $callback): static
    {
        $this->authorizeUsingCallback = $callback;

        return $this;
    }

    /**
     * Check if the current user can save the form (create or update).
     */
    public function canSave(): bool
    {
        // Custom callback takes highest priority
        if ($this->authorizeUsingCallback) {
            $user = auth()->guard()->user();

            return $user ? (bool) ($this->authorizeUsingCallback)($user) : false;
        }

        if (! $this->usePolicy) {
            return true;
        }

        $model = $this->getModel();
        if (! $model) {
            return true;
        }

        if ($model instanceof Model && $model->exists) {
            return Gate::allows('update', $model);
        }

        $modelClass = $model instanceof Model ? $model::class : $model;

        return Gate::allows('create', $modelClass);
    }

    /**
     * Check if the form is read-only due to authorization.
     */
    public function isReadOnly(): bool
    {
        if ($this->authorizeUsingCallback) {
            return ! $this->canSave();
        }

        return $this->usePolicy && ! $this->canSave();
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
     * Locate the component bound to the given absolute state path — flat fields
     * directly, fields inside repeater items through the per-item schema (see
     * FormRuntime::findComponentByStatePath()). The canonical lookup used by
     * every reactive dispatch (afterStateUpdated, live validation, field
     * actions, remote search).
     */
    public function findComponentByStatePath(string $absolutePath): ?\NyonCode\WireCore\Foundation\Components\Component
    {
        return $this->getRuntime()->findComponentByStatePath($absolutePath);
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
