<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/**
 * Titles a record page after the record rather than after its resource.
 *
 * A {@see ViewPage} is titled by the
 * resource's singular by default, and the default is right for a resource whose
 * records have no name to use. Roles and users have one — and the title is also
 * the last breadcrumb, so the trail read "Roles / Role" over a heading that said
 * "Role" for a third time.
 *
 * **The attribute is named by the page, never guessed.** This framework does not
 * infer a record's label from a column it hopes exists: a searchable resource
 * says what a result is called ({@see GloballySearchable}),
 * and a page says what its heading is, for the same reason. Which is why the
 * attribute is `abstract` here: the trait carries the rule, and the page — which
 * is the thing that knows its own model — carries the one fact the rule needs.
 *
 * In `wire-panels` rather than in a module, because it is the lowest layer that
 * can own it: `ViewPage` lives here, and the modules that use it are three
 * packages that cannot see each other.
 *
 * @phpstan-require-extends ViewPage
 */
trait InteractsWithRecordTitle
{
    public function getTitle(): ?string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $record = $this->nativeRecord();

        if ($record instanceof Model) {
            $name = trim((string) $record->getAttribute($this->recordTitleAttribute()));

            if ($name !== '') {
                return $name;
            }
        }

        // A record with a blank name, or one that is not a model at all: the
        // resource's own singular, which is what the page said before.
        return parent::getTitle();
    }

    /** The attribute this page's records are named by. */
    abstract protected function recordTitleAttribute(): string;
}
