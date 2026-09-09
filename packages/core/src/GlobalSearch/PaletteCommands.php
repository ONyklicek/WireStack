<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Contracts\ClassifiesComponentActions;
use NyonCode\WireCore\Foundation\Contracts\ProvidesCommands;
use NyonCode\WireCore\Foundation\Registration\Catalog;

/**
 * Actions the palette may offer, from everything that registered any.
 *
 * Reads the {@see Catalog} exactly as the record search does, so a module gains
 * commands the moment it is registered and there is no second list to fall out
 * of date. {@see ProvidesCommands} is the opt-in, and it lives in Foundation
 * precisely so this class can name it: `GlobalSearch` and `Actions` are sibling
 * L2 modules, and a contract owned by `Actions` would have been unreadable here.
 *
 * ## What it refuses to list
 *
 * Anything the user may not run. {@see ClassifiesComponentActions::isRunnable()}
 * is `canExecute()` on the far side of the container — visibility *and*
 * authorization — and it is asked before a row exists, not when one is clicked.
 * A palette that listed the label of a forbidden action would have leaked it
 * whether or not the click was refused afterwards, which is the same rule the
 * record search follows with policies.
 *
 * Actions that would open a modal are **not** filtered out here. The palette
 * cannot host a modal, but it can hand one to something that can, so the
 * decision belongs at the point of selection rather than at the point of
 * listing — otherwise the offer would change depending on which page ⌘K was
 * opened on.
 */
class PaletteCommands
{
    /** The reserved group key standalone commands are collected under. */
    public const GROUP = 'palette:commands';

    /** The reserved group key a drilled-into record's actions are collected under. */
    public const RECORD_GROUP = 'palette:record-actions';

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ClassifiesComponentActions $classifier,
    ) {}

    /**
     * Standalone commands whose label matches the term.
     *
     * @return array<int, GlobalSearchResult>
     */
    public function search(string $term): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $rows = [];

        foreach ($this->catalog->implementing(ProvidesCommands::class) as $key => $owner) {
            foreach ($owner::commands() as $action) {
                $label = $this->label($action);

                if (mb_stripos($label, $term) === false || ! $this->classifier->isRunnable($action)) {
                    continue;
                }

                $rows[] = $this->row($key, $label, $action, PaletteRowKind::Command);
            }
        }

        return $rows;
    }

    /**
     * Every action one record offers, unfiltered by any term.
     *
     * The second level of the palette: a user who has already picked a row is
     * asking "what can I do with this", and filtering that by whatever they typed
     * to *find* the record would hide most of the answer.
     *
     * The record is passed to `commands()` and then again to `isRunnable()`,
     * because an action's own `visible()` closure is usually about the record —
     * "cancel, if it is not already cancelled" — and a context-free check would
     * answer the same for every row.
     *
     * @return array<int, GlobalSearchResult>
     */
    public function forRecord(string $ownerKey, object $record): array
    {
        $owner = $this->catalog->find($ownerKey);

        if ($owner === null || ! is_subclass_of($owner, ProvidesCommands::class)) {
            return [];
        }

        $rows = [];

        foreach ($owner::commands($record) as $action) {
            if (! $this->classifier->isRunnable($action, $record)) {
                continue;
            }

            $rows[] = $this->row(
                $ownerKey,
                $this->label($action),
                $action,
                PaletteRowKind::RecordAction,
                recordKey: $record instanceof Model ? $record->getKey() : null,
            );
        }

        return $rows;
    }

    /**
     * What the user reads for this action.
     *
     * `getLabel()` when the action carries one, otherwise the headline of its
     * name — the same fallback `HasLabel` applies everywhere else, so an action
     * that never named itself reads as "Recount stock" rather than "recount".
     * Asked through `method_exists` because {@see ActionContract} promises only a
     * name, and this may be handed a foreign implementation.
     */
    protected function label(ActionContract $action): string
    {
        if (method_exists($action, 'getLabel')) {
            $label = $action->getLabel();

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return $action->getName();
    }

    protected function row(
        string $ownerKey,
        string $label,
        ActionContract $action,
        PaletteRowKind $kind,
        int|string|null $recordKey = null,
    ): GlobalSearchResult {
        return new GlobalSearchResult(
            resourceKey: $ownerKey,
            recordKey: $recordKey,
            title: $label,
            subtitle: null,
            url: null,
            icon: method_exists($action, 'getIcon') ? $action->getIcon() : null,
            kind: $kind,
            actionName: $action->getName(),
        );
    }
}
