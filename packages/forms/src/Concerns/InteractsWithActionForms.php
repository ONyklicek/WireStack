<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\Contracts\ModalForm;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\Runtime\StateDehydrator;
use Throwable;

/**
 * Form-hosting half of the action runtime.
 *
 * wire-core owns the form-agnostic engine
 * (NyonCode\WireCore\Actions\Concerns\InteractsWithActions); this concern adds
 * everything that touches a wire-forms Form: building/validating the modal
 * form, multi-step wizards, and attaching a Form to a halt modal.
 *
 * It is designed to be composed alongside InteractsWithActions and the standard
 * wire-forms field concerns (InteractsWithFieldActions provides the
 * `fieldActionForms()`/`resolveFieldForAction()` seams that pick up
 * `getActionModalFormInstance()` automatically). Both wire-table's WithTable and
 * the standalone WithActions host compose it.
 *
 * It relies on the state seams declared by InteractsWithActions
 * (`getMountedActionState`, `getMountedActionFormData`, `setMountedActionFormData`,
 * `resolveCurrentModalAction`), so the same code backs a StateContainer bag
 * (table) or plain public props (standalone).
 */
trait InteractsWithActionForms
{
    /** @var Form|null Resolved Form instance for the current action modal */
    protected ?Form $actionModalFormInstance = null;

    /** @var Form|null Resolved Form instance for the halt modal */
    protected ?Form $haltModalFormInstance = null;

    /**
     * The Livewire binding path to the halt modal's form-data bag. Standalone
     * hosts bind it to a public array property; wire-table overrides this to its
     * StateContainer path.
     */
    protected function haltModalFormStatePath(): string
    {
        return 'actionModalHaltData';
    }

    /**
     * Get the resolved Form instance for the current action modal, if any.
     * Re-resolves on demand since the Form instance is not serialized between
     * Livewire requests.
     */
    public function getActionModalFormInstance(): ?Form
    {
        if ($this->actionModalFormInstance === null
            && $this->isActionModalVisible()
            && $this->getMountedActionState('name')) {
            $this->resolveActionModalFormInstance();
        }

        return $this->actionModalFormInstance;
    }

    /**
     * Resolve the Form instance from the currently mounted action.
     */
    protected function resolveActionModalFormInstance(): void
    {
        [$action, $context] = $this->resolveCurrentModalAction();

        if ($action !== null) {
            $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $context);
        }
    }

    /**
     * Resolve the Form instance for the open modal at the given stack depth, so
     * the view can render every live frame. Each frame binds to its own
     * depth-scoped state path ({@see actionFrameStatePath()}), which keeps the
     * parent form live and reactive behind the active one.
     */
    public function getActionModalFormInstanceForDepth(int $depth): ?Form
    {
        [$action, $context] = $this->resolveActionForFrame($depth);

        if ($action === null) {
            return null;
        }

        return $this->buildModalActionFormInstance($action, $context, $depth);
    }

    /**
     * Resolve the Form instance for an action modal, honouring multi-step
     * wizards. A wizard renders only the current step's schema while all steps
     * share the same form-data bag. Binds to the frame's depth-scoped state path.
     */
    protected function buildModalActionFormInstance(Action|BulkAction|HeaderAction $action, mixed $context, ?int $depth = null): ?Form
    {
        $depth ??= $this->topActionFrameIndex();
        $statePath = $this->actionFrameStatePath($depth);
        $context = $this->actionFormContext($depth, $context);

        $form = $action->hasMultipleSteps()
            ? $action->getStepFormInstance($this, $context, (int) $this->getActionFrameState($depth, 'currentStep', 0), $statePath)
            : $action->getFormInstance($this, $context, $statePath);

        // Core hands back the ModalForm seam; this bridge owns the concrete Form.
        return $form instanceof Form ? $form : null;
    }

    /**
     * The context an action's FORM builds from at the given depth. A header
     * action has no record, so its schema/validation closures receive the live
     * form-data bag — this is what lets a wizard's later steps build from values
     * entered earlier. Every other action keeps its record/records context.
     *
     * This is the single home for that nuance: config and infolist resolution
     * deliberately keep the canonical (record) context from resolveActionForFrame.
     */
    protected function actionFormContext(int $depth, mixed $fallback): mixed
    {
        return $this->getActionFrameState($depth, 'isHeaderAction')
            ? $this->readActionFrameData($depth)
            : $fallback;
    }

    /**
     * The active (top) action together with the context its form should build
     * from ({@see actionFormContext()}).
     *
     * @return array{0: Action|BulkAction|HeaderAction|null, 1: mixed}
     */
    protected function resolveCurrentActionForm(): array
    {
        [$action, $context] = $this->resolveCurrentModalAction();

        return [$action, $this->actionFormContext($this->topActionFrameIndex(), $context)];
    }

    /**
     * Validate the current action modal's Form (or every wizard step). Called by
     * the core engine before footer-action submits and by callMountedAction.
     */
    protected function validateMountedActionForm(): void
    {
        [$action, $context] = $this->resolveCurrentActionForm();

        // Defensive: every caller (callMountedAction / callModalFooterAction)
        // already resolved a non-null action before validating.
        // @codeCoverageIgnoreStart
        if ($action === null) {
            return;
        }
        // @codeCoverageIgnoreEnd

        if ($action->hasMultipleSteps()) {
            // Validate every step's schema and rules against the shared form data.
            // Intermediate steps' afterValidation hooks already ran while stepping
            // forward, so they are skipped here to avoid firing twice — but the
            // last step is never stepped off of, so its hook runs here (once) on
            // submit; otherwise a final-step async/uniqueness gate never fires.
            $lastStep = $action->getStepCount() - 1;
            for ($step = 0; $step <= $lastStep; $step++) {
                $this->validateModalStep($action, $context, $step, runAfterValidation: $step === $lastStep);
            }

            return;
        }

        // Re-resolve Form instance (not serialized between Livewire requests),
        // bound to the active frame's depth-scoped state path so error keys and
        // field bindings line up with the top modal.
        if ($this->actionModalFormInstance === null) {
            $form = $action->getFormInstance($this, $context, $this->actionFrameStatePath($this->topActionFrameIndex()));
            $this->actionModalFormInstance = $form instanceof Form ? $form : null;
        }

        $this->actionModalFormInstance?->validate();
    }

    /**
     * Apply each field's dehydration to the modal's data before an action
     * callback sees it — the wire-forms half of the core seam.
     *
     * Until this existed a form behaved differently depending on which door its
     * values left through: `Form::save()` dehydrated, an action modal did not,
     * so the same `Select` handed a record `null` and a callback `''`.
     *
     * Deliberately not part of {@see validateMountedActionForm()}. Validation
     * runs once per wizard step and again on every footer submit, and a
     * transform with a side effect — a `FileUpload` moving its upload to
     * permanent storage — must not run once per step. This runs once, at the
     * hand-over, and its result is not written back into the frame's bag.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dehydrateMountedActionFormData(array $data): array
    {
        [$action, $context] = $this->resolveCurrentActionForm();

        // Defensive: every caller resolved a non-null action before submitting.
        // @codeCoverageIgnoreStart
        if ($action === null) {
            return $data;
        }
        // @codeCoverageIgnoreEnd

        $dehydrator = new StateDehydrator;
        $record = $context instanceof Model ? $context : null;

        foreach ($this->mountedActionFormSchemas($action, $context) as $schema) {
            $data = $dehydrator->dehydrate($data, $schema, $record);
        }

        return $data;
    }

    /**
     * The schemas backing the active modal's data bag.
     *
     * A wizard's steps share one bag while each step's Form carries only its own
     * schema, so every step is asked in turn — otherwise only the last step's
     * fields would ever be dehydrated.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function mountedActionFormSchemas(Action|BulkAction|HeaderAction $action, mixed $context): array
    {
        $statePath = $this->actionFrameStatePath($this->topActionFrameIndex());

        $forms = $action->hasMultipleSteps()
            ? array_map(
                fn (int $step): ?ModalForm => $action->getStepFormInstance($this, $context, $step, $statePath),
                range(0, $action->getStepCount() - 1),
            )
            : [$action->getFormInstance($this, $context, $statePath)];

        $schemas = [];

        foreach ($forms as $form) {
            // Core hands back the ModalForm seam; only a concrete Form has one.
            if ($form instanceof Form) {
                $schemas[] = $form->getSchema();
            }
        }

        return $schemas;
    }

    // ==========================================
    // Wizard stepping
    // ==========================================

    /**
     * Advance the wizard to the next step after validating the current one.
     */
    public function nextActionModalStep(): void
    {
        [$action, $context] = $this->resolveCurrentActionForm();

        if (! $action || ! $action->hasMultipleSteps()) {
            return;
        }

        $current = (int) $this->getMountedActionState('currentStep', 0);

        $this->validateModalStep($action, $context, $current);

        $next = min($current + 1, $action->getStepCount() - 1);

        $this->runModalStepBeforeCallback($action, $context, $next);

        $this->setMountedActionState('currentStep', $next);
        $this->actionModalFormInstance = null;
    }

    /**
     * Step the wizard back one step. No validation runs when moving backwards.
     */
    public function prevActionModalStep(): void
    {
        $current = (int) $this->getMountedActionState('currentStep', 0);

        $this->setMountedActionState('currentStep', max(0, $current - 1));
        $this->actionModalFormInstance = null;
    }

    /**
     * Validate a single wizard step: the step's field schema via the Form
     * runtime, then any extra rules declared with ModalStep::validation(), then
     * the optional afterValidation() hook.
     */
    protected function validateModalStep(Action|BulkAction|HeaderAction $action, mixed $context, int $stepIndex, bool $runAfterValidation = true): void
    {
        $action->getStepFormInstance($this, $context, $stepIndex, $this->actionFrameStatePath($this->topActionFrameIndex()))?->validate();

        $step = $action->getModalStep($stepIndex);

        // Defensive: the iterated indices always map to declared ModalSteps.
        // @codeCoverageIgnoreStart
        if (! $step instanceof ModalStep) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $formData = $this->getMountedActionFormData();

        $rules = $step->getValidation($context);

        if ($rules !== []) {
            Validator::make($formData, $rules, $step->getValidationMessages())->validate();
        }

        if ($runAfterValidation && ($callback = $step->getAfterValidationCallback())) {
            $callback($formData, $context);
        }
    }

    /**
     * Run a step's before() hook, letting it pre-fill form state before the step
     * is shown. The returned array (if any) is merged into the form data bag.
     */
    protected function runModalStepBeforeCallback(Action|BulkAction|HeaderAction $action, mixed $context, int $stepIndex): void
    {
        $step = $action->getModalStep($stepIndex);

        // Defensive: the target step index always maps to a declared ModalStep.
        // @codeCoverageIgnoreStart
        if (! $step instanceof ModalStep) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $callback = $step->getBeforeCallback();

        if ($callback === null) {
            return;
        }

        $formData = $this->getMountedActionFormData();

        $result = $callback($formData, $context);

        if (is_array($result)) {
            $this->setMountedActionFormData(array_merge($formData, $result));
        }
    }

    // ==========================================
    // Halt modal form
    // ==========================================

    /**
     * Attach a Form instance to the halt modal when the halt declares one. Hook
     * called from the core engine's showHaltModal().
     */
    protected function resolveHaltModalForm(ActionHalt $halt): void
    {
        $formInstance = $halt->getFormInstance();

        // Core hands back the ModalForm seam; this bridge owns the concrete Form.
        if (! $formInstance instanceof Form) {
            return;
        }

        $formInstance->statePath($this->haltModalFormStatePath());

        // Persist across Livewire re-renders — before the host is bound, not
        // after. A Livewire component is not serializable, so binding it first
        // made *every* halt form throw here: the fallback this exists for never
        // fired once, the form was gone on the next request, and the confirm had
        // no schema to shape its data with. The restore end re-binds the host, so
        // the copy has no business carrying one.
        //
        // A schema holding a Closure (an options callback, a visible() condition)
        // still cannot be serialized, and that is what the catch is for: the
        // modal stays open and submits from the first render.
        try {
            session()->put('wire.halt_form_instance', serialize($formInstance));
        } catch (Throwable) {
            // Non-serializable form — session fallback unavailable.
        }

        $formInstance->livewire($this);
        $this->haltModalFormInstance = $formInstance;
    }

    /**
     * Get the resolved Form instance for the halt modal, if any.
     */
    public function getHaltModalFormInstance(): ?Form
    {
        return $this->haltModalFormInstance;
    }

    /**
     * Validate a halt modal's form before its action is re-executed.
     *
     * Two layers, the same two an action modal has: the fields' own rules
     * (`->required()`, `->rules()`), asked of the Form itself, and the extra
     * rules the halt declared with {@see ActionHalt::validation()} against the
     * bag as a whole.
     *
     * The declared rules are written against bare field names, and their failures
     * are re-keyed onto the paths the halt form's inputs are bound to. A field
     * looks itself up by state path, and a confirmation modal renders no error
     * summary to fall back on — reported unscoped, the message would have nowhere
     * to appear and the confirm would look like it had simply done nothing.
     *
     * When the halt form could not be restored — a schema holding a Closure
     * cannot be serialized into the session — only the declared rules run. They
     * live in the halt config, which is plain state and always survives.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     *
     * @throws ValidationException
     */
    protected function validateHaltModalForm(array $data, array $rules = [], array $messages = [], array $attributes = []): void
    {
        $this->getHaltModalFormInstance()?->validate();

        if ($rules === []) {
            return;
        }

        $result = app(ValidationPipeline::class)->validate($data, $rules, $messages, $attributes);

        if (! $result->failed()) {
            return;
        }

        $statePath = $this->haltModalFormStatePath();

        $scoped = [];

        foreach ($result->errors() as $field => $errors) {
            $scoped["{$statePath}.{$field}"] = $errors;
        }

        throw ValidationException::withMessages($scoped);
    }

    /**
     * Apply each field's dehydration to a halt modal's data before the halted
     * action is re-executed with it.
     *
     * The halt modal is the third door out of a form — `Form::save()`, an action
     * modal, and this — and it is the one whose form the *action itself* asked
     * for mid-flight (`$halt()->form([...])`). Its fields bind straight to the
     * halt data bag ({@see haltModalFormStatePath()}), so that bag is the form's
     * live state and gets the same treatment as any other: a cleared select
     * arrives as null, a date in its storage format, an upload as a stored path.
     *
     * Unlike {@see dehydrateMountedActionFormData()} this has no counterpart in
     * wire-core, because nothing in wire-core re-executes a halted action —
     * `showHaltModal()` only records the state, and the host owns the confirm.
     *
     * Only keys the halt form declares are touched, so data the halt carried over
     * from the original action passes through as it arrived.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dehydrateHaltModalFormData(array $data, ?Model $record = null): array
    {
        $form = $this->getHaltModalFormInstance();

        if (! $form instanceof Form) {
            return $data;
        }

        return (new StateDehydrator)->dehydrate($data, $form->getSchema(), $record);
    }
}
