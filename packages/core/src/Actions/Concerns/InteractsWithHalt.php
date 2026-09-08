<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use ReflectionMethod;

/**
 * A halt on any component: stop, ask, and go on where you stopped.
 *
 * The mechanism was born inside the action pipeline and stayed there — it could
 * only be raised from an action's callback, on a host composing the whole action
 * runtime and a form layer with it. Yet nothing about "ask before you continue"
 * is about actions: the object is a modal description, the state is five keys,
 * and the resume is a method name plus scalars. This concern is that part on its
 * own, so any Livewire component can raise one:
 *
 *     use NyonCode\WireCore\Actions\Concerns\InteractsWithHalt;
 *
 *     public function archive(): void
 *     {
 *         $this->halt(
 *             ActionHalt::confirmDanger('Archive this order?', 'It leaves the active list.'),
 *             then: 'archiveConfirmed',
 *             arguments: ['id' => $this->orderId],
 *         );
 *     }
 *
 *     public function archiveConfirmed(array $data, array $arguments): void
 *     {
 *         Order::findOrFail($arguments['id'])->archive();
 *     }
 *
 * plus `<x-wire-actions::halt-host :component="$this" />` in the view — or
 * nothing at all if the component already renders the action modal host, which
 * draws the halt with it.
 *
 * **Why the resume is a name and not a closure.** A halt lives across a request
 * boundary: it is drawn, read and answered on a later request than the one that
 * raised it, and a closure does not survive that trip. So it carries what the
 * action pipeline has always carried — a name and scalars. The named method is a
 * public method of the component, which the browser can already call directly;
 * naming it here grants nothing that was not already reachable.
 *
 * **What a halt with fields needs.** Fields come from `wire-forms`, and so does
 * the layer that renders, validates and dehydrates them; this concern asks the
 * host for that layer only where it exists. On a component that has none a halt
 * is a confirmation — heading, description, two buttons — and any rules it
 * declares are still checked against the data it was submitted with.
 */
trait InteractsWithHalt
{
    /**
     * The halt's state bag: `show`, `config`, `formData`, `context`, and
     * whatever the resume needs. Public because a halt outlives the request that
     * raised it — that is the whole point of one.
     *
     * @var array<string, mixed>
     */
    public array $mountedHalt = [];

    // ==========================================
    // Storage — overridable, because a host may keep it elsewhere
    // ==========================================

    protected function setHaltModalState(string $key, mixed $value): void
    {
        $this->mountedHalt[$key] = $value;
    }

    protected function getHaltModalState(string $key, mixed $default = null): mixed
    {
        return $this->mountedHalt[$key] ?? $default;
    }

    /**
     * The **live** bag the halt form's fields are bound to.
     *
     * Not the same as the `formData` the halt was raised with: that is the seed,
     * this is what the user has typed since. A host binding its fields to a
     * property of its own overrides this to return that property.
     *
     * @return array<string, mixed>
     */
    protected function getHaltFormData(): array
    {
        return (array) $this->getHaltModalState('formData', []);
    }

    // ==========================================
    // Raising one
    // ==========================================

    /**
     * Stop and ask, then continue in `$then` once the user confirms.
     *
     * `protected` on purpose: this is server-side control flow, not an endpoint.
     * The two methods the browser calls — `submitHaltModal()` and
     * `closeHaltModal()` — are the whole public surface of a halt.
     *
     * @param  string  $then  A method of this component, called as
     *                        `$then(array $data, array $arguments)` on the
     *                        confirmed pass, `$data` being what the halt collected.
     * @param  array<string, mixed>  $arguments  Scalars carried across the request.
     */
    protected function halt(ActionHalt $halt, string $then, array $arguments = []): void
    {
        $this->showHalt($halt, ['then' => $then, 'arguments' => $arguments]);
    }

    /**
     * Put a halt on screen. What a caller adds to `$state` is what its own
     * resume will need — a method name here, an action name and a record key
     * when the action pipeline raises one.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $formData
     */
    protected function showHalt(ActionHalt $halt, array $state = [], array $formData = []): void
    {
        foreach ($state as $key => $value) {
            $this->setHaltModalState($key, $value);
        }

        $serialized = $halt->toArray();

        $this->setHaltModalState('config', $serialized['modal']);
        $this->setHaltModalState('formData', $halt->getModalFormData() ?? $formData);
        $this->setHaltModalState('context', $serialized['context'] ?? []);

        $this->resolveHaltModalForm($halt);

        $this->setHaltModalState('show', true);
    }

    // ==========================================
    // Reading it
    // ==========================================

    public function isHaltModalVisible(): bool
    {
        return (bool) $this->getHaltModalState('show', false);
    }

    /**
     * The halt's serialized modal bag, as the shared partial reads it.
     *
     * @return array<string, mixed>
     */
    public function getHaltModalData(): array
    {
        return (array) $this->getHaltModalState('config', []);
    }

    // ==========================================
    // Answering it
    // ==========================================

    /**
     * Confirm: check what the halt collected, then continue where it stopped.
     *
     * The order is the contract. Validation throws *before* anything runs or
     * closes, so a failed rule leaves the modal open with its messages on the
     * fields; the fields then shape what they collected exactly as a save would;
     * and only then does the halt close and the resume run — which is free to
     * raise the next halt on top of the state this one just cleared.
     *
     * @param  array<string, mixed>  $formData  Escape hatch for a caller submitting from code.
     */
    public function submitHaltModal(array $formData = []): void
    {
        if (! $this->hasPendingHalt()) {
            return;
        }

        $config = $this->getHaltModalData();
        $data = array_merge($this->getHaltFormData(), $formData);

        $this->validateHaltData(
            $data,
            (array) ($config['formValidation'] ?? []),
            (array) ($config['formValidationMessages'] ?? []),
            (array) ($config['formValidationAttributes'] ?? []),
        );

        // Read before closing, which drops the form instance and its parked copy.
        $data = $this->dehydrateHaltData($data);

        $context = (array) $this->getHaltModalState('context', []);
        $resume = $this->haltResumeState();

        $this->closeHaltModal();

        $this->resumeHalt($resume, $data);

        $redirect = $context['redirectAfterConfirm'] ?? null;

        if (is_string($redirect) && $redirect !== '') {
            $this->redirect($redirect);
        }
    }

    /**
     * Whether there is a halt to answer — asked of the halt's own state, never
     * of the `show` flag.
     *
     * The flag is entangled with the dialog, and the browser sets it false as it
     * closes: by the time the confirm roundtrip lands, `show` is already false.
     * Gating the submit on it therefore refused every confirmation made by an
     * actual click, while passing a test that calls the method directly — which
     * is precisely how it got written.
     */
    protected function hasPendingHalt(): bool
    {
        return $this->getHaltModalData() !== [];
    }

    /**
     * Dismiss a halt without continuing.
     */
    public function closeHaltModal(): void
    {
        $this->clearHaltModalState();
        $this->clearHaltModalForm();
        $this->afterHaltClosed();
    }

    /**
     * Forget everything about the halt that just closed.
     *
     * Wholesale rather than key by key, so nothing outlives the modal it
     * belonged to: a leftover action name is a confirmation that could be
     * answered twice. A host keeping its halt somewhere shared — wire-table
     * keeps it inside the table's state object — overrides this to clear its own
     * keys instead of the bag.
     */
    protected function clearHaltModalState(): void
    {
        $this->mountedHalt = [];
    }

    /**
     * Everything the resume needs, read out of the state before it is cleared.
     *
     * @return array<string, mixed>
     */
    protected function haltResumeState(): array
    {
        return [
            'then' => $this->getHaltModalState('then'),
            'arguments' => (array) $this->getHaltModalState('arguments', []),
        ];
    }

    /**
     * Continue where the halt stopped.
     *
     * The default is the general case: call the named method with what the halt
     * collected. A host that halted an *action* overrides this to re-execute
     * that action with `$confirmed` true instead.
     *
     * @param  array<string, mixed>  $resume
     * @param  array<string, mixed>  $data
     */
    protected function resumeHalt(array $resume, array $data): void
    {
        $then = $resume['then'] ?? null;

        if (! is_string($then) || ! $this->isResumableHaltMethod($then)) {
            return;
        }

        $this->{$then}($data, (array) ($resume['arguments'] ?? []));
    }

    /**
     * Whether a name may be resumed into.
     *
     * **Public only**, and that is a boundary rather than a style rule. The
     * halt's state is a public Livewire property, so the browser can rewrite
     * `then` before confirming; resolving it through `method_exists()` alone
     * would have turned a confirmation into a way to call this component's
     * *protected* methods with an array of the caller's choosing. A public
     * method is one the browser can already call directly, so naming one here
     * grants nothing new — which is exactly the property that makes the
     * name-not-closure contract safe.
     */
    protected function isResumableHaltMethod(string $method): bool
    {
        if ($method === '' || str_starts_with($method, '__')) {
            return false;
        }

        if (! method_exists($this, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($this, $method);

        return $reflection->isPublic() && ! $reflection->isStatic();
    }

    /**
     * Anything the host needs to do once a halt is off the screen.
     */
    protected function afterHaltClosed(): void
    {
        // No-op by default.
    }

    // ==========================================
    // The form layer, when the host has one
    // ==========================================

    /**
     * Check the halt's own declared rules, and the form's if there is one.
     *
     * Errors are keyed by the state path the fields bind to, so a message lands
     * on the field that earned it rather than beside the modal.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     *
     * @throws ValidationException
     */
    protected function validateHaltData(array $data, array $rules = [], array $messages = [], array $attributes = []): void
    {
        if (method_exists($this, 'validateHaltModalForm')) {
            $this->validateHaltModalForm($data, $rules, $messages, $attributes);

            return;
        }

        if ($rules === []) {
            return;
        }

        $result = app(ValidationPipeline::class)->validate($data, $rules, $messages, $attributes);

        if (! $result->failed()) {
            return;
        }

        $statePath = $this->haltStatePath();
        $scoped = [];

        foreach ($result->errors() as $field => $errors) {
            $scoped["{$statePath}.{$field}"] = $errors;
        }

        throw ValidationException::withMessages($scoped);
    }

    /**
     * Let the fields shape what they collected, when there are fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dehydrateHaltData(array $data): array
    {
        if (method_exists($this, 'dehydrateHaltModalFormData')) {
            return $this->dehydrateHaltModalFormData($data, $this->resolveHaltRecord());
        }

        return $data;
    }

    /**
     * Where the halt's data bag lives in component state, for scoping errors.
     */
    protected function haltStatePath(): string
    {
        return method_exists($this, 'haltModalFormStatePath')
            ? $this->haltModalFormStatePath()
            : 'mountedHalt.formData';
    }

    /**
     * Give the form layer a chance to attach a Form instance to the halt.
     */
    protected function resolveHaltModalForm(ActionHalt $halt): void
    {
        // No-op — the wire-forms bridge overrides this.
    }

    /**
     * Drop the resolved form instance and the copy parked for the next request.
     */
    protected function clearHaltModalForm(): void
    {
        if (property_exists($this, 'haltModalFormInstance')) {
            $this->haltModalFormInstance = null;
        }

        if (method_exists($this, 'forgetHaltForm')) {
            $this->forgetHaltForm();
        }
    }

    /**
     * The record a halt's fields shape their data against, when there is one.
     */
    protected function resolveHaltRecord(): ?Model
    {
        return null;
    }
}
