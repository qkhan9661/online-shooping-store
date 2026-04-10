<?php

namespace App\Jobs\Vton;

use App\Models\Garment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 — Generate AR-ready LOD meshes (decimate, unwrap, compress Draco) for mobile.
 */
class GenerateARModelJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public int $garmentId)
    {
        $this->onQueue('vton-gpu');
    }

    public function handle(): void
    {
        $g = Garment::query()->find($this->garmentId);
        if (! $g) {
            return;
        }

        Log::info('GenerateARModelJob stub', ['garment_id' => $this->garmentId]);

        // TODO: Blender CLI / gltf-transform; set mesh_ar_lod_path + mesh_cdn_url.
    }
}
