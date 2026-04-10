<?php

namespace App\Jobs;

use App\Events\FitUpdated;
use App\Models\FitRecommendation;
use App\Models\Measurement;
use App\Services\FitRecommendationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateFitRecommendationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public int $fitRecommendationId)
    {
        $this->onQueue('fits');
    }

    public function handle(FitRecommendationService $fit): void
    {
        $row = FitRecommendation::query()->find($this->fitRecommendationId);
        if (! $row) {
            return;
        }

        if ($row->status === 'completed' && $row->recommended_size !== null) {
            return;
        }

        $row->update(['status' => 'processing']);

        try {
            $measurement = Measurement::query()
                ->where('user_id', $row->user_id)
                ->orderByDesc('id')
                ->first();

            if (! $measurement) {
                throw new \RuntimeException('No measurements found for user.');
            }

            $garment = $row->garment()->firstOrFail();
            $result = $fit->compute($measurement, $garment);

            $row->update([
                'status' => 'completed',
                'recommended_size' => $result['recommended_size'],
                'fit_notes' => $result['fit_notes'],
                'confidence' => $result['confidence'],
                'measurement_snapshot' => [
                    'chest_cm' => (float) $measurement->chest_cm,
                    'waist_cm' => (float) $measurement->waist_cm,
                    'hips_cm' => (float) $measurement->hips_cm,
                    'shoulder_width_cm' => (float) $measurement->shoulder_width_cm,
                    'details' => $result['details'],
                ],
            ]);

            FitUpdated::dispatch($row->user_id, $row->garment_id, 'size_chart', [
                'fit_recommendation_id' => $row->id,
                'status' => 'completed',
                'recommended_size' => $result['recommended_size'],
                'confidence' => $result['confidence'],
                'fit_notes' => $result['fit_notes'],
            ]);
        } catch (Throwable $e) {
            $row->update([
                'status' => 'failed',
                'fit_notes' => $e->getMessage(),
                'recommended_size' => null,
                'confidence' => null,
            ]);

            FitUpdated::dispatch($row->user_id, $row->garment_id, 'size_chart', [
                'fit_recommendation_id' => $row->id,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
