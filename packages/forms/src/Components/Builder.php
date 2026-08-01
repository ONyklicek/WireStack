<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireForms\Contracts\HasValidation;
use NyonCode\WireForms\Exceptions\FormConfigurationException;

/**
 * Block builder for heterogeneous content: a list of items where each item picks
 * its own {@see Block} type and is edited with that block's schema.
 *
 * Where a Repeater repeats one schema, a Builder chooses among several — the
 * shape behind a page builder or a rich content field. Each stored item is
 * `['type' => <block name>, 'data' => [...]]`, and the item's fields bind under
 * `<statePath>.<index>.data`, so a block's schema needs no knowledge of its
 * position.
 *
 * It *is* a Repeater — same add/remove/reorder endpoints, same per-item cloning,
 * and the same treatment by the form runtime (reactivity, flattening, save) —
 * specialised on where an item's schema comes from. Only `relationship()` does
 * not apply: mixed block types have no single related model, so a builder is
 * stored as an array (a JSON column).
 *
 * Usage:
 *   Builder::make('content')
 *       ->blocks([
 *           Block::make('heading')->icon('h1')->schema([TextInput::make('text')]),
 *           Block::make('paragraph')->schema([Textarea::make('body')]),
 *       ])
 *       ->reorderable()
 */
class Builder extends Repeater
{
    /** @var array<int, Block> */
    protected array $blocks = [];

    /**
     * Declare the block types this builder can place.
     *
     * @param  array<int, Block>  $blocks
     */
    public function blocks(array $blocks): static
    {
        $this->blocks = $blocks;

        return $this;
    }

    /**
     * @return array<int, Block>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getBlock(string $name): ?Block
    {
        foreach ($this->blocks as $block) {
            if ($block->getName() === $name) {
                return $block;
            }
        }

        return null;
    }

    public function getAddButtonLabel(): string
    {
        return $this->addButtonLabel ?? __('Add block');
    }

    /**
     * The stored type of the item at this index, or null when it has none.
     *
     * @param  array<string, mixed>|mixed  $item
     */
    public function getItemType(mixed $item): ?string
    {
        $type = is_array($item) ? ($item['type'] ?? null) : null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    /**
     * State path of one item's block data.
     *
     * The fields live under `.data` so the item's `type` discriminator cannot
     * collide with a field named `type` inside a block.
     */
    public function getItemStatePath(int $index): string
    {
        return "{$this->getStatePath()}.{$index}.data";
    }

    /**
     * The block schema for one item, bound to that item's state path.
     *
     * An item whose stored type names no declared block yields an empty schema
     * rather than throwing: stored content outlives the code that declared it,
     * and a renamed or removed block must not make the whole form unrenderable.
     *
     * @return array<int, Component|LayoutComponent>
     */
    public function getItemSchema(int $index, ?string $blockName = null): array
    {
        $block = $blockName !== null ? $this->getBlock($blockName) : null;

        if ($block === null) {
            return [];
        }

        return $this->cloneSchemaForItem($block->getSchema(), $this->getItemStatePath($index));
    }

    /**
     * Per-item child rules across every block, keyed under the item's `data`
     * envelope so the resolver mounts them at `<path>.*.data.<field>`.
     *
     * Blocks sharing a field name share its rules — the resolver validates by
     * wildcard path, which cannot tell one block's `text` from another's. Rules
     * are therefore only as strict as the loosest block declaring that name;
     * name fields distinctly where blocks must validate differently.
     *
     * @return array<string, array<int, mixed>>
     */
    public function getItemValidationRules(): array
    {
        $rules = [];

        foreach ($this->blocks as $block) {
            foreach ($this->collectBlockRules($block->getSchema()) as $field => $fieldRules) {
                $rules["data.{$field}"] ??= $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * @param  array<int, Component|LayoutComponent>  $components
     * @return array<string, array<int, mixed>>
     */
    private function collectBlockRules(array $components): array
    {
        $rules = [];

        foreach ($components as $component) {
            if ($component instanceof LayoutComponent) {
                $rules = array_merge($rules, $this->collectBlockRules($component->getSchema()));

                continue;
            }

            if ($component instanceof HasValidation) {
                $componentRules = $component->getValidationRules();

                // Fall back to ['nullable'] like the repeater does: a child with no
                // rules still needs a wildcard entry, or Livewire's validate() omits
                // it from the validated data and the value is silently dropped.
                $rules[$component->getName()] = $componentRules !== [] ? $componentRules : ['nullable'];
            }
        }

        return $rules;
    }

    /**
     * The table layout does not apply: it lays one schema out as columns, and a
     * builder's items each carry a different block's schema, so there is no
     * shared set of columns to head. Inherited from Repeater, this would have
     * accepted the flag and rendered the builder view regardless.
     */
    public function table(bool $condition = true): static
    {
        throw FormConfigurationException::builderHasNoTableLayout($this->getName());
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.builder';
    }
}
