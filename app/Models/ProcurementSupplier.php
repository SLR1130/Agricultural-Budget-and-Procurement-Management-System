<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementSupplier extends Model
{
    protected $fillable = ['name', 'contact_person', 'email', 'phone', 'address', 'registration_reference', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class, 'supplier_id'); }
}
