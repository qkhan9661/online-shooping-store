<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'front_image_path',
        'side_image_path',
        'right_shoulder_path',
        'left_shoulder_path',
        'chest_detail_path',
        'height_cm',
        'ai_confidence',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'decimal:2',
            'ai_confidence' => 'decimal:4',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }
}
