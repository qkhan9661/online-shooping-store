<?php

namespace App\Services;

use App\Models\Scan;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * HTTP client for the GPU inference microservice (PyTorch/CUDA). Laravel jobs call this only — no inference in PHP.
 */
class GpuInferenceClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.gpu.base_url'), '/');
    }

    public function timeoutSeconds(): int
    {
        return (int) config('services.gpu.timeout', 180);
    }

    /**
     * Multipart body scan → GPU /infer/pose (returns measurements + optional pose/skeleton when implemented).
     *
     * @return array<string, mixed>
     */
    public function inferPoseFromScan(Scan $scan): array
    {
        $disk = Storage::disk('public');
        $front = $disk->path($scan->front_image_path);
        if (! is_file($front)) {
            throw new RuntimeException('Front image missing on disk.');
        }

        $url = $this->baseUrl().'/infer/pose';

        $request = Http::timeout($this->timeoutSeconds())
            ->acceptJson()
            ->attach('front_image', file_get_contents($front), basename($front), ['Content-Type' => 'image/jpeg']);

        $this->maybeAttach($request, $disk, $scan->side_image_path, 'side_image');
        $this->maybeAttach($request, $disk, $scan->right_shoulder_path, 'right_shoulder_image');
        $this->maybeAttach($request, $disk, $scan->left_shoulder_path, 'left_shoulder_image');
        $this->maybeAttach($request, $disk, $scan->chest_detail_path, 'chest_detail_image');

        $response = $request->post($url, [
            'height_cm' => (string) $scan->height_cm,
            'scan_id' => (string) $scan->id,
            'user_id' => (string) $scan->user_id,
        ]);

        return $this->decodeJsonResponse($response, 'infer/pose');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function inferTryon(array $payload): array
    {
        $response = Http::timeout($this->timeoutSeconds())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/infer/tryon', $payload);

        return $this->decodeJsonResponse($response, 'infer/tryon');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function inferFit(array $payload): array
    {
        $response = Http::timeout($this->timeoutSeconds())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/infer/fit', $payload);

        return $this->decodeJsonResponse($response, 'infer/fit');
    }

    /**
     * Single-frame or lightweight pose JSON (e.g. base64 frame) → /infer/pose JSON variant.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function inferPoseJson(array $payload): array
    {
        $response = Http::timeout((int) config('services.gpu.realtime_timeout', 30))
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/infer/pose', $payload);

        return $this->decodeJsonResponse($response, 'infer/pose');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(Response $response, string $endpoint): array
    {
        if (! $response->successful()) {
            throw new RuntimeException("GPU service {$endpoint} error: ".$response->body());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException("GPU service {$endpoint} returned invalid JSON.");
        }

        return $json;
    }

    private function maybeAttach(PendingRequest $request, Filesystem $disk, ?string $path, string $asName): void
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
