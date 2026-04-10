<?php

namespace App\Jobs\Vton;

use App\Models\Garment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 — Offload heavy cloth bake / PhysX or Blender cloth cache to GPU workers.
 * Wire to NVIDIA Warp, Unity headless, or farm scripts.
 */
class ProcessClothSimulationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

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

        $g->update(['asset_pipeline_status' => 'processing']);

        Log::info('ProcessClothSimulationJob stub', ['garment_id' => $this->garmentId]);

        // TODO: pull GLB + physics_profile, run simulation, write cached morph targets / bone weights to S3.
        // Do not mark `ready` until artifacts exist; leave as `processing` or set `failed` on error.
    }
}
