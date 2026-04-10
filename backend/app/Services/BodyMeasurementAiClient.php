<?php

namespace App\Services;

use App\Models\Scan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BodyMeasurementAiClient
{
    /**
     * @return array{measurements: array<string, float|int>, confidence: float|int, image_quality?: array, debug?: array}
     */
    public function estimateFromScan(Scan $scan): array
    {
        $disk = Storage::disk('public');
        $front = $disk->path($scan->front_image_path);
        if (! is_file($front)) {
            throw new RuntimeException('Front image missing on disk.');
        }

        $url = rtrim(config('services.ai.base_url'), '/').'/v1/estimate-measurements';

        $request = Http::timeout((int) config('services.ai.timeout', 120))
            ->acceptJson()
            ->attach('front_image', file_get_contents($front), basename($front), ['Content-Type' => 'image/jpeg']);

        $this->maybeAttach($request, $disk, $scan->side_image_path, 'side_image');
        $this->maybeAttach($request, $disk, $scan->right_shoulder_path, 'right_shoulder_image');
        $this->maybeAttach($request, $disk, $scan->left_shoulder_path, 'left_shoulder_image');
        $this->maybeAttach($request, $disk, $scan->chest_detail_path, 'chest_detail_image');

        $response = $request->post($url, [
            'height_cm' => (string) $scan->height_cm,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI service error: '.$response->body());
        }

        $json = $response->json();
        if (! is_array($json) || ! isset($json['measurements'], $json['confidence'])) {
            throw new RuntimeException('Invalid AI response shape.');
        }

        return $json;
    }

    private function maybeAttach(\Illuminate\Http\Client\PendingRequest $request, \Illuminate\Contracts\Filesystem\Filesystem $disk, ?string $path, string $asName): void
    {
        if (! $path) {
            return;
        }
        $full = $disk->path($path);
        if (! is_file($full)) {
            return;
        }
        $request->attach($asName, file_get_contents($full), basename($full), ['Content-Type' => 'image/jpeg']);
    }
}
