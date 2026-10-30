<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetAllocation extends Model
{
    protected $fillable = ['reference', 'fiscal_period_id', 'program_code', 'program_name', 'funding_category', 'description', 'authorized_amount', 'status', 'created_by'];

    protected function casts(): array { return ['authorized_amount' => 'decimal:2']; }

    public function period(): BelongsTo { return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function transactions(): HasMany { return $this->hasMany(BudgetTransaction::class); }
    public function requests(): HasMany { return $this->hasMany(ProcurementRequest::class); }
}
