<?php

namespace App\Jobs\Vton;

use App\Models\Garment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 — Mesh optimization (topology, LODs, texture atlasing) after Marvelous/Blender export.
 */
class OptimizeMeshJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

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

        Log::info('OptimizeMeshJob stub', ['garment_id' => $this->garmentId]);

        // TODO: meshoptimizer / RapidCompact; upload to S3; update garment row.
    }
}
