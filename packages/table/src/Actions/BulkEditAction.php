<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireTable\Table;

/**
 * Set one or more fields to the same value on every selected record — "move
 * these 40 invoices to Paid", "assign these tickets to Eva".
 *
 * Each field you list is drawn behind a switch, "Change Status", and only the
 * switched-on fields are written: a bulk edit that also wrote every field it
 * showed would blank whatever the user left alone. The rest of the record is
 * untouched.
 *
 * Records are saved one by one through Eloquent, so casts, observers, the audit
 * trail and optimistic locking all see an ordinary save. A record the user may
 * not update — a policy with an `update` method that says no — is skipped and
 * counted, never written; the notification says how many were changed and how
 * many were not.
 *
 * The selection arrives as the table's capped collection
 * ({@see Table::bulkMaxRecords()}).
 *
 * @phpstan-consistent-constructor
 */
class BulkEditAction extends BulkAction
{
    /** @var list<Field> */
    protected array $editableFields = [];

    /** @var list<Field> The switches and fields the modal draws, in order. */
    protected array $formSchema = [];

    public function __construct(string $name = 'bulkEdit')
    {
        parent::__construct($name);

        $this->label(Trans::get('wire-table::messages.bulk_edit_label'))
            ->icon('pencil-square')
            ->modalHeading(Trans::get('wire-table::messages.bulk_edit_heading'))
            ->modalSubmitActionLabel(Trans::get('wire-table::messages.bulk_edit_submit'))
            ->action(fn (Collection $records, array $data): array => $this->applyTo($records, $data));
    }

    public static function make(string $name = 'bulkEdit'): static
    {
        return new static($name);
    }

    /**
     * The fields the user may set, each behind its own "Change …" switch.
     *
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): static
    {
        $this->editableFields = array_values($fields);
        $schema = [];

        foreach ($this->editableFields as $field) {
            $switch = self::switchFor($field->getName());

            $schema[] = Toggle::make($switch)
                ->label(Trans::get('wire-table::messages.bulk_edit_change', ['field' => (string) $field->getLabel()]))
                ->live();
            $schema[] = $field->visible(static fn ($get): bool => (bool) $get($switch));
        }

        $this->formSchema = $schema;

        return $this->form($schema);
    }

    /** @return list<Field> */
    public function getEditableFields(): array
    {
        return $this->editableFields;
    }

    /** @return list<Field> */
    public function getFormSchema(): array
    {
        return $this->formSchema;
    }

    /**
     * The attributes the form asked to change: the switched-on fields, and
     * nothing that was not listed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function changesFrom(array $data): array
    {
        $changes = [];

        foreach ($this->editableFields as $field) {
            $name = $field->getName();

            if (($data[self::switchFor($name)] ?? false) && array_key_exists($name, $data)) {
                $changes[$name] = $data[$name];
            }
        }

        return $changes;
    }

    /**
     * Write the changes to each record the user may update.
     *
     * @param  Collection<int, mixed>  $records
     * @param  array<string, mixed>  $data
     * @return array{updated: int, skipped: int}
     */
    public function applyTo(Collection $records, array $data): array
    {
        $changes = $this->changesFrom($data);
        $updated = 0;
        $skipped = 0;

        if ($changes === []) {
            return ['updated' => 0, 'skipped' => 0];
        }

        foreach ($records as $record) {
            if (! $record instanceof Model || ! self::mayUpdate($record)) {
                $skipped++;

                continue;
            }

            $record->forceFill($changes)->save();
            $updated++;
        }

        $message = trans_choice('wire-table::messages.bulk_edit_done', $updated, ['count' => $updated]);

        if ($skipped > 0) {
            $message .= ' '.trans_choice('wire-table::messages.bulk_edit_skipped', $skipped, ['count' => $skipped]);
        }

        $this->successNotification($message);

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /** Asked only where a policy answers `update`: no policy, no opinion. */
    private static function mayUpdate(Model $record): bool
    {
        $policy = Gate::getPolicyFor($record);

        if ($policy === null || ! method_exists($policy, 'update')) {
            return true;
        }

        return Gate::allows('update', $record);
    }

    /** The switch that says a field is to be written. */
    private static function switchFor(string $field): string
    {
        return '__change_'.str_replace('.', '__', $field);
    }
}
