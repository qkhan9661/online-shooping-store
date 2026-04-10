<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Garment extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'image_path',
        'mesh_glb_path',
        'mesh_ar_lod_path',
        'physics_profile',
        'asset_pipeline_status',
        'mesh_cdn_url',
        'size_chart',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'size_chart' => 'array',
            'physics_profile' => 'array',
        ];
    }

    public function fitRecommendations(): HasMany
    {
        return $this->hasMany(FitRecommendation::class);
    }
}
