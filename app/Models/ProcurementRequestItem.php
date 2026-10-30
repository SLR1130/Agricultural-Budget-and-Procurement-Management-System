<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequestItem extends Model
{
    protected $fillable = ['procurement_request_id', 'item_type', 'description', 'unit', 'quantity', 'estimated_unit_price', 'estimated_total'];

    protected function casts(): array { return ['quantity' => 'decimal:2', 'estimated_unit_price' => 'decimal:2', 'estimated_total' => 'decimal:2']; }

    public function request(): BelongsTo { return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id'); }
}
