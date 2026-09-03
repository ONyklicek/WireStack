<?php

declare(strict_types=1);

namespace Workbench\App\Modules;

use NyonCode\WireCore\Core\Modules\DomainModule;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use Workbench\App\Resources\InvoiceResource;

/**
 * The first of the workbench's two domain modules — V2.6 §7 asks for at least
 * two, because one module proves nothing a resource registry did not already do.
 *
 * Everything this business area consists of is named here: its resource, and the
 * menu heading its entries sit under. Compare it with what the workbench's
 * provider used to say line by line — the same registrations, moved from an
 * application that had to know all of them to the area that owns them.
 */
final class BillingModule extends DomainModule
{
    public function getId(): string
    {
        return 'billing';
    }

    public function resources(): array
    {
        return [InvoiceResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        return NavigationGroup::make('billing')
            ->label('Billing & invoicing')
            ->icon('outline:banknotes')
            ->sort(20);
    }
}
