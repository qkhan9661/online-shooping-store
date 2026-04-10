<?php

namespace App\Jobs;

use App\Events\FitUpdated;
use App\Models\Garment;
use App\Models\Measurement;
use App\Models\User;
use App\Services\GpuInferenceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * GPU-backed fit estimation: /infer/fit (heatmap / scores). Separate from size-chart GenerateFitRecommendationJob.
 */
class FitPredictionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $userId, public int $garmentId)
    {
        $this->onQueue('vton-gpu');
    }

    public function handle(GpuInferenceClient $gpu): void
    {
        if (! User::query()->whereKey($this->userId)->exists() || ! Garment::query()->whereKey($this->garmentId)->exists()) {
            return;
        }

        $measurement = Measurement::query()
            ->where('user_id', $this->userId)
            ->orderByDesc('id')
            ->first();

        $garment = Garment::query()->findOrFail($this->garmentId);

        $payload = [
            'user_id' => $this->userId,
            'garment_id' => $this->garmentId,
            'garment_sku' => $garment->sku,
            'size_chart' => $garment->size_chart,
            'measurements' => $measurement ? [
                'chest_cm' => (float) $measurement->chest_cm,
                'waist_cm' => (float) $measurement->waist_cm,
                'hips_cm' => (float) $measurement->hips_cm,
                'shoulder_width_cm' => (float) $measurement->shoulder_width_cm,
            ] : null,
        ];

        try {
            $result = $gpu->inferFit($payload);
            FitUpdated::dispatch($this->userId, $this->garmentId, 'gpu_fit', $result);
        } catch (Throwable $e) {
            FitUpdated::dispatch($this->userId, $this->garmentId, 'gpu_fit', [
                'error' => $e->getMessage(),
                'status' => 'failed',
            ]);
        }
    }
}
