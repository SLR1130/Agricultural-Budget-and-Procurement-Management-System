<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRequest extends Model
{
    protected $fillable = ['request_number', 'budget_allocation_id', 'requester_id', 'requesting_office', 'purpose', 'justification', 'estimated_total', 'status', 'reviewed_by', 'reviewed_at', 'review_notes', 'idempotency_key'];

    protected function casts(): array { return ['estimated_total' => 'decimal:2', 'reviewed_at' => 'datetime']; }

    public function allocation(): BelongsTo { return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function items(): HasMany { return $this->hasMany(ProcurementRequestItem::class); }
    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
    public function histories(): HasMany { return $this->hasMany(ProcurementStatusHistory::class); }
}
