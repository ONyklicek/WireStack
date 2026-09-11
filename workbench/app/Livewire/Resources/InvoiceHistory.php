<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WirePanels\Resources\Concerns\BelongsToResource;
use NyonCode\WirePanels\Resources\Concerns\LinksToRecordPages;
use NyonCode\WirePanels\Resources\Concerns\ResolvesOneRecord;
use Workbench\App\Resources\InvoiceResource;

/**
 * A page of the resource that is none of the four kinds — and still a tab.
 *
 * The case the sub-navigation exists to cover as much as `view` and `edit` do:
 * a page an application invents, at a URI of its own, about **one record**. It
 * joins the row by declaring `{record}` in that URI and composing the same three
 * traits the shipped record pages do, which is exactly what `docs/panels/pages.md`
 * tells a reader to write.
 *
 * It is also the workbench's answer to a measurement: the demo user is the first
 * seeded one and does not hold `invoices.update`, so the edit tab is correctly
 * dropped here — leaving a single tab, which draws no bar at all. A fixture with
 * only `view` and `edit` therefore proves nothing about tabs on this server.
 */
class InvoiceHistory extends Component
{
    use BelongsToResource;
    use LinksToRecordPages;
    use ResolvesOneRecord;

    protected static ?string $resource = InvoiceResource::class;

    public function getTitle(): ?string
    {
        return 'History';
    }

    public function render(): View
    {
        $record = $this->nativeRecord();

        return view('livewire.invoice-history', [
            'title' => $this->getTitle(),
            'breadcrumbs' => $this->breadcrumbs(),
            'subNavigation' => $this->subNavigation($record),
            'invoice' => $record,
        ]);
    }
}
