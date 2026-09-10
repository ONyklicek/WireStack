<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    // A property rather than `#[Fillable]`: that attribute is Laravel 13's, and
    // these packages support 12, where it is not read at all.
    protected $fillable = ['invoice_id', 'product', 'quantity', 'unit_price', 'line_total'];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
