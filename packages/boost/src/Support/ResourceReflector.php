<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;

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
    ];

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
}
