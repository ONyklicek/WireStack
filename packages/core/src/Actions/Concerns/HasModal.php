<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;
use Livewire\Component;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Modals\Contracts\ModalContract;
use NyonCode\WireCore\Modals\Modal;
use NyonCode\WireCore\Modals\SlideOver;
use NyonCode\WireCore\Modals\Wizard;
use NyonCode\WireForms\Forms\Form;

/**
 * Trait HasModal
 *
 * Extracts ALL modal-related properties and methods shared across Action, BulkAction, HeaderAction.
 */
trait HasModal
{
    protected const TABLE_ACTION_FORM_STATE_PATH = 'tableState.modal.action.formData';

    protected const LEGACY_ACTION_FORM_STATE_PATH = 'actionModalFormData';

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

    /** @var Infolist|Closure|null Infolist instance or closure returning Infolist */
    protected Infolist|Closure|null $infolistInstance = null;

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

    public function modalIcon(string|Icon|null $icon, string|Color|null $color = null): static
    {
        $this->modalIcon = $icon instanceof Icon ? $icon->value() : $icon;
        $this->modalIconColor = $color instanceof Color ? $color->value : $color;

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
     * Define a read-only infolist for this action's modal.
     *
     * Accepts:
     * - Infolist instance: ->infolist(Infolist::make()->schema([...]))
     * - Array of entry/layout components: ->infolist([TextEntry::make('name')])
     * - Closure returning Infolist: ->infolist(fn ($record) => Infolist::make()->schema([...]))
     * - Closure returning array of components: ->infolist(fn ($record) => [TextEntry::make('name')])
     *
     * The action's record is bound to the infolist automatically, so the modal
     * shows the record without a submit action.
     *
     * @param  array<int, mixed>|Infolist|Closure  $components
     */
    public function infolist(array|Infolist|Closure $components): static
    {
        if ($components instanceof Infolist || $components instanceof Closure) {
            $this->infolistInstance = $components;
        } else {
            $this->infolistInstance = Infolist::make()->schema($components);
        }
        $this->hasModal = true;

        return $this;
    }

    /**
     * Configure this action's modal from a declarative modal config object.
     *
     * Accepts any {@see ModalContract}: Modal, SlideOver, ConfirmationDialog or
     * Wizard. The config object's values are translated into this action's modal
     * state, so the existing modal runtime/blade renders it — there is a single
     * canonical modal owner (HasModal) rather than two parallel APIs.
     *
     *   ->modal(Wizard::make()->heading('Create')->steps([...]))
     *   ->modal(SlideOver::make()->heading('Details'))
     *   ->modal(ConfirmationDialog::delete('User'))
     */
    public function modal(ModalContract $modal): static
    {
        $this->hasModal = true;

        // Shared properties common to every modal config object.
        $this->modalHeading = $modal->getHeading();
        $this->modalDescription = $modal->getDescription();
        $this->modalWidth = $modal->getWidth();
        $this->modalCloseOnClickAway = $modal->shouldCloseOnClickAway();
        $this->modalCloseOnEscape = $modal->shouldCloseOnEscape();
        $this->modalIcon = $modal->getIcon();
        $this->modalIconColor = $modal->getIconColor();
        $this->modalSubmitLabel = $modal->getSubmitLabel();
        $this->modalCancelLabel = $modal->getCancelLabel();
        $this->modalMaxHeight = $modal->getMaxHeight();
        $this->stickyFooter = $modal->hasStickyFooter();
        $this->stickyHeader = $modal->hasStickyHeader();

        // The modal's accent color drives the submit button; mirror it onto the
        // action's own color (HasColor is always present on the host action, the
        // same way getModalConfig() reads $this->getColor()).
        $color = $modal->getColor();
        if ($color !== null) {
            $this->color($color);
        }

        // Type-specific translation.
        match (true) {
            $modal instanceof Wizard => $this->modalSteps = $modal->getSteps(),
            $modal instanceof SlideOver => $modal->isMobileOnly()
                ? $this->slideOverOnMobile = true
                : $this->slideOver = true,
            $modal instanceof Modal => $this->applyPlainModalOptions($modal),
            default => null,
        };

        return $this;
    }

    protected function applyPlainModalOptions(Modal $modal): void
    {
        $this->fullScreenOnMobile = $modal->isFullScreenOnMobile();
        $this->mobileModalWidth = $modal->getMobileWidth();
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
        if ($this->modalHeadingCallback !== null && $context !== null) {
            return ($this->modalHeadingCallback)($context);
        }

        return $this->modalHeading ?? Trans::get('wire-core::actions.confirm_heading');
    }

    public function getModalDescription(mixed $context = null): ?string
    {
        if ($this->modalDescriptionCallback !== null && $context !== null) {
            return ($this->modalDescriptionCallback)($context);
        }

        return $this->modalDescription ?? ($this->doesRequireConfirmation() ? Trans::get('wire-core::actions.confirm_description') : null);
    }

    public function doesRequireConfirmation(): bool
    {
        return $this->hasModal
            && $this->formInstance === null
            && $this->infolistInstance === null
            && ! $this->hasMultipleSteps();
    }

    public function getModalIcon(): ?string
    {
        return $this->modalIcon;
    }

    public function getModalIconColor(): string
    {
        return $this->modalIconColor ?? Color::Warning->value;
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
        return $this->hasModal && ($this->formInstance !== null || $this->hasMultipleSteps());
    }

    public function hasInfolistModal(): bool
    {
        return $this->hasModal && $this->infolistInstance !== null;
    }

    /**
     * Check if this action has an Infolist instance configured.
     */
    public function hasInfolistInstance(): bool
    {
        return $this->infolistInstance !== null;
    }

    /**
     * Resolve the Infolist instance for this action's modal, bound to the
     * action's record/context. Closures are resolved here.
     */
    public function getInfolistInstance(mixed $context = null): ?Infolist
    {
        $infolist = null;

        if ($this->infolistInstance instanceof Closure) {
            $resolved = ($this->infolistInstance)($context);
            if ($resolved instanceof Infolist) {
                $infolist = $resolved;
            } elseif (is_array($resolved)) {
                $infolist = Infolist::make()->schema($resolved);
            }
        } elseif ($this->infolistInstance instanceof Infolist) {
            $infolist = $this->infolistInstance;
        }

        if ($infolist === null) {
            return null;
        }

        return $infolist->record($context);
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
            $resolved = ($this->formInstance)($context);
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

        $statePath = $this->resolveModalFormStatePath($livewire);
        $form->statePath($statePath);

        // Fields stay deferred (plain wire:model) by default: Livewire sends
        // dirty model values along with the submit call, so live sync adds
        // nothing — it only triggers a full re-render per keystroke pause,
        // whose morph races further typing and erases it. Reactive fields
        // opt in individually via ->live().

        if ($livewire) {
            $form->livewire($livewire);
        }

        // Fill form with defaults from fillFormUsing callback only if Livewire state is empty.
        // Context may be null for header actions, which still need their defaults seeded.
        if ($this->fillFormUsing !== null && $livewire !== null) {
            $currentState = $this->getCurrentModalFormState($livewire, $statePath);
            if (empty($currentState)) {
                $defaults = ($this->fillFormUsing)($context);
                if (is_array($defaults) && ! empty($defaults)) {
                    $form->fill($defaults);
                }
            }
        }

        return $form;
    }

    protected function resolveModalFormStatePath(?Component $livewire = null): string
    {
        if ($livewire !== null
            && isset($livewire->tableState)
            && $livewire->tableState instanceof StateContainer) {
            return self::TABLE_ACTION_FORM_STATE_PATH;
        }

        return self::LEGACY_ACTION_FORM_STATE_PATH;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getCurrentModalFormState(Component $livewire, string $statePath): array
    {
        if ($statePath === self::TABLE_ACTION_FORM_STATE_PATH
            && isset($livewire->tableState)
            && $livewire->tableState instanceof StateContainer) {
            $state = $livewire->tableState->get('modal.action.formData', []);

            return is_array($state) ? $state : [];
        }

        $state = data_get($livewire, $statePath, []);

        return is_array($state) ? $state : [];
    }

    /**
     * Check if this action has a Form instance configured.
     */
    public function hasFormInstance(): bool
    {
        return $this->formInstance !== null || $this->hasMultipleSteps();
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
        if ($this->modalFormValidationCallback !== null && $context !== null) {
            return ($this->modalFormValidationCallback)($context);
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
        $messages = ($this->modalFormValidationMessagesCallback !== null && $context !== null)
            ? ($this->modalFormValidationMessagesCallback)($context)
            : ($this->modalFormValidationMessages ?? []);

        return $this->prefixValidationRules($messages);
    }

    /**
     * @return array<string, string>
     */
    public function getRawValidationMessages(mixed $context = null): array
    {
        if ($this->modalFormValidationMessagesCallback !== null && $context !== null) {
            return ($this->modalFormValidationMessagesCallback)($context);
        }

        return $this->modalFormValidationMessages ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationAttributes(mixed $context = null): array
    {
        $attributes = ($this->modalFormValidationAttributesCallback !== null && $context !== null)
            ? ($this->modalFormValidationAttributesCallback)($context)
            : ($this->modalFormValidationAttributes ?? []);

        return $this->prefixValidationRules($attributes);
    }

    /**
     * @return array<string, string>
     */
    public function getRawValidationAttributes(mixed $context = null): array
    {
        if ($this->modalFormValidationAttributesCallback !== null && $context !== null) {
            return ($this->modalFormValidationAttributesCallback)($context);
        }

        return $this->modalFormValidationAttributes ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormDefaults(mixed $context = null): array
    {
        // Header actions have no record, so $context is null. Their fillFormUsing
        // closure takes no arguments and still needs to run, otherwise array
        // fields (e.g. CheckboxList) are never seeded and Livewire collapses the
        // grouped checkboxes into a single shared boolean (checking one checks all).
        if ($this->fillFormUsing !== null) {
            return ($this->fillFormUsing)($context);
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
            'hasInfolist' => $this->hasInfolistModal(),
            'isConfirmation' => $this->doesRequireConfirmation(),
            'actionColor' => $this->getColor(),
            'submitButtonClasses' => HasColor::getModalSubmitButtonClasses($this->getColor()),
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

    public function getStepCount(): int
    {
        return count($this->modalSteps);
    }

    /**
     * Resolve a single wizard step by its index, clamped to the valid range.
     */
    public function getModalStep(int $index): ?ModalStep
    {
        $steps = array_values($this->modalSteps);

        if ($steps === []) {
            return null;
        }

        $index = max(0, min($index, count($steps) - 1));
        $step = $steps[$index];

        return $step instanceof ModalStep ? $step : null;
    }

    /**
     * Build a Form instance for a single wizard step. The form shares the same
     * state path as a normal action form, so each step reads and writes the same
     * `modal.action.formData` bag and data persists as the user moves between
     * steps. Returns null when this action is not a multi-step wizard.
     */
    public function getStepFormInstance(?Component $livewire = null, mixed $context = null, int $stepIndex = 0): ?Form
    {
        $step = $this->getModalStep($stepIndex);

        if ($step === null) {
            return null;
        }

        $form = Form::make()->schema($step->getSchema($context));

        $form->statePath($this->resolveModalFormStatePath($livewire));

        if ($livewire) {
            $form->livewire($livewire);
        }

        return $form;
    }

    /**
     * The configured modal footer action objects (not the render config).
     *
     * @return array<int, mixed>
     */
    public function getModalFooterActions(): array
    {
        return $this->modalFooterActions;
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
