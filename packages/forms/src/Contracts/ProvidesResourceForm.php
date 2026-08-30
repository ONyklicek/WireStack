<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Contracts;

use NyonCode\WireForms\Forms\Form;

/**
 * A resource that can create and edit its records.
 *
 * Lives in wire-forms, with the `Form` it names, so a resource that has a form
 * and no list needs wire-forms and nothing else — the identity half is in
 * wire-core, which wire-forms already requires.
 *
 * One schema serves both pages deliberately — a create form and an edit form
 * that drift apart is the bug this shape prevents; where they must differ, the
 * page passes a form the resource then shapes, rather than the resource
 * declaring two.
 *
 * Persistence is the form's, not the resource's: `Form` already owns the save
 * lifecycle, and a non-Eloquent resource writes through `Form::using()` (ADR
 * 0020 Q3 — a `DataSource` write contract stays out of V2.3).
 */
interface ProvidesResourceForm
{
    public function form(Form $form): Form;
}
