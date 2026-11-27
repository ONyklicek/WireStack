<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\Validation\ComponentValidator;

#[Name('validate-wire-component')]
#[Description('Build a wire table/form/infolist component, or a page that hosts actions, and check it against the canonical vocabularies: unknown colors (which silently render gray), unregistered icons, column names the model cannot resolve (which silently render blank) and aggregates over relations that do not exist. Every action\'s modal form is built and checked too — row, bulk, header and page actions, groups and wizard steps. Pass `record` for a view or edit page, and `action` to check a single action. Run this after writing or editing a wire component — these faults do not throw and do not fail a render test.')]
class ValidateComponent extends BoostTool
{
    public function __construct(private ComponentValidator $validator) {}

    protected function run(Request $request): Response
    {
        $record = $request->get('record');
        $action = $request->get('action');

        return $this->json($this->validator->validate(
            (string) $request->get('component'),
            $record === null || $record === '' ? null : $record,
            is_string($action) && $action !== '' ? $action : null,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'component' => $schema->string()
                ->description('Fully-qualified class name of the Livewire component, e.g. "App\\\\Livewire\\\\UsersTable".')
                ->required(),
            'record' => $schema->string()
                ->description('Key of the record a view or edit page shows. Set on the page\'s public $record before anything is built; without it a record page cannot build its form or infolist.'),
            'action' => $schema->string()
                ->description('Name of one action to check (its icon, color and modal form). Omit to check every action.'),
        ];
    }
}
