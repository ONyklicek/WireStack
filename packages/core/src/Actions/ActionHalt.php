<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use NyonCode\WireCore\Actions\Concerns\HasIcons;
use NyonCode\WireCore\Actions\Contracts\ModalForm;
use NyonCode\WireCore\Actions\Support\ModalForms;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasModalProperties;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithColor;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * ActionHalt – stops execution pipeline and shows a dynamic modal.
 *
 * Halt can be triggered from:
 *   1. action() callback:  return $halt()->heading('...');
 *   2. before() hook:      $action->halt()->heading('...');
 *   3. after() hook:       $action->halt()->heading('...');
 *
 * After user confirms the halt modal, the pipeline resumes with confirmed=true.
 * Before hooks can check $confirmed to skip their checks on re-execution.
 *
 * Usage:
 *   // Simple confirmation
 *   return $halt()->danger()
 *       ->heading('Smazat?')
 *       ->description('Tato akce je nevratná.');
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

    /*
     * A halt *is* a modal, so it speaks the modal vocabulary the framework
     * already owns — `heading()`, `description()`, `width()`, `closeOnEscape()`
     * — rather than a private copy of it. An **action** prefixes the same
     * settings (`modalHeading()`, `modalWidth()`) because an action is a button
     * that *has* a modal: unprefixed `icon()` and `color()` there are the
     * button's own. That is the whole rule behind what used to look like two
     * spellings of one thing.
     *
     * A closure heading is resolved at declaration rather than at render: a
     * halt is serialized into component state the moment it is raised, and the
     * scope that could answer the closure is gone by the time the modal draws.
     */
    use HasModalProperties {
        heading as private setHeading;
        description as private setDescription;
    }
    use InteractsWithColor;

    protected ?string $modalIcon = null;

    protected ?string $modalIconColor = null;

    protected ?string $modalSubmitLabel = null;

    protected ?string $modalCancelLabel = null;

    protected bool $isDanger = false;

    protected bool $isInformative = false;

    // Form
    protected ?ModalForm $formInstance = null;

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

        return self::make()
            ->heading(Trans::get('wire-core::actions.delete_heading'))
            ->description($description)
            ->icon('trash', Color::Danger)
            ->submitLabel(Trans::get('wire-core::actions.delete_submit'))
            ->danger();
    }

    /**
     * Preset: Generic danger confirmation.
     */
    public static function confirmDanger(string $heading, ?string $description = null): static
    {
        return self::make()
            ->heading($heading)
            ->description($description)
            ->icon('warning', Color::Danger)
            ->danger();
    }

    /**
     * Preset: Warning confirmation.
     */
    public static function confirmWarning(string $heading, ?string $description = null): static
    {
        return self::make()
            ->heading($heading)
            ->description($description)
            ->icon('warning', Color::Warning);
    }

    /**
     * Preset: Informative (no action, just info).
     */
    public static function info(string $heading, ?string $description = null): static
    {
        return self::make()
            ->heading($heading)
            ->description($description)
            ->icon('info', Color::Info)
            ->informative();
    }

    /**
     * Preset: Success info.
     */
    public static function success(string $heading, ?string $description = null): static
    {
        return self::make()
            ->heading($heading)
            ->description($description)
            ->icon('check-circle', Color::Success)
            ->informative();
    }

    // ─── Fluent setters (compact) ───────────────────────────────

    public function heading(string|Closure|null $heading): static
    {
        return $this->setHeading($heading instanceof Closure ? $heading() : $heading);
    }

    public function description(string|Closure|null $description): static
    {
        return $this->setDescription($description instanceof Closure ? $description() : $description);
    }

    public function icon(string|Icon|null $icon, string|Color|null $color = null): static
    {
        $this->modalIcon = $icon instanceof Icon ? $icon->value() : $icon;
        $this->modalIconColor = $color instanceof Color ? $color->value : $color;

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

    public function danger(bool $danger = true): static
    {
        $this->isDanger = $danger;

        // `danger()` names the intent, not the hue: it fills the colour slot only
        // when nothing has chosen one. The same rule already guards the two
        // confirmation constructors (`Modals\Html\Confirmation`,
        // `Modals\View\ConfirmationComponent`), and they are the newer copies —
        // without the guard, `->color('primary')->danger()` and
        // `->danger()->color('primary')` disagree, and so does the Blade tag.
        if ($danger && $this->color === null) {
            $this->color = Color::Danger->value;
        }

        return $this;
    }

    /**
     * A halt with nothing to confirm: no submit button, no form, no rules — one
     * way out. The action is not re-executed, because there is nothing to
     * re-execute it for.
     *
     * Whatever a form declared is dropped here, and declaring one afterwards
     * takes the halt back out of informative: whichever was said last is what
     * the modal does.
     */
    public function informative(bool $informative = true): static
    {
        $this->isInformative = $informative;

        if ($informative) {
            $this->modalSubmitLabel = null;
            $this->formInstance = null;
            $this->formValidation = null;
            $this->formValidationMessages = null;
            $this->formValidationAttributes = null;
            $this->formData = null;
        }

        return $this;
    }

    public function noSubmit(bool $noSubmit = true): static
    {
        return $this->informative($noSubmit);
    }

    /**
     * Fields the halt collects before the action is re-executed.
     *
     * Asking for a form is asking for a submit, so this undoes `informative()`
     * rather than being quietly overruled by it. Before 2.0 the two were
     * order-dependent: `->informative()->form([...])` left `hasForm()` false and
     * dropped the fields on the floor, while still serializing the instance the
     * modal would never render.
     *
     * @param  array<int, mixed>|ModalForm  $fields
     */
    public function form(array|ModalForm $fields): static
    {
        $this->formInstance = $fields instanceof ModalForm ? $fields : ModalForms::make($fields);
        $this->isInformative = false;

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
     * @param  array<string, mixed>  $data
     */
    public function fillForm(array $data): static
    {
        $this->formData = $data;

        return $this;
    }

    // Context

    /**
     * Where in the pipeline this halt was raised — `before`, `after` or
     * `action`, plus the hook's index.
     *
     * @docs-ignore Written by the pipeline, never by a call site: the halt is
     * already returned from the place that would have had something to say.
     */
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

    public function getModalIcon(): ?string
    {
        return $this->modalIcon;
    }

    public function getModalIconColor(): ?string
    {
        return $this->modalIconColor ?? ($this->isDanger ? Color::Danger->value : null);
    }

    public function getModalSubmitLabel(): ?string
    {
        return $this->isInformative ? null : ($this->modalSubmitLabel ?? Trans::get('wire-core::actions.confirm_submit'));
    }

    public function getModalCancelLabel(): string
    {
        return $this->modalCancelLabel ?? ($this->isInformative ? Trans::get('wire-core::actions.confirm_close') : Trans::get('wire-core::actions.confirm_cancel'));
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

    public function getFormInstance(): ?ModalForm
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
                'heading' => $this->getHeading(),
                'description' => $this->getDescription(),
                'icon' => $this->modalIcon,
                'iconColor' => $this->getModalIconColor(),
                'submitLabel' => $this->getModalSubmitLabel(),
                'cancelLabel' => $this->getModalCancelLabel(),
                'width' => $this->getWidth(),
                'closeOnClickAway' => $this->shouldCloseOnClickAway(),
                'closeOnEscape' => $this->shouldCloseOnEscape(),
                'maxHeight' => $this->getMaxHeight(),
                'id' => $this->getId(),
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
