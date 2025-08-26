<?php

namespace App\__Infrastructure__\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectEnrichmentHistoryEloquent extends Model
{
    use HasFactory;

    protected $table = 'prospect_enrichment_history';

    protected $fillable = [
        'prospect_id',
        'enrichment_type',
        'status',
        'contacts_found',
        'execution_time_ms',
        'services_used',
        'error_message',
        'triggered_by',
        'triggered_by_user_id',
    ];

    protected $casts = [
        'contacts_found' => 'array',
        'services_used' => 'array',
        'execution_time_ms' => 'integer',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(ProspectEloquent::class, 'prospect_id');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByTriggeredBy($query, string $triggeredBy)
    {
        return $query->where('triggered_by', $triggeredBy);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}