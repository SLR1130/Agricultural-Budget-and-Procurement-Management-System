<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementReceiptItem extends Model
{
    protected $fillable = ['procurement_receipt_id', 'purchase_order_item_id', 'quantity', 'inventory_movement_id', 'batch_number', 'expires_on', 'inventory_location_id', 'notes'];

    protected function casts(): array { return ['quantity' => 'decimal:2', 'expires_on' => 'date']; }

    public function receipt(): BelongsTo { return $this->belongsTo(ProcurementReceipt::class, 'procurement_receipt_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id'); }
}
