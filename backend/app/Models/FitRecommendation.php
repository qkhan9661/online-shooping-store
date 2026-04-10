<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FitRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'garment_id',
        'status',
        'recommended_size',
        'fit_notes',
        'confidence',
        'measurement_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:4',
            'measurement_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class);
    }
}
