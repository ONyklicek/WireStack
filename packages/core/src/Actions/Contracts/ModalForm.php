<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Contracts;

use Illuminate\Contracts\Support\Htmlable;
use Livewire\Component;

/**
 * The form surface an action modal needs, owned by wire-core.
 *
 * wire-core drives the action/modal lifecycle (build a form from a schema, bind
 * it to a state path and Livewire component, seed and validate it, render it in
 * the modal) but must NOT depend on wire-forms — the dependency graph runs
 * `wire-sortable -> wire-table -> wire-forms -> wire-core`. This contract is the
 * canonical seam: core type-hints and calls only these methods, wire-forms'
 * `Form` implements them, and the concrete instance is produced by a
 * {@see ModalFormFactory} resolved from the container. Core never names `Form`.
 *
 * Deliberately narrow: exactly what core production code and the generic action
 * modal runtime call — not the full wire-forms `Form` API (no `model()`,
 * `save()`, wizards, or `getFlatComponents()`). Extends {@see Htmlable} so a
 * modal body can render it with `{{ $formInstance }}`.
 */
interface ModalForm extends Htmlable
{
    /** Bind the form's fields to a Livewire state path (per modal-stack depth). */
    public function statePath(string $path): static;

    /** Bind the form to its host Livewire component. */
    public function livewire(Component $component): static;

    /**
     * Seed the form's fields with default values.
     *
     * @param  array<string, mixed>  $data
     */
    public function fill(array $data): static;

    /**
     * The form's initial (default) state, used to seed modal form data.
     *
     * @return array<string, mixed>
     */
    public function getInitialState(): array;

    /**
     * Validate the bound state, returning the validated data.
     *
     * @return array<string, mixed>
     */
    public function validate(): array;
}
