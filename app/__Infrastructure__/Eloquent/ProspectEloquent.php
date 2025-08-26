<?php

namespace App\__Infrastructure__\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        // Colonnes d'enrichissement
        'last_enrichment_at',
        'enrichment_attempts',
        'enrichment_status',
        'enrichment_score',
        'auto_enrich_enabled',
        'enrichment_blacklisted_at',
        'enrichment_data',
        'data_completeness_score',
    ];

    protected $casts = [
        'contact_info' => 'array',
        'raw_data' => 'array',
        'relevance_score' => 'integer',
        // Casts pour enrichissement
        'last_enrichment_at' => 'datetime',
        'enrichment_attempts' => 'integer',
        'enrichment_score' => 'float',
        'auto_enrich_enabled' => 'boolean',
        'enrichment_blacklisted_at' => 'datetime',
        'enrichment_data' => 'array',
        'data_completeness_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserEloquent::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProspectNoteEloquent::class, 'prospect_id');
    }

    /**
     * Relation Many-to-Many avec les catégories
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ProspectCategoryEloquent::class,
            'prospect_category_prospect',
            'prospect_id',
            'prospect_category_id'
        )->withTimestamps();
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

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('prospect_category_id', $categoryId);
        });
    }

    public function scopeWithoutCategory($query)
    {
        return $query->whereDoesntHave('categories');
    }

    /**
     * Relation avec l'historique d'enrichissement
     */
    public function enrichmentHistory(): HasMany
    {
        return $this->hasMany(ProspectEnrichmentHistoryEloquent::class, 'prospect_id');
    }

    /**
     * Conversion vers le modèle de domaine
     */
    public function toDomainModel(): \App\__Domain__\Data\Prospect\Model
    {
        return new \App\__Domain__\Data\Prospect\Model(
            id: $this->id,
            userId: $this->user_id,
            name: $this->name,
            company: $this->company,
            sector: $this->sector,
            city: $this->city,
            postalCode: $this->postal_code,
            address: $this->address,
            contactInfo: $this->contact_info ?? [],
            description: $this->description,
            relevanceScore: $this->relevance_score ?? 0,
            status: $this->status ?? 'new',
            source: $this->source,
            externalId: $this->external_id,
            rawData: $this->raw_data ?? [],
            createdAt: $this->created_at ? \DateTimeImmutable::createFromMutable($this->created_at) : null,
            updatedAt: $this->updated_at ? \DateTimeImmutable::createFromMutable($this->updated_at) : null
        );
    }
}