<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementReceipt extends Model
{
    protected $fillable = ['receipt_reference', 'purchase_order_id', 'received_by', 'received_on', 'notes', 'idempotency_key'];

    protected function casts(): array { return ['received_on' => 'date']; }

    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function items(): HasMany { return $this->hasMany(ProcurementReceiptItem::class); }
}
