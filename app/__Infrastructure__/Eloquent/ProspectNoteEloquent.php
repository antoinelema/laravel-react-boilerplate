<?php

namespace App\__Infrastructure__\Eloquent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectNoteEloquent extends Model
{
    use HasFactory;

    protected $table = 'prospect_notes';

    protected $fillable = [
        'prospect_id',
        'user_id',
        'content',
        'type',
        'reminded_at',
    ];

    protected $casts = [
        'reminded_at' => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(ProspectEloquent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserEloquent::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeReminders($query)
    {
        return $query->where('type', 'reminder');
    }

    public function scopeDueReminders($query, $date = null)
    {
        $date = $date ?? now();
        return $query->reminders()
                     ->where('reminded_at', '<=', $date);
    }
}