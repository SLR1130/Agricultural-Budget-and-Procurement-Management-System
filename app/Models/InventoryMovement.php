<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = ['movement_reference', 'movement_type', 'item_description', 'quantity', 'inventory_location_id', 'procurement_receipt_id', 'performed_by'];
    protected function casts(): array { return ['quantity' => 'decimal:2']; }
}
