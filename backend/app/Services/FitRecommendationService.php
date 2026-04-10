<?php

namespace App\Services;

use App\Models\Garment;
use App\Models\Measurement;
use RuntimeException;

class FitRecommendationService
{
    /**
     * @return array{recommended_size: string, fit_notes: string, confidence: float, details: array}
     */
    public function compute(Measurement $measurement, Garment $garment): array
    {
        $chart = $garment->size_chart;
        if (! is_array($chart) || $chart === []) {
            throw new RuntimeException('Garment has no size chart.');
        }

        $user = [
            'chest' => (float) $measurement->chest_cm,
            'waist' => (float) $measurement->waist_cm,
            'hips' => (float) $measurement->hips_cm,
            'shoulder' => (float) $measurement->shoulder_width_cm,
        ];

        $keys = ['chest', 'waist', 'hips', 'shoulder'];
        $bestSize = null;
        $bestScore = PHP_FLOAT_MAX;
        $perSize = [];

        foreach ($chart as $sizeLabel => $dims) {
            if (! is_array($dims)) {
                continue;
            }
            $score = 0.0;
            $count = 0;
            foreach ($keys as $k) {
                if (! isset($dims[$k])) {
                    continue;
                }
                $diff = $user[$k] - (float) $dims[$k];
                $score += $diff * $diff;
                $count++;
            }
            if ($count === 0) {
                continue;
            }
            $score = $score / $count;
            $perSize[(string) $sizeLabel] = round($score, 4);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestSize = (string) $sizeLabel;
            }
        }

        if ($bestSize === null) {
            throw new RuntimeException('Could not score any size.');
        }

        $recommendedDims = $chart[$bestSize] ?? [];
        $notes = $this->buildFitNotes($user, is_array($recommendedDims) ? $recommendedDims : []);

        $confidence = 1.0 - min(1.0, sqrt($bestScore) / 25.0);
        $confidence = max(0.05, min(1.0, round($confidence, 4)));

        return [
            'recommended_size' => $bestSize,
            'fit_notes' => $notes,
            'confidence' => $confidence,
            'details' => [
                'score_by_size' => $perSize,
                'best_score' => round($bestScore, 4),
            ],
        ];
    }

    /**
     * @param  array<string, float|int|string>  $recommendedDims
     */
    private function buildFitNotes(array $user, array $recommendedDims): string
    {
        $parts = [];
        foreach (['chest' => 'Chest', 'waist' => 'Waist', 'hips' => 'Hips', 'shoulder' => 'Shoulders'] as $k => $label) {
            if (! isset($recommendedDims[$k])) {
                continue;
            }
            $diff = $user[$k] - (float) $recommendedDims[$k];
            if (abs($diff) < 2) {
                $parts[] = "{$label}: good fit";
            } elseif ($diff > 4) {
                $parts[] = "{$label}: likely loose";
            } elseif ($diff > 0) {
                $parts[] = "{$label}: slightly loose";
            } elseif ($diff < -4) {
                $parts[] = "{$label}: likely tight";
            } else {
                $parts[] = "{$label}: slightly tight";
            }
        }

        return implode('; ', $parts) ?: 'Overall: review size chart';
    }
}
