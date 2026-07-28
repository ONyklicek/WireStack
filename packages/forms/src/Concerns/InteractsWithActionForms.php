<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Illuminate\Support\Facades\Validator;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Forms\Form;
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
        $formInstance->livewire($this);
        $this->haltModalFormInstance = $formInstance;

        // Persist across Livewire re-renders; Form schema may contain
        // non-serializable closures (options callbacks, validation rules), so we
        // swallow the exception. If serialization fails the form won't survive
        // polling re-renders, but the halt modal stays open and the user can
        // still submit on first render.
        try {
            session()->put('wire.halt_form_instance', serialize($formInstance));
        } catch (Throwable) {
            // Non-serializable form — session fallback unavailable.
        }
    }

    /**
     * Get the resolved Form instance for the halt modal, if any.
     */
    public function getHaltModalFormInstance(): ?Form
    {
        return $this->haltModalFormInstance;
    }
}
