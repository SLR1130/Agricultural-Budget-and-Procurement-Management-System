<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplyBatch extends Model
{
    protected $fillable = ['batch_number', 'item_description', 'quantity_received', 'quantity_available', 'inventory_location_id', 'procurement_receipt_id', 'expires_on'];
    protected function casts(): array { return ['quantity_received' => 'decimal:2', 'quantity_available' => 'decimal:2', 'expires_on' => 'date']; }
}
