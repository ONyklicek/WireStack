<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Binds an owner's options to an Eloquent relationship, labelled by one attribute.
 *
 * The canonical owner of the `relationship($name, $titleAttribute)` pair. It is
 * pure configuration: the trait holds the relationship name and the attribute the
 * related rows are labelled by, and nothing else. Loading the options is left to
 * each consumer, because the query differs per surface — a form field can search
 * and paginate remotely, while a table cell loads the list once per render and
 * reuses it for every row (see WireTable\Columns\SelectColumn).
 *
 * Consumers: WireForms Select (and its BelongsToSelect subclass), WireForms Tags,
 * WireTable SelectColumn.
 *
 * Deliberately *not* used by:
 *  - WireForms Repeater — its `relationship()` binds child rows to a hasMany for
 *    saving, has no title attribute, and would gain a dead `getTitleAttribute()`.
 *  - WireForms MorphToSelect\Type — one type per morph target, so the model class
 *    replaces the relationship name and `titleAttribute()` is a setter of its own.
 *
 * `getRelationship()` returns the *name*, matching the field vocabulary the forms
 * API has always used. WireTable\RelationManagers\RelationManager is the one place
 * that reads `getRelationship()` as a live Relation instance — it configures a
 * whole table around a parent record rather than a list of options, and owns that
 * pair (`getRelationshipName()` / `getRelationship()`) itself.
 */
trait HasRelationship
{
    protected ?string $relationship = null;

    protected ?string $titleAttribute = null;

    /** Source the options from an Eloquent relationship, labelled by `$titleAttribute`. */
    public function relationship(?string $name, ?string $titleAttribute = null): static
    {
        $this->relationship = $name;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    /** The configured relationship name, or null when the options are not relation-backed. */
    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    /** The attribute on the related model that labels each option. */
    public function getTitleAttribute(): ?string
    {
        return $this->titleAttribute;
    }
}
