<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Panels\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireCore\Panels\Components\EditableEntry;
use NyonCode\WireCore\Panels\Panel;
use Throwable;

/**
 * Host trait for a Livewire component that renders an editable panel.
 *
 * Provides {@see updatePanelEntry()} — the write endpoint the shared
 * `wireEditableCell` Alpine engine commits to (via `commitMethod:
 * 'updatePanelEntry'`). It mirrors the table's `updateTableCell` contract
 * (optimistic UI + optimistic locking) but for a single bound record:
 *
 *  - The record is resolved server-authoritatively from the host's own panel
 *    ({@see Panel()}), never from the client. The client-supplied key must match
 *    the bound record, so a component that shows one record can only ever write
 *    to that record.
 *  - Only entries declared as {@see EditableEntry} in the schema are writable —
 *    this is the write whitelist, blocking arbitrary attribute writes.
 *  - The write happens inside a locked transaction with an updated_at version
 *    check, so a stale client loses the race and reconciles inline.
 *
 * @phpstan-require-extends Component
 */
trait WithEditablePanel
{
    /**
     * The panel rendered by this host, with its record bound. Implemented by the
     * concrete component.
     */
    abstract public function panel(): Panel;

    /**
     * Persist a single editable entry's value.
     *
     * @param  mixed  $recordKey  Primary key the client believes it is editing
     * @param  string  $entryName  The editable entry (attribute) name
     * @param  mixed  $value  The new value
     * @param  string|null  $recordVersion  updated_at timestamp when the client loaded the value
     * @return array{success: bool, message?: string, errors?: array<int, string>, conflict?: bool, currentValue?: string, currentVersion?: string, version?: string|null}
     */
    public function updatePanelEntry(mixed $recordKey, string $entryName, mixed $value, ?string $recordVersion = null): array
    {
        // Do not re-render: a DOM morph would destroy the cell's Alpine state.
        // The cell switches optimistically and reconciles from the response.
        if (method_exists($this, 'skipRender')) {
            $this->skipRender();
        }

        $panel = $this->panel();
        $entry = $this->findEditableEntry($panel->getSchema(), $entryName);

        if (! $entry instanceof EditableEntry) {
            return ['success' => false, 'message' => __('wire-core::messages.entry_not_editable')];
        }

        // ── Permission (record-independent, read-only). Canonical fail-CLOSED Gate
        // check (HasAuthorization::isAuthorized) — the previous `method_exists(...,
        // 'hasPermissionTo')` probe failed OPEN for user models without that method,
        // silently bypassing `->permission()`. Returns true when no permission is set. ──
        if (! $entry->isAuthorized()) {
            return ['success' => false, 'message' => __('wire-core::messages.no_permission')];
        }

        // ── Resolve the record from the host, not the client ──
        $bound = $panel->getRecord();
        if (! $bound instanceof Model) {
            return ['success' => false, 'message' => __('wire-core::messages.record_not_found')];
        }

        // A component shows exactly one record; reject a mismatched client key.
        if ((string) $bound->getKey() !== (string) $recordKey) {
            return ['success' => false, 'message' => __('wire-core::messages.record_not_found')];
        }

        $key = $bound->getKey();

        $value = $entry->formatForSave($value);

        // ── Pre-validate (no DB writes) ──
        $rules = $entry->getEditableRules();
        if (! empty($rules)) {
            $validator = validator([$entryName => $value], [$entryName => $rules]);
            if ($validator->fails()) {
                $errors = array_values($validator->errors()->get($entryName));

                return [
                    'success' => false,
                    'message' => $errors[0] ?? __('wire-core::messages.validation_failed'),
                    'errors' => $errors,
                ];
            }
        }

        try {
            $result = DB::transaction(function () use ($bound, $key, $entry, $entryName, $value, $recordVersion) {
                $record = $bound->newQuery()->whereKey($key)->lockForUpdate()->first();

                if (! $record instanceof Model) {
                    return ['success' => false, 'message' => __('wire-core::messages.record_not_found')];
                }

                if (! $entry->canEdit($record)) {
                    return ['success' => false, 'message' => __('wire-core::messages.no_permission_edit')];
                }

                // ── Optimistic locking ──
                $version = app(RecordVersion::class);

                if ($version->conflicts($record, $recordVersion)) {
                    return [
                        'success' => false,
                        'message' => __('wire-core::messages.record_conflict'),
                        'conflict' => true,
                        'currentValue' => (string) (data_get($record, $entryName) ?? ''),
                        'currentVersion' => $version->stamp($record),
                    ];
                }

                if ($entry->getSaveCallback()) {
                    call_user_func($entry->getSaveCallback(), $record, $value, $entry);
                } else {
                    $record->{$entryName} = $value;
                    $record->save();
                }

                $record->refresh();

                return ['success' => true, 'version' => $version->stamp($record), 'record' => $record, 'value' => $value];
            });

            if (($result['success'] ?? false) === true) {
                $record = $result['record'] ?? null;
                if ($record instanceof Model && $entry->getAfterStateUpdatedCallback()) {
                    call_user_func($entry->getAfterStateUpdatedCallback(), $record, $result['value'] ?? $value);
                }
                unset($result['record'], $result['value']);
            }

            return $result;
        } catch (Throwable $e) {
            return ['success' => false, 'message' => __('wire-core::messages.save_error', ['error' => $e->getMessage()])];
        }
    }

    /**
     * Depth-first search for an editable entry by name, recursing through layout
     * components. Returns null for unknown or read-only entry names.
     *
     * @param  array<int, mixed>  $components
     */
    protected function findEditableEntry(array $components, string $name): ?EditableEntry
    {
        foreach ($components as $component) {
            if ($component instanceof LayoutComponent) {
                $found = $this->findEditableEntry($component->getSchema(), $name);
                if ($found !== null) {
                    return $found;
                }
            } elseif ($component instanceof EditableEntry && $component->getName() === $name) {
                return $component;
            }
        }

        return null;
    }
}
