<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use Livewire\Component;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireForms\Forms\Form;

/**
 * Trait HasModal
 *
 * Extracts ALL modal-related properties and methods shared across Action, BulkAction, HeaderAction.
 */
trait HasModal
{
    protected bool $hasModal = false;

    protected ?string $modalHeading = null;

    protected ?Closure $modalHeadingCallback = null;

    protected ?string $modalDescription = null;

    protected ?Closure $modalDescriptionCallback = null;

    protected ?string $modalIcon = null;

    protected ?string $modalIconColor = null;

    protected ?string $modalSubmitLabel = null;

    protected ?string $modalCancelLabel = null;

    protected ?string $modalWidth = 'md';

    protected bool $modalCloseOnClickAway = true;

    protected bool $modalCloseOnEscape = true;

    /** @var Form|Closure|null Form instance or closure returning Form */
    protected Form|Closure|null $formInstance = null;

    /** @var array<string, mixed>|null */
    protected ?array $modalFormValidation = null;

    protected ?Closure $modalFormValidationCallback = null;

    /** @var array<string, string>|null */
    protected ?array $modalFormValidationMessages = null;

    protected ?Closure $modalFormValidationMessagesCallback = null;

    /** @var array<string, string>|null */
    protected ?array $modalFormValidationAttributes = null;

    protected ?Closure $modalFormValidationAttributesCallback = null;

    protected ?Closure $fillFormUsing = null;

    protected bool $slideOver = false;

    protected bool $slideOverOnMobile = false;

    protected bool $fullScreenOnMobile = false;

    protected ?string $mobileModalWidth = null;

    public function requiresConfirmation(bool $requires = true): static
    {
        $this->hasModal = $requires;

        return $this;
    }

    public function hasModal(): bool
    {
        return $this->hasModal;
    }

    public function modalHeading(string|Closure|null $heading): static
    {
        if ($heading instanceof Closure) {
            $this->modalHeadingCallback = $heading;
        } else {
            $this->modalHeading = $heading;
        }
        $this->hasModal = true;

        return $this;
    }

    public function modalDescription(string|Closure|null $description): static
    {
        if ($description instanceof Closure) {
            $this->modalDescriptionCallback = $description;
        } else {
            $this->modalDescription = $description;
        }

        return $this;
    }

    public function modalIcon(?string $icon, ?string $color = null): static
    {
        $this->modalIcon = $icon;
        $this->modalIconColor = $color;

        return $this;
    }

    public function modalSubmitActionLabel(?string $label): static
    {
        $this->modalSubmitLabel = $label;

        return $this;
    }

    public function modalCancelActionLabel(?string $label): static
    {
        $this->modalCancelLabel = $label;

        return $this;
    }

    public function modalWidth(string $width): static
    {
        $this->modalWidth = $width;

        return $this;
    }

    public function closeModalOnClickAway(bool $close = true): static
    {
        $this->modalCloseOnClickAway = $close;

        return $this;
    }

    public function closeModalOnEscape(bool $close = true): static
    {
        $this->modalCloseOnEscape = $close;

        return $this;
    }

    public function slideOver(bool $slideOver = true): static
    {
        $this->slideOver = $slideOver;
        $this->hasModal = true;

        return $this;
    }

    public function slideOverOnMobile(bool $slideOver = true): static
    {
        $this->slideOverOnMobile = $slideOver;
        $this->hasModal = true;

        return $this;
    }

    public function fullScreenOnMobile(bool $fullScreen = true): static
    {
        $this->fullScreenOnMobile = $fullScreen;

        return $this;
    }

    public function mobileModalWidth(string $width): static
    {
        $this->mobileModalWidth = $width;

        return $this;
    }

    /**
     * Define form for this action's modal.
     *
     * Accepts:
     * - Form instance: ->form(Form::make()->schema([...]))
     * - Array of field components: ->form([TextInput::make('name'), Select::make('role')])
     * - Closure returning Form: ->form(fn ($record) => Form::make()->schema([...]))
     * - Closure returning array of components: ->form(fn ($record) => [TextInput::make('name')])
     *
     * @param  array<int, mixed>|Form|Closure  $fields
     */
    public function form(array|Form|Closure $fields): static
    {
        if ($fields instanceof Form) {
            $this->formInstance = $fields;
        } elseif ($fields instanceof Closure) {
            $this->formInstance = $fields;
        } else {
            $this->formInstance = Form::make()->schema($fields);
        }
        $this->hasModal = true;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $rules
     */
    public function formValidation(array|Closure $rules): static
    {
        if ($rules instanceof Closure) {
            $this->modalFormValidationCallback = $rules;
        } else {
            $this->modalFormValidation = $rules;
        }

        return $this;
    }

    /**
     * @param  array<string, string>|Closure  $messages
     */
    public function validationMessages(array|Closure $messages): static
    {
        if ($messages instanceof Closure) {
            $this->modalFormValidationMessagesCallback = $messages;
        } else {
            $this->modalFormValidationMessages = $messages;
        }

        return $this;
    }

    /**
     * @param  array<string, string>|Closure  $attributes
     */
    public function validationAttributes(array|Closure $attributes): static
    {
        if ($attributes instanceof Closure) {
            $this->modalFormValidationAttributesCallback = $attributes;
        } else {
            $this->modalFormValidationAttributes = $attributes;
        }

        return $this;
    }

    public function fillFormUsing(Closure $callback): static
    {
        $this->fillFormUsing = $callback;

        return $this;
    }

    public function getFillFormCallback(): ?Closure
    {
        return $this->fillFormUsing;
    }

    // Getters
    public function getModalHeading(mixed $context = null): string
    {
        if ($this->modalHeadingCallback && $context) {
            return call_user_func($this->modalHeadingCallback, $context);
        }

        return $this->modalHeading ?? Trans::get('wire-core::actions.confirm_heading');
    }

    public function getModalDescription(mixed $context = null): ?string
    {
        if ($this->modalDescriptionCallback && $context) {
            return call_user_func($this->modalDescriptionCallback, $context);
        }

        return $this->modalDescription ?? ($this->doesRequireConfirmation() ? Trans::get('wire-core::actions.confirm_description') : null);
    }

    public function doesRequireConfirmation(): bool
    {
        return $this->hasModal && $this->formInstance === null;
    }

    public function getModalIcon(): ?string
    {
        return $this->modalIcon;
    }

    public function getModalIconColor(): string
    {
        return $this->modalIconColor ?? 'warning';
    }

    public function getModalSubmitActionLabel(): string
    {
        return $this->modalSubmitLabel ?? Trans::get('wire-core::actions.confirm_submit');
    }

    public function getModalCancelActionLabel(): string
    {
        return $this->modalCancelLabel ?? Trans::get('wire-core::actions.confirm_cancel');
    }

    public function getModalWidth(): string
    {
        return $this->modalWidth ?? 'md';
    }

    public function shouldCloseModalOnClickAway(): bool
    {
        return $this->modalCloseOnClickAway;
    }

    public function shouldCloseModalOnEscape(): bool
    {
        return $this->modalCloseOnEscape;
    }

    public function isSlideOver(): bool
    {
        return $this->slideOver;
    }

    public function isSlideOverOnMobile(): bool
    {
        return $this->slideOverOnMobile;
    }

    public function isFullScreenOnMobile(): bool
    {
        return $this->fullScreenOnMobile;
    }

    public function getMobileModalWidth(): ?string
    {
        return $this->mobileModalWidth;
    }

    public function hasFormModal(): bool
    {
        return $this->hasModal && $this->formInstance !== null;
    }

    /**
     * Resolve the Form instance for this action's modal.
     *
     * When a closure was passed to form(), it will be resolved here.
     * The Form is automatically configured with statePath and livewire binding.
     */
    public function getFormInstance(?Component $livewire = null, mixed $context = null): ?Form
    {
        $form = null;

        if ($this->formInstance instanceof Closure) {
            $resolved = call_user_func($this->formInstance, $context);
            if ($resolved instanceof Form) {
                $form = $resolved;
            } elseif (is_array($resolved)) {
                $form = Form::make()->schema($resolved);
            }
        } elseif ($this->formInstance instanceof Form) {
            $form = $this->formInstance;
        }

        if ($form === null) {
            return null;
        }

        $form->statePath('actionModalFormData');

        if ($livewire) {
            $form->livewire($livewire);
        }

        // Fill form with defaults from fillFormUsing callback only if Livewire state is empty
        if ($this->fillFormUsing && $context && $livewire) {
            $currentState = data_get($livewire, 'actionModalFormData', []);
            if (empty($currentState)) {
                $defaults = call_user_func($this->fillFormUsing, $context);
                if (is_array($defaults) && ! empty($defaults)) {
                    $form->fill($defaults);
                }
            }
        }

        return $form;
    }

    /**
     * Check if this action has a Form instance configured.
     */
    public function hasFormInstance(): bool
    {
        return $this->formInstance !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormValidation(mixed $context = null): array
    {
        return $this->prefixValidationRules($this->getRawFormValidation($context));
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawFormValidation(mixed $context = null): array
    {
        if ($this->modalFormValidationCallback && $context) {
            return call_user_func($this->modalFormValidationCallback, $context);
        }

        return $this->modalFormValidation ?? [];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function prefixValidationRules(array $rules, string $prefix = 'actionModalFormData.'): array
    {
        $prefixed = [];
        foreach ($rules as $field => $rule) {
            $prefixed[str_starts_with($field, $prefix) ? $field : $prefix.$field] = $rule;
        }

        return $prefixed;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationMessages(mixed $context = null): array
    {
        $messages = ($this->modalFormValidationMessagesCallback && $context)
            ? call_user_func($this->modalFormValidationMessagesCallback, $context)
            : ($this->modalFormValidationMessages ?? []);

        return $this->prefixValidationRules($messages);
    }

    /**
     * @return array<string, string>
     */
    public function getRawValidationMessages(mixed $context = null): array
    {
        if ($this->modalFormValidationMessagesCallback && $context) {
            return call_user_func($this->modalFormValidationMessagesCallback, $context);
        }

        return $this->modalFormValidationMessages ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationAttributes(mixed $context = null): array
    {
        $attributes = ($this->modalFormValidationAttributesCallback && $context)
            ? call_user_func($this->modalFormValidationAttributesCallback, $context)
            : ($this->modalFormValidationAttributes ?? []);

        return $this->prefixValidationRules($attributes);
    }

    /**
     * @return array<string, string>
     */
    public function getRawValidationAttributes(mixed $context = null): array
    {
        if ($this->modalFormValidationAttributesCallback && $context) {
            return call_user_func($this->modalFormValidationAttributesCallback, $context);
        }

        return $this->modalFormValidationAttributes ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormDefaults(mixed $context = null): array
    {
        if ($this->fillFormUsing && $context) {
            return call_user_func($this->fillFormUsing, $context);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getModalConfig(mixed $context = null): array
    {
        return [
            'heading' => $this->getModalHeading($context),
            'description' => $this->getModalDescription($context),
            'icon' => $this->getModalIcon(),
            'iconColor' => $this->getModalIconColor(),
            'submitLabel' => $this->getModalSubmitActionLabel(),
            'cancelLabel' => $this->getModalCancelActionLabel(),
            'width' => $this->getModalWidth(),
            'closeOnClickAway' => $this->shouldCloseModalOnClickAway(),
            'closeOnEscape' => $this->shouldCloseModalOnEscape(),
            'slideOver' => $this->isSlideOver(),
            'slideOverOnMobile' => $this->isSlideOverOnMobile(),
            'fullScreenOnMobile' => $this->isFullScreenOnMobile(),
            'mobileWidth' => $this->getMobileModalWidth(),
            'hasForm' => $this->hasFormModal(),
            'hasFormInstance' => $this->hasFormInstance(),
            'isConfirmation' => $this->doesRequireConfirmation(),
            'actionColor' => $this->getColor(),
            // Enhanced modal features
            'footerActions' => $this->getModalFooterActionsConfig(),
            'headerActions' => $this->getModalHeaderActionsConfig(),
            'steps' => $this->hasMultipleSteps() ? $this->getStepsConfig($context) : null,
            'currentStep' => $this->hasMultipleSteps() ? 0 : null,
            'totalSteps' => $this->hasMultipleSteps() ? count($this->modalSteps) : null,
            'stickyFooter' => $this->stickyFooter,
            'stickyHeader' => $this->stickyHeader,
            'maxHeight' => $this->modalMaxHeight,
        ];
    }

    // ─── Multi-step modal support ───────────────────────────────

    /** @var array<int, mixed> */
    protected array $modalSteps = [];

    protected bool $stickyFooter = false;

    protected bool $stickyHeader = false;

    protected ?string $modalMaxHeight = null;

    /** @var array<int, mixed> */
    protected array $modalFooterActions = [];

    /** @var array<int, mixed> */
    protected array $modalHeaderActions = [];

    /**
     * Define multi-step modal wizard.
     *
     * @param  array<int, mixed>  $steps
     */
    public function steps(array $steps): static
    {
        $this->modalSteps = $steps;
        $this->hasModal = true;

        return $this;
    }

    public function stickyFooter(bool $sticky = true): static
    {
        $this->stickyFooter = $sticky;

        return $this;
    }

    public function stickyHeader(bool $sticky = true): static
    {
        $this->stickyHeader = $sticky;

        return $this;
    }

    public function modalMaxHeight(string $maxHeight): static
    {
        $this->modalMaxHeight = $maxHeight;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $actions
     */
    public function modalFooterActions(array $actions): static
    {
        $this->modalFooterActions = $actions;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $actions
     */
    public function modalHeaderActions(array $actions): static
    {
        $this->modalHeaderActions = $actions;

        return $this;
    }

    public function hasMultipleSteps(): bool
    {
        return ! empty($this->modalSteps);
    }

    /**
     * @return array<int, mixed>
     */
    public function getModalSteps(): array
    {
        return $this->modalSteps;
    }

    /**
     * @return array<int, mixed>
     */
    public function getStepsConfig(mixed $context = null): array
    {
        return array_map(function ($step) use ($context) {
            if (is_object($step) && method_exists($step, 'toArray')) {
                return $step->toArray($context);
            }

            return $step;
        }, $this->modalSteps);
    }

    /**
     * @return array<int, mixed>
     */
    public function getModalFooterActionsConfig(): array
    {
        return array_map(function ($action) {
            if (is_object($action) && method_exists($action, 'toArray')) {
                return $action->toArray();
            }

            return $action;
        }, $this->modalFooterActions);
    }

    /**
     * @return array<int, mixed>
     */
    public function getModalHeaderActionsConfig(): array
    {
        return array_map(function ($action) {
            if (is_object($action) && method_exists($action, 'toArray')) {
                return $action->toArray();
            }

            return $action;
        }, $this->modalHeaderActions);
    }
}
