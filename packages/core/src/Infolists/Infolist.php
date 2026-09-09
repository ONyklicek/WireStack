<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Core\Plugin\HookDispatch;
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\HookTarget;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Infolists\Components\Entry;

/**
 * Public infolist API — a declarative, read-only display of a single record.
 *
 * Mirrors the wire-forms Form ergonomics (schema of sections/grids +
 * components) but renders {@see Entry} components instead of editable fields.
 * Lives in core next to Widgets because it is a display assembly, and depends
 * only on the shared schema layout + entries.
 *
 * @phpstan-consistent-constructor
 */
class Infolist implements Htmlable
{
    protected mixed $record = null;

    /** @var array<int, Entry|LayoutComponent> */
    protected array $schema = [];

    /**
     * The schema after anything installed has had its say, memoized.
     *
     * Memoized because `getSchema()` is read more than once per render — the
     * action runtime walks it to find an infolist action, and `toHtml()` reads it
     * again — and a hook that fired per read would append the same entry twice on
     * the second one.
     *
     * @var array<int, Entry|LayoutComponent>|null
     */
    protected ?array $configured = null;

    /** The host component this renders in, when it renders in one. */
    protected mixed $livewireComponent = null;

    /** @var int|array<string|int, int|string> */
    protected int|array $columns = 1;

    public static function make(): static
    {
        return new static;
    }

    public function record(mixed $record): static
    {
        $this->record = $record;

        return $this;
    }

    public function getRecord(): mixed
    {
        return $this->record;
    }

    /**
     * Bind a plain array of data as the record.
     *
     * @param  array<string, mixed>  $data
     */
    public function state(array $data): static
    {
        return $this->record($data);
    }

    /**
     * @param  array<int, Entry|LayoutComponent>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        // A re-declared schema is a different infolist, so the configured copy
        // stops being an answer to it.
        $this->configured = null;

        return $this;
    }

    /**
     * The schema, after anything installed has had its say.
     *
     * @return array<int, Entry|LayoutComponent>
     */
    public function getSchema(): array
    {
        return $this->configured ??= $this->configuredSchema();
    }

    /**
     * Bind the host component this infolist renders through.
     *
     * @docs-ignore Plumbing, not configuration: a resource page calls it while
     * building the infolist, so a hook can be scoped to the resource that page
     * shows. Nothing an owner writes calls it.
     */
    public function livewireComponent(mixed $component): static
    {
        $this->livewireComponent = $component;

        return $this;
    }

    public function getLivewireComponent(): mixed
    {
        return $this->livewireComponent;
    }

    /**
     * Set the column grid the infolist lays its top-level components out in.
     *
     * An int reflows mobile-first; a per-breakpoint map — `['default' => 1,
     * 'md' => 2, 'xl' => 4]` — says it exactly. The same vocabulary
     * {@see Grid} and Section take,
     * because an author who learned it once should not learn it twice.
     *
     * @param  int|array<string|int, int|string>  $columns
     */
    public function columns(int|array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @return int|array<string|int, int|string>
     */
    public function getColumns(): int|array
    {
        return $this->columns;
    }

    public function toHtml(): string
    {
        $components = $this->getSchema();

        $this->prepareComponents($components);

        return view('wire-core::infolists.infolist', [
            'components' => $components,
            'columns' => $this->columns,
        ])->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Let anything installed add to this infolist before it is read.
     *
     * The read-only counterpart of `form.configuring`, and it shipped once the
     * module packages did: four of them send five resources with a detail page,
     * each composed inside the package, so the key that resource registered under
     * is the only handle an application has on it. The host supplies that key —
     * see {@see livewireComponent()}.
     *
     * A record that is a plain array carries no model to scope by, which is why
     * the target is asked for one only when there is an object to ask about.
     *
     * @return array<int, Entry|LayoutComponent>
     */
    protected function configuredSchema(): array
    {
        $payload = HookDispatch::typed(Hook::InfolistConfiguring, fn () => new InfolistConfiguringPayload(
            infolist: $this,
            schema: $this->schema,
            target: HookTarget::for(
                'infolist',
                $this->livewireComponent,
                is_object($this->record) ? $this->record : null,
            ),
        ));

        if ($payload === null) {
            return $this->schema;
        }

        /** @var array<int, Entry|LayoutComponent> $schema */
        $schema = $payload->schema;

        return $schema;
    }

    /**
     * Propagate the bound record to every entry, recursing through layout
     * components. Repeatable entries receive the parent record and rebind their
     * own children per row at render time.
     *
     * @param  array<int, Entry|LayoutComponent>  $components
     */
    protected function prepareComponents(array $components): void
    {
        foreach ($components as $component) {
            if ($component instanceof LayoutComponent) {
                $this->prepareComponents($component->getSchema());
            } elseif ($component instanceof Entry) {
                $component->record($this->record);
            }
        }
    }
}
