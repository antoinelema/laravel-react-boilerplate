<?php

namespace App\__Infrastructure__\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectEloquent extends Model
{
    use HasFactory;

    protected $table = 'prospects';

    protected $fillable = [
        'user_id',
        'name',
        'company',
        'sector',
        'city',
        'postal_code',
        'address',
        'contact_info',
        'description',
        'relevance_score',
        'status',
        'source',
        'external_id',
        'raw_data',
    ];

    protected $casts = [
        'contact_info' => 'array',
        'raw_data' => 'array',
        'relevance_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserEloquent::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProspectNoteEloquent::class, 'prospect_id');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeBySector($query, string $sector)
    {
        return $query->where('sector', $sector);
    }

    public function scopeByCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeByRelevanceScore($query, int $minScore)
    {
        return $query->where('relevance_score', '>=', $minScore);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('company', 'like', "%{$search}%")
              ->orWhere('sector', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%");
        });
    }
}