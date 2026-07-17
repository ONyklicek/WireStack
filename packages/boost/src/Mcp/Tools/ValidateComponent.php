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
#[Description('Build a wire table/form/infolist component and check it against the canonical vocabularies: unknown colors (which silently render gray), unregistered icons, and column/field names the model cannot resolve (which silently render blank). Run this after writing or editing a wire component — these faults do not throw and do not fail a render test.')]
class ValidateComponent extends BoostTool
{
    public function __construct(private ComponentValidator $validator) {}

    protected function run(Request $request): Response
    {
        return $this->json($this->validator->validate((string) $request->get('component')));
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
        ];
    }
}
