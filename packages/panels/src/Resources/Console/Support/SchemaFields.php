<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * A model's columns, read off its table, as the lines a generated resource
 * starts from — a form field, a table column and an infolist entry per column.
 *
 * A starting point and nothing more: the generator writes these once, and the
 * resource is the application's from then on. So the mapping is deliberately
 * plain — the type decides the component, nullability decides `required()` —
 * and what cannot be read (no table yet, no connection) yields no lines rather
 * than an error, because an empty resource is still a correct one to write.
 */
final class SchemaFields
{
    /** Columns a person never types into, and a list rarely shows. */
    private const SKIPPED = ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, array{name: string, kind: string, required: bool}>
     */
    public function columns(string $modelClass): array
    {
        try {
            /** @var Model $model */
            $model = new $modelClass;
            $columns = Schema::connection($model->getConnectionName())->getColumns($model->getTable());
        } catch (Throwable) {
            // No table, no connection, no class: the resource is written without
            // generated lines, and the command says so.
            return [];
        }

        $key = $model->getKeyName();
        $fields = [];

        foreach ($columns as $column) {
            $name = $column['name'];

            if ($name === $key || in_array($name, self::SKIPPED, true)) {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'kind' => $this->kind($column['type_name'], $column['type']),
                'required' => ! $column['nullable'] && $column['default'] === null,
            ];
        }

        return $fields;
    }

    /**
     * The form field, table column and infolist entry for each column.
     *
     * @param  array<int, array{name: string, kind: string, required: bool}>  $columns
     * @return array{fields: array<int, string>, columns: array<int, string>, entries: array<int, string>, imports: array<int, string>}
     */
    public function lines(array $columns): array
    {
        $fields = $tableColumns = $entries = $imports = [];

        foreach ($columns as $column) {
            $name = var_export($column['name'], true);
            $required = $column['required'] ? '->required()' : '';

            [$field, $fieldClass] = match ($column['kind']) {
                'boolean' => ["Toggle::make({$name})", 'NyonCode\\WireForms\\Components\\Toggle'],
                'date' => ["DateTimePicker::make({$name})->asDate(){$required}", 'NyonCode\\WireForms\\Components\\DateTimePicker'],
                'datetime' => ["DateTimePicker::make({$name}){$required}", 'NyonCode\\WireForms\\Components\\DateTimePicker'],
                'text' => ["Textarea::make({$name}){$required}", 'NyonCode\\WireForms\\Components\\Textarea'],
                'number' => ["TextInput::make({$name})->numeric(){$required}", 'NyonCode\\WireForms\\Components\\TextInput'],
                default => ["TextInput::make({$name}){$required}", 'NyonCode\\WireForms\\Components\\TextInput'],
            };

            [$tableColumn, $columnClass] = match ($column['kind']) {
                'boolean' => ["BooleanColumn::make({$name})", 'NyonCode\\WireTable\\Columns\\BooleanColumn'],
                'date' => ["TextColumn::make({$name})->date()->sortable()", 'NyonCode\\WireTable\\Columns\\TextColumn'],
                'datetime' => ["TextColumn::make({$name})->dateTime()->sortable()", 'NyonCode\\WireTable\\Columns\\TextColumn'],
                'text' => ["TextColumn::make({$name})->limit(50)", 'NyonCode\\WireTable\\Columns\\TextColumn'],
                default => ["TextColumn::make({$name})->searchable()->sortable()", 'NyonCode\\WireTable\\Columns\\TextColumn'],
            };

            [$entry, $entryClass] = match ($column['kind']) {
                'boolean' => ["BooleanEntry::make({$name})", 'NyonCode\\WireCore\\Infolists\\Components\\BooleanEntry'],
                'date' => ["TextEntry::make({$name})->date()", 'NyonCode\\WireCore\\Infolists\\Components\\TextEntry'],
                'datetime' => ["TextEntry::make({$name})->dateTime()", 'NyonCode\\WireCore\\Infolists\\Components\\TextEntry'],
                default => ["TextEntry::make({$name})", 'NyonCode\\WireCore\\Infolists\\Components\\TextEntry'],
            };

            $fields[] = $field.',';
            $tableColumns[] = $tableColumn.',';
            $entries[] = $entry.',';
            array_push($imports, $fieldClass, $columnClass, $entryClass);
        }

        return ['fields' => $fields, 'columns' => $tableColumns, 'entries' => $entries, 'imports' => array_values(array_unique($imports))];
    }

    /** The component family a database type belongs to. */
    private function kind(string $typeName, string $type): string
    {
        $typeName = Str::lower($typeName);

        return match (true) {
            in_array($typeName, ['bool', 'boolean'], true), Str::lower($type) === 'tinyint(1)' => 'boolean',
            $typeName === 'date' => 'date',
            in_array($typeName, ['datetime', 'datetimetz', 'timestamp', 'timestamptz'], true) => 'datetime',
            in_array($typeName, ['text', 'mediumtext', 'longtext', 'json', 'jsonb'], true) => 'text',
            in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'numeric', 'float', 'double', 'real'], true) => 'number',
            default => 'string',
        };
    }
}
