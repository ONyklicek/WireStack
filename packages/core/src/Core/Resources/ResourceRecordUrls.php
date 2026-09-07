<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Contracts\ResolvesRecordUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/**
 * The default answer to "where can this record be read": the view page of
 * whichever resource owns its model.
 *
 * The registry already knows which resource owns a model
 * ({@see ResourceRegistry::forModel()}) and `wire-panels` already knows where
 * that resource's pages are, so this is a lookup rather than a feature. It
 * lives in `Core/` because it names a resource, and is reached from Foundation
 * only through {@see ResolvesRecordUrls}.
 */
final class ResourceRecordUrls implements ResolvesRecordUrls
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ResolvesPageUrls $urls,
    ) {}

    public function urlForRecord(Model $record, ?string $zone = null): ?string
    {
        $key = $record->getKey();

        if ($key === null || $key === '') {
            return null;
        }

        /** @var class-string<DescribesResource>|null $resource */
        $resource = $this->resources->forModel($record::class);

        if ($resource === null) {
            return null;
        }

        $parameters = ['record' => $key];

        // View first, then edit: a link out of a body of text is a read, so a
        // read-only screen is the better landing — but a resource that only has
        // an edit page still beats no link.
        return $this->urls->urlFor($resource::key(), 'view', $parameters, $zone)
            ?? $this->urls->urlFor($resource::key(), 'edit', $parameters, $zone);
    }
}
