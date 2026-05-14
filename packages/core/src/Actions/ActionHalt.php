<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Actions\Concerns\HasIcons;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireForms\Forms\Form;

/**
 * ActionHalt – stops execution pipeline and shows a dynamic modal.
 *
 * Halt can be triggered from:
 *   1. action() callback:  return $halt()->modalHeading('...');
 *   2. before() hook:      $action->halt()->modalHeading('...');
 *   3. after() hook:       $action->halt()->modalHeading('...');
 *
 * After user confirms the halt modal, the pipeline resumes with confirmed=true.
 * Before hooks can check $confirmed to skip their checks on re-execution.
 *
 * Usage:
 *   // Simple confirmation
 *   return $halt()->danger()
 *       ->heading('Smazat?')
 *       ->body('Tato akce je nevratná.');
 *
 *   // With form
 *   return $halt()
 *       ->heading('Důvod zamítnutí')
 *       ->form([TextInput::make('reason')->required()])
 *       ->validation(['reason' => 'required|min:10'])
 *       ->submitLabel('Zamítnout')
 *       ->danger();
 *
 *   // Informative (no submit)
 *   return $halt()->informative()
 *       ->heading('Info')
 *       ->body('Záznam je uzamčen.')
 *       ->icon('lock', 'warning');
 *
 *   // Presets
 *   return ActionHalt::confirmDelete($record->name);
 *   return ActionHalt::confirmDanger('Opravdu?', 'Toto nelze vrátit.');
 *   return ActionHalt::info('Hotovo', 'Operace proběhla úspěšně.');
 *
 * @author Ondřej Nyklíček
 *
 * @phpstan-consistent-constructor
 */
final class ActionHalt
{
    use HasIcons;

    protected ?string $modalHeading = null;

    protected ?string $modalDescription = null;

    protected ?string $modalIcon = null;

    protected ?string $modalIconColor = null;

    protected ?string $modalSubmitLabel = null;

    protected ?string $modalCancelLabel = null;

    protected ?string $modalWidth = 'md';

    protected ?string $color = null;

    protected bool $isDanger = false;

    protected bool $isInformative = false;

    // Form
    protected ?Form $formInstance = null;

    /** @var array<string, mixed>|null */
    protected ?array $formValidation = null;

    /** @var array<string, string>|null */
    protected ?array $formValidationMessages = null;

    /** @var array<string, string>|null */
    protected ?array $formValidationAttributes = null;

    /** @var array<string, mixed>|null */
    protected ?array $formData = null;

    // Context – tracks where halt was triggered
    protected ?string $haltSource = null;  // 'before', 'action', 'after'

    protected int $haltIndex = 0;          // which hook index triggered it

    // Chaining – what happens after confirm
    protected bool $skipBeforeOnConfirm = true;  // default: skip before hooks on re-execution

    protected ?string $redirectAfterConfirm = null;

    // ─── Factory ────────────────────────────────────────────────

    public static function make(): static
    {
        return new self;
    }

    // ─── Presets ────────────────────────────────────────────────

    /**
     * Preset: Delete confirmation.
     */
    public static function confirmDelete(?string $recordName = null): static
    {
        $description = $recordName
            ? Trans::get('wire-core::actions.delete_description_named', ['name' => $recordName])
            : Trans::get('wire-core::actions.delete_description');

        return static::make()
            ->heading(Trans::get('wire-core::actions.delete_heading'))
            ->body($description)
            ->icon('trash', 'danger')
            ->submitLabel(Trans::get('wire-core::actions.delete_submit'))
            ->danger();
    }

    /**
     * Preset: Generic danger confirmation.
     */
    public static function confirmDanger(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->body($description)
            ->icon('warning', 'danger')
            ->danger();
    }

    /**
     * Preset: Warning confirmation.
     */
    public static function confirmWarning(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->body($description)
            ->icon('warning', 'warning');
    }

    /**
     * Preset: Informative (no action, just info).
     */
    public static function info(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->body($description)
            ->icon('info', 'info')
            ->informative();
    }

    /**
     * Preset: Success info.
     */
    public static function success(string $heading, ?string $description = null): static
    {
        return static::make()
            ->heading($heading)
            ->body($description)
            ->icon('check-circle', 'success')
            ->informative();
    }

    // ─── Fluent setters (compact) ───────────────────────────────

    public function heading(?string $heading): static
    {
        $this->modalHeading = $heading;

        return $this;
    }

    public function body(?string $description): static
    {
        $this->modalDescription = $description;

        return $this;
    }

    public function icon(?string $icon, ?string $color = null): static
    {
        $this->modalIcon = $icon;
        $this->modalIconColor = $color;

        return $this;
    }

    public function submitLabel(?string $label): static
    {
        $this->modalSubmitLabel = $label;

        return $this;
    }

    public function cancelLabel(?string $label): static
    {
        $this->modalCancelLabel = $label;

        return $this;
    }

    public function width(?string $width): static
    {
        $this->modalWidth = $width;

        return $this;
    }

    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /** @deprecated Use heading() instead. Will be removed in v2.0. */
    public function modalHeading(?string $heading): static
    {
        Deprecation::method('modalHeading', 'heading');

        return $this->heading($heading);
    }

    /** @deprecated Use body() instead. Will be removed in v2.0. */
    public function modalDescription(?string $description): static
    {
        Deprecation::method('modalDescription', 'body');

        return $this->body($description);
    }

    /** @deprecated Use icon() instead. Will be removed in v2.0. */
    public function modalIcon(?string $icon, ?string $color = null): static
    {
        Deprecation::method('modalIcon', 'icon');

        return $this->icon($icon, $color);
    }

    /** @deprecated Use submitLabel() instead. Will be removed in v2.0. */
    public function modalSubmitLabel(?string $label): static
    {
        Deprecation::method('modalSubmitLabel', 'submitLabel');

        return $this->submitLabel($label);
    }

    /** @deprecated Use cancelLabel() instead. Will be removed in v2.0. */
    public function modalCancelLabel(?string $label): static
    {
        Deprecation::method('modalCancelLabel', 'cancelLabel');

        return $this->cancelLabel($label);
    }

    /** @deprecated Use width() instead. Will be removed in v2.0. */
    public function modalWidth(?string $width): static
    {
        Deprecation::method('modalWidth', 'width');

        return $this->width($width);
    }

    public function danger(bool $danger = true): static
    {
        $this->isDanger = $danger;
        if ($danger) {
            $this->color = 'danger';
        }

        return $this;
    }

    public function informative(bool $informative = true): static
    {
        $this->isInformative = $informative;
        if ($informative) {
            $this->modalSubmitLabel = null;
            $this->formInstance = null;
            $this->formValidation = null;
        }

        return $this;
    }

    public function noSubmit(bool $noSubmit = true): static
    {
        return $this->informative($noSubmit);
    }

    /**
     * @param  array<int, mixed>|Form  $fields
     */
    public function form(array|Form $fields): static
    {
        if ($fields instanceof Form) {
            $this->formInstance = $fields;
        } else {
            $this->formInstance = Form::make()->schema($fields);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>|null  $messages
     * @param  array<string, string>|null  $attributes
     */
    public function validation(array $rules, ?array $messages = null, ?array $attributes = null): static
    {
        $this->formValidation = $rules;
        $this->formValidationMessages = $messages;
        $this->formValidationAttributes = $attributes;

        return $this;
    }

    /**
     * @deprecated Use validation() instead. Will be removed in v2.0.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>|null  $messages
     * @param  array<string, string>|null  $attributes
     */
    public function formValidation(array $rules, ?array $messages = null, ?array $attributes = null): static
    {
        Deprecation::method('formValidation', 'validation');

        return $this->validation($rules, $messages, $attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fillForm(array $data): static
    {
        $this->formData = $data;

        return $this;
    }

    // Context
    public function source(string $source, int $index = 0): static
    {
        $this->haltSource = $source;
        $this->haltIndex = $index;

        return $this;
    }

    public function skipBeforeOnConfirm(bool $skip = true): static
    {
        $this->skipBeforeOnConfirm = $skip;

        return $this;
    }

    public function redirectAfterConfirm(?string $url): static
    {
        $this->redirectAfterConfirm = $url;

        return $this;
    }

    // ─── Getters ────────────────────────────────────────────────

    public function getModalHeading(): ?string
    {
        return $this->modalHeading;
    }

    public function getModalDescription(): ?string
    {
        return $this->modalDescription;
    }

    public function getModalIcon(): ?string
    {
        return $this->modalIcon;
    }

    public function getModalIconColor(): ?string
    {
        return $this->modalIconColor ?? ($this->isDanger ? 'danger' : null);
    }

    public function getModalSubmitLabel(): ?string
    {
        return $this->isInformative ? null : ($this->modalSubmitLabel ?? Trans::get('wire-core::actions.confirm_submit'));
    }

    public function getModalCancelLabel(): string
    {
        return $this->modalCancelLabel ?? ($this->isInformative ? Trans::get('wire-core::actions.confirm_close') : Trans::get('wire-core::actions.confirm_cancel'));
    }

    public function getModalWidth(): string
    {
        return $this->modalWidth ?? 'md';
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function isDanger(): bool
    {
        return $this->isDanger;
    }

    public function isInformative(): bool
    {
        return $this->isInformative;
    }

    public function hasForm(): bool
    {
        return ! $this->isInformative && $this->formInstance !== null;
    }

    public function getFormInstance(): ?Form
    {
        return $this->formInstance;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getModalFormData(): ?array
    {
        return $this->formData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getModalFormValidation(): ?array
    {
        return $this->formValidation;
    }

    /**
     * @return array<string, string>|null
     */
    public function getModalFormValidationMessages(): ?array
    {
        return $this->formValidationMessages;
    }

    /**
     * @return array<string, string>|null
     */
    public function getModalFormValidationAttributes(): ?array
    {
        return $this->formValidationAttributes;
    }

    public function getSource(): ?string
    {
        return $this->haltSource;
    }

    public function getHaltIndex(): int
    {
        return $this->haltIndex;
    }

    public function shouldSkipBeforeOnConfirm(): bool
    {
        return $this->skipBeforeOnConfirm;
    }

    public function getRedirectAfterConfirm(): ?string
    {
        return $this->redirectAfterConfirm;
    }

    // ─── Serialization ──────────────────────────────────────────

    /**
     * Convert to array for frontend / Livewire state.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'halt' => true,
            'modal' => [
                'heading' => $this->modalHeading,
                'description' => $this->modalDescription,
                'icon' => $this->modalIcon,
                'iconColor' => $this->getModalIconColor(),
                'submitLabel' => $this->getModalSubmitLabel(),
                'cancelLabel' => $this->getModalCancelLabel(),
                'width' => $this->modalWidth,
                'color' => $this->color,
                'danger' => $this->isDanger,
                'informative' => $this->isInformative,
                'hasSubmit' => ! $this->isInformative,
                'hasForm' => $this->hasForm(),
                'formValidation' => $this->formValidation,
                'formValidationMessages' => $this->formValidationMessages,
                'formValidationAttributes' => $this->formValidationAttributes,
                'formData' => $this->formData,
            ],
            'context' => [
                'source' => $this->haltSource,
                'index' => $this->haltIndex,
                'skipBeforeOnConfirm' => $this->skipBeforeOnConfirm,
                'redirectAfterConfirm' => $this->redirectAfterConfirm,
            ],
        ];
    }
}
