<?php

namespace App\Jobs;

use App\Events\PoseUpdated;
use App\Models\Measurement;
use App\Models\Scan;
use App\Services\GpuInferenceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Phase 3 — Body scan processing: calls GPU /infer/pose (multipart). No inference in Laravel.
 */
class ScanBodyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public int $scanId)
    {
        $this->onQueue('scans');
    }

    public function handle(GpuInferenceClient $gpu): void
    {
        $scan = Scan::query()->find($this->scanId);
        if (! $scan) {
            return;
        }

        if ($scan->status === 'completed' && $scan->measurements()->exists()) {
            return;
        }

        $scan->update(['status' => 'processing', 'error_message' => null]);

        try {
            $payload = $gpu->inferPoseFromScan($scan);
            $m = $payload['measurements'] ?? null;
            if (! is_array($m)) {
                throw new \RuntimeException('Invalid GPU /infer/pose response: missing measurements.');
            }

            Measurement::query()->create([
                'user_id' => $scan->user_id,
                'scan_id' => $scan->id,
                'chest_cm' => (float) ($m['chest_cm'] ?? 0),
                'waist_cm' => (float) ($m['waist_cm'] ?? 0),
                'hips_cm' => (float) ($m['hips_cm'] ?? 0),
                'shoulder_width_cm' => (float) ($m['shoulder_width_cm'] ?? 0),
                'confidence' => (float) ($payload['confidence'] ?? 0),
                'meta' => [
                    'image_quality' => $payload['image_quality'] ?? null,
                    'gpu_debug' => $payload['debug'] ?? null,
                    'raw' => $m,
                    'inference_route' => 'infer/pose',
                ],
            ]);

            $scan->update([
                'status' => 'completed',
                'ai_confidence' => (float) ($payload['confidence'] ?? 0),
                'processed_at' => now(),
            ]);

            PoseUpdated::dispatch($scan->user_id, 'scan_body', [
                'scan_id' => $scan->id,
                'confidence' => (float) ($payload['confidence'] ?? 0),
                'skeleton' => $payload['skeleton'] ?? null,
                'body_state' => $payload['body_state'] ?? null,
            ]);
        } catch (Throwable $e) {
            $scan->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
