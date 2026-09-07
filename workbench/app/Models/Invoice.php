<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NyonCode\WireModuleMedia\Concerns\HasMedia;

#[Fillable(['number', 'customer', 'status', 'issued_at', 'notes'])]
class Invoice extends Model
{
    // Files attached from the library, through the pivot every model can use.
    // Nothing on this table changes for it, which is the point: an invoice with
    // three attachments and a post with the same cover image share the rows.
    use HasMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
