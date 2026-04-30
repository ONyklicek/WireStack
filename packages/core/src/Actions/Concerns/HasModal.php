<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Closure;

/**
 * Trait HasModal
 *
 * Extracts ALL modal-related properties and methods shared across Action, BulkAction, HeaderAction.
 * Eliminates ~400 lines of duplicated code across 3 classes.
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

    /** @var array<int, mixed> */
    protected array $modalFormFields = [];

    protected ?Closure $modalFormFieldsCallback = null;

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
     * @param  array<int, mixed>|Closure  $fields
     */
    public function form(array|Closure $fields): static
    {
        if ($fields instanceof Closure) {
            $this->modalFormFieldsCallback = $fields;
        } else {
            $this->modalFormFields = $fields;
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

        return $this->modalHeading ?? 'Potvrdit akci';
    }

    public function getModalDescription(mixed $context = null): ?string
    {
        if ($this->modalDescriptionCallback && $context) {
            return call_user_func($this->modalDescriptionCallback, $context);
        }

        return $this->modalDescription ?? ($this->doesRequireConfirmation() ? 'Opravdu chcete provést tuto akci?' : null);
    }

    public function doesRequireConfirmation(): bool
    {
        return $this->hasModal && empty($this->modalFormFields) && ! $this->modalFormFieldsCallback;
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
        return $this->modalSubmitLabel ?? 'Potvrdit';
    }

    public function getModalCancelActionLabel(): string
    {
        return $this->modalCancelLabel ?? 'Zrušit';
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
        return $this->hasModal && (! empty($this->modalFormFields) || $this->modalFormFieldsCallback);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFormFields(mixed $context = null): array
    {
        $fields = ($this->modalFormFieldsCallback && $context)
            ? call_user_func($this->modalFormFieldsCallback, $context)
            : $this->modalFormFields;

        return $this->normalizeFormFields($fields, $context);
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeFormFields(array $fields, mixed $context = null): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (is_object($field) && method_exists($field, 'toArray')) {
                $normalized[] = $field->toArray($context);
            } elseif (is_array($field)) {
                if (isset($field['schema']) && is_array($field['schema'])) {
                    $field['schema'] = $this->normalizeFormFields($field['schema'], $context);
                }
                $normalized[] = $field;
            }
        }

        return $normalized;
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

        return $this->extractDefaults($this->getFormFields($context));
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    protected function extractDefaults(array $fields): array
    {
        $defaults = [];
        foreach ($fields as $field) {
            if (isset($field['name'])) {
                $defaults[$field['name']] = $field['default'] ?? null;
            }
            if (isset($field['schema']) && is_array($field['schema'])) {
                $defaults = array_merge($defaults, $this->extractDefaults($field['schema']));
            }
        }

        return $defaults;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    protected function extractFieldNames(array $fields): array
    {
        $names = [];
        foreach ($fields as $field) {
            if (isset($field['name'])) {
                $names[] = $field['name'];
            }
            if (isset($field['schema']) && is_array($field['schema'])) {
                $names = array_merge($names, $this->extractFieldNames($field['schema']));
            }
        }

        return $names;
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
            'fields' => $this->getFormFields($context),
            'validation' => $this->getRawFormValidation($context),
            'defaults' => $this->getFormDefaults($context),
            'hasForm' => $this->hasFormModal(),
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
     * Usage:
     *   ->steps([
     *       ModalStep::make('Základní údaje')
     *           ->description('Vyplňte základní informace')
     *           ->icon('user')
     *           ->schema([
     *               TextInput::make('name')->required(),
     *               TextInput::make('email')->required(),
     *           ]),
     *       ModalStep::make('Nastavení')
     *           ->schema([
     *               Select::make('role')->options([...]),
     *           ]),
     *   ])
     */
    /**
     * @param  array<int, mixed>  $steps
     */
    public function steps(array $steps): static
    {
        $this->modalSteps = $steps;
        $this->hasModal = true;

        return $this;
    }

    /**
     * Sticky footer (stays visible when scrolling long forms).
     */
    public function stickyFooter(bool $sticky = true): static
    {
        $this->stickyFooter = $sticky;

        return $this;
    }

    /**
     * Sticky header (stays visible when scrolling long forms).
     */
    public function stickyHeader(bool $sticky = true): static
    {
        $this->stickyHeader = $sticky;

        return $this;
    }

    /**
     * Set max height for scrollable modal body.
     */
    public function modalMaxHeight(string $maxHeight): static
    {
        $this->modalMaxHeight = $maxHeight;

        return $this;
    }

    /**
     * Add extra action buttons to modal footer.
     *
     * Usage:
     *   ->modalFooterActions([
     *       ModalFooterAction::make('preview')
     *           ->label('Náhled')
     *           ->color('gray')
     *           ->action(fn ($data) => ...),
     *   ])
     */
    /**
     * @param  array<int, mixed>  $actions
     */
    public function modalFooterActions(array $actions): static
    {
        $this->modalFooterActions = $actions;

        return $this;
    }

    /**
     * Add action buttons to modal header (next to close button).
     */
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
            if (is_array($step)) {
                if (isset($step['schema'])) {
                    $step['schema'] = $this->normalizeFormFields($step['schema'], $context);
                }

                return $step;
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
