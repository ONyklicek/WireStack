<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;

/**
 * What an application's resources declare.
 *
 * Separate from {@see ComponentReflector} because a resource is not a Livewire
 * component: that one instantiates a host and calls `table()`/`form()` on it to
 * see what comes out, and none of that applies here. A resource answers most of
 * what matters *statically* — which is the whole reason identity is static — so
 * describing one is reading contracts, not building surfaces.
 *
 * Which surfaces a resource has is therefore reported as "does it implement the
 * contract", not "what does the surface contain". Composing the table would mean
 * instantiating the resource and building a `Table`, which is exactly the cost
 * the static half exists to avoid; `describe-table` already answers that for the
 * page that renders it.
 */
class ResourceReflector
{
    /**
     * The surface contracts a resource may implement, by the word used to report
     * them. Kept as strings rather than imported: three of the four live in
     * downstream packages, and boost must describe an application that installs
     * only some of them.
     *
     * @var array<string, class-string|string>
     */
    private const SURFACES = [
        'table' => 'NyonCode\\WirePanels\\Resources\\Contracts\\ProvidesResourceTable',
        'form' => 'NyonCode\\WireForms\\Contracts\\ProvidesResourceForm',
        'infolist' => 'NyonCode\\WireCore\\Infolists\\Contracts\\ProvidesResourceInfolist',
        'relationManagers' => 'NyonCode\\WirePanels\\Resources\\Contracts\\ProvidesRelationManagers',
        // Not a surface of its own but a switch on the list and record pages —
        // reported beside them because it changes what those pages offer.
        'trash' => 'NyonCode\\WirePanels\\Resources\\Contracts\\ManagesTrashedRecords',
    ];

    /** A resource whose records live under one record of another. */
    private const NESTED = 'NyonCode\\WirePanels\\Resources\\Contracts\\NestedResource';

    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * Every registered resource, with what each one declares.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values(array_map(
            fn (string $resource): array => $this->describeClass($resource),
            $this->registry->all(),
        ));
    }

    /**
     * One resource, by key or by class name.
     *
     * @return array<string, mixed>|null Null when nothing is registered under it.
     */
    public function describe(string $keyOrClass): ?array
    {
        $resource = $this->registry->find($keyOrClass);

        if ($resource === null && $this->registry->has($keyOrClass) === false) {
            // Not a key — accept the class name too, since that is what a
            // developer reading their own config has in front of them.
            foreach ($this->registry->all() as $candidate) {
                if (ltrim($candidate, '\\') === ltrim($keyOrClass, '\\')) {
                    $resource = $candidate;
                    break;
                }
            }
        }

        return $resource === null ? null : $this->describeClass($resource);
    }

    /**
     * @param  class-string<DescribesResource>  $resource
     * @return array<string, mixed>
     */
    private function describeClass(string $resource): array
    {
        $surfaces = [];

        foreach (self::SURFACES as $name => $contract) {
            $surfaces[$name] = interface_exists($contract) && is_subclass_of($resource, $contract);
        }

        $described = [
            'key' => $resource::key(),
            'class' => $resource,
            'model' => $resource::modelClass(),
            'label' => $resource::label(),
            'pluralLabel' => $resource::pluralLabel(),
            'surfaces' => $surfaces,
        ];

        // Where a nested resource belongs: the pages route under the parent's
        // record, and an agent writing a link or a test needs the parent key too.
        if (interface_exists(self::NESTED) && is_subclass_of($resource, self::NESTED)) {
            $described['parent'] = [
                'resource' => $resource::parentResource(),
                'relationship' => $resource::parentRelationship(),
            ];
        }

        if (is_subclass_of($resource, ProvidesPages::class)) {
            $described['pages'] = $this->pages($resource::pages());
        }

        if (is_subclass_of($resource, ProvidesNavigation::class)) {
            $item = $resource::navigation();

            $described['navigation'] = [
                'label' => $item->getLabel(),
                'icon' => $item->getIcon(),
                'group' => $item->getGroup(),
                'sort' => $item->getSort(),
                'visible' => $item->isVisible(),
            ];
        }

        return $described;
    }

    /**
     * The pages a resource declares — the component each kind renders and the
     * ability its route is guarded by — without building or routing any of them.
     *
     * @param  array<array-key, class-string|RoutePage>  $pages
     * @return array<string, array{component: string, permission: string|null}>
     */
    private function pages(array $pages): array
    {
        $described = [];

        foreach ($pages as $kind => $page) {
            $described[(string) $kind] = $page instanceof RoutePage
                ? ['component' => $page->component, 'permission' => $page->getPermission()]
                : ['component' => (string) $page, 'permission' => null];
        }

        return $described;
    }
}
