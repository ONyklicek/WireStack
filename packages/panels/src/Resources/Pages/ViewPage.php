<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Pages;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Contracts\IdentifiesHookTarget;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Concerns\EmbedsRelationManagers;
use NyonCode\WirePanels\Resources\Concerns\LinksToRecordPages;
use NyonCode\WirePanels\Resources\Concerns\ResolvesOneRecord;

/**
 * A full page showing one of a resource's records, read-only.
 *
 * ADR 0020 asked whether a view page needs an owner concern of its own; it does
 * not (Q2). `Infolist` is a mature read-only surface, so this is a renderer of
 * one and little else — which is why it composes no host trait: there is no
 * state to bind and nothing to submit, so `WithForms`/`WithTable` would only add
 * a lifecycle this page never uses.
 *
 *   class ViewOrder extends ViewPage
 *   {
 *       protected static ?string $resource = OrderResource::class;
 *   }
 *
 *   `@livewire`(ViewOrder::class, ['record' => $order->getKey()])
 *
 * The record travels as a key, for the reason {@see EditPage} gives.
 */
abstract class ViewPage extends Component implements IdentifiesHookTarget, ProvidesBreadcrumbs
{
    use BelongsToResource;
    use EmbedsRelationManagers;
    use LinksToRecordPages;
    use ResolvesOneRecord;

    /**
     * The resource whose record this shows, or null when the page builds its own
     * infolist.
     *
     * @var class-string<DescribesResource>|null
     */
    protected static ?string $resource = null;

    /**
     * The infolist, bound to this record.
     *
     * Built per request rather than cached on the component: an infolist holds a
     * record, and a record cached across a Livewire round trip is a stale record.
     */
    public function infolist(): Infolist
    {
        $resource = $this->requireResource(ProvidesResourceInfolist::class);

        $infolist = $resource->infolist(Infolist::make()->record($this->resolveRecord()));

        // Bound after the resource has spoken, not before: a resource is free to
        // return an infolist of its own rather than the one it was handed, and a
        // host bound on the way in would be lost with it. Nothing has read the
        // schema yet, so the hook still sees the binding — which is what lets
        // `infolist.configuring` be scoped to the resource this page shows.
        return $infolist->livewireComponent($this);
    }

    /** A view page is titled by the singular. */
    public function getTitle(): ?string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        return $this->resourceLabel();
    }

    public function render(): View
    {
        // Resolved once and passed on. Every tab of the sub-navigation needs the
        // record's key to build its URL, and `resolveRecord()` is a query each
        // time it is asked — a page that asked per tab would run three.
        $record = $this->nativeRecord();

        return view('wire-panels::pages.view-page', [
            'title' => $this->getTitle(),
            'breadcrumbs' => $this->breadcrumbs(),
            'subNavigation' => $this->subNavigation($record),
            'relationManagers' => $this->relationManagers(),
            // Not `record`: that is the public property holding the *key*, and
            // Livewire injects public properties into the view scope, where it
            // would shadow this.
            'ownerRecord' => $record,
            'infolist' => $this->infolist(),
        ]);
    }
}
