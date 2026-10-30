<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'procurement_request_item_id', 'item_type', 'description', 'unit', 'ordered_quantity', 'unit_price', 'line_total', 'received_quantity'];

    protected function casts(): array { return ['ordered_quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'received_quantity' => 'decimal:2']; }

    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function requestItem(): BelongsTo { return $this->belongsTo(ProcurementRequestItem::class, 'procurement_request_item_id'); }
}
