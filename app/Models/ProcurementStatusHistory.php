<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementStatusHistory extends Model
{
    protected $fillable = ['procurement_request_id', 'purchase_order_id', 'from_status', 'to_status', 'notes', 'processed_by'];
    public function actor() { return $this->belongsTo(User::class, 'processed_by'); }
}
