<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = ['purchase_order_number', 'procurement_request_id', 'supplier_id', 'created_by', 'approved_by', 'cancelled_by', 'total_amount', 'ordered_on', 'expected_delivery_on', 'approved_at', 'cancelled_at', 'status', 'notes', 'idempotency_key'];

    protected function casts(): array { return ['total_amount' => 'decimal:2', 'ordered_on' => 'date', 'expected_delivery_on' => 'date', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime']; }

    public function request(): BelongsTo { return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(ProcurementSupplier::class, 'supplier_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
    public function receipts(): HasMany { return $this->hasMany(ProcurementReceipt::class); }
    public function histories(): HasMany { return $this->hasMany(ProcurementStatusHistory::class); }
}
