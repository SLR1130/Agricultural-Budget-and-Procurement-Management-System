<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentAsset extends Model
{
    protected $fillable = ['asset_tag', 'name', 'acquired_on', 'acquisition_cost', 'purchase_order_reference', 'purchase_order_item_id', 'status', 'location', 'notes'];
    protected function casts(): array { return ['acquired_on' => 'date', 'acquisition_cost' => 'decimal:2']; }
}
