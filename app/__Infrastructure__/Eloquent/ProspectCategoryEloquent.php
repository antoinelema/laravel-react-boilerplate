<?php

namespace App\__Infrastructure__\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProspectCategoryEloquent extends Model
{
    use HasFactory;

    protected $table = 'prospect_categories';

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * Relation avec l'utilisateur propriétaire
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserEloquent::class);
    }

    /**
     * Relation Many-to-Many avec les prospects
     */
    public function prospects(): BelongsToMany
    {
        return $this->belongsToMany(
            ProspectEloquent::class,
            'prospect_category_prospect',
            'prospect_category_id',
            'prospect_id'
        )->withTimestamps();
    }

    /**
     * Scope pour filtrer par utilisateur
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour ordonner par position
     */
    public function scopeOrderedByPosition($query)
    {
        return $query->orderBy('position')->orderBy('name');
    }
}