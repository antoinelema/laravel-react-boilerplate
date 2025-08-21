<?php

namespace App\__Infrastructure__\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectSearchEloquent extends Model
{
    use HasFactory;

    protected $table = 'prospect_searches';

    protected $fillable = [
        'user_id',
        'query',
        'filters',
        'sources',
        'results_count',
        'saved_count',
    ];

    protected $casts = [
        'filters' => 'array',
        'sources' => 'array',
        'results_count' => 'integer',
        'saved_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserEloquent::class);
    }

    public function scopeRecent($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function scopePopular($query, int $limit = 5)
    {
        return $query->where('saved_count', '>', 0)
                     ->orderBy('saved_count', 'desc')
                     ->limit($limit);
    }

    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }
}