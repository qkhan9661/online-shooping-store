<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RealtimePoseInferenceJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orchestration only: queues GPU /infer/pose (JSON). Clients receive PoseUpdated over WebSockets (Reverb).
 */
class RealtimePoseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'frame_base64' => ['nullable', 'string', 'max:65536'],
            'timestamp_ms' => ['nullable', 'integer', 'min:0'],
            'device_fps' => ['nullable', 'numeric', 'min:1', 'max:120'],
        ]);

        $payload = array_filter([
            'frame_base64' => $data['frame_base64'] ?? null,
            'timestamp_ms' => $data['timestamp_ms'] ?? null,
            'device_fps' => $data['device_fps'] ?? null,
        ], fn ($v) => $v !== null);

        RealtimePoseInferenceJob::dispatch($request->user()->id, $payload);

        return response()->json([
            'message' => 'Pose inference queued; subscribe to private channel user.{id}.pose for PoseUpdated',
        ], 202);
    }
}
