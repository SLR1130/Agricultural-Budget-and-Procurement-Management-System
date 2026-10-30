<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'reference', 'metadata', 'ip_address'];
    protected function casts(): array { return ['metadata' => 'array']; }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $entityType, ?int $entityId, ?string $reference, array $metadata = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'reference' => $reference,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
