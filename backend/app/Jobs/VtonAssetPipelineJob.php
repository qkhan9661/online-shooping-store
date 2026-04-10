<?php

namespace App\Jobs;

use App\Events\VtonProgress;
use App\Models\Garment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Offline asset pipeline: produce model.glb + physics_profile.json on object storage (S3 + CloudFront in production).
 */
class VtonAssetPipelineJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(
        public int $garmentId,
        public ?int $triggeredByUserId = null,
    ) {
        $this->onQueue('assets');
    }

    public function handle(): void
    {
        $garment = Garment::query()->find($this->garmentId);
        if (! $garment) {
            return;
        }

        $uid = $this->triggeredByUserId ?? 0;

        if ($uid > 0) {
            VtonProgress::dispatch($uid, $this->garmentId, 'asset_pipeline_started', 5, []);
        }

        $garment->update([
            'asset_pipeline_status' => 'processing',
        ]);

        try {
            $disk = Storage::disk(config('filesystems.default', 'public'));
            $prefix = 'garments/'.$this->garmentId;

            $physicsProfile = [
                'version' => 1,
                'mass_kg' => 0.35,
                'stretch' => 0.12,
                'bend' => 0.08,
                'friction' => 0.45,
                'note' => 'stub — replace with Marvelous/Blender export',
            ];

            $disk->put($prefix.'/physics_profile.json', json_encode($physicsProfile, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            // Stub GLB placeholder: replace with real mesh from DCC pipeline.
            $glbStub = $prefix.'/model.glb';
            $disk->put($glbStub, '');

            $garment->update([
                'physics_profile' => $physicsProfile,
                'mesh_glb_path' => $glbStub,
                'mesh_ar_lod_path' => null,
                'mesh_cdn_url' => null,
                'asset_pipeline_status' => 'ready_stub',
            ]);

            if ($uid > 0) {
                VtonProgress::dispatch($uid, $this->garmentId, 'asset_pipeline_complete', 100, [
                    'mesh_key' => $glbStub,
                    'physics_key' => $prefix.'/physics_profile.json',
                ]);
            }
        } catch (Throwable $e) {
            $garment->update([
                'asset_pipeline_status' => 'failed',
            ]);

            if ($uid > 0) {
                VtonProgress::dispatch($uid, $this->garmentId, 'asset_pipeline_failed', 100, [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
