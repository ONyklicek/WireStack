<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Exceptions\DocumentNotFoundException;
use NyonCode\WireBoost\Support\Docs\DocsCorpus;
use NyonCode\WireBoost\Support\Docs\DocSection;
use NyonCode\WireBoost\Support\Docs\DocsIndex;

#[Name('fetch-wire-doc')]
#[Description('Read wireStack documentation by id. Pass a section id from search-wire-docs ("docs/table/columns/index.md#shared-column-api") to read that section in full, or a document id ("docs/table/columns/index.md") to get its outline. Add full=true to read a whole document.')]
class FetchDoc extends BoostTool
{
    public function __construct(
        private DocsIndex $docs,
        private DocsCorpus $corpus,
    ) {}

    protected function run(Request $request): Response
    {
        $id = trim((string) $request->get('id'));
        $full = (bool) $request->get('full');

        if (str_contains($id, '#') && ! $full) {
            return $this->section($id);
        }

        // full=true on a section id means "the page this section is on".
        $document = str_contains($id, '#') ? (string) strstr($id, '#', true) : $id;

        return $this->document($document, $full);
    }

    private function section(string $id): Response
    {
        $section = $this->docs->section($id);

        if (! $section instanceof DocSection) {
            throw DocumentNotFoundException::section($id);
        }

        return $this->json(array_merge($section->toArray(), [
            'content' => $section->markdown(),
            'siblings' => $this->outline($section->document),
        ]));
    }

    private function document(string $id, bool $full): Response
    {
        $sections = $this->docs->document($id);

        if ($sections === []) {
            throw DocumentNotFoundException::document($id);
        }

        $payload = [
            'document' => $id,
            'title' => $sections[0]->title,
            'package' => $sections[0]->package,
            'sections' => $this->outline($id),
        ];

        if (! $full) {
            // A whole page runs to tens of kilobytes; an outline plus targeted
            // section fetches is cheaper and usually more accurate.
            $payload['hint'] = 'Outline only. Fetch a section id for its content, or pass full=true for the entire document.';

            return $this->json($payload);
        }

        $payload['content'] = $this->corpus->read($id);

        return $this->json($payload);
    }

    /**
     * @return array<int, array{id: string, heading: string, level: int}>
     */
    private function outline(string $document): array
    {
        $outline = [];

        foreach ($this->docs->document($document) as $section) {
            if ($section->heading === '') {
                continue;
            }

            $outline[] = [
                'id' => $section->id,
                'heading' => $section->heading,
                'level' => $section->level,
            ];
        }

        return $outline;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('A section id ("docs/core/actions.md#confirmation") or a document id ("docs/core/actions.md").')
                ->required(),
            'full' => $schema->boolean()
                ->description('Return the entire document instead of a single section or an outline.'),
        ];
    }
}
